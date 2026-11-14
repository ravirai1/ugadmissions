<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Application;
use App\Models\Course;
use App\Models\Department;
use App\Models\Scrutiny;
use App\Models\ScrutinyQuery;
use App\Models\User;
use App\Notifications\QueryRaised;
use App\Notifications\QueryReminder;
use App\Services\Payments\GatewayResult;
use App\Services\Payments\PaymentService;
use App\Services\ScrutinyService;
use App\Support\Roles;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\FillsApplication;
use Tests\TestCase;

class ScrutinyTest extends TestCase
{
    use FillsApplication, RefreshDatabase;

    private User $user;

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        $this->user = User::factory()->create();
        $this->base = '/'.config('admissions.admin_path');
        Notification::fake();
        Storage::fake('documents');
    }

    private function staff(string $role, ?int $dept = null): Admin
    {
        return Admin::create(['name' => ucfirst($role), 'email' => $role.uniqid().'@ststephens.edu', 'password' => 'Str0ng@Passw0rd', 'role' => $role, 'department_id' => $dept, 'is_active' => true, 'must_change_password' => false]);
    }

    private function as(Admin $a)
    {
        return $this->actingAs($a, 'admin')->withSession(['admin_otp_verified' => true, 'admin_last_activity' => time()]);
    }

    private function dept(string $courseCode): int
    {
        return Course::where('code', $courseCode)->value('department_id');
    }

    /** Christian (CNI) applicant for Physics + Economics, paid through the payment service. */
    private function paid(): Application
    {
        $this->fillToCourses();
        $this->go('courses', ['csas_application_no' => 'DU27X1', 'courses' => [
            Course::where('code', 'BSC-PHY')->value('id') => ['selected' => 1, 'du_preference' => 2, 'language' => $this->sub('ENG'), 'combination' => 'PCM (Physics, Chemistry, Mathematics)'],
            Course::where('code', 'BA-ECO')->value('id') => ['selected' => 1, 'du_preference' => 3, 'jmc_preference' => 1, 'language' => $this->sub('ENG'), 'domains' => [$this->sub('MAT'), $this->sub('ECO')]],
        ]])->assertSessionHasNoErrors();
        $app = Application::first();
        $app->forceFill(['status' => Application::SUBMITTED, 'submitted_at' => now(), 'application_no' => 'SSC27000001'])->save();

        $svc = app(PaymentService::class);
        $p = $svc->initiateApplicationFee($app, '127.0.0.1');
        $svc->apply($p, new GatewayResult($p->order_no, 'success', (float) $p->amount, 'REF1', gatewayStatus: 'SUCCESS'), 'push', verified: true);

        return $app->refresh();
    }

    private function academic(Application $app, string $code): Scrutiny
    {
        return Scrutiny::where('type', 'academic')->whereHas('applicationCourse.course', fn ($c) => $c->where('code', $code))->firstOrFail();
    }

    public function test_paid_application_enters_department_and_religious_queues(): void
    {
        $app = $this->paid();
        $this->assertSame(2, $app->scrutinies()->where('type', 'academic')->count());
        $this->assertSame(1, $app->scrutinies()->where('type', 'religious')->count());

        $phy = $this->staff(Roles::DEPARTMENT, $this->dept('BSC-PHY'));
        $this->as($phy)->get("$this->base/scrutiny/academic")->assertOk()->assertSee('SSC27000001')->assertSee('B.Sc. (Hons.) Physics')->assertDontSee('B.A. (Hons.) Economics');
        $this->as($phy)->get("$this->base/scrutiny/item/".$this->academic($app, 'BSC-PHY')->id)->assertOk()->assertSee('Academic mapping')->assertSee('Meets the DU rule');
        $this->as($phy)->get("$this->base/scrutiny/item/".$this->academic($app, 'BA-ECO')->id)->assertNotFound();
        $this->as($phy)->get("$this->base/scrutiny/religious")->assertForbidden();

        $other = $this->staff(Roles::DEPARTMENT, Department::whereNotIn('id', [$this->dept('BSC-PHY'), $this->dept('BA-ECO')])->value('id'));
        $this->as($other)->get("$this->base/scrutiny/academic")->assertOk()->assertDontSee('SSC27000001');

        $rel = $this->staff(Roles::RELIGIOUS);
        $this->as($rel)->get("$this->base/scrutiny/religious")->assertOk()->assertSee('SSC27000001');
        $this->as($rel)->get("$this->base/scrutiny/academic")->assertForbidden();
    }

    public function test_decision_rules_and_locking(): void
    {
        $app = $this->paid();
        $s = $this->academic($app, 'BSC-PHY');
        $phy = $this->staff(Roles::DEPARTMENT, $this->dept('BSC-PHY'));

        $this->as($phy)->post("$this->base/scrutiny/item/{$s->id}/decide", ['status' => 'rejected'])->assertSessionHasErrors('remarks');
        $this->as($phy)->post("$this->base/scrutiny/item/{$s->id}/decide", ['status' => 'approved', 'verified_cuet_score' => 698.5, 'checklist' => ['class12_marks', 'cuet_scores']])
            ->assertSessionHasNoErrors();
        $s->refresh();
        $this->assertSame('approved', $s->status);
        $this->assertSame('698.50', $s->verified_cuet_score);
        $this->assertSame($phy->id, $s->reviewed_by);

        // locked for the department; admin can reopen
        $this->as($phy)->post("$this->base/scrutiny/item/{$s->id}/decide", ['status' => 'rejected', 'remarks' => 'x'])->assertForbidden();
        $this->as($phy)->post("$this->base/scrutiny/item/{$s->id}/reopen")->assertForbidden();
        $this->as($this->staff(Roles::ADMIN))->post("$this->base/scrutiny/item/{$s->id}/reopen")->assertSessionHasNoErrors();
        $this->assertSame('pending', $s->refresh()->status);
    }

    public function test_query_secure_link_reply_adds_document_to_profile(): void
    {
        $app = $this->paid();
        $s = $this->academic($app, 'BSC-PHY');
        $phy = $this->staff(Roles::DEPARTMENT, $this->dept('BSC-PHY'));

        $this->as($phy)->post("$this->base/scrutiny/item/{$s->id}/query", ['message' => 'Please upload a clear Class 12 marksheet.', 'document_types' => ['class12']])->assertSessionHasNoErrors();
        $this->assertSame('query', $s->refresh()->status);

        $token = null;
        Notification::assertSentTo($this->user, QueryRaised::class, function ($n) use (&$token) {
            $url = $n->toMail($this->user)->actionUrl;
            $token = basename($url);

            return str_contains($url, '/query/');
        });
        $q = ScrutinyQuery::first();
        $this->assertNotSame($token, $q->token_hash, 'only a hash of the token is stored');

        // cannot decide while the query is open
        $this->as($phy)->post("$this->base/scrutiny/item/{$s->id}/decide", ['status' => 'approved', 'verified_cuet_score' => 700])->assertSessionHasErrors('status');

        $this->get("/query/{$q->id}/wrong-token")->assertForbidden();
        $this->get("/query/{$q->id}/$token")->assertOk()->assertSee('Please upload a clear Class 12 marksheet.');

        // requested document is required
        $this->post("/query/{$q->id}/$token", ['reply' => 'here'])->assertSessionHasErrors('files.class12');
        $this->post("/query/{$q->id}/$token", ['reply' => 'Uploaded the clear copy.', 'files' => ['class12' => UploadedFile::fake()->image('m.png', 600, 800)->size(200)],
            'extra' => [UploadedFile::fake()->create('note.pdf', 50, 'application/pdf')]])->assertSessionHasErrors(); // fake PDF has no %PDF header
        $this->post("/query/{$q->id}/$token", ['reply' => 'Uploaded the clear copy.', 'files' => ['class12' => UploadedFile::fake()->image('m.png', 600, 800)->size(200)]])
            ->assertRedirect(); // home, or dashboard if logged in

        $q->refresh();
        $this->assertSame('answered', $q->status);
        $this->assertSame(1, $q->attachments()->count());
        $current = $app->documents()->where('type', 'class12')->first();
        $this->assertSame($q->id, $current->query_id, 'the reply is now the current Class 12 marksheet on the profile');
        $s->refresh();
        $this->assertSame('pending', $s->status);
        $this->assertTrue($s->reply_received);

        // the link cannot be used again
        $this->get("/query/{$q->id}/$token")->assertForbidden();
        $this->as($phy)->get("$this->base/scrutiny/academic?status=reply")->assertSee('SSC27000001');
        $this->as($phy)->get("$this->base/scrutiny/item/{$s->id}")->assertOk()->assertSee('Uploaded the clear copy.');
    }

    public function test_applicant_replies_from_dashboard(): void
    {
        $app = $this->paid();
        [$q] = app(ScrutinyService::class)->raiseQuery($app->religiousScrutiny() ?? $app->scrutinies()->where('type', 'religious')->first(), 'Please explain your denomination.', [], $this->staff(Roles::RELIGIOUS));

        $this->actingAs($this->user)->get('/applicant/dashboard')->assertSee('Action needed');
        $this->actingAs(User::factory()->create())->get("/applicant/queries/{$q->id}")->assertNotFound();
        $this->actingAs($this->user)->post("/applicant/queries/{$q->id}", [])->assertSessionHasErrors('reply');
        $this->actingAs($this->user)->post("/applicant/queries/{$q->id}", ['reply' => 'I belong to the CNI diocese of Delhi.'])->assertRedirect('/applicant/dashboard');
        $this->assertSame('answered', $q->refresh()->status);
    }

    public function test_expired_link_and_reminders(): void
    {
        $app = $this->paid();
        $s = $this->academic($app, 'BA-ECO');
        [$q, $token] = app(ScrutinyService::class)->raiseQuery($s, 'Upload the CUET scorecard.', ['cuet_scorecard'], $this->staff(Roles::ADMIN));

        $this->travel(3)->days();
        $this->assertSame(1, app(ScrutinyService::class)->remind());
        Notification::assertSentTo($this->user, QueryReminder::class);
        $this->get("/query/{$q->id}/$token")->assertForbidden(); // reminder issued a fresh link
        $this->assertSame(0, app(ScrutinyService::class)->remind(), 'at most one reminder a day');

        $this->travel(8)->days();
        $this->assertFalse($q->refresh()->linkValid('anything'));
    }

    public function test_religious_rejection_moves_to_general_and_interview_eligibility(): void
    {
        $app = $this->paid();
        $admin = $this->staff(Roles::ADMIN);
        $phyChoice = $this->academic($app, 'BSC-PHY');

        $this->assertFalse($app->refresh()->load('scrutinies')->eligibleForInterview($phyChoice->applicationCourse));

        $this->as($admin)->post("$this->base/scrutiny/item/{$phyChoice->id}/decide", ['status' => 'approved', 'verified_cuet_score' => 700]);
        $app->refresh()->load('scrutinies');
        $this->assertFalse($app->eligibleForInterview($phyChoice->applicationCourse), 'religious scrutiny still pending');

        $rel = $app->scrutinies->firstWhere('type', 'religious');
        $this->as($this->staff(Roles::RELIGIOUS))->post("$this->base/scrutiny/item/{$rel->id}/decide", ['status' => 'rejected', 'remarks' => 'Baptism certificate not genuine.']);
        $app->refresh()->load('scrutinies');
        $this->assertSame('UR', $app->meritCategory());
        $this->assertTrue($app->religiousCleared());
        $this->assertTrue($app->eligibleForInterview($phyChoice->applicationCourse->refresh()));

        $this->actingAs($this->user)->get('/applicant/dashboard')->assertSee('Not accepted – treated as General');
    }
}
