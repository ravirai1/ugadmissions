{{-- Baptism and parish rows for a Christian applicant. Needs $b (baptism_details) and $app. --}}
@php $d = fn ($v) => $v ? \Illuminate\Support\Carbon::parse($v)->format('d M Y') : ''; @endphp
@if ($app->isBaptised())
    <tr><th>Baptism</th><td>{{ $d($b['baptism_date'] ?? null) }}, {{ $b['baptism_church'] ?? '' }}, {{ $b['baptism_place'] ?? '' }}</td></tr>
    <tr><th>Baptised by</th><td>{{ $b['baptism_priest_designation'] ?? '' }} {{ $b['baptism_priest'] ?? '' }}</td></tr>
@else
    <tr><th>Baptism</th><td><strong>Not baptised</strong>; parent's baptism given</td></tr>
    @foreach (['father' => "Father's baptism", 'mother' => "Mother's baptism"] as $p => $label)
        @if (! empty($b[$p.'_baptism_date']))
            <tr><th>{{ $label }}</th><td>{{ $d($b[$p.'_baptism_date']) }}, {{ $b[$p.'_baptism_church'] ?? '' }}, {{ $b[$p.'_baptism_place'] ?? '' }}; baptised by {{ $b[$p.'_baptism_priest'] ?? '' }}</td></tr>
        @endif
    @endforeach
@endif
<tr><th>Denomination</th><td>{{ $b['denomination'] ?? '' }}</td></tr>
<tr><th>Present church / priest</th><td>{{ $b['current_church'] ?? '' }} / {{ $b['current_priest'] ?? '' }}</td></tr>
<tr><th>Church address</th><td>{{ $b['current_church_address'] ?? '' }}</td></tr>
<tr><th>Parish priest's contact</th><td>{{ collect([! empty($b['current_priest_mobile']) ? '+91 '.$b['current_priest_mobile'] : null, $b['current_priest_email'] ?? null])->filter()->implode(' / ') }}</td></tr>
