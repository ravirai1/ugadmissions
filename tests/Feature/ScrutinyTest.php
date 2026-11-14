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
use App\Services\ScrutinyVerifier;
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

    /** Tick OK for every item to verify. */
    private function allOk(Scrutiny $s, array $override = []): array
    {
        $v = collect(app(ScrutinyVerifier::class)->fields($s->fresh()))->map(fn () => ['ok' => '1'])->all();

        return array_replace($v, $override);
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
        $url = "$this->base/scrutiny/item/{$s->id}/decide";
        $before = $app->class12Marks()->pluck('marks_obtained', 'id')->all();

        $this->as($phy)->get("$this->base/scrutiny/item/{$s->id}")->assertOk()->assertSee('Verify the data')->assertDontSee('value="rejected"', false)
            ->assertSee('Academic record')->assertSee('Subject combination')->assertSee('PCM')->assertSee('Class 12 subject')->assertSee('CUET paper');
        // documents may be framed by the portal itself only
        $doc = $app->documents()->first();
        if ($doc) {
            $r = $this->as($phy)->get("$this->base/applications/documents/{$doc->id}")->assertOk()->assertHeader('X-Frame-Options', 'SAMEORIGIN');
            $this->assertStringContainsString("frame-ancestors 'self'", $r->headers->get('Content-Security-Policy'));
        }
        $this->get('/')->assertHeader('X-Frame-Options', 'DENY');
        // only the TFA admin can reject; review needs a comment; approval needs every item verified
        $this->as($phy)->post($url, ['action' => 'rejected', 'remarks' => 'x'])->assertForbidden();
        $this->as($phy)->post($url, ['action' => 'review'])->assertSessionHasErrors('review_comment');
        $this->as($phy)->post($url, ['action' => 'approved'])->assertSessionHasErrors('action');

        // verify in parts: one CUET paper corrected, a Class 12 mark corrected, the rest OK
        $fields = app(ScrutinyVerifier::class)->fields($s);
        $cuetKey = collect($fields)->keys()->first(fn ($k) => str_starts_with($k, 'cuet_'));
        $c12Key = collect($fields)->keys()->first(fn ($k) => str_starts_with($k, 'c12_'));
        $this->as($phy)->post($url, ['action' => 'save', 'verify' => [$cuetKey => ['value' => '999']]])->assertSessionHasErrors("verify.$cuetKey.value");
        $this->as($phy)->post($url, ['action' => 'save', 'verify' => [$cuetKey => ['value' => '150']]])->assertSessionHasNoErrors();
        $this->assertSame(1, $s->verifications()->count());
        $this->as($phy)->post($url, ['action' => 'approved', 'checklist' => ['class12_marks'], 'verify' => $this->allOk($s, [$cuetKey => ['value' => '150'], $c12Key => ['value' => '50']])])
            ->assertSessionHasNoErrors();
        $s->refresh();
        $this->assertSame('approved', $s->status);
        $this->assertSame($phy->id, $s->reviewed_by);
        $this->assertSame('corrected', $s->verifications()->where('field', $cuetKey)->value('result'));
        $expected = collect($fields)->filter(fn ($f, $k) => str_starts_with($k, 'cuet_'))->map(fn ($f, $k) => $k === $cuetKey ? 150 : (float) $f['value'])->sum();
        $this->assertEquals($expected, (float) $s->verified_cuet_score);
        $this->assertNotNull($s->verified_class12_percent);
        // the applicant's own data is untouched
        $this->assertEquals($before, $app->class12Marks()->pluck('marks_obtained', 'id')->all());

        // locked for the department; TFA admin can reopen
        $this->as($phy)->post($url, ['action' => 'review', 'review_comment' => 'x'])->assertForbidden();
        $this->as($phy)->post("$this->base/scrutiny/item/{$s->id}/reopen")->assertForbidden();
        $this->as($this->staff(Roles::TFA))->post("$this->base/scrutiny/item/{$s->id}/reopen")->assertSessionHasNoErrors();
        $this->assertSame('pending', $s->refresh()->status);
        $this->assertNull($s->verified_cuet_score);
    }

    public function test_religious_team_sees_only_basic_and_religious_details(): void
    {
        $app = $this->paid();
        $rel = $app->scrutinies()->where('type', 'religious')->first();
        $relStaff = $this->staff(Roles::RELIGIOUS);
        $this->as($relStaff)->get("$this->base/scrutiny/item/{$rel->id}")->assertOk()
            ->assertSee('Applicant')->assertSee('Date of birth')->assertSee('Verify the data')
            ->assertDontSee('Full application')->assertDontSee('Class 12 subject')->assertDontSee('B.Sc. (Hons.) Physics');
        $this->as($relStaff)->get("$this->base/applications/{$app->id}")->assertRedirect(route('admin.scrutiny.show', $rel));
        $marksheet = $app->documents()->create(['type' => 'class12', 'path' => 'x/m.png', 'original_name' => 'm.png', 'mime' => 'image/png', 'size' => 1, 'sha256' => str_repeat('a', 64)]);
        $this->as($relStaff)->get("$this->base/applications/documents/{$marksheet->id}")->assertNotFound();
        // admins still see everything
        $this->as($this->staff(Roles::ADMIN))->get("$this->base/scrutiny/item/{$rel->id}")->assertOk()->assertSee('Full application');
    }

    public function test_marked_for_review_goes_to_tfa_admin_who_has_final_say(): void
    {
        $app = $this->paid();
        $s = $this->academic($app, 'BSC-PHY');
        $phy = $this->staff(Roles::DEPARTMENT, $this->dept('BSC-PHY'));
        $tfa = $this->staff(Roles::TFA);
        $url = "$this->base/scrutiny/item/{$s->id}/decide";

        $this->as($phy)->post($url, ['action' => 'review', 'review_comment' => 'Marksheet looks altered.'])->assertSessionHasNoErrors();
        $this->assertSame('review', $s->refresh()->status);
        $this->as($phy)->post($url, ['action' => 'approved', 'verify' => $this->allOk($s)])->assertForbidden();

        $this->as($this->staff(Roles::DEPARTMENT, $this->dept('BSC-PHY')))->get("$this->base/scrutiny/overview")->assertForbidden();
        $this->as($tfa)->get("$this->base/scrutiny/overview")->assertOk()->assertSee('Marksheet looks altered.')->assertSee($app->application_no);
        $this->as($tfa)->get("$this->base/scrutiny/academic")->assertOk()->assertSee($app->application_no); // TFA to-do = review items
        $this->as($tfa)->get("$this->base/scrutiny/item/{$s->id}")->assertOk()->assertSee('value="rejected"', false);
        $this->as($tfa)->post($url, ['action' => 'rejected'])->assertSessionHasErrors('remarks');
        $this->as($tfa)->post($url, ['action' => 'rejected', 'remarks' => 'Class 12 marksheet is not genuine.'])->assertSessionHasNoErrors();
        $s->refresh();
        $this->assertSame('rejected', $s->status);
        $this->assertSame($tfa->id, $s->reviewed_by);
        $this->as($tfa)->get("$this->base/scrutiny/overview")->assertSee('Class 12 marksheet is not genuine.');
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
        $this->as($phy)->post("$this->base/scrutiny/item/{$s->id}/decide", ['action' => 'approved', 'verify' => $this->allOk($s)])->assertSessionHasErrors('action');

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

        // the file got a unique, readable name at upload time
        $this->assertMatchesRegularExpression('/^'.preg_quote($this->user->cuet_no, '/').'_class12_\d{8}-\d{6}_[a-z0-9]{6}\.png$/', $current->stored_name);
        $this->assertStringEndsWith($current->stored_name, $current->path);

        // the query, the reply and the upload are on the applicant's dashboard too
        $this->actingAs($this->user)->get('/applicant/dashboard')->assertOk()
            ->assertSee('Queries from the scrutiny team')
            ->assertSee('Please upload a clear Class 12 marksheet.')
            ->assertSee('Uploaded the clear copy.')
            ->assertSee('1 document uploaded')
            ->assertSee('Replied, under review');
        $this->actingAs($this->user)->get("/applicant/queries/{$q->id}")->assertOk()->assertSee('Your reply')->assertSee('Uploaded the clear copy.');

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

        $this->as($admin)->post("$this->base/scrutiny/item/{$phyChoice->id}/decide", ['action' => 'approved', 'verify' => $this->allOk($phyChoice)])->assertSessionHasNoErrors();
        $app->refresh()->load('scrutinies');
        $this->assertFalse($app->eligibleForInterview($phyChoice->applicationCourse), 'religious scrutiny still pending');

        $rel = $app->scrutinies->firstWhere('type', 'religious');
        $relStaff = $this->staff(Roles::RELIGIOUS);
        $relUrl = "$this->base/scrutiny/item/{$rel->id}/decide";
        // religious staff verify: sub-category found to be CNID, not CNI
        $this->as($relStaff)->post($relUrl, ['action' => 'approved', 'verify' => $this->allOk($rel, ['subcategory' => ['value' => 'CNID']])])->assertSessionHasNoErrors();
        $app->refresh()->load('scrutinies');
        $this->assertSame('CNID', $app->meritCategory(), 'merit uses the verified sub-category');
        $this->assertSame('CNI', $app->christian_subcategory, 'applicant data unchanged');
        $this->assertTrue($app->eligibleForInterview($phyChoice->applicationCourse->refresh()));

        // only the TFA admin can reject; then the applicant competes as General
        $this->as($relStaff)->post($relUrl, ['action' => 'rejected', 'remarks' => 'x'])->assertForbidden();
        $tfa = $this->staff(Roles::TFA);
        $this->as($tfa)->post("$this->base/scrutiny/item/{$rel->id}/reopen");
        $this->as($tfa)->post($relUrl, ['action' => 'rejected', 'remarks' => 'Baptism certificate not genuine.'])->assertSessionHasNoErrors();
        $app->refresh()->load('scrutinies');
        $this->assertSame('UR', $app->meritCategory());
        $this->assertTrue($app->religiousCleared());

        $this->actingAs($this->user)->get('/applicant/dashboard')->assertSee('Not accepted – treated as General');
    }
}
