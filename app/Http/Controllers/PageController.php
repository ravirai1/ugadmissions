<?php

namespace App\Http\Controllers;

class PageController extends Controller
{
    public function home()
    {
        return view('pages.home');
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
