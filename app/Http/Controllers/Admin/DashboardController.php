<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\BlockedIp;
use App\Models\LoginAttempt;
use App\Models\Payment;
use App\Models\User;
use App\Services\AdmissionStats;
use App\Support\ApplicationAccess;
use App\Support\Excel;
use App\Support\Roles;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private AdmissionStats $stats) {}

    /** Roles that see college-wide statistics. */
    public const STATS_ROLES = [Roles::ADMIN, Roles::ACCOUNTS];

    public function index(Request $request)
    {
        $admin = $request->user('admin');
        $scope = array_key_exists($request->query('scope'), AdmissionStats::SCOPES) ? $request->query('scope') : 'paid';
        $data = ['scope' => $scope, 'scopes' => AdmissionStats::SCOPES, 'admin' => $admin];

        if ($admin->hasRole(...self::STATS_ROLES)) {
            $data += [
                'totals' => $this->stats->totals(),
                'summary' => $this->stats->summary($scope),
                'courseWise' => $this->stats->courseWise($scope),
                'categoryWise' => $this->stats->categoryWise($scope),
                'genderWise' => $this->stats->genderWise($scope),
                'stateWise' => [
                    'cur' => $this->stats->stateWise($scope, 'cur'),
                    'perm' => $this->stats->stateWise($scope, 'perm'),
                    'domicile' => $this->stats->stateWise($scope, 'domicile'),
                ],
                'pwbdWise' => $this->stats->pwbdWise($scope),
                'daily' => $this->stats->daily(),
                'scrutiny' => collect(['academic', 'religious'])->mapWithKeys(fn ($t) => [$t => \App\Models\Scrutiny::where('type', $t)
                    ->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status')->all()])->all(),
                'payments' => [
                    'byPurpose' => $this->stats->payments(),
                    'today' => (float) Payment::where('status', Payment::SUCCESS)->whereDate('paid_at', today())->sum('amount'),
                    'unconfirmed' => Payment::whereIn('status', [Payment::INITIATED, Payment::PENDING])
                        ->where('created_at', '<=', now()->subMinutes(config('payment.status_check_after_minutes')))->count(),
                    'duplicates' => Payment::where('is_duplicate', true)->count(),
                ],
            ];
        } elseif ($admin->hasRole(...ApplicationAccess::VIEW_ROLES)) {
            // Department / religious / panel: their own queue size.
            $mine = ApplicationAccess::scope($admin, Application::query());
            $data['myCount'] = (clone $mine)->count();
            $data['myCourses'] = in_array($admin->role, StaffController::DEPARTMENT_ROLES, true)
                ? collect($this->stats->courseWise('paid'))->filter(fn ($r) => $admin->department?->courses->pluck('name')->contains($r['course']))->values()->all()
                : [];
        }

        if ($admin->isSuperAdmin()) {
            $data['security'] = [
                'failedLoginsToday' => LoginAttempt::where('successful', false)->whereDate('created_at', today())->count(),
                'blockedIps' => BlockedIp::active()->count(),
                'registeredToday' => User::whereDate('created_at', today())->count(),
            ];
        }

        return view('admin.dashboard', $data);
    }

    /** All dashboard tables in one Excel workbook, one sheet each. */
    public function export(Request $request)
    {
        $scope = array_key_exists($request->query('scope'), AdmissionStats::SCOPES) ? $request->query('scope') : 'paid';
        AuditLog::record('stats.export', null, ['scope' => $scope]);
        $s = $this->stats;
        $label = AdmissionStats::SCOPES[$scope];

        $states = function (string $by) use ($s, $scope) {
            return ['headers' => ['State / UT', 'Applications'], 'rows' => array_map(fn ($r) => [$r['state'], $r['count']], $s->stateWise($scope, $by))];
        };

        return Excel::download("statistics-$scope-".now('Asia/Kolkata')->format('Ymd-Hi').'.xlsx', [
            'Summary' => ['headers' => ['Measure', 'Value'], 'rows' => array_merge(
                [['Scope', $label], ['Generated (IST)', now('Asia/Kolkata')->format('d-m-Y H:i')]],
                collect($s->totals())->map(fn ($v, $k) => [ucfirst($k), $v])->values()->all(),
                collect($s->summary($scope))->map(fn ($v, $k) => [ucfirst($k).' ('.$label.')', $v])->values()->all(),
            )],
            'Course-wise' => ['headers' => ['Course', 'Applications', 'Female', 'Christian', 'With JMC preference', 'Residence', 'Added after payment'],
                'rows' => array_map(fn ($r) => array_values($r), $s->courseWise($scope))],
            'Category-wise' => ['headers' => ['Category', 'Applications'], 'rows' => array_map(fn ($r) => [$r['label'], $r['count']], $s->categoryWise($scope))],
            'Gender' => ['headers' => ['Gender', 'Applications'], 'rows' => collect($s->genderWise($scope))->map(fn ($n, $g) => [$g, $n])->values()->all()],
            'Current state' => $states('cur'),
            'Permanent state' => $states('perm'),
            'State belongs to' => $states('domicile'),
            'PwBD' => ['headers' => ['Disability', 'Group', 'Applications'], 'rows' => array_map(fn ($r) => [$r['type'], (string) $r['group'], $r['count']], $s->pwbdWise($scope))],
            'Payments' => ['headers' => ['Purpose', 'Successful payments', 'Amount (Rs)'], 'rows' => array_map(fn ($r) => [$r['purpose'], $r['count'], $r['amount']], $s->payments())],
            'Daily' => ['headers' => ['Date', 'Submitted', 'Fee paid'], 'rows' => array_map(fn ($r) => [$r['date'], $r['submitted'], $r['paid']], $s->daily(30))],
        ]);
    }
}
