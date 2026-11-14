<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Notice;
use App\Support\Prospectus;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProspectusTickerTest extends TestCase
{
    use RefreshDatabase;

    private function admin(string $role = Roles::ADMIN)
    {
        $a = Admin::create(['name' => 'Staff', 'email' => $role.uniqid().'@ststephens.edu', 'password' => 'Str0ng@Passw0rd', 'role' => $role, 'is_active' => true, 'must_change_password' => false]);

        return $this->actingAs($a, 'admin')->withSession(['admin_otp_verified' => true, 'admin_last_activity' => time()]);
    }

    private function pdf(string $name = 'prospectus.pdf'): UploadedFile
    {
        return UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n1 0 obj<<>>endobj\ntrailer<<>>\n%%EOF");
    }

    public function test_admin_uploads_prospectus_and_public_sees_flipbook(): void
    {
        Storage::fake('documents');
        $base = '/'.config('admissions.admin_path');
        $this->get('/prospectus')->assertNotFound();
        $this->get('/')->assertDontSee('Read the prospectus');

        $this->admin(Roles::PRINT)->get($base.'/prospectus')->assertForbidden();
        $this->admin()->post($base.'/prospectus', ['title' => 'UG Prospectus 2027', 'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
            ->assertSessionHasErrors('file'); // not a real PDF
        $this->admin()->post($base.'/prospectus', ['title' => 'UG Prospectus 2027', 'file' => $this->pdf()])->assertSessionHasNoErrors();

        $this->get('/')->assertSee('Read the prospectus');
        $this->get('/prospectus')->assertOk()->assertSee('UG Prospectus 2027')->assertSee('page-flip.browser.js');
        $this->get('/prospectus/file')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('attachment', $this->get('/prospectus/file?download=1')->headers->get('Content-Disposition'));

        // replacing removes the old file; title-only update keeps the file
        $old = Prospectus::current()['path'];
        $this->admin()->post($base.'/prospectus', ['title' => 'New', 'file' => $this->pdf('v2.pdf')])->assertSessionHasNoErrors();
        Storage::disk('documents')->assertMissing($old);
        $this->admin()->post($base.'/prospectus', ['title' => 'Renamed'])->assertSessionHasNoErrors();
        $this->get('/prospectus')->assertSee('Renamed');

        $this->admin()->delete($base.'/prospectus')->assertRedirect();
        $this->get('/prospectus')->assertNotFound();
    }

    public function test_scrolling_bar_shows_chosen_public_notices(): void
    {
        Notice::create(['title' => 'Registration opens 1 June', 'body' => 'x', 'audience' => 'public', 'is_published' => true, 'in_ticker' => true, 'published_at' => now()]);
        Notice::create(['title' => 'Not in the bar', 'body' => 'x', 'audience' => 'public', 'is_published' => true, 'published_at' => now()]);
        Notice::create(['title' => 'Paid only bar', 'body' => 'x', 'audience' => 'paid', 'is_published' => true, 'in_ticker' => true, 'published_at' => now()]);

        $this->get('/about')->assertOk()->assertSee('ssc-ticker', false)->assertSee('Registration opens 1 June')->assertDontSee('Paid only bar');
        $bar = explode('<main', explode('ssc-ticker-track', $this->get('/faq')->getContent())[1])[0];
        $this->assertStringContainsString('Registration opens 1 June', $bar);
        $this->assertStringNotContainsString('Not in the bar', $bar);

        Notice::query()->update(['in_ticker' => false]);
        Cache::forget('notices.ticker');
        $this->get('/about')->assertDontSee('ssc-ticker-track', false);
    }
}
