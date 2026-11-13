@php
    $map = [
        'success' => ['bg-success', 'Successful'],
        'pending' => ['bg-warning text-dark', 'Awaiting confirmation'],
        'initiated' => ['bg-secondary', 'Not completed'],
        'failed' => ['bg-danger', 'Failed'],
    ];
    [$cls, $label] = $map[$p->status] ?? ['bg-secondary', ucfirst($p->status)];
@endphp
<span class="badge {{ $cls }}">{{ $label }}</span>
