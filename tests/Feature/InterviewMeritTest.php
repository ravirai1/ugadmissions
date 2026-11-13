<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Application;
use App\Models\ApplicationCourse;
use App\Models\CallLetter;
use App\Models\Category;
use App\Models\Class12Subject;
use App\Models\Course;
use App\Models\InterviewMark;
use App\Models\Scrutiny;
use App\Models\SeatOffer;
use App\Models\User;
use App\Notifications\CallLetterIssued;
use App\Notifications\SeatOffered;
use App\Services\AllotmentService;
use App\Services\InterviewService;
use App\Services\MeritService;
use App\Support\Roles;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class InterviewMeritTest extends TestCase
{
    use RefreshDatabase;

    private string $base;

    private Course $phy;

    private Course $eco;

    private int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        $this->base = '/'.config('admissions.admin_path');
        $this->phy = Course::where('code', 'BSC-PHY')->first();
        $this->eco = Course::where('code', 'BA-ECO')->first();
        Notification::fake();
    }

    private function staff(string $role, ?int $dept = null): Admin
    {
        return Admin::create(['name' => ucfirst($role).' '.uniqid(), 'email' => $role.uniqid().'@ststephens.edu', 'password' => 'Str0ng@Passw0rd', 'role' => $role, 'department_id' => $dept, 'is_active' => true, 'must_change_password' => false]);
    }

    private function as(Admin $a)
    {
        return $this->actingAs($a, 'admin')->withSession(['admin_otp_verified' => true, 'admin_last_activity' => time()]);
    }

    /**
     * A paid, scrutiny-approved applicant. $courses: [Course, du_preference, verified CUET score (3 papers, max 750)].
     */
    private function candidate(string $name, array $courses, string $category = 'UR', ?string $sub = null, string $dob = '2008-01-01', float $class12 = 90, bool $pwbd = false): Application
    {
        $n = ++$this->seq;
        $user = User::factory()->create(['name' => $name]);
        $app = new Application;
        $app->user_id = $user->id;
        $app->forceFill([
            'status' => Application::SUBMITTED, 'submitted_at' => now(), 'fee_paid_at' => now(), 'application_no' => 'SSC27'.str_pad((string) $n, 6, '0', STR_PAD_LEFT),
            'dob' => $dob, 'gender' => 'Female', 'category_code' => $category, 'christian_subcategory' => $sub, 'is_pwbd' => $pwbd,
        ])->save();
        $app->class12Marks()->create(['class12_subject_id' => Class12Subject::first()->id, 'max_marks' => 100, 'marks_obtained' => $class12]);
        foreach ($courses as [$course, $pref, $score]) {
            $choice = ApplicationCourse::create(['application_id' => $app->id, 'course_id' => $course->id, 'du_preference' => $pref, 'papers' => [1, 2, 3], 'cuet_score' => $score, 'payment_status' => 'paid']);
            Scrutiny::create(['application_id' => $app->id, 'application_course_id' => $choice->id, 'type' => Scrutiny::ACADEMIC, 'status' => Scrutiny::APPROVED, 'verified_cuet_score' => $score]);
        }
        if ($category === Category::CHRISTIAN) {
            Scrutiny::create(['application_id' => $app->id, 'type' => Scrutiny::RELIGIOUS, 'status' => Scrutiny::APPROVED]);
        }

        return $app->refresh();
    }

    /** Give a candidate a call letter, mark present and enter panel marks directly. */
    private function interviewed(Application $app, Course $course, array $marks): CallLetter
    {
        $choice = $app->courses()->where('course_id', $course->id)->first();
        $letter = CallLetter::create(['application_id' => $app->id, 'application_course_id' => $choice->id, 'course_id' => $course->id, 'interview_date' => now()->toDateString(), 'reporting_time' => '9 AM', 'venue' => 'Hall']);
        $letter->forceFill(['attendance' => CallLetter::PRESENT])->save();
        foreach ($marks as $m) {
            InterviewMark::create(['call_letter_id' => $letter->id, 'admin_id' => $this->staff(Roles::INTERVIEW_PANEL)->id, 'marks' => $m]);
        }

        return $letter;
    }

    public function test_call_letters_attendance_and_panel_marks(): void
    {
        $asha = $this->candidate('Asha', [[$this->phy, 1, 600]]);
        $ravi = $this->candidate('Ravi', [[$this->phy, 1, 550]]);
        $pending = $this->candidate('Pending', [[$this->phy, 1, 500]]);
        $pending->scrutinies()->update(['status' => Scrutiny::PENDING]);
        $admin = $this->staff(Roles::ADMIN);

        $this->as($admin)->get($this->base.'/interviews')->assertOk()->assertSee('B.Sc. (Hons.) Physics');
        $this->as($admin)->get($this->base."/interviews/course/{$this->phy->id}")->assertOk()->assertSee('SSC27000001')->assertSee('SSC27000002')->assertDontSee('SSC27000003');

        $choices = ApplicationCourse::whereIn('application_id', [$asha->id, $ravi->id, $pending->id])->pluck('id')->all();
        $this->as($admin)->post($this->base."/interviews/course/{$this->phy->id}/issue", ['interview_date' => now()->addDays(2)->toDateString(), 'reporting_time' => '09:00 AM', 'venue' => 'College Hall', 'choices' => $choices, 'email' => 1])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, CallLetter::count(), 'only eligible candidates get letters');
        Notification::assertSentTo($asha->user, CallLetterIssued::class);
        $letter = CallLetter::where('application_id', $asha->id)->first();
        $this->assertMatchesRegularExpression('/^SSC27CL\d{6}$/', $letter->letter_no);

        // applicant sees the letter with a QR code; nobody else can
        $this->actingAs($asha->user)->get('/applicant/dashboard')->assertSee('Call letter');
        $this->actingAs($asha->user)->get("/applicant/call-letter/{$letter->id}")->assertOk()->assertSee($letter->letter_no)->assertSee('data:image/svg+xml;base64', false);
        $this->actingAs($ravi->user)->get("/applicant/call-letter/{$letter->id}")->assertNotFound();

        // attendance desk
        $desk = $this->staff(Roles::ATTENDANCE);
        $this->as($this->staff(Roles::PRINT))->get($this->base."/attendance/scan/{$letter->qr_token}")->assertForbidden();
        $this->as($desk)->get($this->base."/attendance/scan/{$letter->qr_token}")->assertOk()->assertSee('Asha')->assertSee('not today');
        $this->as($desk)->post($this->base."/attendance/{$letter->id}", ['attendance' => 'present'])->assertSessionHasNoErrors();
        $this->assertSame('present', $letter->refresh()->attendance);
        $this->assertSame($desk->id, $letter->attendance_by);
        $raviLetter = CallLetter::where('application_id', $ravi->id)->first();
        $this->as($desk)->post($this->base."/attendance/{$raviLetter->id}", ['attendance' => 'absent']);

        // panel marks: own department only, out of 15, present candidates only, averaged
        $p1 = $this->staff(Roles::INTERVIEW_PANEL, $this->phy->department_id);
        $p2 = $this->staff(Roles::INTERVIEW_PANEL, $this->phy->department_id);
        $ecoPanel = $this->staff(Roles::INTERVIEW_PANEL, $this->eco->department_id);
        $this->as($p1)->get($this->base.'/panel?course='.$this->phy->id)->assertOk()->assertSee($letter->letter_no);
        $this->as($ecoPanel)->post($this->base."/panel/{$this->phy->id}", ['marks' => [$letter->id => 10]])->assertForbidden();
        $this->as($p1)->post($this->base."/panel/{$this->phy->id}", ['marks' => [$letter->id => 16]])->assertSessionHasErrors('marks.'.$letter->id);
        $this->as($p1)->post($this->base."/panel/{$this->phy->id}", ['marks' => [$raviLetter->id => 10]])->assertSessionHasErrors('marks');
        $this->as($p1)->post($this->base."/panel/{$this->phy->id}", ['marks' => [$letter->id => 12]])->assertSessionHasNoErrors();
        $this->as($p2)->post($this->base."/panel/{$this->phy->id}", ['marks' => [$letter->id => 13.5]])->assertSessionHasNoErrors();
        $this->assertSame(12.75, $letter->refresh()->interviewScore());

        // cannot mark absent once scored; finalising locks marks and attendance
        $this->as($desk)->post($this->base."/attendance/{$letter->id}", ['attendance' => 'absent'])->assertSessionHasErrors('attendance');
        $this->as($admin)->post($this->base."/interviews/course/{$this->phy->id}/lock", ['lock' => 1]);
        $this->assertTrue(InterviewService::isLocked($this->phy->id));
        $this->as($p1)->post($this->base."/panel/{$this->phy->id}", ['marks' => [$letter->id => 15]])->assertSessionHasErrors('locked');
        $this->assertSame(12.75, $letter->refresh()->interviewScore());

        // print desk prints call letters
        $this->as($this->staff(Roles::PRINT))->get($this->base.'/print/call-letters?course_id='.$this->phy->id)->assertOk()->assertSee($letter->letter_no)->assertSee($raviLetter->letter_no);
    }

    public function test_merit_is_out_of_100_with_tie_breaks(): void
    {
        // 600/750 x 85 = 68.00; (12 + 13) / 2 = 12.50; total 80.50
        $a = $this->candidate('First', [[$this->phy, 1, 600]]);
        $this->interviewed($a, $this->phy, [12, 13]);
        // same total, higher CUET part wins: 615/750 x 85 = 69.70; interview 10.80; total 80.50
        $b = $this->candidate('HigherCuet', [[$this->phy, 1, 615]]);
        $this->interviewed($b, $this->phy, [10.8]);
        // identical scores: higher Class 12 % wins, then the older applicant
        $c = $this->candidate('Younger', [[$this->phy, 1, 540]], dob: '2008-06-01', class12: 95);
        $this->interviewed($c, $this->phy, [10]);
        $d = $this->candidate('Older', [[$this->phy, 1, 540]], dob: '2007-06-01', class12: 95);
        $this->interviewed($d, $this->phy, [10]);
        $e = $this->candidate('LowerClass12', [[$this->phy, 1, 540]], dob: '2006-01-01', class12: 80);
        $this->interviewed($e, $this->phy, [10]);
        $absent = $this->candidate('Absent', [[$this->phy, 1, 700]]);
        $this->interviewed($absent, $this->phy, [])->forceFill(['attendance' => 'absent'])->save();

        $list = app(MeritService::class)->forCourse($this->phy);
        $this->assertSame(['HigherCuet', 'First', 'Older', 'Younger', 'LowerClass12'], $list['ranked']->map(fn ($r) => $r['app']->user->name)->all());
        $first = $list['ranked'][1];
        $this->assertSame(68.0, $first['cuet']);
        $this->assertSame(12.5, $first['interview']);
        $this->assertSame(80.5, $first['total']);
        $this->assertCount(1, $list['absent']);

        $admin = $this->staff(Roles::ADMIN);
        $this->as($admin)->get($this->base."/merit/{$this->phy->id}")->assertOk()->assertSee('CUET (85)')->assertSee('Interview (15)')->assertSee('Total (100)')->assertSee('80.50');
        $this->as($admin)->get($this->base.'/merit')->assertOk()->assertSee('Highest merit')->assertSee('HigherCuet');
        $this->as($admin)->get($this->base."/merit/{$this->phy->id}/export")->assertOk()->assertHeader('content-type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->as($this->staff(Roles::INTERVIEW_PANEL, $this->phy->department_id))->get($this->base."/merit/{$this->phy->id}")->assertForbidden();
    }

    public function test_allotment_gives_first_preference_and_offers_lapse(): void
    {
        $this->phy->update(['seats' => ['UR' => 1, 'CNI' => 1]]);
        $this->eco->update(['seats' => ['UR' => 1]]);

        // Top of both lists, prefers Economics: gets Economics, shown as already selected in Physics.
        $top = $this->candidate('Top', [[$this->eco, 1, 700], [$this->phy, 2, 700]]);
        $this->interviewed($top, $this->eco, [14]);
        $this->interviewed($top, $this->phy, [14]);
        // Second in Physics: takes the Physics UR seat that Top left.
        $second = $this->candidate('Second', [[$this->phy, 1, 650]]);
        $this->interviewed($second, $this->phy, [12]);
        // CNI candidate below Second: takes the CNI seat.
        $cni = $this->candidate('Cni', [[$this->phy, 1, 600]], Category::CHRISTIAN, 'CNI');
        $this->interviewed($cni, $this->phy, [12]);
        // Waiting list in Physics.
        $wait = $this->candidate('Wait', [[$this->phy, 1, 550]]);
        $this->interviewed($wait, $this->phy, [12]);

        $admin = $this->staff(Roles::ADMIN);
        // nothing until marks are finalised
        $this->assertSame([], app(AllotmentService::class)->preview()['assigned']);
        $svc = app(InterviewService::class);
        $svc->setLocked($this->phy, true, $admin);
        $svc->setLocked($this->eco, true, $admin);

        $assigned = app(AllotmentService::class)->preview()['assigned'];
        $this->assertSame([$this->eco->id, 'UR'], [$assigned[$top->id]['course_id'], $assigned[$top->id]['bucket']]);
        $this->assertSame([$this->phy->id, 'UR'], [$assigned[$second->id]['course_id'], $assigned[$second->id]['bucket']]);
        $this->assertSame([$this->phy->id, 'CNI'], [$assigned[$cni->id]['course_id'], $assigned[$cni->id]['bucket']]);
        $this->assertArrayNotHasKey($wait->id, $assigned);

        $this->as($admin)->get($this->base."/merit/{$this->phy->id}")->assertSee('Already selected – first preference: B.A. (Hons.) Economics');
        $this->as($admin)->get($this->base.'/allotment')->assertOk()->assertSee('Publish round');

        $deadline = now('Asia/Kolkata')->addDays(2)->format('Y-m-d\TH:i');
        $this->as($admin)->post($this->base.'/allotment/publish', ['deadline' => $deadline])->assertRedirect();
        $this->assertSame(3, SeatOffer::where('round', 1)->count());
        Notification::assertSentTo($top->user, SeatOffered::class);
        $offer = SeatOffer::where('application_id', $top->id)->first();
        $this->assertMatchesRegularExpression('/^SSC27OF\d{6}$/', $offer->offer_no);

        // the applicant sees the offer, other course disabled; accepts
        $this->actingAs($top->user)->get('/applicant/dashboard')->assertSee('Seat offered')->assertSee('Already selected – first preference');
        $this->actingAs($second->user)->get("/applicant/offer/{$offer->id}")->assertNotFound();
        $this->actingAs($top->user)->get("/applicant/offer/{$offer->id}")->assertOk()->assertSee('93.33'); // 700/750 x 85 = 79.33 + 14
        $this->actingAs($top->user)->post("/applicant/offer/{$offer->id}", ['decision' => 'accept'])->assertSessionHasErrors('confirm');
        $this->actingAs($top->user)->post("/applicant/offer/{$offer->id}", ['decision' => 'accept', 'confirm' => 1])->assertSessionHasNoErrors();
        $this->assertSame(SeatOffer::ACCEPTED, $offer->refresh()->status);

        // Second lets the Physics seat lapse; the next round offers it to Wait
        $this->travel(3)->days();
        $this->assertSame(2, app(AllotmentService::class)->lapse()); // Second and Cni did not respond
        $this->actingAs($second->user)->post('/applicant/offer/'.SeatOffer::where('application_id', $second->id)->value('id'), ['decision' => 'accept', 'confirm' => 1])->assertSessionHasErrors('offer');
        $assigned = app(AllotmentService::class)->preview()['assigned'];
        $this->assertSame([$wait->id], array_keys($assigned), 'the lapsed UR seat goes to the next in merit; the CNI seat has no CNI candidate left');

        // selected list dashboard and export
        $this->as($admin)->get($this->base.'/allotment/selected')->assertOk()->assertSee('Category-wise')->assertSee('SSC27OF');
        $this->as($admin)->get($this->base.'/allotment/selected/export')->assertOk();
        $this->as($this->staff(Roles::ACCOUNTS))->get($this->base.'/allotment/selected')->assertForbidden();
    }
}
