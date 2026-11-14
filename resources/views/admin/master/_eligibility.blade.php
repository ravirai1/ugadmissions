{{-- Eligibility rules as a form. $rules is the saved rules array (or the old form input after a validation error). --}}
@php
    $subjects = \App\Models\Subject::where('is_active', true)->orderBy('sort')->get(['code', 'name', 'type']);
    $langs = $subjects->where('type', \App\Models\Subject::LANGUAGE);
    $domains = $subjects->where('type', \App\Models\Subject::DOMAIN);
    $nonGeneral = $subjects->where('type', '!=', \App\Models\Subject::GENERAL);
    $sel = fn ($v) => array_map('strval', (array) ($v ?? []));
    $combos = array_values($rules['combinations'] ?? []);
    while (count($combos) < 4) { $combos[] = ['label' => '', 'papers' => []]; }
    $prog = $rules['programme_options'] ?? '';
    if (is_array($prog)) { $prog = implode("\n", $prog); }
    $checks = function (string $name, $list, array $chosen) {
        $out = '<div class="elig-checks">';
        // ticked subjects first, so they are visible without scrolling
        foreach ($list->sortBy(fn ($s) => in_array($s->code, $chosen, true) ? 0 : 1, SORT_REGULAR, false) as $s) {
            $id = str_replace(['[', ']'], '_', $name).$s->code;
            $out .= '<div class="form-check form-check-inline"><input class="form-check-input" type="checkbox" name="'.e($name).'[]" value="'.e($s->code).'" id="'.e($id).'"'.(in_array($s->code, $chosen, true) ? ' checked' : '').'><label class="form-check-label small" for="'.e($id).'">'.e($s->name).'</label></div>';
        }

        return $out.'</div>';
    };
@endphp
<fieldset class="border rounded p-3">
    <legend class="float-none w-auto px-2 fs-6 fw-bold mb-0">{{ $label }}</legend>
    @error('eligibility')<div class="alert alert-danger py-2">{{ $message }}</div>@enderror
    <p class="small text-muted">Check against the DU 2027 bulletin. Applicants are checked against these rules when they choose courses, and the CUET papers counted give their CUET score for the course.</p>

    <h6 class="mt-3">Class 12</h6>
    <div class="row g-2">
        <div class="col-md-9">
            <label class="form-label small mb-1">Subjects the applicant must have studied in Class 12</label>
            {!! $checks('eligibility[class12_required]', $nonGeneral, $sel($rules['class12_required'] ?? [])) !!}
        </div>
        <div class="col-md-3">
            <label class="form-label small mb-1" for="elig_min12">Minimum Class 12 aggregate %</label>
            <input type="number" class="form-control form-control-sm" id="elig_min12" name="eligibility[min_class12_percent]" value="{{ $rules['min_class12_percent'] ?? '' }}" min="0" max="100" step="0.01" placeholder="None">
        </div>
    </div>

    <h6 class="mt-4">CUET papers counted</h6>
    <div class="row g-2 mb-2">
        <div class="col-md-3">
            <label class="form-label small mb-1" for="elig_lang">Language papers</label>
            <input type="number" class="form-control form-control-sm" id="elig_lang" name="eligibility[languages][count]" value="{{ $rules['languages']['count'] ?? 0 }}" min="0" max="5">
        </div>
        <div class="col-md-9">
            <label class="form-label small mb-1">Only these languages (leave all unticked for any language)</label>
            {!! $checks('eligibility[languages][allowed]', $langs, $sel($rules['languages']['allowed'] ?? [])) !!}
        </div>
    </div>
    <div class="row g-2 mb-2">
        <div class="col-md-3">
            <label class="form-label small mb-1" for="elig_dom">Domain papers (applicant chooses)</label>
            <input type="number" class="form-control form-control-sm" id="elig_dom" name="eligibility[domains][count]" value="{{ $rules['domains']['count'] ?? 0 }}" min="0" max="5">
            <div class="form-text">Use 0 if the course uses fixed combinations below.</div>
        </div>
        <div class="col-md-9">
            <label class="form-label small mb-1">Only these domain subjects (leave all unticked for any)</label>
            {!! $checks('eligibility[domains][allowed]', $domains, $sel($rules['domains']['allowed'] ?? [])) !!}
            <label class="form-label small mb-1 mt-2">Must include</label>
            {!! $checks('eligibility[domains][must_include]', $domains, $sel($rules['domains']['must_include'] ?? [])) !!}
        </div>
    </div>
    <div class="form-check mb-3">
        <input class="form-check-input" type="checkbox" id="elig_gt" name="eligibility[general_test]" value="1" @checked(! empty($rules['general_test']))>
        <label class="form-check-label" for="elig_gt">General Aptitude Test (GAT) is also counted</label>
    </div>

    <h6 class="mt-3">Fixed subject combinations <span class="small text-muted fw-normal">(for science courses; the applicant picks one)</span></h6>
    @foreach ($combos as $i => $c)
        <div class="row g-2 mb-2 align-items-start">
            <div class="col-md-4"><input class="form-control form-control-sm" name="eligibility[combinations][{{ $i }}][label]" value="{{ $c['label'] ?? '' }}" placeholder="e.g. PCM (Physics, Chemistry, Mathematics)" aria-label="Combination {{ $i + 1 }} name"></div>
            <div class="col-md-8">{!! $checks("eligibility[combinations][$i][papers]", $domains, $sel($c['papers'] ?? [])) !!}</div>
        </div>
    @endforeach

    <h6 class="mt-3">Programme subject pairs <span class="small text-muted fw-normal">(B.A. Programme only)</span></h6>
    <textarea class="form-control form-control-sm" name="eligibility[programme_options]" rows="3" placeholder="One per line, e.g. Economics + History" aria-label="Programme subject pairs">{{ $prog }}</textarea>
</fieldset>
