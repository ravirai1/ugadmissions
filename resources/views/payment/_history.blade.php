{{-- Payment attempts with status, "Check payment status" (after the wait) and receipt links. --}}
@if ($payments->isEmpty())
    <p class="small text-muted mb-0">No payments yet.</p>
@else
    <div class="table-responsive">
        <table class="table table-sm table-bordered align-middle small mb-0">
            <thead class="table-light">
                <tr><th>Date</th><th>Order no.</th><th>For</th><th class="text-end">Amount (₹)</th><th>Status</th><th>Action</th></tr>
            </thead>
            <tbody>
                @foreach ($payments as $p)
                    <tr>
                        <td class="text-nowrap">{{ $p->created_at->timezone('Asia/Kolkata')->format('d M Y, h:i A') }}</td>
                        <td>{{ $p->order_no }}@if ($p->gateway_ref)<br><span class="text-muted">SBI ref. {{ $p->gateway_ref }}</span>@endif</td>
                        <td>{{ $p->purposeLabel() }}</td>
                        <td class="text-end">{{ number_format((float) $p->amount, 2) }}</td>
                        <td>
                            @include('payment._status', ['p' => $p])
                            @if ($p->is_duplicate)<br><span class="badge bg-warning text-dark">Duplicate – refund review</span>@endif
                        </td>
                        <td class="text-nowrap">
                            @if ($p->isSuccess())
                                <a href="{{ route('payment.receipt', $p) }}" class="btn btn-sm btn-outline-primary"><i class="fa fa-receipt me-1"></i>Receipt</a>
                            @elseif ($p->canCheckStatus())
                                <form method="POST" action="{{ route('payment.check', $p) }}" data-pay-once>
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-warning"><i class="fa fa-sync me-1"></i>Check payment status</button>
                                </form>
                            @else
                                <span class="text-muted">Status check from {{ $p->checkAvailableAt()->timezone('Asia/Kolkata')->format('h:i A') }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <p class="small text-muted mt-2 mb-0">If money was debited but the payment is not shown as successful, wait {{ config('payment.status_check_after_minutes') }} minutes and click <strong>Check payment status</strong>. We fetch the result from SBI ePay and generate your receipt if the payment went through. Do not pay again in the meantime.</p>
@endif
