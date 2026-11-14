<?php

namespace App\Http\Controllers;

use App\Models\Notice;
use App\Support\Prospectus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class PageController extends Controller
{
    public function home()
    {
        return view('pages.home', ['notices' => Notice::forUser(null)->limit(5)->get()]);
    }

    /** Public notices, plus the applicant's own when logged in. */
    public function notices(Request $request)
    {
        return view('pages.notices', ['notices' => Notice::forUser($request->user()?->hasVerifiedEmail() ? $request->user() : null)->paginate(20)]);
    }

    /** The prospectus as a page-turning book, with a download link. */
    public function prospectus()
    {
        abort_unless($p = Prospectus::current(), 404);

        return view('pages.prospectus', ['prospectus' => $p]);
    }

    /** The prospectus PDF itself (shown inline, or downloaded with ?download=1). */
    public function prospectusFile(Request $request)
    {
        $p = Prospectus::current();
        abort_unless($p && Storage::disk('documents')->exists($p['path']), 404);
        $name = 'SSC-UG-Prospectus-2027.pdf';
        $res = Storage::disk('documents')->response($p['path'], $name, [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'public, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ], $request->boolean('download') ? 'attachment' : 'inline');

        return $res;
    }

    public function about()
    {
        return view('pages.about');
    }

    public function faq()
    {
        return view('pages.faq');
    }

    public function contact()
    {
        return view('pages.contact');
    }

    public function documents()
    {
        return view('pages.documents');
    }

    public function christianGuidelines()
    {
        return view('pages.christian-guidelines');
    }

    public function legal(string $page)
    {
        abort_unless(in_array($page, ['privacy-policy', 'terms-and-conditions', 'refund-policy', 'disclaimer'], true), 404);

        return view('pages.legal.'.$page);
    }
}
