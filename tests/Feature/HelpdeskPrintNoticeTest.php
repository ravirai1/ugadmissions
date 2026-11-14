<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Application;
use App\Models\Course;
use App\Models\Notice;
use App\Models\PrintLog;
use App\Models\Ticket;
use App\Models\User;
use App\Notifications\NoticePublished;
use App\Notifications\TicketReceived;
use App\Notifications\TicketReplied;
use App\Services\Payments\GatewayResult;
use App\Services\Payments\PaymentService;
use App\Support\Roles;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\FillsApplication;
use Tests\TestCase;

class HelpdeskPrintNoticeTest extends TestCase
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

    private function staff(string $role): Admin
    {
        return Admin::create(['name' => ucfirst($role).' Staff', 'email' => $role.uniqid().'@ststephens.edu', 'password' => 'Str0ng@Passw0rd', 'role' => $role, 'is_active' => true, 'must_change_password' => false]);
    }

    private function as(Admin $a)
    {
        return $this->actingAs($a, 'admin')->withSession(['admin_otp_verified' => true, 'admin_last_activity' => time()]);
    }

    private function paid(): Application
    {
        $this->fillToCourses();
        $this->go('courses', ['csas_application_no' => 'DU27X1', 'courses' => [
            Course::where('code', 'BSC-PHY')->value('id') => ['selected' => 1, 'du_preference' => 2, 'language' => $this->sub('ENG'), 'combination' => 'PCM (Physics, Chemistry, Mathematics)'],
        ]])->assertSessionHasNoErrors();
        $app = Application::first();
        $app->forceFill(['status' => Application::SUBMITTED, 'submitted_at' => now(), 'application_no' => 'SSC27000001'])->save();
        $svc = app(PaymentService::class);
        $p = $svc->initiateApplicationFee($app, '127.0.0.1');
        $svc->apply($p, new GatewayResult($p->order_no, 'success', (float) $p->amount, 'REF1', gatewayStatus: 'SUCCESS'), 'push', verified: true);

        return $app->refresh();
    }

    public function test_anyone_can_send_a_query_and_staff_reply_by_email(): void
    {
        $this->get('/help')->assertRedirect('/help/query');
        $this->get('/help/query')->assertOk()->assertSee('Mobile');

        $this->post('/help/query', ['name' => 'Ravi', 'email' => 'parent@example.com', 'subject' => 'Fee', 'message' => 'short', 'captcha' => 'test-pass'])
            ->assertSessionHasErrors('message');
        $this->post('/help/query', ['name' => 'Ravi', 'email' => 'parent@example.com', 'mobile' => '9876543210', 'subject' => 'Fee query',
            'message' => 'Is there a fee concession for PwBD applicants?', 'captcha' => 'test-pass',
            'attachment' => UploadedFile::fake()->create('x.pdf', 20, 'application/pdf')])->assertSessionHasErrors('attachment');
        $this->post('/help/query', ['name' => 'Ravi', 'email' => 'parent@example.com', 'mobile' => '9876543210', 'subject' => 'Fee query',
            'message' => 'Is there a fee concession for PwBD applicants?', 'captcha' => 'test-pass',
            'attachment' => UploadedFile::fake()->image('shot.png', 400, 300)])->assertSessionHasNoErrors()->assertRedirect('/help/query');

        $t = Ticket::first();
        $this->assertMatchesRegularExpression('/^HD27\d{5}$/', $t->ticket_no);
        $this->assertSame('query', $t->channel);
        Notification::assertSentTo(new AnonymousNotifiable, TicketReceived::class, fn ($n, $ch, $to) => $to->routes['mail'] === 'parent@example.com');

        $this->get($this->base.'/helpdesk')->assertRedirect(); // staff only
        $this->as($this->staff(Roles::PRINT))->get($this->base.'/helpdesk')->assertForbidden();

        $hd = $this->staff(Roles::HELPDESK);
        $this->as($hd)->get($this->base.'/helpdesk')->assertOk()->assertSee($t->ticket_no);
        $this->as($hd)->get($this->base."/helpdesk/{$t->id}")->assertOk()->assertSee('PwBD applicants');
        $msg = $t->messages()->first();
        $this->as($hd)->get($this->base."/helpdesk/attachment/{$msg->id}")->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');

        $this->as($hd)->post($this->base."/helpdesk/{$t->id}/reply", ['message' => 'Yes, see the fee page.'])->assertRedirect();
        $t->refresh();
        $this->assertSame(Ticket::ANSWERED, $t->status);
        $this->assertSame($hd->id, $t->assigned_to);
        $this->assertNotNull($t->first_response_at);
        Notification::assertSentTo(new AnonymousNotifiable, TicketReplied::class);

        $this->as($hd)->post($this->base."/helpdesk/{$t->id}/status", ['status' => 'closed'])->assertRedirect();
        $this->assertSame(Ticket::CLOSED, $t->refresh()->status);
        $this->as($hd)->get($this->base.'/helpdesk/report')->assertOk()->assertSee($hd->name);
    }

    public function test_logged_in_applicant_chats_with_staff(): void
    {
        $this->get('/help/chat')->assertRedirect('/login');
        $this->actingAs($this->user)->get('/help')->assertRedirect('/help/chat');
        $this->actingAs($this->user)->get('/help/chat')->assertOk();

        $this->actingAs($this->user)->postJson('/help/chat', ['message' => 'My payment is pending.'])->assertOk()->assertJson(['ok' => true]);
        $this->actingAs($this->user)->postJson('/help/chat', ['message' => 'Order SSC27 something.'])->assertOk();
        $t = Ticket::sole();
        $this->assertSame('chat', $t->channel);
        $this->assertTrue($t->unread_for_staff);
        $this->assertSame(2, $t->messages()->count());

        $hd = $this->staff(Roles::HELPDESK);
        $this->as($hd)->getJson($this->base."/helpdesk/{$t->id}/messages?after=0")->assertOk()->assertJsonCount(2, 'messages');
        $this->assertFalse($t->refresh()->unread_for_staff);
        $this->as($hd)->postJson($this->base."/helpdesk/{$t->id}/reply", ['message' => 'We are checking with SBI.', 'attachment' => UploadedFile::fake()->image('a.jpg', 300, 300)])->assertOk();

        $last = $t->messages()->reorder()->latest('id')->first();
        $res = $this->actingAs($this->user)->getJson('/help/chat/messages?after='.($last->id - 1))->assertOk()->assertJsonCount(1, 'messages');
        $this->assertSame('Admissions Helpdesk', $res->json('messages.0.sender'));
        $this->assertFalse($res->json('messages.0.mine'));
        $this->actingAs($this->user)->get("/help/attachment/{$last->id}")->assertOk();

        // another applicant cannot read this chat or its files
        $other = User::factory()->create();
        $this->actingAs($other)->getJson('/help/chat/messages')->assertOk()->assertJsonCount(0, 'messages');
        $this->actingAs($other)->get("/help/attachment/{$last->id}")->assertNotFound();

        // closing the chat starts a new one on the next message
        $t->update(['status' => Ticket::CLOSED]);
        $this->actingAs($this->user)->postJson('/help/chat', ['message' => 'One more question.'])->assertOk();
        $this->assertSame(2, Ticket::count());
    }

    public function test_print_desk_prints_forms_and_logs_every_print(): void
    {
        $app = $this->paid();
        $receipt = $app->payments()->where('status', 'success')->first();
        $pr = $this->staff(Roles::PRINT);

        $this->as($this->staff(Roles::HELPDESK))->get($this->base.'/print')->assertForbidden();
        $this->as($pr)->get($this->base.'/print?q=SSC27000001')->assertOk()->assertSee('SSC27000001')->assertSee($receipt->receipt_no);
        $this->as($pr)->get($this->base."/print/application/{$app->id}")->assertOk()->assertSee('SSC27000001')->assertSee('Printed by '.$pr->name)->assertSee('Dr. John Thomas')->assertSee('Associate Professor');
        $this->as($pr)->get($this->base.'/print/bulk?course_id='.Course::where('code', 'BSC-PHY')->value('id'))->assertOk()->assertSee('SSC27000001');
        $this->as($pr)->get($this->base.'/print/bulk?course_id='.Course::where('code', 'BA-ECO')->value('id'))->assertOk()->assertDontSee('SSC27000001');
        $this->as($pr)->get($this->base."/payments/{$receipt->id}/receipt")->assertOk();

        $this->assertSame(['application', 'application-bulk', 'application-bulk', 'receipt'], PrintLog::orderBy('id')->pluck('item')->all());
        $this->assertSame($pr->id, PrintLog::first()->admin_id);
        $this->as($pr)->get($this->base.'/print/log?item=receipt')->assertOk()->assertSee($receipt->receipt_no);
    }

    public function test_notices_show_by_audience_and_email_once(): void
    {
        $admin = $this->staff(Roles::ADMIN);
        $this->as($this->staff(Roles::PRINT))->get($this->base.'/notices')->assertForbidden();

        $this->as($admin)->post($this->base.'/notices', ['title' => 'Interview schedule announced', 'body' => 'Interviews begin on 1 July.', 'audience' => 'public', 'is_published' => 1, 'is_pinned' => 1])->assertRedirect();
        $this->as($admin)->post($this->base.'/notices', ['title' => 'Paid applicants only', 'body' => 'Check your scrutiny status.', 'audience' => 'paid', 'is_published' => 1])->assertRedirect();
        $this->as($admin)->post($this->base.'/notices', ['title' => 'Draft notice', 'body' => 'Not yet.', 'audience' => 'public'])->assertRedirect();

        $this->get('/')->assertSee('Interview schedule announced')->assertDontSee('Paid applicants only')->assertDontSee('Draft notice');
        $this->get('/notices')->assertOk()->assertSee('Interviews begin on 1 July.')->assertDontSee('Check your scrutiny status.');
        $this->actingAs($this->user)->get('/applicant/dashboard')->assertSee('Interview schedule announced')->assertDontSee('Paid applicants only');

        $this->paid();
        $this->actingAs($this->user)->get('/applicant/dashboard')->assertSee('Paid applicants only');

        $notice = Notice::where('audience', 'paid')->first();
        $unpaid = User::factory()->create();
        $this->as($admin)->post($this->base."/notices/{$notice->id}/email")->assertSessionHasNoErrors();
        Notification::assertSentTo($this->user, NoticePublished::class);
        Notification::assertNotSentTo($unpaid, NoticePublished::class);
        $this->assertSame(1, $notice->refresh()->emailed_count);
        $this->as($admin)->post($this->base."/notices/{$notice->id}/email")->assertSessionHasErrors('email');

        $draft = Notice::where('title', 'Draft notice')->first();
        $this->as($admin)->post($this->base."/notices/{$draft->id}/email")->assertSessionHasErrors('email');
    }
}
