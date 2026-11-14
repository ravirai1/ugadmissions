<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Subject;
use App\Support\MasterData;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MasterDataController extends Controller
{
    public function index(Request $request, string $type)
    {
        $def = MasterData::get($type);
        $q = $def['model']::query();
        foreach ($def['order'] as $col) {
            $q->orderBy($col);
        }
        if (($f = $def['filter'] ?? null) && $request->filled($f)) {
            $q->where($f, $request->integer($f));
        }
        if ($term = trim((string) $request->query('q'))) {
            $q->where(function ($w) use ($def, $term) {
                foreach ($def['search'] as $col) {
                    $w->orWhere($col, 'like', '%'.addcslashes($term, '%_\\').'%');
                }
            });
        }

        return view('admin.master.index', [
            'def' => $def,
            'types' => MasterData::types(),
            'rows' => $q->paginate(50)->withQueryString(),
            'options' => $this->options($def),
        ]);
    }

    public function create(string $type)
    {
        $def = MasterData::get($type);
        $model = new $def['model'](['is_active' => true]);

        return view('admin.master.form', ['def' => $def, 'row' => $model, 'options' => $this->options($def)]);
    }

    public function store(Request $request, string $type)
    {
        $def = MasterData::get($type);
        $row = $def['model']::create($this->validated($request, $def));
        $this->changed($def, 'create', $row);

        return redirect()->route('admin.master.index', $type)->with('status', 'Added.');
    }

    public function edit(string $type, int $id)
    {
        $def = MasterData::get($type);

        return view('admin.master.form', ['def' => $def, 'row' => $def['model']::findOrFail($id), 'options' => $this->options($def)]);
    }

    public function update(Request $request, string $type, int $id)
    {
        $def = MasterData::get($type);
        $row = $def['model']::findOrFail($id);
        $row->update($this->validated($request, $def, $row));
        $this->changed($def, 'update', $row);

        return redirect()->route('admin.master.index', $type)->with('status', 'Saved.');
    }

    public function toggle(string $type, int $id)
    {
        $def = MasterData::get($type);
        $row = $def['model']::findOrFail($id);
        $row->forceFill(['is_active' => ! $row->is_active])->save();
        $this->changed($def, $row->is_active ? 'activate' : 'deactivate', $row);

        return back()->with('status', $row->is_active ? 'Activated.' : 'Deactivated.');
    }

    /** Add many records (one per line) under one parent, e.g. all districts of a state. */
    public function bulk(Request $request, string $type)
    {
        $def = MasterData::get($type);
        $parent = $def['bulk'] ?? abort(404);
        $data = $request->validate([
            $parent => $def['fields'][$parent]['rules'],
            'names' => ['required', 'string', 'max:20000'],
        ]);

        $added = 0;
        foreach (preg_split('/\r\n|\r|\n/', $data['names']) as $name) {
            $name = trim(preg_replace('/\s+/', ' ', $name));
            if ($name === '' || mb_strlen($name) > 100) {
                continue;
            }
            $row = $def['model']::firstOrCreate([$parent => $data[$parent], 'name' => $name], ['is_active' => true]);
            $added += $row->wasRecentlyCreated ? 1 : 0;
        }
        AuditLog::record("master.$type.bulk", null, [$parent => $data[$parent], 'added' => $added]);

        return redirect()->route('admin.master.index', [$type, $parent => $data[$parent]])->with('status', "Added $added new record(s).");
    }

    /* ---- helpers ---- */

    private function options(array $def): array
    {
        return collect($def['fields'])->filter(fn ($f) => $f['type'] === 'select')->map(fn ($f) => value($f['options']))->all();
    }

    private function validated(Request $request, array $def, ?Model $row = null): array
    {
        $rules = [];
        foreach ($def['fields'] as $name => $f) {
            if ($f['type'] === 'bool') {
                $request->merge([$name => $request->boolean($name)]);
                $rules[$name] = ['boolean'];

                continue;
            }
            $rules[$name] = $f['rules'];
            if (! empty($f['unique'])) {
                $rules[$name][] = Rule::unique((new $def['model'])->getTable(), $name)->ignore($row?->getKey());
            }
        }
        $data = $request->validate($rules);

        foreach ($def['fields'] as $name => $f) {
            if ($f['type'] === 'json') {
                $data[$name] = filled($data[$name] ?? null) ? json_decode($data[$name], true) : null;
            }
            if ($f['type'] === 'number' && ($data[$name] ?? null) === null) {
                $data[$name] = 0;
            }
        }
        if ($def['type'] === 'courses') {
            $data['eligibility'] = self::eligibilityFromForm($data['eligibility'] ?? []);
            $this->checkEligibility($data['eligibility']);
        }

        return $data;
    }

    /** Catches typos in subject codes and shapes before they reach applicants. */
    private function checkEligibility(mixed $r): void
    {
        $fail = fn (string $m) => throw ValidationException::withMessages(['eligibility' => $m]);
        if (! is_array($r)) {
            $fail('Eligibility rules are missing.');
        }
        if (empty($r['languages']) && empty($r['domains']) && empty($r['combinations']) && empty($r['general_test'])) {
            $fail('Choose which CUET papers count: languages, domain subjects, a fixed combination or the General Test.');
        }
        if (! empty($r['combinations']) && ! empty($r['domains'])) {
            $fail('Use either domain subjects or fixed combinations, not both.');
        }
        $known = ['class12_required', 'min_class12_percent', 'languages', 'domains', 'general_test', 'combinations', 'programme_options'];
        if ($extra = array_diff(array_keys($r), $known)) {
            $fail('Unknown key(s): '.implode(', ', $extra).'.');
        }

        $codes = Subject::pluck('code')->all();
        $used = array_merge(
            $r['class12_required'] ?? [],
            $r['languages']['allowed'] ?? [],
            $r['domains']['allowed'] ?? [],
            $r['domains']['must_include'] ?? [],
            collect($r['combinations'] ?? [])->flatMap(fn ($c) => $c['papers'] ?? [])->all(),
        );
        if ($bad = array_diff($used, $codes)) {
            $fail('Unknown subject code(s): '.implode(', ', array_unique($bad)).'. Use codes from CUET Subjects.');
        }
        foreach (['languages', 'domains'] as $k) {
            if (isset($r[$k]) && (! is_array($r[$k]) || ! is_int($r[$k]['count'] ?? null) || $r[$k]['count'] < 0 || $r[$k]['count'] > 5)) {
                $fail(ucfirst($k).': the number of papers must be between 0 and 5.');
            }
        }
        foreach ($r['combinations'] ?? [] as $c) {
            if (empty($c['label']) || empty($c['papers']) || ! is_array($c['papers'])) {
                $fail('Each combination needs a name and at least one paper.');
            }
        }
        if (isset($r['min_class12_percent']) && (! is_numeric($r['min_class12_percent']) || $r['min_class12_percent'] < 0 || $r['min_class12_percent'] > 100)) {
            $fail('Minimum Class 12 % must be between 0 and 100.');
        }
    }

    /** Turns the eligibility form into the rules array used by course selection. Empty parts are left out. */
    public static function eligibilityFromForm(array $f): array
    {
        $codes = fn ($v) => array_values(array_filter(array_map('strval', (array) ($v ?? [])), fn ($c) => $c !== ''));
        $r = [];
        if ($c = $codes($f['class12_required'] ?? [])) {
            $r['class12_required'] = $c;
        }
        if (($f['min_class12_percent'] ?? '') !== '' && $f['min_class12_percent'] !== null) {
            $r['min_class12_percent'] = (float) $f['min_class12_percent'];
        }
        foreach (['languages', 'domains'] as $k) {
            $count = (int) ($f[$k]['count'] ?? 0);
            if ($count > 0) {
                $r[$k] = ['count' => $count, 'allowed' => $codes($f[$k]['allowed'] ?? []) ?: null];
                if ($k === 'domains' && ($m = $codes($f[$k]['must_include'] ?? []))) {
                    $r[$k]['must_include'] = $m;
                }
            }
        }
        if (! empty($f['general_test'])) {
            $r['general_test'] = true;
        }
        $combos = collect($f['combinations'] ?? [])->map(fn ($c) => ['label' => trim((string) ($c['label'] ?? '')), 'papers' => $codes($c['papers'] ?? [])])
            ->filter(fn ($c) => $c['label'] !== '' || $c['papers'])->values()->all();
        if ($combos) {
            $r['combinations'] = $combos;
        }
        $opts = array_values(array_filter(array_map('trim', preg_split('/\R/', (string) ($f['programme_options'] ?? '')))));
        if ($opts) {
            $r['programme_options'] = $opts;
        }

        return $r;
    }

    private function changed(array $def, string $action, Model $row): void
    {
        Cache::forget('master.'.$def['type']);
        AuditLog::record("master.{$def['type']}.$action", $row, $action === 'update' ? ['changed' => array_keys($row->getChanges())] : []);
    }
}
