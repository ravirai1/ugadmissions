<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\Category;
use App\Models\Scrutiny;
use App\Models\ScrutinyVerification;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

/**
 * Item-by-item verification for scrutiny. Staff never change what the applicant entered: for each item they
 * either confirm it ("OK") or record the value they found in the documents. Those verified values, kept in
 * scrutiny_verifications, are what the merit list uses.
 */
class ScrutinyVerifier
{
    /**
     * Items to verify for this scrutiny record.
     *
     * @return array<string, array{label: string, value: ?string, type: string, options?: array, min?: float, max?: float}>
     */
    public function fields(Scrutiny $s): array
    {
        $app = $s->application;

        if ($s->type === Scrutiny::ACADEMIC) {
            $choice = $s->applicationCourse;
            $papers = $choice->papers ?? [];
            $scores = $app->cuetScores()->with('subject')->get()->keyBy('subject_id');
            $paperMax = (float) config('admissions.merit.cuet_paper_max', 250);
            $f = [];
            foreach ($papers as $id) {
                $row = $scores[$id] ?? null;
                $f["cuet_{$id}"] = ['label' => 'CUET score: '.($row?->subject->name ?? "paper {$id}"), 'value' => $row ? $this->num($row->score) : null, 'type' => 'number', 'min' => 0, 'max' => $paperMax, 'group' => 'CUET papers counted for this course'];
            }
            foreach ($app->class12Marks()->with('class12Subject')->get() as $m) {
                $f["c12_{$m->id}"] = ['label' => 'Class 12 marks: '.$m->class12Subject->name.' (out of '.$m->max_marks.')', 'value' => $this->num($m->marks_obtained), 'type' => 'number', 'min' => 0, 'max' => (float) $m->max_marks, 'group' => 'Class 12 marks'];
            }

            return $f;
        }

        $b = $app->baptism_details ?? [];
        $subs = Category::where('parent_code', Category::CHRISTIAN)->orderBy('sort')->pluck('name', 'code')->all();
        $f = [
            'subcategory' => ['label' => 'Christian sub-category', 'value' => $app->christian_subcategory, 'type' => 'select', 'options' => $subs, 'group' => 'Category'],
            'cst' => ['label' => 'CST (Christian Scheduled Tribe)', 'value' => $app->is_cst ? 'yes' : 'no', 'type' => 'select', 'options' => ['yes' => 'Yes', 'no' => 'No'], 'group' => 'Category'],
        ];
        $prefixes = $app->isBaptised() ? ['' => 'Applicant'] : array_filter(['father_' => 'Father', 'mother_' => 'Mother'], fn ($p) => ! empty($b[$p.'baptism_date']), ARRAY_FILTER_USE_KEY);
        foreach ($prefixes as $p => $who) {
            $f[$p.'baptism_date'] = ['label' => "{$who}: date of baptism", 'value' => $b[$p.'baptism_date'] ?? null, 'type' => 'date', 'group' => 'Baptism'];
            $f[$p.'baptism_church'] = ['label' => "{$who}: church where baptised", 'value' => $b[$p.'baptism_church'] ?? null, 'type' => 'text', 'group' => 'Baptism'];
        }
        foreach (['denomination' => 'Denomination', 'current_church' => 'Present church / parish', 'current_priest' => 'Parish priest', 'current_priest_mobile' => "Parish priest's mobile"] as $k => $label) {
            $f[$k] = ['label' => $label, 'value' => $b[$k] ?? null, 'type' => 'text', 'group' => 'Church'];
        }

        return $f;
    }

    /**
     * Save what staff entered. $input is field => ['ok' => '1'] or field => ['value' => '...'].
     * Items left blank stay as they were, so the work can be done in parts.
     */
    public function save(Scrutiny $s, array $input, Admin $admin): int
    {
        $fields = $this->fields($s);
        $errors = [];
        $saved = 0;
        foreach ($fields as $key => $f) {
            $row = $input[$key] ?? [];
            $value = trim((string) ($row['value'] ?? ''));
            if (! empty($row['ok'])) {
                $result = ScrutinyVerification::OK;
                $value = (string) $f['value'];
            } elseif ($value !== '') {
                if ($msg = $this->invalid($f, $value)) {
                    $errors["verify.{$key}.value"] = $f['label'].': '.$msg;

                    continue;
                }
                $value = $f['type'] === 'number' ? $this->num($value) : ($f['type'] === 'date' ? Carbon::parse($value)->toDateString() : $value);
                $result = $value === (string) $f['value'] ? ScrutinyVerification::OK : ScrutinyVerification::CORRECTED;
            } else {
                continue;
            }
            ScrutinyVerification::updateOrCreate(['scrutiny_id' => $s->id, 'field' => $key], [
                'label' => $f['label'], 'applicant_value' => $f['value'], 'verified_value' => $value, 'result' => $result, 'verified_by' => $admin->id,
            ]);
            $saved++;
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return $saved;
    }

    /** Fields not yet verified. */
    public function missing(Scrutiny $s): array
    {
        $done = $s->verifications()->pluck('field')->all();

        return array_values(array_diff(array_keys($this->fields($s)), $done));
    }

    /** On approval: store the verified figures the merit list uses. */
    public function finalise(Scrutiny $s): void
    {
        $v = $s->verifications()->pluck('verified_value', 'field');
        if ($s->type === Scrutiny::ACADEMIC) {
            $papers = $s->applicationCourse->papers ?? [];
            $s->verified_cuet_score = round(array_sum(array_map(fn ($id) => (float) ($v["cuet_{$id}"] ?? 0), $papers)), 2);
            $marks = $s->application->class12Marks()->get();
            $max = $marks->sum('max_marks');
            $got = $marks->sum(fn ($m) => (float) ($v["c12_{$m->id}"] ?? $m->marks_obtained));
            $s->verified_class12_percent = $max > 0 ? round($got / $max * 100, 2) : null;
        } else {
            $s->verified_subcategory = $v['subcategory'] ?? $s->application->christian_subcategory;
            $s->verified_cst = ($v['cst'] ?? ($s->application->is_cst ? 'yes' : 'no')) === 'yes';
        }
        $s->save();
    }

    private function invalid(array $f, string $value): ?string
    {
        return match ($f['type']) {
            'number' => ! is_numeric($value) ? 'enter a number.' : (((float) $value < ($f['min'] ?? 0) || (float) $value > ($f['max'] ?? PHP_INT_MAX)) ? 'must be between '.$this->num($f['min'] ?? 0).' and '.$this->num($f['max']).'.' : null),
            'date' => strtotime($value) === false ? 'enter a valid date.' : null,
            'select' => ! array_key_exists($value, $f['options']) ? 'choose from the list.' : null,
            default => mb_strlen($value) > 300 ? 'too long.' : null,
        };
    }

    private function num(mixed $v): string
    {
        return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    }
}
