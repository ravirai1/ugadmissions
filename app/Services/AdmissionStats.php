<?php

namespace App\Services;

use App\Models\Application;
use App\Models\Category;
use App\Models\Course;
use App\Models\Payment;
use App\Models\PwbdType;
use App\Models\State;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Dashboard figures. $scope is "submitted" (all submitted applications) or "paid" (fee paid).
 */
class AdmissionStats
{
    public const SCOPES = ['paid' => 'Fee paid', 'submitted' => 'All submitted'];

    public function base(string $scope): Builder
    {
        $q = Application::query()->where('applications.status', Application::SUBMITTED);

        return $scope === 'paid' ? $q->whereNotNull('applications.fee_paid_at') : $q;
    }

    public function totals(): array
    {
        return [
            'registrations' => User::count(),
            'verified' => User::whereNotNull('email_verified_at')->count(),
            'started' => Application::where('status', Application::DRAFT)->count(),
            'submitted' => Application::where('status', Application::SUBMITTED)->count(),
            'paid' => Application::whereNotNull('fee_paid_at')->count(),
            'collected' => (float) Payment::where('status', Payment::SUCCESS)->sum('amount'),
        ];
    }

    public function summary(string $scope): array
    {
        $b = fn () => $this->base($scope);

        return [
            'applications' => $b()->count(),
            'residence' => $b()->where('residence', true)->count(),
            'pwbd' => $b()->where('is_pwbd', true)->count(),
            'christian' => $b()->where('category_code', Category::CHRISTIAN)->count(),
            'cst' => $b()->where('is_cst', true)->count(),
            'female' => $b()->where('gender', 'Female')->count(),
        ];
    }

    /** Applications per course; with the fee-paid scope only paid course choices count. */
    public function courseWise(string $scope): array
    {
        $rows = DB::table('application_courses as ac')
            ->join('applications as a', 'a.id', '=', 'ac.application_id')
            ->where('a.status', Application::SUBMITTED)
            ->when($scope === 'paid', fn ($q) => $q->where('ac.payment_status', 'paid'))
            ->groupBy('ac.course_id')
            ->select('ac.course_id',
                DB::raw('count(*) as total'),
                DB::raw("sum(case when a.gender = 'Female' then 1 else 0 end) as female"),
                DB::raw("sum(case when a.category_code = '".Category::CHRISTIAN."' then 1 else 0 end) as christian"),
                DB::raw('sum(case when ac.jmc_preference is not null then 1 else 0 end) as jmc'),
                DB::raw('sum(case when a.residence then 1 else 0 end) as residence'),
                DB::raw('sum(case when ac.added_after_payment then 1 else 0 end) as added_later'))
            ->get()->keyBy('course_id');

        return Course::orderBy('sort')->get()->map(fn ($c) => [
            'course' => $c->name,
            'total' => (int) ($rows[$c->id]->total ?? 0),
            'female' => (int) ($rows[$c->id]->female ?? 0),
            'christian' => (int) ($rows[$c->id]->christian ?? 0),
            'jmc' => (int) ($rows[$c->id]->jmc ?? 0),
            'residence' => (int) ($rows[$c->id]->residence ?? 0),
            'added_later' => (int) ($rows[$c->id]->added_later ?? 0),
        ])->all();
    }

    /** Category-wise, with Christian split into its sub-categories. */
    public function categoryWise(string $scope): array
    {
        $names = Category::pluck('name', 'code');
        $main = $this->base($scope)->groupBy('category_code')->select('category_code', DB::raw('count(*) as n'))->pluck('n', 'category_code');
        $sub = $this->base($scope)->where('category_code', Category::CHRISTIAN)->groupBy('christian_subcategory')
            ->select('christian_subcategory', DB::raw('count(*) as n'))->pluck('n', 'christian_subcategory');

        $out = [];
        foreach ($main as $code => $n) {
            $out[] = ['label' => $names[$code] ?? ($code ?: 'Not given'), 'count' => (int) $n, 'sub' => false];
            if ($code === Category::CHRISTIAN) {
                foreach ($sub as $s => $m) {
                    $out[] = ['label' => '– '.($names[$s] ?? $s ?? 'Not given'), 'count' => (int) $m, 'sub' => true];
                }
            }
        }

        return $out;
    }

    public function genderWise(string $scope): array
    {
        return $this->base($scope)->groupBy('gender')->select('gender', DB::raw('count(*) as n'))->pluck('n', 'gender')
            ->mapWithKeys(fn ($n, $g) => [$g ?: 'Not given' => (int) $n])->all();
    }

    /** @param  string  $by  cur | perm | domicile */
    public function stateWise(string $scope, string $by): array
    {
        $col = ['cur' => 'cur_state_id', 'perm' => 'perm_state_id', 'domicile' => 'domicile_state_id'][$by] ?? 'cur_state_id';
        $names = State::pluck('name', 'id');

        return $this->base($scope)->groupBy($col)->select($col.' as sid', DB::raw('count(*) as n'))->orderByDesc('n')->get()
            ->map(fn ($r) => ['state' => $names[$r->sid] ?? 'Not given', 'count' => (int) $r->n])->all();
    }

    public function pwbdWise(string $scope): array
    {
        $types = PwbdType::all()->keyBy('id');

        return $this->base($scope)->where('is_pwbd', true)->groupBy('pwbd_type_id')->select('pwbd_type_id', DB::raw('count(*) as n'))->get()
            ->map(fn ($r) => ['type' => $types[$r->pwbd_type_id]?->name ?? 'Not given', 'group' => $types[$r->pwbd_type_id]?->group_name, 'count' => (int) $r->n])->all();
    }

    public function payments(): array
    {
        return Payment::where('status', Payment::SUCCESS)->groupBy('purpose')
            ->select('purpose', DB::raw('count(*) as n'), DB::raw('sum(amount) as amount'))->get()
            ->map(fn ($r) => ['purpose' => Payment::PURPOSES[$r->purpose] ?? $r->purpose, 'count' => (int) $r->n, 'amount' => (float) $r->amount])->all();
    }

    /** Submissions and fee payments per day, last $days days (IST). */
    public function daily(int $days = 14): array
    {
        $from = now('Asia/Kolkata')->subDays($days - 1)->startOfDay();
        $sub = Application::whereNotNull('submitted_at')->where('submitted_at', '>=', $from->copy()->utc())->pluck('submitted_at');
        $paid = Application::whereNotNull('fee_paid_at')->where('fee_paid_at', '>=', $from->copy()->utc())->pluck('fee_paid_at');
        $count = fn ($list) => $list->countBy(fn ($d) => $d->timezone('Asia/Kolkata')->toDateString());
        $s = $count($sub);
        $p = $count($paid);

        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $d = $from->copy()->addDays($i)->toDateString();
            $out[] = ['date' => $d, 'submitted' => $s[$d] ?? 0, 'paid' => $p[$d] ?? 0];
        }

        return $out;
    }
}
