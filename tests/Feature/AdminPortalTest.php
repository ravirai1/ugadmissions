<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Application;
use App\Models\Course;
use App\Models\Department;
use App\Models\District;
use App\Models\State;
use App\Models\User;
use App\Services\FeeCalculator;
use App\Support\Roles;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\FillsApplication;
use Tests\TestCase;

class AdminPortalTest extends TestCase
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
    }

    private function staff(string $role, array $attrs = []): Admin
    {
        return Admin::create($attrs + ['name' => ucfirst($role), 'email' => $role.uniqid().'@ststephens.edu', 'password' => 'Str0ng@Passw0rd', 'role' => $role, 'is_active' => true, 'must_change_password' => false]);
    }

    private function as(Admin $a)
    {
        return $this->actingAs($a, 'admin')->withSession(['admin_otp_verified' => true, 'admin_last_activity' => time()]);
    }

    /** A submitted, fee-paid application for Physics (+ Economics), Christian CNI, female. */
    private function paidApplication(): Application
    {
        $this->fillToCourses();
        $this->go('courses', ['csas_application_no' => 'DU27X1', 'residence' => '1', 'courses' => [
            Course::where('code', 'BSC-PHY')->value('id') => ['selected' => 1, 'du_preference' => 2, 'language' => $this->sub('ENG'), 'combination' => 'PCM (Physics, Chemistry, Mathematics)'],
            Course::where('code', 'BA-ECO')->value('id') => ['selected' => 1, 'du_preference' => 3, 'jmc_preference' => 1, 'language' => $this->sub('ENG'), 'domains' => [$this->sub('MAT'), $this->sub('ECO')]],
        ]])->assertSessionHasNoErrors();
        $app = Application::first();
        $app->forceFill(['status' => Application::SUBMITTED, 'submitted_at' => now(), 'application_no' => 'SSC27000001', 'fee_paid_at' => now()])->save();
        $app->courses()->update(['payment_status' => 'paid']);

        return $app->refresh();
    }

    public function test_super_admin_creates_department_and_staff(): void
    {
        $super = $this->staff(Roles::SUPER_ADMIN);
        $this->as($super)->post("$this->base/departments", ['name' => 'Department of Music', 'code' => 'MUS'])->assertSessionHasNoErrors();
        $dept = Department::where('code', 'MUS')->first();

        // department faculty must have a department
        $this->as($super)->post("$this->base/staff", ['name' => 'Dr A', 'email' => 'a@ststephens.edu', 'role' => Roles::DEPARTMENT, 'is_active' => 1])
            ->assertSessionHasErrors('department_id');
        // cannot create a super admin from the web
        $this->as($super)->post("$this->base/staff", ['name' => 'X', 'email' => 'x@ststephens.edu', 'role' => Roles::SUPER_ADMIN, 'is_active' => 1])
            ->assertSessionHasErrors('role');

        $this->as($super)->post("$this->base/staff", ['name' => 'Dr A', 'email' => 'A@ststephens.edu', 'role' => Roles::DEPARTMENT, 'department_id' => $dept->id, 'is_active' => 1])
            ->assertRedirect("$this->base/staff")->assertSessionHas('temp_password');
        $a = Admin::where('email', 'a@ststephens.edu')->first();
        $this->assertTrue($a->must_change_password);
        $this->assertSame($dept->id, $a->department_id);

        $this->as($super)->post("$this->base/staff/{$a->id}/toggle")->assertSessionHasNoErrors();
        $this->assertFalse($a->refresh()->is_active);
        $this->as($super)->post("$this->base/staff/{$a->id}/reset-password")->assertSessionHas('temp_password');
        $this->as($super)->get("$this->base/staff")->assertOk()->assertSee('a@ststephens.edu');
        $this->as($super)->get("$this->base/staff/{$super->id}/edit")->assertForbidden();
    }

    public function test_only_super_admin_manages_staff_and_fees(): void
    {
        $admin = $this->staff(Roles::ADMIN);
        $this->as($admin)->get("$this->base/staff")->assertForbidden();
        $this->as($admin)->get("$this->base/settings/fees")->assertForbidden();
    }

    public function test_super_admin_changes_fees(): void
    {
        $super = $this->staff(Roles::SUPER_ADMIN);
        $this->as($super)->get("$this->base/settings/fees")->assertOk();
        $this->as($super)->put("$this->base/settings/fees", ['first_course' => 600, 'additional_course' => 250, 'residence' => 1200, 'course_after_payment' => 600, 'residence_after_payment' => 1200])
            ->assertSessionHasNoErrors();
        $this->assertSame(600, FeeCalculator::fees()['first_course']);
        $this->get('/faq')->assertSee('₹600 for the first course');
    }

    public function test_admin_edits_master_data(): void
    {
        $admin = $this->staff(Roles::ADMIN);
        $this->as($admin)->get("$this->base/master/courses")->assertOk()->assertSee('B.Sc. (Hons.) Physics');

        $this->as($admin)->post("$this->base/master/states", ['name' => 'Testland', 'is_ut' => 1, 'is_active' => 1, 'sort' => 5])->assertSessionHasNoErrors();
        $state = State::where('name', 'Testland')->first();
        $this->assertTrue($state->is_ut);
        $this->as($admin)->post("$this->base/master/states", ['name' => 'Testland', 'is_active' => 1])->assertSessionHasErrors('name');

        $this->as($admin)->post("$this->base/master/districts/bulk", ['state_id' => $state->id, 'names' => "North\nSouth\n\nNorth"])->assertSessionHasNoErrors();
        $this->assertSame(2, District::where('state_id', $state->id)->count());

        $this->as($admin)->post("$this->base/master/states/{$state->id}/toggle");
        $this->assertFalse($state->refresh()->is_active);

        $course = Course::where('code', 'BSC-PHY')->first();
        $form = ['code' => 'BSC-PHY', 'name' => $course->name, 'is_active' => 1, 'offered_at_jmc' => 0, 'sort' => 1];
        $this->as($admin)->get("$this->base/master/courses/{$course->id}/edit")->assertOk()->assertSee('Fixed subject combinations')->assertSee('PCM (Physics, Chemistry, Mathematics)');
        $this->as($admin)->put("$this->base/master/courses/{$course->id}", $form + ['eligibility' => ['class12_required' => ['PHYX'], 'languages' => ['count' => 1]]])->assertSessionHasErrors('eligibility');
        $this->as($admin)->put("$this->base/master/courses/{$course->id}", $form + ['eligibility' => ['class12_required' => ['PHY']]])->assertSessionHasErrors('eligibility'); // no papers counted
        $this->as($admin)->put("$this->base/master/courses/{$course->id}", $form + ['eligibility' => ['languages' => ['count' => 1], 'domains' => ['count' => 2], 'combinations' => [['label' => 'PCM', 'papers' => ['PHY', 'CHE', 'MAT']]]]])->assertSessionHasErrors('eligibility');
        $this->as($admin)->put("$this->base/master/courses/{$course->id}", $form + ['eligibility' => [
            'class12_required' => ['PHY', 'MAT'], 'min_class12_percent' => '60', 'languages' => ['count' => '1'], 'domains' => ['count' => '0'],
            'combinations' => [['label' => 'PCM', 'papers' => ['PHY', 'CHE', 'MAT']], ['label' => '', 'papers' => []]], 'programme_options' => '',
        ]])->assertSessionHasNoErrors();
        $course->refresh();
        $this->assertEquals(['class12_required' => ['PHY', 'MAT'], 'min_class12_percent' => 60, 'languages' => ['count' => 1, 'allowed' => null], 'combinations' => [['label' => 'PCM', 'papers' => ['PHY', 'CHE', 'MAT']]]], $course->eligibility);

        // course can be switched off and on
        $this->as($admin)->post("$this->base/master/courses/{$course->id}/toggle");
        $this->assertFalse($course->refresh()->is_active);

        // seat matrix, with PwBD supernumerary seats
        $this->as($admin)->get("$this->base/seats")->assertOk()->assertSee('PwBD (extra)');
        $eco = Course::where('code', 'BA-ECO')->first();
        $this->as($admin)->put("$this->base/seats", ['pwbd_percent' => 5, 'seats' => [$course->id => ['UR' => 'ten']]])->assertSessionHasErrors();
        $this->as($admin)->put("$this->base/seats", ['pwbd_percent' => 5, 'seats' => [
            $course->id => ['UR' => 20, 'OBC' => 10, 'SC' => '', 'CNI' => 5, 'PWBD' => ''],
            $eco->id => ['UR' => 10, 'PWBD' => 0],
        ]])->assertSessionHasNoErrors();
        $this->assertSame(['UR' => 20, 'OBC' => 10, 'CNI' => 5], $course->refresh()->seats);
        $this->assertSame(['UR' => 20, 'OBC' => 10, 'CNI' => 5, 'PWBD' => 2], \App\Services\AllotmentService::seats($course), '5% of 35, rounded up, as extra seats');
        $this->assertSame(['UR' => 10], \App\Services\AllotmentService::seats($eco->refresh()), 'PwBD set to 0 for this course');
        $this->as($this->staff(Roles::ACCOUNTS))->get("$this->base/seats")->assertForbidden();

        $this->as($this->staff(Roles::DEPARTMENT))->get("$this->base/master/courses")->assertForbidden();
    }

    public function test_applications_list_is_scoped_by_role(): void
    {
        $app = $this->paidApplication();
        $phyDept = Course::where('code', 'BSC-PHY')->value('department_id');
        $otherDept = Department::where('id', '!=', $phyDept)->value('id');

        $this->as($this->staff(Roles::ADMIN))->get("$this->base/applications")->assertOk()->assertSee('SSC27000001');
        $this->as($this->staff(Roles::ADMIN))->get("$this->base/applications/{$app->id}")->assertOk()->assertSee('Why St. Stephen');

        $mine = $this->staff(Roles::DEPARTMENT, ['department_id' => $phyDept]);
        $this->as($mine)->get("$this->base/applications")->assertOk()->assertSee('SSC27000001');
        $this->as($mine)->get("$this->base/applications/{$app->id}")->assertOk();

        $other = $this->staff(Roles::DEPARTMENT, ['department_id' => $otherDept]);
        $this->as($other)->get("$this->base/applications")->assertOk()->assertDontSee('SSC27000001');
        $this->as($other)->get("$this->base/applications/{$app->id}")->assertNotFound();

        $this->as($this->staff(Roles::RELIGIOUS))->get("$this->base/applications")->assertOk()->assertSee('SSC27000001'); // Christian applicant
        $this->as($this->staff(Roles::ATTENDANCE))->get("$this->base/applications")->assertForbidden();

        // unpaid applications are not in department queues
        $app->forceFill(['fee_paid_at' => null])->save();
        $this->as($mine)->get("$this->base/applications")->assertDontSee('SSC27000001');
    }

    public function test_application_filters_and_excel_export(): void
    {
        $this->paidApplication();
        $admin = $this->staff(Roles::ADMIN);
        $this->as($admin)->get("$this->base/applications?gender=Male")->assertDontSee('SSC27000001');
        $this->as($admin)->get("$this->base/applications?gender=Female&residence=1&category=CHRISTIAN")->assertSee('SSC27000001');

        $res = $this->as($admin)->get("$this->base/applications/export?fee=paid")->assertOk();
        $this->assertStringContainsString('spreadsheetml', $res->headers->get('Content-Type'));
        $this->assertStringStartsWith('PK', $res->streamedContent()); // xlsx is a zip
    }

    public function test_dashboard_statistics_and_export(): void
    {
        $this->paidApplication();
        $admin = $this->staff(Roles::ADMIN);
        $this->as($admin)->get($this->base)->assertOk()
            ->assertSee('Course-wise applications')->assertSee('B.Sc. (Hons.) Physics')
            ->assertSee('Category-wise')->assertSee('State belongs to')->assertSee('Kerala');

        $res = $this->as($admin)->get("$this->base/statistics.xlsx?scope=submitted")->assertOk();
        $this->assertStringStartsWith('PK', $res->streamedContent());

        $phyDept = Course::where('code', 'BSC-PHY')->value('department_id');
        $this->as($this->staff(Roles::DEPARTMENT, ['department_id' => $phyDept]))->get($this->base)->assertOk()
            ->assertSee('Applications in your list')->assertDontSee('Course-wise applications');
        $this->as($this->staff(Roles::DEPARTMENT))->get("$this->base/statistics.xlsx")->assertForbidden();
    }
}
