<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Class12Subject;
use App\Models\Course;
use App\Models\Department;
use App\Models\District;
use App\Models\PwbdType;
use App\Models\State;
use App\Models\Subject;

/**
 * Master data tables the admin can edit, described once so one controller and one set of views serve them all.
 * Records are deactivated rather than deleted, because applications refer to them.
 *
 * Field types: text, number, bool, select (options => [value => label] or a closure), json.
 */
final class MasterData
{
    public static function types(): array
    {
        return [
            'courses' => [
                'label' => 'Courses',
                'model' => Course::class,
                'order' => ['sort', 'name'],
                'columns' => ['code', 'name', 'department_id', 'offered_at_jmc', 'sort', 'is_active'],
                'search' => ['code', 'name'],
                'fields' => [
                    'code' => ['label' => 'Code', 'type' => 'text', 'rules' => ['required', 'string', 'max:30', 'alpha_dash'], 'unique' => true],
                    'name' => ['label' => 'Course name', 'type' => 'text', 'rules' => ['required', 'string', 'max:150']],
                    'department_id' => ['label' => 'Department', 'type' => 'select', 'options' => fn () => Department::orderBy('name')->pluck('name', 'id')->all(), 'rules' => ['nullable', 'exists:departments,id']],
                    'offered_at_jmc' => ['label' => 'Also offered at JMC', 'type' => 'bool', 'help' => 'Female applicants are asked for their JMC preference for this course.'],
                    'sort' => ['label' => 'Display order', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:0', 'max:999']],
                    'seats' => ['label' => 'Seats by category (JSON)', 'type' => 'json', 'rows' => 3, 'rules' => ['nullable', 'json'], 'help' => 'Example: {"UR": 20, "OBC": 5, "SC": 3, "ST": 2, "EWS": 2, "CNI": 5, "CNID": 2, "COTH": 3, "PWBD": 1}. Used for seat allotment.'],
                    'eligibility' => ['label' => 'DU eligibility rules (JSON)', 'type' => 'json', 'rows' => 16, 'rules' => ['required', 'json'], 'help' => 'Keys: class12_required (subject codes), min_class12_percent, languages {count, allowed}, domains {count, allowed, must_include}, general_test, combinations [{label, papers}], programme_options. Check against the DU 2027 bulletin.'],
                    'is_active' => ['label' => 'Open for applications', 'type' => 'bool'],
                ],
            ],
            'states' => [
                'label' => 'States / UTs',
                'model' => State::class,
                'order' => ['sort', 'name'],
                'columns' => ['name', 'is_ut', 'sort', 'is_active'],
                'search' => ['name'],
                'fields' => [
                    'name' => ['label' => 'Name', 'type' => 'text', 'rules' => ['required', 'string', 'max:100'], 'unique' => true],
                    'is_ut' => ['label' => 'Union Territory', 'type' => 'bool'],
                    'sort' => ['label' => 'Display order', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:0', 'max:999']],
                    'is_active' => ['label' => 'Active', 'type' => 'bool'],
                ],
            ],
            'districts' => [
                'label' => 'Districts',
                'model' => District::class,
                'order' => ['name'],
                'columns' => ['name', 'state_id', 'is_active'],
                'search' => ['name'],
                'filter' => 'state_id',
                'bulk' => 'state_id', // add many names at once for one state
                'fields' => [
                    'state_id' => ['label' => 'State / UT', 'type' => 'select', 'options' => fn () => State::orderBy('name')->pluck('name', 'id')->all(), 'rules' => ['required', 'exists:states,id']],
                    'name' => ['label' => 'District', 'type' => 'text', 'rules' => ['required', 'string', 'max:100']],
                    'is_active' => ['label' => 'Active', 'type' => 'bool'],
                ],
            ],
            'categories' => [
                'label' => 'Categories',
                'model' => Category::class,
                'order' => ['sort', 'name'],
                'columns' => ['code', 'name', 'parent_code', 'certificate_label', 'is_active'],
                'search' => ['code', 'name'],
                'fields' => [
                    'code' => ['label' => 'Code', 'type' => 'text', 'rules' => ['required', 'string', 'max:20', 'alpha_dash'], 'unique' => true],
                    'name' => ['label' => 'Name', 'type' => 'text', 'rules' => ['required', 'string', 'max:150']],
                    'parent_code' => ['label' => 'Sub-category of', 'type' => 'select', 'options' => fn () => [Category::CHRISTIAN => 'Christian'], 'rules' => ['nullable', 'in:'.Category::CHRISTIAN]],
                    'certificate_label' => ['label' => 'Certificate required (leave blank if none)', 'type' => 'text', 'rules' => ['nullable', 'string', 'max:150']],
                    'sort' => ['label' => 'Display order', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:0', 'max:999']],
                    'is_active' => ['label' => 'Active', 'type' => 'bool'],
                ],
            ],
            'pwbd-types' => [
                'label' => 'PwBD Types',
                'model' => PwbdType::class,
                'order' => ['group_no', 'name'],
                'columns' => ['group_no', 'group_name', 'name', 'is_active'],
                'search' => ['name', 'group_name'],
                'fields' => [
                    'group_no' => ['label' => 'Group no. (RPwD Act)', 'type' => 'number', 'rules' => ['required', 'integer', 'min:1', 'max:9']],
                    'group_name' => ['label' => 'Group name', 'type' => 'text', 'rules' => ['required', 'string', 'max:150']],
                    'name' => ['label' => 'Disability', 'type' => 'text', 'rules' => ['required', 'string', 'max:150']],
                    'is_active' => ['label' => 'Active', 'type' => 'bool'],
                ],
            ],
            'subjects' => [
                'label' => 'CUET Subjects (NTA)',
                'model' => Subject::class,
                'order' => ['type', 'sort', 'name'],
                'columns' => ['code', 'name', 'type', 'is_active'],
                'search' => ['code', 'name'],
                'fields' => [
                    'code' => ['label' => 'Code', 'type' => 'text', 'rules' => ['required', 'string', 'max:20', 'alpha_dash'], 'unique' => true],
                    'name' => ['label' => 'Name as in NTA list', 'type' => 'text', 'rules' => ['required', 'string', 'max:150']],
                    'type' => ['label' => 'Type', 'type' => 'select', 'options' => fn () => ['language' => 'Language', 'domain' => 'Domain', 'general' => 'General Test'], 'rules' => ['required', 'in:language,domain,general']],
                    'sort' => ['label' => 'Display order', 'type' => 'number', 'rules' => ['nullable', 'integer', 'min:0', 'max:999']],
                    'is_active' => ['label' => 'Active', 'type' => 'bool'],
                ],
            ],
            'class12-subjects' => [
                'label' => 'Class 12 Subjects',
                'model' => Class12Subject::class,
                'order' => ['name'],
                'columns' => ['name', 'subject_id', 'is_active'],
                'search' => ['name'],
                'fields' => [
                    'name' => ['label' => 'Subject name as printed by boards', 'type' => 'text', 'rules' => ['required', 'string', 'max:150'], 'unique' => true],
                    'subject_id' => ['label' => 'Counts for CUET paper', 'type' => 'select', 'options' => fn () => Subject::orderBy('name')->pluck('name', 'id')->all(), 'rules' => ['nullable', 'exists:subjects,id'], 'help' => 'Used to check that each CUET domain paper matches a Class 12 subject.'],
                    'is_active' => ['label' => 'Active', 'type' => 'bool'],
                ],
            ],
        ];
    }

    public static function get(string $type): array
    {
        $def = self::types()[$type] ?? abort(404);
        $def['type'] = $type;

        return $def;
    }
}
