@extends('layouts.frontend')
@section('title', 'Documents Required')
@section('page_title', 'Documents Required')
@section('content')
<div class="container">
    <p>Keep scanned copies of the following ready before you start. Photographs and signatures must be <strong>JPG/PNG</strong>; certificates may be <strong>JPG/PNG or PDF</strong>.</p>
    <div class="table-responsive">
        <table class="table table-bordered align-middle">
            <thead class="table-light">
                <tr><th>#</th><th>Document</th><th>Required for</th><th>Format / size</th></tr>
            </thead>
            <tbody>
                @foreach ([
                    ['Recent passport-size photograph (last 3 months, white background)', 'All applicants', 'JPG/PNG, 20 KB to 300 KB'],
                    ['Signature (black or blue ink on white paper)', 'All applicants', 'JPG/PNG, 4 KB to 150 KB'],
                    ['Class 10 marksheet / certificate', 'All applicants', 'JPG/PNG/PDF, up to 2 MB'],
                    ['Class 12 marksheet', 'All applicants', 'JPG/PNG/PDF, up to 2 MB'],
                    ['CUET (UG) 2027 scorecard', 'All applicants', 'JPG/PNG/PDF, up to 2 MB'],
                    ['DU CSAS (UG) preference page', 'All applicants', 'JPG/PNG/PDF, up to 2 MB'],
                    ['Baptism certificate (at least 2 years old)', 'Christian categories (CNI, CNID, COTH)', 'JPG/PNG/PDF, up to 2 MB'],
                    ['Parent\'s baptism certificate', 'Christian applicants who are not baptised', 'JPG/PNG/PDF, up to 2 MB'],
                    ['Church membership / priest\'s letter', 'Christian categories', 'JPG/PNG/PDF, up to 2 MB'],
                    ['ST certificate', 'CST (Christian ST)', 'JPG/PNG/PDF, up to 2 MB'],
                    ['Category certificate (OBC-NCL / SC / ST / EWS)', 'Applicants claiming these categories', 'JPG/PNG/PDF, up to 2 MB'],
                    ['Disability certificate (UDID)', 'PwBD applicants', 'JPG/PNG/PDF, up to 2 MB'],
                ] as $i => [$doc, $for, $fmt])
                    <tr><td>{{ $i + 1 }}</td><td>{{ $doc }}</td><td>{{ $for }}</td><td>{{ $fmt }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <h4 class="mt-4 mb-3">Photograph and signature instructions</h4>
    @include('application._photo-instructions')
</div>
@endsection
