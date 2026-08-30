<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\IssueRefundRequest;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\ActivityLogger;
use App\Services\Payments\PaymentManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PaymentController extends Controller
{
    public function __construct(
        protected PaymentManager $payments,
        protected ActivityLogger $activityLogger,
    ) {
    }

    /** Admin/dashboard view: transactions across all gateways, with filters. */
    public function index(Request $request)
    {
        $query = Payment::query()->with('order.customer');

        if ($gateway = $request->query('gateway')) {
            $query->where('gateway', $gateway);
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        $payments = $query->orderByDesc('created_at')->paginate($request->integer('per_page', 20));

        return response()->json($payments->through(fn (Payment $p) => [
            'id' => $p->id,
            'transaction_reference' => $p->transaction_reference,
            'order_number' => $p->order->order_number,
            'customer' => $p->order->customer->name,
            'gateway' => $p->gateway,
            'amount' => (float) $p->amount,
            'status' => $p->status,
            'created_at' => $p->created_at->toIso8601String(),
        ]));
    }

    /**
     * Storefront checkout entry point — resolves the right gateway via
     * PaymentManager without this controller knowing which one it is.
     */
    public function initiate(Request $request, Order $order)
    {
        $data = $request->validate([
            'gateway' => ['required', 'in:stripe,paypal,bank_transfer'],
        ]);

        $gateway = $this->payments->gateway($data['gateway']);
        $result = $gateway->initiate($order);

        return response()->json($result);
    }

    public function summary()
    {
        return response()->json([
            'total_processed' => (float) Payment::sum('amount'),
            'successful' => (float) Payment::where('status', 'paid')->sum('amount'),
            'pending' => (float) Payment::where('status', 'pending')->sum('amount'),
            'failed' => (float) Payment::where('status', 'failed')->sum('amount'),
            'by_gateway' => Payment::selectRaw('gateway, count(*) as orders_count, sum(amount) as revenue')
                ->where('status', 'paid')
                ->groupBy('gateway')
                ->get(),
        ]);
    }

    public function refund(IssueRefundRequest $request, Payment $payment)
    {
        $data = $request->validated();

        DB::transaction(function () use ($payment, $data, $request) {
            $this->payments->gateway($payment->gateway)->refund($payment, $data['amount'], $data['reason']);

            $isPartial = $data['amount'] < (float) $payment->amount;
            $payment->update(['status' => $isPartial ? 'partially_refunded' : 'refunded']);

            $refund = Refund::create([
                'refund_number' => 'REF-'.random_int(10000, 99999),
                'order_id' => $payment->order_id,
                'payment_id' => $payment->id,
                'amount' => $data['amount'],
                'reason' => $data['reason'],
                'status' => 'completed',
                'processed_by' => $request->user()->id,
            ]);

            app(\App\Services\Orders\OrderService::class)->applyPaymentResult($payment->order, $payment->status);

            $this->activityLogger->log($request->user(), 'Refund issued', 'Refunds', $refund, $refund->refund_number, [
                'amount' => $data['amount'],
            ]);
        });

        return response()->json(['message' => 'Refund processed.']);
    }
}
