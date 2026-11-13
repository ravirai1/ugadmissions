<?php

namespace Tests\Feature;

use App\Models\BlockedIp;
use App\Models\User;
use App\Notifications\ApplicantVerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ApplicantAuthTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Asha Thomas',
            'email' => 'asha@example.com',
            'mobile' => '9876543210',
            'cuet_no' => '271234567890',
            'password' => 'Secret@123',
            'password_confirmation' => 'Secret@123',
            'captcha' => 'test-pass',
        ], $overrides);
    }

    public function test_public_pages_load_with_security_headers(): void
    {
        foreach (['/', '/about', '/faq', '/contact', '/documents-required', '/register', '/login'] as $url) {
            $this->get($url)->assertOk()
                ->assertHeader('X-Frame-Options', 'DENY')
                ->assertHeader('X-Content-Type-Options', 'nosniff')
                ->assertHeader('Content-Security-Policy');
        }
    }

    public function test_applicant_can_register_and_gets_verification_email(): void
    {
        Notification::fake();

        $this->post('/register', $this->payload())->assertRedirect(route('verification.notice'));

        $user = User::where('email', 'asha@example.com')->firstOrFail();
        $this->assertSame('271234567890', $user->cuet_no);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, ApplicantVerifyEmail::class);
    }

    public function test_cuet_number_must_start_with_27(): void
    {
        $this->post('/register', $this->payload(['cuet_no' => '261234567890']))->assertSessionHasErrors('cuet_no');
        $this->post('/register', $this->payload(['cuet_no' => '27ABC']))->assertSessionHasErrors('cuet_no');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_mobile_and_password_rules(): void
    {
        $this->post('/register', $this->payload(['mobile' => '1234567890']))->assertSessionHasErrors('mobile');
        $this->post('/register', $this->payload(['password' => 'weak', 'password_confirmation' => 'weak']))->assertSessionHasErrors('password');
    }

    public function test_wrong_captcha_is_rejected(): void
    {
        $this->get('/register');
        $this->post('/register', $this->payload(['captcha' => '999']))->assertSessionHasErrors('captcha');
    }

    public function test_cuet_number_is_unique(): void
    {
        User::factory()->create(['cuet_no' => '271234567890']);
        $this->post('/register', $this->payload())->assertSessionHasErrors('cuet_no');
    }

    public function test_unverified_user_cannot_open_dashboard(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user)->get('/applicant/dashboard')->assertRedirect(route('verification.notice'));
    }

    public function test_verified_user_sees_dashboard(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/applicant/dashboard')->assertOk()->assertSee($user->cuet_no);
    }

    public function test_login_and_ip_block_after_repeated_failures(): void
    {
        $user = User::factory()->create(['password' => 'Secret@123']);
        $max = config('admissions.lockout.applicant.max_attempts');

        for ($i = 0; $i < $max; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong', 'captcha' => 'test-pass']);
        }

        $this->assertTrue(BlockedIp::active()->where('guard', 'applicant')->exists());
        $this->post('/login', ['email' => $user->email, 'password' => 'Secret@123', 'captcha' => 'test-pass'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_successful_login(): void
    {
        $user = User::factory()->create(['password' => 'Secret@123']);
        $this->post('/login', ['email' => $user->email, 'password' => 'Secret@123', 'captcha' => 'test-pass'])
            ->assertRedirect(route('applicant.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_forgot_password_does_not_reveal_accounts(): void
    {
        $this->post('/forgot-password', ['email' => 'nobody@example.com', 'captcha' => 'test-pass'])
            ->assertSessionHas('status');
    }
}
