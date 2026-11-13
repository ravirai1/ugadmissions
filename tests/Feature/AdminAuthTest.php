<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\BlockedIp;
use App\Models\User;
use App\Notifications\AdminOtp;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AdminAuthTest extends TestCase
{
    use RefreshDatabase;

    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        $this->base = '/'.config('admissions.admin_path');
    }

    private function makeAdmin(array $attrs = []): Admin
    {
        return Admin::create(array_merge([
            'name' => 'Super',
            'email' => 'super@ststephens.edu',
            'password' => 'Str0ng@Passw0rd',
            'role' => Roles::SUPER_ADMIN,
            'is_active' => true,
            'must_change_password' => false,
        ], $attrs));
    }

    public function test_full_login_with_otp(): void
    {
        Notification::fake();
        $admin = $this->makeAdmin();
        $code = null;

        $this->post("$this->base/login", ['email' => $admin->email, 'password' => 'Str0ng@Passw0rd', 'captcha' => 'test-pass'])
            ->assertRedirect(route('admin.otp.show'));

        Notification::assertSentTo($admin, AdminOtp::class, function ($n) use (&$code, $admin) {
            preg_match('/\*\*(\d{6})\*\*/', implode(' ', $n->toMail($admin)->introLines), $m);
            $code = $m[1] ?? null;

            return true;
        });

        // Dashboard is not reachable before OTP.
        $this->get("$this->base")->assertRedirect(route('admin.login'));

        $this->post("$this->base/otp", ['otp' => $code])->assertRedirect(route('admin.dashboard'));
        $this->get("$this->base")->assertOk()->assertSee('Registrations');
    }

    public function test_wrong_password_blocks_ip_after_five_attempts(): void
    {
        $admin = $this->makeAdmin();

        for ($i = 0; $i < 5; $i++) {
            $this->post("$this->base/login", ['email' => $admin->email, 'password' => 'nope', 'captcha' => 'test-pass'])
                ->assertSessionHasErrors('email');
        }

        $this->assertTrue(BlockedIp::active()->where('guard', 'admin')->exists());
        $this->post("$this->base/login", ['email' => $admin->email, 'password' => 'Str0ng@Passw0rd', 'captcha' => 'test-pass'])
            ->assertStatus(429);
    }

    public function test_inactive_admin_cannot_log_in(): void
    {
        $admin = $this->makeAdmin(['is_active' => false]);
        $this->post("$this->base/login", ['email' => $admin->email, 'password' => 'Str0ng@Passw0rd', 'captcha' => 'test-pass'])
            ->assertSessionHasErrors('email');
    }

    public function test_applicant_session_cannot_reach_admin_panel(): void
    {
        $this->actingAs(User::factory()->create())->get("$this->base")->assertRedirect(route('admin.login'));
    }

    public function test_must_change_password_is_enforced(): void
    {
        $admin = $this->makeAdmin(['must_change_password' => true, 'role' => Roles::DEPARTMENT, 'email' => 'f@ststephens.edu']);

        $this->actingAs($admin, 'admin')
            ->withSession(['admin_otp_verified' => true, 'admin_last_activity' => time()])
            ->get("$this->base")
            ->assertRedirect(route('admin.password.edit'));
    }

    public function test_only_super_admin_sees_security_pages(): void
    {
        $faculty = $this->makeAdmin(['role' => Roles::DEPARTMENT, 'email' => 'f@ststephens.edu']);

        $this->actingAs($faculty, 'admin')
            ->withSession(['admin_otp_verified' => true, 'admin_last_activity' => time()])
            ->get("$this->base/security/blocked-ips")
            ->assertForbidden();
    }

    public function test_idle_session_is_logged_out(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin, 'admin')
            ->withSession(['admin_otp_verified' => true, 'admin_last_activity' => time() - 3600])
            ->get("$this->base")
            ->assertRedirect(route('admin.login'));
    }

    public function test_super_admin_can_unblock_ip(): void
    {
        $admin = $this->makeAdmin();
        $row = BlockedIp::create(['ip' => '10.0.0.9', 'guard' => 'admin', 'blocked_until' => now()->addHour()]);

        $this->actingAs($admin, 'admin')
            ->withSession(['admin_otp_verified' => true, 'admin_last_activity' => time()])
            ->delete("$this->base/security/blocked-ips/{$row->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('blocked_ips', ['id' => $row->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'security.ip_unblocked']);
    }
}
