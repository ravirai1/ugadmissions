<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Application;
use App\Models\Course;
use App\Models\Payment;
use App\Models\Setting;
use App\Models\User;
use App\Notifications\PaymentReceived;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\GatewayResult;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentService;
use App\Services\Payments\SbiEpayCrypto;
use App\Services\Payments\SbiEpayGateway;
use App\Support\AdmissionWindow;
use App\Support\Roles;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\FillsApplication;
use Tests\TestCase;

class PaymentTest extends TestCase
{
    use FillsApplication, RefreshDatabase;

    private User $user;

    private const PCM = 'PCM (Physics, Chemistry, Mathematics)';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        $this->user = User::factory()->create();
        Notification::fake();
    }

    /** Submitted application: Physics (first course) + Economics (additional) + residence = 500 + 200 + 1000. */
    private function submitted(bool $residence = true): Application
    {
        $this->fillToCourses();
        $this->go('courses', ['csas_application_no' => 'DU27X1', 'residence' => $residence ? '1' : '0', 'courses' => [
            $this->course('BSC-PHY')->id => ['selected' => 1, 'du_preference' => 2, 'language' => $this->sub('ENG'), 'combination' => self::PCM],
            $this->course('BA-ECO')->id => ['selected' => 1, 'du_preference' => 3, 'jmc_preference' => 1, 'language' => $this->sub('ENG'), 'domains' => [$this->sub('MAT'), $this->sub('ECO')]],
        ]])->assertSessionHasNoErrors();

        $app = Application::first();
        $app->forceFill(['status' => Application::SUBMITTED, 'submitted_at' => now(), 'application_no' => 'SSC27'.str_pad((string) $app->id, 6, '0', STR_PAD_LEFT)])->save();

        return $app->refresh();
    }

    private function course(string $code): Course
    {
        return Course::where('code', $code)->first();
    }

    private function fake(): FakeGateway
    {
        return app(PaymentGateway::class);
    }

    /** Start the application-fee payment and return it. */
    private function startPayment(): Payment
    {
        $this->actingAs($this->user)->post('/payment/application')->assertOk()->assertSee(route('payment.fake'), false);

        return Payment::latest('id')->first();
    }

    /** Simulate the test gateway page: the bank's outcome, then the browser returning to us. */
    private function payAtGateway(Payment $payment, string $outcome)
    {
        $sig = $this->fake()->sign($payment->order_no);
        $res = $this->actingAs($this->user)->post('/payment/fake/complete', ['order_no' => $payment->order_no, 'sig' => $sig, 'outcome' => $outcome]);
        if ($outcome === 'timeout') {
            return $res; // the browser never comes back to us
        }

        return $this->post('/payment/sbi/response', ['order_no' => $payment->order_no, 'sig' => $sig, 'outcome' => $outcome]);
    }

    private function staff(string $role): Admin
    {
        return Admin::firstOrCreate(['email' => $role.'@ststephens.edu'], ['name' => 'Staff', 'password' => 'Str0ng@Passw0rd', 'role' => $role, 'is_active' => true, 'must_change_password' => false]);
    }

    private function asStaff(string $role)
    {
        return $this->actingAs($this->staff($role), 'admin')->withSession(['admin_otp_verified' => true, 'admin_last_activity' => time()]);
    }

    public function test_fee_is_worked_out_on_the_server(): void
    {
        $this->submitted();
        $this->actingAs($this->user)->get('/payment')->assertOk()->assertSee('₹1,700')->assertSee('Pay ₹1,700 with SBI ePay');

        $p = $this->startPayment();
        $this->assertSame('1700.00', $p->amount);
        $this->assertSame(Payment::APPLICATION, $p->purpose);
        $this->assertCount(3, $p->items);
    }

    public function test_cannot_pay_before_submitting(): void
    {
        $this->fillToCourses();
        $this->actingAs($this->user)->post('/payment/application')->assertForbidden();
        $this->actingAs($this->user)->get('/payment')->assertForbidden();
    }

    public function test_successful_payment_generates_receipt_and_marks_fee_paid(): void
    {
        $app = $this->submitted();
        $p = $this->startPayment();

        $res = $this->payAtGateway($p, 'success');
        $res->assertRedirect();
        $this->assertStringContainsString('/payment/result/'.$p->id, $res->headers->get('Location'));
        $this->get($res->headers->get('Location'))->assertOk()->assertSee('Payment successful')->assertSee('SSC27R');

        $p->refresh();
        $this->assertSame(Payment::SUCCESS, $p->status);
        $this->assertMatchesRegularExpression('/^SSC27R\d{6}$/', $p->receipt_no);
        $this->assertTrue($app->refresh()->isPaid());
        $this->assertSame(2, $app->courses()->where('payment_status', 'paid')->count());
        Notification::assertSentTo($this->user, PaymentReceived::class);

        $this->actingAs($this->user)->get("/payment/{$p->id}/receipt")->assertOk()->assertSee($p->receipt_no)->assertSee('PAID');
        $this->actingAs($this->user)->get('/applicant/dashboard')->assertOk()->assertSee('Application fee paid')->assertSee('Receipt');
    }

    public function test_replayed_response_changes_nothing(): void
    {
        $this->submitted();
        $p = $this->startPayment();
        $this->payAtGateway($p, 'success');
        $receipt = $p->refresh()->receipt_no;

        // A later "failed" post for the same order (or the same success again) is ignored.
        $sig = $this->fake()->sign($p->order_no);
        $this->post('/payment/sbi/response', ['order_no' => $p->order_no, 'sig' => $sig, 'outcome' => 'failed']);
        $this->post('/payment/sbi/response', ['order_no' => $p->order_no, 'sig' => $sig, 'outcome' => 'success']);

        $p->refresh();
        $this->assertSame(Payment::SUCCESS, $p->status);
        $this->assertSame($receipt, $p->receipt_no);
        Notification::assertSentToTimes($this->user, PaymentReceived::class, 1);
    }

    public function test_tampered_response_is_rejected(): void
    {
        $this->submitted();
        $p = $this->startPayment();
        $this->post('/payment/sbi/response', ['order_no' => $p->order_no, 'sig' => 'forged', 'outcome' => 'success'])
            ->assertRedirect('/applicant/dashboard');
        $this->assertSame(Payment::INITIATED, $p->refresh()->status);
    }

    public function test_success_redirect_is_not_trusted_without_status_api_confirmation(): void
    {
        $this->submitted();
        $p = $this->startPayment();
        // Browser claims success but "the bank" has no record (nothing cached for the status query).
        $sig = $this->fake()->sign($p->order_no);
        $this->post('/payment/sbi/response', ['order_no' => $p->order_no, 'sig' => $sig, 'outcome' => 'success']);

        $this->assertSame(Payment::PENDING, $p->refresh()->status);
        $this->assertNull($p->receipt_no);
    }

    public function test_failed_payment(): void
    {
        $app = $this->submitted();
        $p = $this->startPayment();
        $this->payAtGateway($p, 'failed');

        $this->assertSame(Payment::FAILED, $p->refresh()->status);
        $this->assertFalse($app->refresh()->isPaid());
    }

    public function test_check_payment_status_after_45_minutes_fetches_and_generates_receipt(): void
    {
        $app = $this->submitted();
        $p = $this->startPayment();
        $this->payAtGateway($p, 'timeout'); // money taken, browser never returned
        $this->assertSame(Payment::INITIATED, $p->refresh()->status);

        // too early
        $this->actingAs($this->user)->get('/applicant/dashboard')->assertSee('Status check from');
        $this->actingAs($this->user)->post("/payment/{$p->id}/check")->assertSessionHas('status', fn ($m) => str_contains($m, 'You can check this payment after'));
        $this->assertSame(Payment::INITIATED, $p->refresh()->status);

        $this->travel(46)->minutes();
        $this->actingAs($this->user)->get('/applicant/dashboard')->assertSee('Check payment status');
        $this->actingAs($this->user)->post("/payment/{$p->id}/check")->assertSessionHas('status', fn ($m) => str_contains($m, 'Receipt SSC27R'));

        $p->refresh();
        $this->assertSame(Payment::SUCCESS, $p->status);
        $this->assertNotNull($p->receipt_no);
        $this->assertTrue($app->refresh()->isPaid());
        $this->assertSame('applicant', $p->events()->where('status', 'success')->value('source'));
    }

    public function test_reconcile_command_confirms_old_unconfirmed_payments(): void
    {
        $this->submitted();
        $p = $this->startPayment();
        $this->payAtGateway($p, 'timeout');

        $this->artisan('payments:reconcile')->expectsOutputToContain('Checked 0');
        $this->travel(50)->minutes();
        $this->artisan('payments:reconcile')->expectsOutputToContain('1 confirmed');
        $this->assertSame(Payment::SUCCESS, $p->refresh()->status);
    }

    public function test_second_successful_application_payment_is_flagged_duplicate(): void
    {
        $this->submitted();
        $p1 = $this->startPayment();
        $this->payAtGateway($p1, 'timeout');
        $p2 = $this->startPayment();
        $this->payAtGateway($p2, 'success');
        $this->travel(46)->minutes();
        $this->actingAs($this->user)->post("/payment/{$p1->id}/check");

        $this->assertTrue($p1->refresh()->isSuccess());
        $this->assertTrue($p1->is_duplicate);
        $this->assertFalse($p2->refresh()->is_duplicate);

        // and a third attempt cannot even start
        $this->actingAs($this->user)->post('/payment/application')->assertRedirect('/payment')->assertSessionHasErrors('payment');
    }

    public function test_amount_mismatch_is_not_accepted(): void
    {
        $app = $this->submitted();
        $p = app(PaymentService::class)->initiateApplicationFee($app, '127.0.0.1');
        $wrong = new GatewayResult($p->order_no, 'success', 1.00, 'REF', gatewayStatus: 'SUCCESS');

        app(PaymentService::class)->apply($p, $wrong, 'push', verified: true);

        $this->assertSame(Payment::FAILED, $p->refresh()->status);
        $this->assertFalse($app->refresh()->isPaid());
    }

    public function test_add_residence_after_payment(): void
    {
        $app = $this->submitted(residence: false);
        $this->payAtGateway($this->startPayment(), 'success');
        $this->assertSame('700.00', Payment::first()->amount); // 500 + 200, no residence

        $this->actingAs($this->user)->post('/payment/add-residence')->assertOk();
        $p = Payment::latest('id')->first();
        $this->assertSame(Payment::ADD_RESIDENCE, $p->purpose);
        $this->assertSame('1000.00', $p->amount);
        $this->payAtGateway($p, 'success');

        $app->refresh();
        $this->assertTrue($app->residence);
        $this->assertTrue($app->residence_added_after_payment);
        $this->actingAs($this->user)->post('/payment/add-residence')->assertSessionHasErrors('payment');
    }

    public function test_add_course_after_payment(): void
    {
        $app = $this->submitted();
        $this->actingAs($this->user)->post('/payment/add-courses')->assertForbidden(); // fee not paid yet
        $this->payAtGateway($this->startPayment(), 'success');

        $math = $this->course('BSC-MAT');
        $this->actingAs($this->user)->get('/payment/add-courses')->assertOk()->assertSee($math->name)->assertDontSee('id="c'.$this->course('BSC-PHY')->id.'"', false);

        $row = ['selected' => 1, 'du_preference' => 4, 'language' => $this->sub('ENG'), 'domains' => [$this->sub('MAT'), $this->sub('PHY')], 'statement' => str_repeat('m', 120)];
        if ($math->offered_at_jmc) {
            $row['jmc_not_applied'] = 1;
        }

        // preference already used by an existing course
        $this->actingAs($this->user)->post('/payment/add-courses', ['courses' => [$math->id => ['du_preference' => 2] + $row]])->assertSessionHasErrors('courses');
        // statement required
        $this->actingAs($this->user)->post('/payment/add-courses', ['courses' => [$math->id => ['statement' => 'short'] + $row]])->assertSessionHasErrors("courses.{$math->id}.statement");

        $this->actingAs($this->user)->post('/payment/add-courses', ['courses' => [$math->id => $row]])->assertOk();
        $p = Payment::latest('id')->first();
        $this->assertSame(Payment::ADD_COURSE, $p->purpose);
        $this->assertSame('500.00', $p->amount);
        $this->assertSame(0, $app->courses()->where('course_id', $math->id)->count()); // added only once paid

        $this->payAtGateway($p, 'success');
        $added = $app->courses()->where('course_id', $math->id)->first();
        $this->assertNotNull($added);
        $this->assertTrue($added->added_after_payment);
        $this->assertSame('paid', $added->payment_status);
        $this->assertSame(4, $added->du_preference);
        $this->assertSame(str_repeat('m', 120), $added->statement);
    }

    public function test_applicant_cannot_see_or_check_another_applicants_payment(): void
    {
        $this->submitted();
        $p = $this->startPayment();
        $this->payAtGateway($p, 'success');

        $other = User::factory()->create();
        $this->actingAs($other)->get("/payment/{$p->id}/receipt")->assertNotFound();
        $this->actingAs($other)->post("/payment/{$p->id}/check")->assertNotFound();
    }

    public function test_admin_payments_list_check_and_export(): void
    {
        $this->submitted();
        $p = $this->startPayment();
        $this->payAtGateway($p, 'timeout');
        $base = '/'.config('admissions.admin_path');

        $this->asStaff(Roles::DEPARTMENT)->get("$base/payments")->assertForbidden();

        $this->asStaff(Roles::ACCOUNTS)->get("$base/payments")->assertOk()->assertSee($p->order_no)->assertSee('Check with SBI');
        $this->asStaff(Roles::ACCOUNTS)->get("$base/payments?q={$p->order_no}&status=unconfirmed")->assertOk()->assertSee($p->order_no);

        // Staff can check at any time (no 45-minute wait).
        $this->asStaff(Roles::ACCOUNTS)->post("$base/payments/{$p->id}/check")->assertSessionHas('status', fn ($m) => str_contains($m, 'confirmed as paid'));
        $this->assertSame(Payment::SUCCESS, $p->refresh()->status);
        $this->assertSame('admin', $p->events()->where('status', 'success')->value('source'));

        $this->asStaff(Roles::ACCOUNTS)->get("$base/payments/{$p->id}")->assertOk()->assertSee($p->receipt_no);
        $this->asStaff(Roles::PRINT)->get("$base/payments/{$p->id}/receipt")->assertOk()->assertSee($p->receipt_no);

        $csv = $this->asStaff(Roles::ACCOUNTS)->get("$base/payments/export")->assertOk()->streamedContent();
        $this->assertStringContainsString($p->order_no, $csv);
        $this->assertStringContainsString($p->receipt_no, $csv);
    }

    public function test_admin_bulk_check_of_unconfirmed_payments(): void
    {
        $this->submitted();
        $p = $this->startPayment();
        $this->payAtGateway($p, 'timeout');
        $base = '/'.config('admissions.admin_path');

        $this->asStaff(Roles::ADMIN)->post("$base/payments/check-pending")->assertSessionHas('status', fn ($m) => str_contains($m, 'Checked 0'));
        $this->travel(46)->minutes();
        $this->asStaff(Roles::ADMIN)->get($base)->assertOk()->assertSee('Check payment status with SBI');
        $this->asStaff(Roles::ADMIN)->post("$base/payments/check-pending")->assertSessionHas('status', fn ($m) => str_contains($m, '1 confirmed'));
        $this->assertTrue($p->refresh()->isSuccess());
    }

    public function test_sbi_gateway_encrypts_request_and_reads_response_and_status(): void
    {
        $cfg = config('payment.sbi_epay');
        $cfg['merchant_id'] = '1000003';
        $crypto = new SbiEpayCrypto('fBc5628ybRQf88f/aqDUOQ==', 'aes-128-ecb');
        $gw = new SbiEpayGateway($cfg, $crypto);

        $app = $this->submitted();
        $p = $app->payments()->create(['order_no' => 'SSC2611130001', 'purpose' => Payment::APPLICATION, 'items' => [], 'amount' => 1700, 'status' => Payment::INITIATED]);

        $form = $gw->requestForm($p, 'https://example.test/payment/sbi/response');
        $plain = $crypto->decrypt($form['fields']['EncryptTrans']);
        $this->assertStringStartsWith('1000003|DOM|IN|INR|1700.00|', $plain);
        $this->assertStringContainsString('|SSC2611130001|', $plain);

        $enc = $crypto->encrypt('SSC2611130001|ATRN123|SUCCESS|1700.00|INR|NB|APP-1|Approved|SBIN|BRN99|2026-11-13 10:00:00|IN||1000003');
        $r = $gw->parseResponse(Request::create('/x', 'POST', ['encData' => $enc]));
        $this->assertSame('success', $r->status);
        $this->assertSame('ATRN123', $r->gatewayRef);
        $this->assertSame(1700.0, $r->amount);
        $this->assertNull($gw->parseResponse(Request::create('/x', 'POST', ['encData' => 'garbage'])));

        Http::fake([$cfg['status_url'] => Http::sequence()
            ->push('1000003|ATRN123|SUCCESS|IN|INR|APP-1|SSC2611130001|1700.00|Completed|SBIN|BRN99|2026-11-13|NB')
            ->push('1000003|ATRN123|SUCCESS|IN|INR|APP-1|OTHERORDER|1700.00')]);
        $s = $gw->queryStatus($p);
        $this->assertSame('success', $s->status);
        $this->assertSame('BRN99', $s->bankRef);

        $this->assertNull($gw->queryStatus($p), 'a status reply for another order is ignored');
    }

    public function test_fake_gateway_cannot_be_used_in_production(): void
    {
        $this->app->forgetInstance(PaymentGateway::class);
        $this->app['env'] = 'production';
        $this->expectException(\RuntimeException::class);
        app(PaymentGateway::class);
    }

    private function window(array $s): void
    {
        Setting::put(AdmissionWindow::KEY, $s + ['main_open' => true, 'main_closes_at' => null, 'reopen' => false, 'reopen_until' => null, 'course_ids' => []]);
    }

    public function test_closed_main_round_blocks_the_form_and_unpaid_fee(): void
    {
        $this->submitted();
        $this->window(['main_open' => false]);

        $this->actingAs($this->user)->post('/payment/application')->assertRedirect()->assertSessionHasErrors('payment');
        $this->assertSame(0, Payment::count());

        $fresh = User::factory()->create();
        $this->actingAs($fresh)->get('/application/basic')->assertRedirect(route('applicant.dashboard'));
        $this->actingAs($fresh)->get('/applicant/dashboard')->assertOk()->assertSee('Applications are closed');
    }

    public function test_reopened_round_offers_only_vacant_courses_and_previous_applicants_can_add_one(): void
    {
        $app = $this->submitted();
        $this->payAtGateway($this->startPayment(), 'success');

        $math = $this->course('BSC-MAT');
        $chem = $this->course('BSC-CHE') ?? $this->course('BA-ENG');
        $this->window(['main_open' => false, 'reopen' => true, 'course_ids' => [$math->id], 'reopen_until' => now()->addDays(3)->toIso8601String()]);

        $this->actingAs($this->user)->get('/applicant/dashboard')->assertOk()->assertSee('reopened for vacant seats')->assertSee($math->name);
        $this->actingAs($this->user)->get('/payment/add-courses')->assertOk()->assertSee($math->name)->assertDontSee('id="c'.$chem->id.'"', false);

        // a course that is not vacant cannot be added even by posting it directly
        $row = ['selected' => 1, 'du_preference' => 4, 'language' => $this->sub('ENG'), 'domains' => [$this->sub('MAT'), $this->sub('PHY')], 'statement' => str_repeat('m', 120), 'jmc_not_applied' => 1];
        $this->actingAs($this->user)->post('/payment/add-courses', ['courses' => [$chem->id => $row]])->assertSessionHasErrors();

        $this->actingAs($this->user)->post('/payment/add-courses', ['courses' => [$math->id => $row]])->assertOk();
        $this->payAtGateway(Payment::latest('id')->first(), 'success');
        $this->assertNotNull($app->courses()->where('course_id', $math->id)->first());
    }

    public function test_admin_sets_the_admission_window(): void
    {
        $math = $this->course('BSC-MAT');
        $this->asStaff(Roles::ADMIN)->get('/ssc-staff/admission-window')->assertOk()->assertSee('Vacant')->assertSee($math->name);
        $this->asStaff(Roles::ADMIN)->put('/ssc-staff/admission-window', ['main_open' => '0', 'reopen' => '1'])->assertSessionHasErrors('course_ids');
        $this->asStaff(Roles::ADMIN)->put('/ssc-staff/admission-window', ['main_open' => '0', 'reopen' => '1', 'course_ids' => [$math->id], 'reopen_until' => now('Asia/Kolkata')->addDays(2)->format('Y-m-d\TH:i')])->assertSessionHasNoErrors();
        $this->assertSame([$math->id], AdmissionWindow::allowedCourseIds());
        $this->asStaff(Roles::ACCOUNTS)->get('/ssc-staff/admission-window')->assertForbidden();
    }

    public function test_dashboard_tracks_every_payment_attempt_with_sbi_updates(): void
    {
        $this->submitted();
        $first = $this->startPayment();
        $this->payAtGateway($first, 'failed');
        $second = $this->startPayment();
        $this->payAtGateway($second, 'success');

        $this->actingAs($this->user)->get('/applicant/dashboard')->assertOk()
            ->assertSee('Track payments')
            ->assertSee('2 payment attempts, 1 successful')
            ->assertSee($first->order_no)->assertSee($second->order_no)
            ->assertSee('SBI updates')
            ->assertSee('Reply from SBI when you returned to this site');
    }
}
