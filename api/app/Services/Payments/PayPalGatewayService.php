<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhookLog;
use App\Services\Orders\OrderService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class PayPalGatewayService implements PaymentGatewayInterface
{
    protected string $baseUrl;

    public function __construct(protected OrderService $orderService)
    {
        $this->baseUrl = config('services.paypal.mode') === 'live'
            ? 'https://api-m.paypal.com'
            : 'https://api-m.sandbox.paypal.com';
    }

    public function name(): string
    {
        return 'paypal';
    }

    protected function accessToken(): string
    {
        $response = Http::asForm()
            ->withBasicAuth(config('services.paypal.client_id'), config('services.paypal.client_secret'))
            ->post("{$this->baseUrl}/v1/oauth2/token", ['grant_type' => 'client_credentials']);

        $response->throw();

        return $response->json('access_token');
    }

    /**
     * Creates a PayPal order (their "Order" is the checkout session, not to be
     * confused with our own Order model) and a local pending Payment row.
     * The customer approves on PayPal's site; capture happens either via the
     * client-side "onApprove" callback hitting our capture endpoint, or via
     * the CHECKOUT.ORDER.APPROVED / PAYMENT.CAPTURE.COMPLETED webhook below.
     */
    public function initiate(Order $order, array $options = []): array
    {
        $response = Http::withToken($this->accessToken())
            ->post("{$this->baseUrl}/v2/checkout/orders", [
                'intent' => 'CAPTURE',
                'purchase_units' => [[
                    'reference_id' => $order->order_number,
                    'amount' => [
                        'currency_code' => $order->currency,
                        'value' => number_format((float) $order->total, 2, '.', ''),
                    ],
                ]],
            ]);

        $response->throw();
        $paypalOrder = $response->json();

        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'paypal',
            'amount' => $order->total,
            'currency' => $order->currency,
            'status' => 'pending',
            'transaction_reference' => $paypalOrder['id'],
        ]);

        PaymentTransaction::create([
            'payment_id' => $payment->id,
            'type' => 'authorize',
            'amount' => $order->total,
            'status' => $paypalOrder['status'] ?? 'CREATED',
            'gateway_response' => $paypalOrder,
        ]);

        $approveLink = collect($paypalOrder['links'] ?? [])->firstWhere('rel', 'approve');

        return [
            'payment' => $payment,
            'approval_url' => $approveLink['href'] ?? null,
            'paypal_order_id' => $paypalOrder['id'],
        ];
    }

    /** Called by the client-side "onApprove" flow to finalize the capture. */
    public function capture(Payment $payment): void
    {
        $response = Http::withToken($this->accessToken())
            ->post("{$this->baseUrl}/v2/checkout/orders/{$payment->transaction_reference}/capture");

        $response->throw();
        $result = $response->json();
        $status = $result['status'] ?? 'FAILED';

        $payment->update([
            'status' => $status === 'COMPLETED' ? 'paid' : 'failed',
            'paid_at' => $status === 'COMPLETED' ? now() : null,
        ]);

        PaymentTransaction::create([
            'payment_id' => $payment->id,
            'type' => 'capture',
            'amount' => $payment->amount,
            'status' => $status,
            'gateway_response' => $result,
        ]);

        $this->orderService->applyPaymentResult($payment->order, $payment->status);
    }

    /**
     * PayPal webhook signature verification requires calling PayPal's
     * verify-webhook-signature endpoint with the request headers — simplified
     * here to the event-processing logic; see StripeGatewayService for the
     * equivalent HMAC-based pattern used on the Stripe side.
     */
    public function handleWebhook(array $payload, ?string $signature = null): void
    {
        $log = PaymentWebhookLog::create([
            'gateway' => 'paypal',
            'event_type' => $payload['event_type'] ?? null,
            'event_id' => $payload['id'] ?? null,
            'payload' => $payload,
            'processed' => false,
        ]);

        if (($payload['id'] ?? null) && PaymentWebhookLog::where('event_id', $payload['id'])
            ->where('processed', true)->where('id', '!=', $log->id)->exists()) {
            $log->update(['processed' => true]);
            return;
        }

        try {
            $eventType = $payload['event_type'] ?? '';
            $resource = $payload['resource'] ?? [];

            match ($eventType) {
                'PAYMENT.CAPTURE.COMPLETED' => $this->markPaidFromCapture($resource),
                'PAYMENT.CAPTURE.DENIED' => $this->markFailedFromCapture($resource),
                'PAYMENT.CAPTURE.REFUNDED' => $this->markRefundedFromCapture($resource),
                default => Log::info("PayPal webhook: unhandled event {$eventType}"),
            };

            $log->update(['processed' => true]);
        } catch (\Throwable $e) {
            $log->update(['processing_error' => $e->getMessage()]);
            Log::error('PayPal webhook processing failed: '.$e->getMessage());
            throw $e;
        }
    }

    protected function findPaymentFromCapture(array $resource): ?Payment
    {
        $paypalOrderId = $resource['supplementary_data']['related_ids']['order_id'] ?? null;

        return $paypalOrderId ? Payment::where('transaction_reference', $paypalOrderId)->first() : null;
    }

    protected function markPaidFromCapture(array $resource): void
    {
        $payment = $this->findPaymentFromCapture($resource);
        if (! $payment) {
            return;
        }

        $payment->update(['status' => 'paid', 'paid_at' => now()]);
        $this->orderService->applyPaymentResult($payment->order, 'paid');
    }

    protected function markFailedFromCapture(array $resource): void
    {
        $payment = $this->findPaymentFromCapture($resource);
        if (! $payment) {
            return;
        }

        $payment->update(['status' => 'failed']);
        $this->orderService->applyPaymentResult($payment->order, 'failed');
    }

    protected function markRefundedFromCapture(array $resource): void
    {
        $payment = $this->findPaymentFromCapture($resource);
        if (! $payment) {
            return;
        }

        $payment->update(['status' => 'refunded']);
        $this->orderService->applyPaymentResult($payment->order, 'refunded');
    }

    public function refund(Payment $payment, float $amount, ?string $reason = null): void
    {
        $response = Http::withToken($this->accessToken())
            ->post("{$this->baseUrl}/v2/payments/captures/{$payment->transaction_reference}/refund", [
                'amount' => [
                    'value' => number_format($amount, 2, '.', ''),
                    'currency_code' => $payment->currency,
                ],
                'note_to_payer' => $reason,
            ]);

        $response->throw();

        PaymentTransaction::create([
            'payment_id' => $payment->id,
            'type' => 'refund',
            'amount' => $amount,
            'status' => $response->json('status', 'COMPLETED'),
            'gateway_response' => $response->json(),
        ]);
    }
}
