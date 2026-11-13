{{-- One course choice: eligibility, DU/JMC preferences, language and papers. --}}
    @php
        $o = old("courses.{$course->id}", []);
        $sname = fn ($id) => $subjects[$id]->name ?? '';
        $rules = $course->eligibility;
        $isCombo = ! empty($rules['combinations']);
        $best = $e['best'];
        $selLang = $o['language'] ?? ($c ? collect($c->papers)->first(fn ($id) => in_array($id, collect($e['languages'])->pluck('id')->all())) : ($best['language'][0] ?? null));
        $selDomains = $o['domains'] ?? ($c ? array_values(array_diff($c->papers, [$selLang])) : ($best['domains'] ?? []));
        $selCombo = $o['combination'] ?? ($c->combination ?? ($best['combination'] ?? null));
        $selProg = $o['programme'] ?? ($c->combination ?? null);
        $checked = $o ? ! empty($o['selected']) : (bool) $c;
        $pool = collect($e['domains']); if ($e['general']) { $pool->push($e['general']); }
    @endphp
    <div class="course-card {{ $e['eligible'] ? '' : 'ineligible' }}">
        <div class="d-flex justify-content-between align-items-start">
            <div class="form-check">
                <input class="form-check-input" type="checkbox" id="c{{ $course->id }}" name="courses[{{ $course->id }}][selected]" value="1" @checked($checked && $e['eligible']) @disabled(! $e['eligible'])>
                <label class="form-check-label fw-bold" for="c{{ $course->id }}">{{ $course->name }}</label>
            </div>
            @if ($e['eligible'])
                <span class="badge bg-success">Eligible</span>
            @else
                <span class="badge bg-secondary">Not eligible</span>
            @endif
        </div>
        @unless ($e['eligible'])
            <ul class="small mb-0 mt-2">@foreach ($e['reasons'] as $r)<li>{{ $r }}</li>@endforeach</ul>
        @endunless
        @if ($e['eligible'])
            <div class="row g-3 mt-1" data-show-when="courses[{{ $course->id }}][selected]">
                <div class="col-md-4">
                    <label class="form-label small">DU CSAS preference no. (St. Stephen's) <span class="req">*</span></label>
                    <input type="number" min="1" max="999" class="form-control form-control-sm" name="courses[{{ $course->id }}][du_preference]" value="{{ $o['du_preference'] ?? $c?->du_preference }}">
                </div>
                @if ($female && $course->offered_at_jmc)
                    <div class="col-md-4">
                        <label class="form-label small">JMC preference no. for this course</label>
                        <input type="number" min="1" max="999" class="form-control form-control-sm" name="courses[{{ $course->id }}][jmc_preference]" value="{{ $o['jmc_preference'] ?? $c?->jmc_preference }}">
                    </div>
                    <div class="col-md-4 d-flex align-items-end">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="jna{{ $course->id }}" name="courses[{{ $course->id }}][jmc_not_applied]" value="1" @checked($o['jmc_not_applied'] ?? $c?->jmc_not_applied)>
                            <label class="form-check-label small" for="jna{{ $course->id }}">Not applied to JMC for this course</label>
                        </div>
                    </div>
                @endif
                <div class="col-md-6">
                    <label class="form-label small">CUET language counted <span class="req">*</span></label>
                    <select class="form-select form-select-sm" name="courses[{{ $course->id }}][language]">
                        @foreach ($e['languages'] as $l)<option value="{{ $l->id }}" @selected((string) $selLang === (string) $l->id)>{{ $l->name }} ({{ $scores[$l->id] }})</option>@endforeach
                    </select>
                </div>
                @if ($isCombo)
                    <div class="col-md-6">
                        <label class="form-label small">Subject combination <span class="req">*</span></label>
                        @foreach ($e['combinations'] as $combo)
                            <div class="form-check">
                                <input class="form-check-input" type="radio" id="cb{{ $course->id }}-{{ $loop->index }}" name="courses[{{ $course->id }}][combination]" value="{{ $combo['label'] }}" @checked($selCombo === $combo['label'])>
                                <label class="form-check-label small" for="cb{{ $course->id }}-{{ $loop->index }}">{{ $combo['label'] }} – papers {{ number_format(array_sum(array_map(fn ($id) => $scores[$id] ?? 0, $combo['papers'])), 2) }} + language</label>
                            </div>
                        @endforeach
                    </div>
                @else
                    @for ($k = 0; $k < ($rules['domains']['count'] ?? 0); $k++)
                        <div class="col-md-6">
                            <label class="form-label small">Domain paper {{ $k + 1 }} <span class="req">*</span></label>
                            <select class="form-select form-select-sm" name="courses[{{ $course->id }}][domains][]">
                                <option value="">Select</option>
                                @foreach ($pool as $d)<option value="{{ $d->id }}" @selected((string) ($selDomains[$k] ?? '') === (string) $d->id)>{{ $d->name }} ({{ $scores[$d->id] }})</option>@endforeach
                            </select>
                        </div>
                    @endfor
                    @if (! empty($rules['domains']['must_include']))
                        <div class="col-12 small text-muted">Must include: {{ collect($rules['domains']['must_include'])->map(fn ($code) => $subjects->firstWhere('code', $code)?->name)->implode(', ') }}</div>
                    @endif
                    @if (! empty($rules['programme_options']))
                        <div class="col-md-6">
                            <label class="form-label small">Discipline combination <span class="req">*</span></label>
                            <select class="form-select form-select-sm" name="courses[{{ $course->id }}][programme]">
                                <option value="">Select</option>
                                @foreach ($rules['programme_options'] as $p)<option value="{{ $p }}" @selected($selProg === $p)>{{ $p }}</option>@endforeach
                            </select>
                        </div>
                    @endif
                @endif
                <div class="col-12 small text-muted">
                    Best total (language + papers): {{ collect(array_merge($best['language'] ?? [], $best['domains'] ?? []))->map($sname)->implode(' + ') }}
                    = <strong>{{ number_format(array_sum(array_map(fn ($id) => $scores[$id] ?? 0, array_merge($best['language'] ?? [], $best['domains'] ?? []))), 2) }}</strong>
                </div>
                @if (! empty($withStatement))
                    <div class="col-12">
                        <label class="form-label small">Why do you want to join {{ $course->name }}? <span class="req">*</span></label>
                        <textarea class="form-control form-control-sm @error("courses.{$course->id}.statement") is-invalid @enderror" name="courses[{{ $course->id }}][statement]" rows="4" maxlength="2000" data-count="100">{{ $o['statement'] ?? '' }}</textarea>
                        @error("courses.{$course->id}.statement")<div class="invalid-feedback">{{ $message }}</div>@enderror
                    </div>
                @endif
            </div>
        @endif
    </div>
