<?php

namespace Tests\Feature;

use App\Models\Application;
use App\Models\Category;
use App\Models\Class12Subject;
use App\Models\Course;
use App\Models\PwbdType;
use App\Models\State;
use App\Models\Subject;
use App\Models\User;
use App\Notifications\ApplicationSubmitted;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ApplicationFormTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        $this->user = User::factory()->create();
        Storage::fake('local');
    }

    private function c12(string $name): int
    {
        return Class12Subject::where('name', $name)->value('id');
    }

    private function sub(string $code): int
    {
        return Subject::where('code', $code)->value('id');
    }

    private function basic(array $o = []): array
    {
        return array_merge([
            'dob' => '2008-05-10', 'gender' => 'Female', 'father_name' => 'John Thomas', 'mother_name' => 'Mary Thomas',
            'family_income' => '5_8', 'last_school' => 'Modern School', 'last_school_city' => 'Delhi',
        ], $o);
    }

    private function address(): array
    {
        $delhi = State::where('name', 'Delhi')->value('id');

        return [
            'cur_line1' => '12 Main Road', 'cur_city' => 'New Delhi', 'cur_state_id' => $delhi, 'cur_district_other' => 'Central Delhi',
            'cur_pin' => '110001', 'perm_same' => '1', 'domicile_state_id' => State::where('name', 'Kerala')->value('id'),
        ];
    }

    private function christian(array $o = []): array
    {
        return array_merge([
            'category_code' => Category::CHRISTIAN, 'christian_subcategory' => 'CNI', 'baptism_date' => '2008-08-01',
            'baptism_church' => 'Cathedral Church of the Redemption', 'baptism_place' => 'New Delhi', 'baptism_priest' => 'A. Kumar',
            'denomination' => 'CNI', 'current_church' => 'St. James Church', 'current_priest' => 'P. Singh',
        ], $o);
    }

    private function academics(array $subjects = ['English Core', 'Mathematics', 'Physics', 'Chemistry', 'Economics']): array
    {
        return [
            'c10_board' => 'CBSE', 'c10_year' => 2024, 'c10_roll_no' => '123', 'c10_max' => 500, 'c10_obtained' => 450,
            'c12_board' => 'CBSE', 'c12_year' => 2026, 'c12_roll_no' => '456',
            'marks' => collect($subjects)->map(fn ($s) => ['subject' => $this->c12($s), 'max' => 100, 'obtained' => 90])->all(),
        ];
    }

    private function cuet(array $papers = ['ENG' => 180, 'MAT' => 190, 'PHY' => 170, 'CHE' => 160, 'ECO' => 150]): array
    {
        return [
            'cuet_roll_no' => 'CU27001',
            'scores' => collect($papers)->map(fn ($score, $code) => ['subject' => $this->sub($code), 'score' => $score])->values()->all(),
        ];
    }

    private function go(string $step, array $data)
    {
        return $this->actingAs($this->user)->post("/application/$step", $data);
    }

    private function fillToCourses(): void
    {
        $this->go('basic', $this->basic())->assertSessionHasNoErrors();
        $this->go('address', $this->address())->assertSessionHasNoErrors();
        $this->go('category', $this->christian())->assertSessionHasNoErrors();
        $this->go('academics', $this->academics())->assertSessionHasNoErrors();
        $this->go('cuet', $this->cuet())->assertSessionHasNoErrors();
    }

    public function test_steps_must_be_done_in_order(): void
    {
        $this->actingAs($this->user)->get('/application/cuet')->assertRedirect('/application/basic');
        $this->go('category', $this->christian())->assertRedirect('/application/basic');
    }

    public function test_all_steps_render(): void
    {
        $this->fillToCourses();
        foreach (['basic', 'address', 'category', 'academics', 'cuet', 'courses'] as $s) {
            $this->actingAs($this->user)->get("/application/$s")->assertOk();
        }
    }

    public function test_christian_details_are_required_and_encrypted(): void
    {
        $this->go('basic', $this->basic());
        $this->go('address', $this->address());
        $this->go('category', ['category_code' => Category::CHRISTIAN])->assertSessionHasErrors(['christian_subcategory', 'baptism_date', 'baptism_priest']);
        $this->go('category', $this->christian(['is_cst' => '1']))->assertSessionHasNoErrors();

        $app = Application::first();
        $this->assertTrue($app->is_cst);
        $this->assertSame('A. Kumar', $app->baptism_details['baptism_priest']);
        $raw = \DB::table('applications')->value('baptism_details');
        $this->assertStringNotContainsString('Kumar', $raw);
        $this->assertContains('st_certificate', $app->requiredDocumentTypes());
    }

    public function test_pwbd_requires_40_percent_and_udid(): void
    {
        $this->go('basic', $this->basic());
        $this->go('address', $this->address());
        $this->go('category', ['category_code' => 'UR', 'is_pwbd' => '1', 'pwbd_type_id' => PwbdType::first()->id, 'pwbd_percentage' => 30])
            ->assertSessionHasErrors(['pwbd_percentage', 'udid_no']);
        $this->go('category', ['category_code' => 'UR', 'is_pwbd' => '1', 'pwbd_type_id' => PwbdType::first()->id, 'pwbd_percentage' => 60, 'udid_no' => 'DL1234567890'])
            ->assertSessionHasNoErrors();
        $this->assertContains('pwbd_certificate', Application::first()->requiredDocumentTypes());
    }

    public function test_cuet_domain_paper_must_match_class12(): void
    {
        $this->go('basic', $this->basic());
        $this->go('address', $this->address());
        $this->go('category', ['category_code' => 'UR']);
        $this->go('academics', $this->academics());
        $this->go('cuet', $this->cuet(['ENG' => 180, 'HIS' => 150]))->assertSessionHasErrors('scores');
        $this->go('cuet', $this->cuet(['MAT' => 180, 'PHY' => 150]))->assertSessionHasErrors('scores'); // no language
        $this->go('cuet', $this->cuet(['ENG' => 180, 'MAT' => 150, 'GAT' => 100]))->assertSessionHasNoErrors();
    }

    public function test_only_eligible_courses_and_unique_preferences(): void
    {
        $this->fillToCourses();
        $phy = Course::where('code', 'BSC-PHY')->first();   // PCM: eligible
        $san = Course::where('code', 'BA-SAN')->first();    // needs Sanskrit: not eligible
        $eco = Course::where('code', 'BA-ECO')->first();
        $eng = $this->sub('ENG');

        $this->go('courses', ['csas_application_no' => 'DU27X1', 'courses' => [
            $san->id => ['selected' => 1, 'du_preference' => 1, 'language' => $eng, 'domains' => [$this->sub('SAN'), $this->sub('MAT')]],
        ]])->assertSessionHasErrors("courses.{$san->id}");

        // duplicate preference numbers
        $this->go('courses', ['csas_application_no' => 'DU27X1', 'courses' => [
            $phy->id => ['selected' => 1, 'du_preference' => 2, 'language' => $eng, 'combination' => 'PCM (Physics, Chemistry, Mathematics)'],
            $eco->id => ['selected' => 1, 'du_preference' => 2, 'jmc_not_applied' => 1, 'language' => $eng, 'domains' => [$this->sub('MAT'), $this->sub('ECO')]],
        ]])->assertSessionHasErrors('courses');

        // female + JMC course without JMC preference
        $this->go('courses', ['csas_application_no' => 'DU27X1', 'courses' => [
            $eco->id => ['selected' => 1, 'du_preference' => 3, 'language' => $eng, 'domains' => [$this->sub('MAT'), $this->sub('ECO')]],
        ]])->assertSessionHasErrors("courses.{$eco->id}.jmc_preference");

        // Economics must include Mathematics
        $this->go('courses', ['csas_application_no' => 'DU27X1', 'courses' => [
            $eco->id => ['selected' => 1, 'du_preference' => 3, 'jmc_preference' => 1, 'language' => $eng, 'domains' => [$this->sub('PHY'), $this->sub('ECO')]],
        ]])->assertSessionHasErrors("courses.{$eco->id}");

        $this->go('courses', ['csas_application_no' => 'DU27X1', 'residence' => '1', 'courses' => [
            $phy->id => ['selected' => 1, 'du_preference' => 2, 'language' => $eng, 'combination' => 'PCM (Physics, Chemistry, Mathematics)'],
            $eco->id => ['selected' => 1, 'du_preference' => 3, 'jmc_preference' => 1, 'language' => $eng, 'domains' => [$this->sub('MAT'), $this->sub('ECO')]],
        ]])->assertSessionHasNoErrors();

        $app = Application::first();
        $this->assertTrue($app->residence);
        $this->assertSame(700.0, $app->courses()->where('course_id', $phy->id)->value('cuet_score') + 0.0); // 180+170+160+190
        $this->assertSame(520.0, $app->courses()->where('course_id', $eco->id)->value('cuet_score') + 0.0); // 180+190+150
    }

    public function test_full_flow_upload_and_submit(): void
    {
        Notification::fake();
        $this->fillToCourses();
        $phy = Course::where('code', 'BSC-PHY')->first();
        $this->go('courses', ['csas_application_no' => 'DU27X1', 'courses' => [
            $phy->id => ['selected' => 1, 'du_preference' => 1, 'language' => $this->sub('ENG'), 'combination' => 'PCM (Physics, Chemistry, Mathematics)'],
        ]])->assertSessionHasNoErrors();

        $courseRow = Application::first()->courses()->first();
        $this->go('statements', ['statement_college' => str_repeat('a', 250), 'course_statement' => [$courseRow->id => str_repeat('b', 150)]])->assertSessionHasNoErrors();

        // cannot finish documents step before uploading
        $this->go('documents', [])->assertSessionHasErrors('documents');

        // wrong type rejected
        $this->actingAs($this->user)->post('/application/documents/photo', ['file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('file');

        $app = Application::first();
        foreach ($app->requiredDocumentTypes() as $type) {
            $file = in_array($type, ['photo', 'signature'], true)
                ? UploadedFile::fake()->image("$type.jpg", 200, 200)->size(50)
                : UploadedFile::fake()->image("$type.png", 600, 800)->size(200);
            $this->actingAs($this->user)->post("/application/documents/$type", ['file' => $file])->assertSessionHasNoErrors();
        }
        $this->go('documents', [])->assertSessionHasNoErrors();

        $this->actingAs($this->user)->get('/application/preview')->assertOk()->assertSee('Final Submit');
        $this->actingAs($this->user)->post('/application/submit', [])->assertSessionHasErrors('declaration');
        $this->actingAs($this->user)->post('/application/submit', ['declaration' => '1'])->assertRedirect('/applicant/dashboard');

        $app->refresh();
        $this->assertSame(Application::SUBMITTED, $app->status);
        $this->assertMatchesRegularExpression('/^SSC27\d{6}$/', $app->application_no);
        Notification::assertSentTo($this->user, ApplicationSubmitted::class);

        // locked after submission
        $this->go('basic', $this->basic())->assertForbidden();
        $this->actingAs($this->user)->get('/application/basic')->assertRedirect('/application/preview');
    }

    public function test_applicant_cannot_view_another_applicants_document(): void
    {
        $this->fillToCourses();
        $other = User::factory()->create();
        $app = Application::first();
        $doc = $app->allDocuments()->create(['type' => 'photo', 'path' => 'x.jpg', 'original_name' => 'x.jpg', 'mime' => 'image/jpeg', 'size' => 1, 'sha256' => str_repeat('0', 64)]);

        $this->actingAs($other)->get("/application/documents/file/{$doc->id}")->assertNotFound();
    }

    public function test_changing_class12_subjects_resets_cuet_step(): void
    {
        $this->fillToCourses();
        $this->assertTrue(Application::first()->hasCompleted('cuet'));
        $this->go('academics', $this->academics(['English Core', 'Mathematics', 'Physics', 'Chemistry', 'History']))->assertSessionHasNoErrors();
        $this->assertFalse(Application::first()->hasCompleted('cuet'));
    }
}
