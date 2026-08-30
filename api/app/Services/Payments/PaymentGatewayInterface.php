<?php

namespace App\Services\Payments;

use App\Models\Order;
use App\Models\Payment;

/**
 * Every gateway (Stripe, PayPal, Bank Transfer, and anything added later —
 * Apple Pay, Mollie, a local Ghanaian gateway, etc.) implements this same
 * contract. Nothing outside this folder ever branches on gateway name;
 * PaymentManager resolves the right implementation and everyone else just
 * talks to a Payment model.
 */
interface PaymentGatewayInterface
{
    /**
     * Start a payment for the given order. Returns data the frontend needs
     * to complete payment (e.g. a Stripe client_secret, a PayPal approval URL,
     * or the bank details to display) alongside the created Payment record.
     */
    public function initiate(Order $order, array $options = []): array;

    /**
     * Handle an inbound webhook/IPN payload from the gateway. Must be
     * idempotent — gateways retry delivery, so processing the same event
     * twice must not double-apply a payment.
     */
    public function handleWebhook(array $payload, ?string $signature = null): void;

    /** Issue a refund against a previously captured payment. */
    public function refund(Payment $payment, float $amount, ?string $reason = null): void;

    public function name(): string;
}
