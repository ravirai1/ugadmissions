<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PrintLog;
use App\Services\Payments\PaymentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    public function index(Request $request)
    {
        $wait = now()->subMinutes(config('payment.status_check_after_minutes'));

        return view('admin.payments.index', [
            'payments' => $this->filtered($request)->with('application.user')->latest('id')->paginate(25)->withQueryString(),
            'stats' => [
                'collected' => (float) Payment::where('status', Payment::SUCCESS)->sum('amount'),
                'success' => Payment::where('status', Payment::SUCCESS)->count(),
                'today' => (float) Payment::where('status', Payment::SUCCESS)->whereDate('paid_at', today())->sum('amount'),
                'unconfirmed' => Payment::whereIn('status', [Payment::INITIATED, Payment::PENDING])->where('created_at', '<=', $wait)->count(),
                'failed' => Payment::where('status', Payment::FAILED)->count(),
                'duplicates' => Payment::where('is_duplicate', true)->count(),
            ],
            'purposes' => Payment::PURPOSES,
        ]);
    }

    public function show(Payment $payment)
    {
        return view('admin.payments.show', ['payment' => $payment->load('application.user', 'events.admin')]);
    }

    /** "Check with SBI" for one payment (any time, unlike applicants who wait 45 minutes). */
    public function check(Request $request, Payment $payment)
    {
        $before = $payment->status;
        $payment = $this->payments->check($payment, 'admin', $request->user('admin'), $request->ip());
        AuditLog::record('payment.check', $payment, ['from' => $before, 'to' => $payment->status]);

        return back()->with('status', "{$payment->order_no}: ".match ($payment->status) {
            Payment::SUCCESS => "confirmed as paid. Receipt {$payment->receipt_no}.",
            Payment::FAILED => 'SBI reports the payment as not successful.',
            default => 'SBI has not confirmed this payment yet.',
        });
    }

    /** Re-check every unconfirmed payment older than the wait period (up to 100 per click). */
    public function checkPending(Request $request)
    {
        $list = Payment::whereIn('status', [Payment::INITIATED, Payment::PENDING])
            ->where('created_at', '<=', now()->subMinutes(config('payment.status_check_after_minutes')))
            ->orderBy('id')->limit(100)->get();

        $ok = 0;
        foreach ($list as $p) {
            $ok += $this->payments->check($p, 'admin', $request->user('admin'), $request->ip())->isSuccess() ? 1 : 0;
        }
        AuditLog::record('payment.check_pending', null, ['checked' => $list->count(), 'confirmed' => $ok]);

        return back()->with('status', "Checked {$list->count()} unconfirmed payment(s) with SBI; {$ok} confirmed as paid and receipts generated.");
    }

    public function receipt(Payment $payment)
    {
        abort_unless($payment->isSuccess(), 404);
        PrintLog::record('receipt', $payment->application, ['receipt_no' => $payment->receipt_no]);

        return view('payment.receipt', ['payment' => $payment->load('application.user'), 'back' => route('admin.payments.show', $payment)]);
    }

    /** CSV (opens in Excel) of the filtered list. */
    public function export(Request $request): StreamedResponse
    {
        AuditLog::record('payment.export', null, $request->only('status', 'purpose', 'q', 'from', 'to', 'duplicates'));
        $query = $this->filtered($request)->with('application.user')->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel shows ₹ and names correctly
            fputcsv($out, ['Order no', 'Receipt no', 'Application no', 'Name', 'CUET no', 'Email', 'Mobile', 'Purpose', 'Amount', 'Status', 'Duplicate', 'SBI ref', 'Bank ref', 'Mode', 'Initiated (IST)', 'Paid (IST)']);
            $query->chunk(500, function ($rows) use ($out) {
                foreach ($rows as $p) {
                    $u = $p->application?->user;
                    fputcsv($out, array_map([$this, 'csvSafe'], [
                        $p->order_no, $p->receipt_no, $p->application?->application_no, $u?->name, $u?->cuet_no, $u?->email, $u?->mobile,
                        $p->purposeLabel(), $p->amount, $p->status, $p->is_duplicate ? 'Yes' : 'No', $p->gateway_ref, $p->bank_ref, $p->pay_mode,
                        $p->created_at?->timezone('Asia/Kolkata')->format('d-m-Y H:i'), $p->paid_at?->timezone('Asia/Kolkata')->format('d-m-Y H:i'),
                    ]));
                }
            });
            fclose($out);
        }, 'payments-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** Stops spreadsheet formula injection from applicant-entered text. */
    public function csvSafe($v): string
    {
        $v = (string) $v;

        return $v !== '' && in_array($v[0], ['=', '+', '-', '@', "\t", "\r"], true) ? "'".$v : $v;
    }

    private function filtered(Request $request): Builder
    {
        $q = Payment::query();

        if ($s = $request->query('status')) {
            $s === 'unconfirmed'
                ? $q->whereIn('status', [Payment::INITIATED, Payment::PENDING])
                : $q->where('status', $s);
        }
        if (($p = $request->query('purpose')) && isset(Payment::PURPOSES[$p])) {
            $q->where('purpose', $p);
        }
        if ($request->boolean('duplicates')) {
            $q->where('is_duplicate', true);
        }
        if ($from = $request->date('from', 'Y-m-d', 'Asia/Kolkata')) {
            $q->where('created_at', '>=', $from->startOfDay()->setTimezone('UTC'));
        }
        if ($to = $request->date('to', 'Y-m-d', 'Asia/Kolkata')) {
            $q->where('created_at', '<=', $to->endOfDay()->setTimezone('UTC'));
        }
        if ($term = trim((string) $request->query('q'))) {
            $q->where(function (Builder $w) use ($term) {
                $w->where('order_no', $term)->orWhere('receipt_no', $term)->orWhere('gateway_ref', $term)->orWhere('bank_ref', $term)
                    ->orWhereHas('application', fn ($a) => $a->where('application_no', $term)
                        ->orWhereHas('user', fn ($u) => $u->where('email', $term)->orWhere('cuet_no', $term)->orWhere('mobile', $term)->orWhere('name', 'like', '%'.addcslashes($term, '%_\\').'%')));
            });
        }

        return $q;
    }
}
