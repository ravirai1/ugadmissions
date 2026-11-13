@extends('layouts.frontend')
@section('title', 'Terms & Conditions')
@section('page_title', 'Terms & Conditions')
@section('content')
<div class="container">
    @include('pages.legal._draft')
    <ol>
        <li class="mb-2">By registering on this portal you confirm that the information you provide is true and complete. False information or forged documents will result in cancellation of your application or admission at any stage.</li>
        <li class="mb-2">Registration and submission of an application do not guarantee an interview or admission. Admissions are governed by the College's admission policy and the rules of the University of Delhi.</li>
        <li class="mb-2">You are responsible for keeping your login details confidential. One account is allowed per CUET application number.</li>
        <li class="mb-2">The application form cannot be edited after final submission, except during a correction window announced by the College.</li>
        <li class="mb-2">The College may contact you at your registered email and mobile number. Keep them active throughout the admission process.</li>
        <li class="mb-2">Fees once paid are subject to the Refund &amp; Cancellation Policy.</li>
        <li class="mb-2">Misuse of the portal, including automated access or attempts to bypass security, is prohibited and may be reported to the authorities.</li>
        <li class="mb-2">The College may update these terms. The version published on this page applies.</li>
        <li class="mb-2">Disputes are subject to the jurisdiction of courts in Delhi.</li>
    </ol>
</div>
@endsection
