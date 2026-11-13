@extends('layouts.frontend')
@section('title', 'FAQ')
@section('page_title', 'Frequently Asked Questions')
@section('content')
<div class="container">
    <div class="accordion" id="faq">
        @foreach ([
            ['Who can apply?', 'Candidates who have appeared in CUET (UG) 2027 and meet the University of Delhi eligibility criteria for the chosen course.'],
            ['What do I need to register?', 'Your full name, a valid email address, a 10-digit mobile number and your CUET 2027 application number (it starts with 27).'],
            ['I did not receive the verification email.', 'Check your spam folder. You can request a new link from the verification page after logging in.'],
            ['How much is the application fee?', '₹500 for the first course and ₹200 for each additional course. Residence is ₹1,000 extra. Courses added after payment cost ₹500 each.'],
            ['Which file formats can I upload?', 'Photographs and signatures as JPG/PNG. Certificates as JPG/PNG or PDF. Maximum sizes are shown on the upload page.'],
            ['Can I edit my form after submitting?', 'No, the form is locked after final submission. Corrections may be allowed only during an announced correction window.'],
            ['How will I know about my interview?', 'Shortlisted candidates receive an interview call letter on their dashboard, by email and by SMS.'],
            ['How do I contact the admissions office?', 'Use the Help button on any page to chat with the admissions team or send a query with a screenshot.'],
        ] as $i => [$q, $a])
            <div class="accordion-item">
                <h2 class="accordion-header" id="h{{ $i }}">
                    <button class="accordion-button {{ $i ? 'collapsed' : '' }} faq-q" type="button" data-bs-toggle="collapse" data-bs-target="#c{{ $i }}" aria-expanded="{{ $i ? 'false' : 'true' }}" aria-controls="c{{ $i }}">{{ $q }}</button>
                </h2>
                <div id="c{{ $i }}" class="accordion-collapse collapse {{ $i ? '' : 'show' }}" aria-labelledby="h{{ $i }}" data-bs-parent="#faq">
                    <div class="accordion-body">{{ $a }}</div>
                </div>
            </div>
        @endforeach
    </div>
</div>
@endsection
