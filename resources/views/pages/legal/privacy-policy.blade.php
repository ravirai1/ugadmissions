@extends('layouts.frontend')
@section('title', 'Privacy Policy')
@section('page_title', 'Privacy Policy')
@section('content')
<div class="container">
    @include('pages.legal._draft')
    <p>St. Stephen's College ("the College") collects personal information through this admissions portal only to process undergraduate admissions for the 2027 session.</p>
    <h5 class="mt-4">Information we collect</h5>
    <p>Name, contact details, date of birth, gender, address, category and religious details, disability details (if declared), academic records, CUET scores, course preferences, uploaded documents, payment transaction references, and technical data such as IP address and login times.</p>
    <h5 class="mt-4">How we use it</h5>
    <p>To verify eligibility, conduct scrutiny and interviews, prepare merit lists, allot seats, communicate with you by email and SMS, process fees, and meet legal and University of Delhi requirements.</p>
    <h5 class="mt-4">Sharing</h5>
    <p>Information is shared only with College staff involved in admissions, the University of Delhi where required, and service providers such as the payment gateway (SBI ePay) and email/SMS providers, each only to the extent needed. We do not sell your data.</p>
    <h5 class="mt-4">Security</h5>
    <p>Data is transmitted over HTTPS, sensitive fields are encrypted in our database, documents are stored privately, and staff access is restricted by role and logged. Card and bank details are entered on the payment gateway's site and are never stored by the College.</p>
    <h5 class="mt-4">Retention</h5>
    <p>Records of admitted students become part of the student record. Records of other applicants are retained for the period required by College and University rules and then deleted.</p>
    <h5 class="mt-4">Your rights</h5>
    <p>You may view your information on your dashboard and request correction during the correction window. For questions, contact {{ config('admissions.contact.email') }}.</p>
</div>
@endsection
