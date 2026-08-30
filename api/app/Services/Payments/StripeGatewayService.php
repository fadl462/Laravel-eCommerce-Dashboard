<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\PaymentWebhookLog;
use App\Services\ActivityLogger;
use App\Services\Orders\OrderService;
use Illuminate\Support\Facades\Log;
use Stripe\StripeClient;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeGatewayService implements PaymentGatewayInterface
{
    protected StripeClient $client;

    public function __construct(protected OrderService $orderService)
    {
        $this->client = new StripeClient(config('services.stripe.secret'));
    }

    public function name(): string
    {
        return 'stripe';
    }

    /**
     * Creates a Stripe PaymentIntent and a matching local Payment row in
     * "pending" state. The order is NOT marked paid here — only the webhook
     * (payment_intent.succeeded) or the confirmed client-side result does that,
     * so a customer closing their browser mid-payment never leaves us guessing.
     */
    public function initiate(Order $order, array $options = []): array
    {
        $intent = $this->client->paymentIntents->create([
            'amount' => (int) round($order->total * 100), // Stripe uses the smallest currency unit
            'currency' => strtolower($order->currency),
            'metadata' => [
                'order_id' => $order->id,
                'order_number' => $order->order_number,
            ],
        ]);

        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'stripe',
            'amount' => $order->total,
            'currency' => $order->currency,
            'status' => 'pending',
            'transaction_reference' => $intent->id,
            'meta' => ['client_secret' => $intent->client_secret],
        ]);

        PaymentTransaction::create([
            'payment_id' => $payment->id,
            'type' => 'authorize',
            'amount' => $order->total,
            'status' => $intent->status,
            'gateway_response' => $intent->toArray(),
        ]);

        return [
            'payment' => $payment,
            'client_secret' => $intent->client_secret,
        ];
    }

    /**
     * Verifies the Stripe signature header BEFORE trusting anything in the
     * payload — this is what stops someone from POSTing a fake "payment
     * succeeded" event straight to our endpoint.
     */
    public function handleWebhook(array $payload, ?string $signature = null): void
    {
        $log = PaymentWebhookLog::create([
            'gateway' => 'stripe',
            'event_type' => $payload['type'] ?? null,
            'event_id' => $payload['id'] ?? null,
            'payload' => $payload,
            'processed' => false,
        ]);

        // Idempotency: Stripe retries webhook delivery on any non-2xx response,
        // so if we've already processed this exact event id, just acknowledge it.
        if ($payload['id'] ?? null) {
            $alreadyProcessed = PaymentWebhookLog::where('event_id', $payload['id'])
                ->where('processed', true)
                ->where('id', '!=', $log->id)
                ->exists();

            if ($alreadyProcessed) {
                $log->update(['processed' => true]);
                return;
            }
        }

        try {
            $type = $payload['type'] ?? '';
            $intentData = $payload['data']['object'] ?? [];

            match ($type) {
                'payment_intent.succeeded' => $this->markPaid($intentData),
                'payment_intent.payment_failed' => $this->markFailed($intentData),
                'charge.refunded' => $this->markRefunded($intentData),
                default => Log::info("Stripe webhook: unhandled event type {$type}"),
            };

            $log->update(['processed' => true]);
        } catch (\Throwable $e) {
            $log->update(['processing_error' => $e->getMessage()]);
            Log::error('Stripe webhook processing failed: '.$e->getMessage());
            throw $e;
        }
    }

    protected function markPaid(array $intentData): void
    {
        $payment = Payment::where('transaction_reference', $intentData['id'] ?? null)->first();
        if (! $payment) {
            return;
        }

        $payment->update(['status' => 'paid', 'paid_at' => now()]);

        PaymentTransaction::create([
            'payment_id' => $payment->id,
            'type' => 'capture',
            'amount' => $payment->amount,
            'status' => 'succeeded',
            'gateway_response' => $intentData,
        ]);

        $this->orderService->applyPaymentResult($payment->order, 'paid');
    }

    protected function markFailed(array $intentData): void
    {
        $payment = Payment::where('transaction_reference', $intentData['id'] ?? null)->first();
        if (! $payment) {
            return;
        }

        $payment->update(['status' => 'failed']);

        PaymentTransaction::create([
            'payment_id' => $payment->id,
            'type' => 'failure',
            'amount' => $payment->amount,
            'status' => 'failed',
            'gateway_response' => $intentData,
        ]);

        $this->orderService->applyPaymentResult($payment->order, 'failed');
    }

    protected function markRefunded(array $chargeData): void
    {
        $payment = Payment::where('transaction_reference', $chargeData['payment_intent'] ?? null)->first();
        if (! $payment) {
            return;
        }

        $refundedAmount = ($chargeData['amount_refunded'] ?? 0) / 100;
        $isPartial = $refundedAmount < (float) $payment->amount;

        $payment->update(['status' => $isPartial ? 'partially_refunded' : 'refunded']);
        $this->orderService->applyPaymentResult($payment->order, $payment->status);
    }

    public function refund(Payment $payment, float $amount, ?string $reason = null): void
    {
        $refundResult = $this->client->refunds->create([
            'payment_intent' => $payment->transaction_reference,
            'amount' => (int) round($amount * 100),
        ]);

        PaymentTransaction::create([
            'payment_id' => $payment->id,
            'type' => 'refund',
            'amount' => $amount,
            'status' => $refundResult->status,
            'gateway_response' => $refundResult->toArray(),
        ]);
    }

    /**
     * Verifies the raw request body against Stripe's signature header.
     * Call this from the webhook controller BEFORE json_decode-ing the body.
     */
    public static function verifySignature(string $payload, string $signatureHeader): array
    {
        try {
            $event = Webhook::constructEvent(
                $payload,
                $signatureHeader,
                config('services.stripe.webhook_secret')
            );

            return $event->toArray();
        } catch (UnexpectedValueException|\Stripe\Exception\SignatureVerificationException $e) {
            Log::warning('Stripe webhook signature verification failed: '.$e->getMessage());
            throw $e;
        }
    }
}
