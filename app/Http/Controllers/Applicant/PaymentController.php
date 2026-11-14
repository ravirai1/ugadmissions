<?php

namespace App\Http\Controllers\Applicant;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\Course;
use App\Models\Payment;
use App\Models\Subject;
use App\Services\CourseSelection;
use App\Services\Eligibility;
use App\Services\FeeCalculator;
use App\Services\Payments\FakeGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentService;
use App\Support\AdmissionWindow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments, private FeeCalculator $fees) {}

    private function app(Request $request): Application
    {
        $app = $request->user()->application()->first();
        abort_unless($app && $app->isSubmitted(), 403, 'Please submit your application first.');

        return $app;
    }

    private function own(Request $request, Payment $payment): void
    {
        abort_unless($payment->application?->user_id === $request->user()->id, 404);
    }

    /** Fee summary, pay buttons, add-ons and payment history. */
    public function index(Request $request)
    {
        $app = $this->app($request);
        $app->load('courses.course');

        return view('payment.index', [
            'app' => $app,
            'fee' => $app->isPaid() ? null : $this->fees->forApplication($app),
            'payments' => $app->payments()->with('events')->get(),
            'fees' => FeeCalculator::fees(),
            'waitMinutes' => config('payment.status_check_after_minutes'),
        ]);
    }

    public function payApplication(Request $request)
    {
        $app = $this->app($request);
        if (! $app->isPaid()) {
            if (! AdmissionWindow::isOpen()) {
                return redirect()->route('payment.index')->withErrors(['payment' => AdmissionWindow::status()]);
            }
            $blocked = $app->courses()->with('course')->get()->reject(fn ($c) => AdmissionWindow::allows($c->course_id));
            if ($blocked->isNotEmpty()) {
                return redirect()->route('payment.index')->withErrors(['payment' => 'These courses are not open now and cannot be paid for: '.$blocked->pluck('course.name')->implode(', ').'. Remove them on the Courses step first.']);
            }
        }

        return $this->start($request, fn (Application $app) => $this->payments->initiateApplicationFee($app, $request->ip()));
    }

    public function payResidence(Request $request)
    {
        return $this->start($request, fn (Application $app) => $this->payments->initiateAddResidence($app, $request->ip()));
    }

    public function addCoursesForm(Request $request, Eligibility $eligibility, CourseSelection $selection)
    {
        $app = $this->app($request);
        abort_unless($app->isPaid(), 403, 'Pay the application fee first.');
        if (! AdmissionWindow::isOpen()) {
            return redirect()->route('payment.index')->withErrors(['payment' => AdmissionWindow::status().' Courses cannot be added now.']);
        }

        [$scores, $class12Ids] = $selection->context($app);
        $have = $app->courses()->with('course')->get();
        $allowed = AdmissionWindow::allowedCourseIds();
        $courses = Course::where('is_active', true)->whereNotIn('id', $have->pluck('course_id'))
            ->when($allowed !== null, fn ($q) => $q->whereIn('id', $allowed))->orderBy('sort')->get();

        return view('payment.add-courses', [
            'app' => $app,
            'have' => $have,
            'courses' => $courses,
            'evals' => $courses->mapWithKeys(fn ($c) => [$c->id => $eligibility->evaluate($c, $app, $scores, $class12Ids)]),
            'scores' => $scores,
            'subjects' => Subject::all()->keyBy('id'),
            'female' => $app->isFemale(),
            'fee' => FeeCalculator::fees()['course_after_payment'],
        ]);
    }

    public function addCourses(Request $request, CourseSelection $selection)
    {
        $app = $this->app($request);
        abort_unless($app->isPaid(), 403, 'Pay the application fee first.');
        if (! AdmissionWindow::isOpen()) {
            return redirect()->route('payment.index')->withErrors(['payment' => AdmissionWindow::status()]);
        }

        $data = $request->validate(CourseSelection::rules() + [
            'courses.*.statement' => ['nullable', 'string', 'max:2000'],
        ]);
        $selected = collect($data['courses'])->filter(fn ($c) => ! empty($c['selected']));
        if ($selected->isEmpty()) {
            throw ValidationException::withMessages(['courses' => 'Select at least one course to add.']);
        }

        $have = $app->courses()->get();
        [$scores, $class12Ids] = $selection->context($app);
        $prefs = $have->flatMap(fn ($c) => array_filter([$c->du_preference, $c->jmc_preference]))->all();
        $items = [];

        foreach ($selected as $courseId => $c) {
            abort_if($have->contains('course_id', (int) $courseId), 422, 'You have already applied for this course.');
            if (! AdmissionWindow::allows((int) $courseId)) {
                throw ValidationException::withMessages(['courses' => 'Applications are not open for this course now.']);
            }
            $course = Course::where('is_active', true)->findOrFail($courseId);
            $built = $selection->build($app, $course, $c, $scores, $class12Ids);

            $statement = trim((string) ($c['statement'] ?? ''));
            if (mb_strlen($statement) < 100) {
                throw ValidationException::withMessages(["courses.$courseId.statement" => "Please write at least 100 characters on why you want to join {$course->name}."]);
            }
            array_push($prefs, ...$built['prefs']);
            $items[] = ['label' => $course->name, 'row' => $built['row'] + ['statement' => $statement]];
        }
        CourseSelection::assertUniquePrefs($prefs);

        return $this->start($request, fn (Application $app) => $this->payments->initiateAddCourses($app, $items, $request->ip()));
    }

    /** Applicant asks us to fetch the status from SBI (available 45 minutes after the attempt). */
    public function check(Request $request, Payment $payment)
    {
        $this->own($request, $payment);

        if ($payment->isSuccess()) {
            return back()->with('status', 'This payment is already confirmed.');
        }
        if (! $payment->canCheckStatus()) {
            return back()->with('status', 'You can check this payment after '.$payment->checkAvailableAt()->timezone('Asia/Kolkata')->format('h:i A').'.');
        }

        $payment = $this->payments->check($payment, 'applicant', ip: $request->ip());

        return back()->with('status', match ($payment->status) {
            Payment::SUCCESS => "Payment found. Receipt {$payment->receipt_no} has been generated.",
            Payment::FAILED => 'SBI ePay reports this payment as not successful. If money was debited, it is normally reversed by your bank within 5–7 working days.',
            default => 'SBI ePay has not confirmed this payment yet. Please check again later.',
        });
    }

    public function receipt(Request $request, Payment $payment)
    {
        $this->own($request, $payment);
        abort_unless($payment->isSuccess(), 404);

        return view('payment.receipt', ['payment' => $payment->load('application.user', 'application.courses.course'), 'back' => route('payment.index')]);
    }

    /** Where SBI ePay sends the browser back. No session is assumed (the bank posts cross-site). */
    public function response(Request $request)
    {
        $payment = $this->fromGateway($request, 'redirect');
        if (! $payment) {
            return redirect()->route('applicant.dashboard')->with('status', 'We could not read the payment response. If money was debited, use "Check payment status" after '.config('payment.status_check_after_minutes').' minutes.');
        }

        return redirect(URL::temporarySignedRoute('payment.result', now()->addMinutes(30), ['payment' => $payment->id]));
    }

    /** Server-to-server notification from SBI ePay. */
    public function push(Request $request)
    {
        return response($this->fromGateway($request, 'push') ? 'OK' : 'INVALID', 200)->header('Content-Type', 'text/plain');
    }

    public function result(Payment $payment)
    {
        return view('payment.result', ['payment' => $payment, 'waitMinutes' => config('payment.status_check_after_minutes')]);
    }

    /* ---- Test gateway (local and staging only; routes are not registered in production) ---- */

    public function fakePage(Request $request, PaymentGateway $gateway)
    {
        abort_unless($gateway instanceof FakeGateway, 404);
        $order = (string) $request->input('order_no');
        abort_unless(hash_equals($gateway->sign($order), (string) $request->input('sig')), 403);

        return view('payment.fake', ['payment' => Payment::where('order_no', $order)->firstOrFail(), 'sig' => $gateway->sign($order)]);
    }

    public function fakeComplete(Request $request, PaymentGateway $gateway)
    {
        abort_unless($gateway instanceof FakeGateway, 404);
        $data = $request->validate(['order_no' => ['required', 'string'], 'sig' => ['required', 'string'], 'outcome' => ['required', 'in:success,failed,timeout']]);
        abort_unless(hash_equals($gateway->sign($data['order_no']), $data['sig']), 403);

        // What "the bank" knows, used by the status check.
        Cache::put('fakepay:'.$data['order_no'], $data['outcome'], now()->addDays(3));

        if ($data['outcome'] === 'timeout') {
            return redirect()->route('applicant.dashboard')->with('status', 'Test gateway: the bank took the money but the browser never came back. Use "Check payment status" after '.config('payment.status_check_after_minutes').' minutes.');
        }

        return view('payment.redirect', [
            'action' => route('payment.sbi.response'),
            'fields' => ['order_no' => $data['order_no'], 'sig' => $data['sig'], 'outcome' => $data['outcome']],
            'title' => 'Returning to St. Stephen\'s College',
        ]);
    }

    /* ---- helpers ---- */

    private function start(Request $request, callable $create)
    {
        $app = $this->app($request);

        try {
            $payment = $create($app);
        } catch (RuntimeException $e) {
            return redirect()->route('payment.index')->withErrors(['payment' => $e->getMessage()]);
        }

        $form = $this->payments->gateway()->requestForm($payment, route('payment.sbi.response'));

        return view('payment.redirect', $form + ['title' => 'Redirecting to SBI ePay', 'payment' => $payment]);
    }

    private function fromGateway(Request $request, string $source): ?Payment
    {
        $result = $this->payments->gateway()->parseResponse($request);
        $payment = $result?->orderNo ? Payment::where('order_no', $result->orderNo)->first() : null;
        if (! $payment) {
            logger()->warning('Unreadable or unknown payment response', ['source' => $source, 'ip' => $request->ip()]);

            return null;
        }

        return $this->payments->apply($payment, $result, $source, ip: $request->ip());
    }
}
