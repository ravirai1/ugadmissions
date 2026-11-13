@extends('layouts.frontend')
@section('title', 'Guidelines for Christian Students')
@section('page_title', 'Guidelines & Procedure for Christian Students')
@section('content')
<div class="container">
    <div class="alert alert-warning small">Draft text. The admissions office should review and replace it with the official 2027 guidelines before the portal opens.</div>

    <p>St. Stephen's College is a Christian minority institution. Seats are reserved for Christian applicants in accordance with the College's admission policy. Applicants who wish to be considered under a Christian category must follow the procedure below.</p>

    <h4 class="mt-4">1. Christian categories</h4>
    <div class="table-responsive">
        <table class="table table-bordered">
            <thead class="table-light"><tr><th>Code</th><th>Category</th><th>Who should choose it</th></tr></thead>
            <tbody>
                <tr><td><strong>CNI</strong></td><td>Church of North India</td><td>Members of a congregation of the Church of North India.</td></tr>
                <tr><td><strong>CNID</strong></td><td>Church of North India – Delhi Diocese</td><td>Members of a congregation of the Delhi Diocese of the Church of North India.</td></tr>
                <tr><td><strong>COTH</strong></td><td>Other Christian denominations</td><td>Members of any other Christian church or denomination.</td></tr>
                <tr><td><strong>CST</strong></td><td>Christian Scheduled Tribe (option)</td><td>Christian applicants who also hold a valid Scheduled Tribe certificate. Tick the CST box in addition to your sub-category.</td></tr>
            </tbody>
        </table>
    </div>

    <h4 class="mt-4">2. Details asked in the form</h4>
    <ul>
        <li>Sub-category (CNI, CNID or COTH) and, if applicable, the CST option</li>
        <li>Date and place of baptism, and the name of the church where you were baptised</li>
        <li>Name and designation of the priest/minister who baptised you</li>
        <li>Name of the church/parish where you are currently a member, and the name of your present priest/minister</li>
    </ul>

    <h4 class="mt-4">3. Documents to upload</h4>
    <ul>
        <li><strong>Baptism certificate</strong> issued by the church, with the church seal</li>
        <li><strong>Letter from your priest/minister</strong> on church letterhead, signed and sealed, confirming that you and your family are members of the congregation</li>
        <li><strong>ST certificate</strong> issued by the competent authority (only if you choose CST)</li>
    </ul>
    <p class="small text-muted">Upload clear scans as JPG/PNG or PDF, up to 2 MB each. Names and dates must match the details you enter in the form.</p>

    <h4 class="mt-4">4. Religious scrutiny</h4>
    <ol>
        <li>After you submit the form and pay the fee, your religious details and certificates are checked by the College's religious scrutiny team.</li>
        <li>If a document is missing or unclear, you will receive an email at your registered address explaining what is needed. Reply using the secure upload link in that email.</li>
        <li>Your application is cleared for interview only when both academic and religious scrutiny are approved.</li>
        <li>If your Christian category claim cannot be verified, your application may be considered under the General category, as per the College's policy.</li>
    </ol>

    <h4 class="mt-4">5. Important</h4>
    <ul>
        <li>Providing false information or forged certificates will lead to cancellation of your application or admission at any stage.</li>
        <li>Original documents must be produced at the time of interview and admission.</li>
        <li>For questions, use the Help button or write to {{ config('admissions.contact.email') }}.</li>
    </ul>
</div>
@endsection
