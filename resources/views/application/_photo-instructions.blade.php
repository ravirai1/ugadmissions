{{-- Photograph and signature instructions. --}}
<div class="row g-3 photo-rules">
    @foreach (['photo' => 'Photograph', 'signature' => 'Signature'] as $t => $title)
        <div class="col-md-6">
            <div class="border rounded p-3 h-100 bg-light">
                <h6 class="mb-2"><i class="fa {{ $t === 'photo' ? 'fa-user' : 'fa-signature' }} text-primary me-2"></i>{{ $title }}</h6>
                <ul class="small mb-0 ps-3">
                    @foreach (\App\Support\PhotoRules::INSTRUCTIONS[$t] as $line)<li>{!! $line !!}</li>@endforeach
                </ul>
            </div>
        </div>
    @endforeach
</div>
<p class="small text-muted mt-2 mb-0">The portal checks the size, shape and background when you upload. A photo or signature that does not follow these rules can make your application liable to rejection at scrutiny.</p>
