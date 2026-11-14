<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\ApplicantActivityLog;
use App\Models\Application;
use App\Models\BlockedIp;
use App\Models\SecurityEvent;
use App\Models\StaffActivityLog;
use App\Models\User;
use App\Notifications\SecurityAlert;
use App\Services\DocumentStore;
use App\Support\Roles;
use Database\Seeders\MasterDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\FillsApplication;
use Tests\TestCase;

class SecurityLogsTest extends TestCase
{
    use FillsApplication, RefreshDatabase;

    private User $user;

    private Admin $super;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(MasterDataSeeder::class);
        Notification::fake();
        Storage::fake('documents');
        $this->user = User::factory()->create(['password' => 'Secret@1234']);
        $this->super = Admin::create(['name' => 'Super', 'email' => 'super@ststephens.edu', 'password' => 'Str0ng@Passw0rd', 'role' => Roles::SUPER_ADMIN, 'is_active' => true, 'must_change_password' => false]);
    }

    private function asSuper()
    {
        return $this->actingAs($this->super, 'admin')->withSession(['admin_otp_verified' => true, 'admin_last_activity' => time()]);
    }

    public function test_applicant_and_staff_activity_go_to_separate_logs(): void
    {
        $this->post('/login', ['email' => $this->user->email, 'password' => 'wrong', 'captcha' => 'test-pass']);
        $this->post('/login', ['email' => $this->user->email, 'password' => 'Secret@1234', 'captcha' => 'test-pass'])->assertRedirect();
        $this->go('basic', $this->basic())->assertSessionHasNoErrors();
        $this->post('/logout');

        $events = ApplicantActivityLog::where('user_id', $this->user->id)->orderBy('id')->pluck('event')->all();
        $this->assertSame(['login_failed', 'login', 'application.save', 'logout'], $events);
        $this->assertSame(0, StaffActivityLog::count());
        $saved = ApplicantActivityLog::where('event', 'application.save')->first();
        $this->assertSame('/application/basic', $saved->path);
        $this->assertSame(302, $saved->status);
        $this->assertDatabaseMissing('applicant_activity_logs', ['path' => 'Secret@1234']); // input is never stored

        // staff: every page opened is recorded in the staff log only
        $before = ApplicantActivityLog::count();
        $this->asSuper()->get('/ssc-staff/applications')->assertOk();
        $this->assertSame('admin.applications.index', StaffActivityLog::where('admin_id', $this->super->id)->latest('id')->value('event'));
        $this->assertSame($before, ApplicantActivityLog::count());

        // the two log pages (super admin only)
        $this->asSuper()->get('/ssc-staff/security/applicant-activity?event=login')->assertOk()->assertSee($this->user->cuet_no)->assertSee('login_failed');
        $this->asSuper()->get('/ssc-staff/security/staff-activity?q=super@')->assertOk()->assertSee('admin.applications.index');
        $admin = Admin::create(['name' => 'A', 'email' => 'a@ststephens.edu', 'password' => 'Str0ng@Passw0rd', 'role' => Roles::ADMIN, 'is_active' => true, 'must_change_password' => false]);
        $this->actingAs($admin, 'admin')->withSession(['admin_otp_verified' => true, 'admin_last_activity' => time()])->get('/ssc-staff/security/applicant-activity')->assertForbidden();
    }

    public function test_sql_injection_and_script_attempts_are_refused_and_saved(): void
    {
        $this->get("/notices?q=1' OR '1'='1")->assertForbidden();
        $this->get('/notices?q='.urlencode('x UNION/**/SELECT password FROM users'))->assertForbidden();
        $this->post('/login', ['email' => "admin'--", 'password' => 'x', 'captcha' => 'test-pass'])->assertForbidden();
        $this->actingAs($this->user)->post('/application/basic', $this->basic(['father_name' => '<script>alert(1)</script>']))->assertForbidden();
        $this->get('/.env')->assertForbidden();

        $this->assertSame(['sqli', 'sqli', 'sqli', 'xss', 'probe'], SecurityEvent::orderBy('id')->pluck('type')->all());
        $this->assertSame('father_name', SecurityEvent::where('type', 'xss')->value('field'));
        Notification::assertSentTo($this->super, SecurityAlert::class); // first attempt from the IP is emailed

        // ordinary text with apostrophes and words like "select" or "union" is fine
        $this->actingAs($this->user)->post('/application/basic', $this->basic(['father_name' => "John O'Brien", 'last_school' => "St. Mary's Union Select School"]))->assertSessionHasNoErrors();
    }

    public function test_repeated_attacks_block_the_ip_from_the_whole_site_and_email_admins(): void
    {
        config(['security.auto_block.max_events' => 3]);
        foreach (['/wp-admin', '/phpmyadmin', '/shell.php'] as $p) {
            $this->get($p)->assertForbidden();
        }
        $this->assertTrue(BlockedIp::where('ip', '127.0.0.1')->where('guard', 'site')->exists());
        $this->assertTrue(SecurityEvent::latest('id')->first()->caused_block);
        Notification::assertSentTo($this->super, SecurityAlert::class, fn ($n) => str_contains($n->title, 'IP blocked'));

        $this->get('/')->assertForbidden(); // even normal pages, until the block ends

        $this->asSuper()->get('/ssc-staff/security/threats')->assertForbidden(); // same IP
        BlockedIp::query()->delete();
        Cache::flush();
        $this->get('/')->assertOk();
        $this->asSuper()->get('/ssc-staff/security/threats')->assertOk()->assertSee('Probing for admin tools or files')->assertSee('/wp-admin');
    }

    public function test_scanner_tools_and_php_uploads_are_refused(): void
    {
        $this->withHeader('User-Agent', 'sqlmap/1.7')->get('/')->assertForbidden();
        $this->assertSame('scanner', SecurityEvent::value('type'));
        $this->withHeader('User-Agent', 'Mozilla/5.0');

        $this->go('basic', $this->basic());
        // a PHP file renamed to .jpg, or a real image with PHP code appended
        $shell = UploadedFile::fake()->createWithContent('photo.php', '<?php system($_GET["c"]); ?>');
        $this->actingAs($this->user)->post('/application/documents/photo', ['file' => $shell])->assertForbidden();

        $img = imagecreatetruecolor(350, 450);
        imagefill($img, 0, 0, imagecolorallocate($img, 255, 255, 255));
        $path = tempnam(sys_get_temp_dir(), 'img').'.jpg';
        imagejpeg($img, $path, 95);
        file_put_contents($path, '<?php system($_GET["c"]); ?>', FILE_APPEND);
        $app = Application::first();
        try {
            app(DocumentStore::class)->store($app, new UploadedFile($path, 'photo.jpg', 'image/jpeg', null, true), 'photo');
            $this->fail('A JPG with PHP code inside must be refused.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('file', $e->errors());
        }
        $this->assertSame(0, $app->allDocuments()->count());
        $this->assertSame(3, SecurityEvent::count()); // scanner, .php upload name, hidden code
        Notification::assertSentTo($this->super, SecurityAlert::class, fn ($n) => str_contains($n->title, 'hidden PHP'));
    }

    public function test_integrity_check_reports_changed_and_planted_files(): void
    {
        $this->artisan('security:integrity --baseline')->assertSuccessful();
        $this->artisan('security:integrity')->assertSuccessful();

        $planted = public_path('images/x.php');
        file_put_contents($planted, '<?php echo 1;');
        try {
            $this->artisan('security:integrity')->assertFailed()->expectsOutputToContain('public/images/x.php');
            Notification::assertSentTo($this->super, SecurityAlert::class, fn ($n) => str_contains($n->title, 'files changed'));
        } finally {
            @unlink($planted);
        }
        $this->artisan('security:integrity')->assertSuccessful();
    }
}
