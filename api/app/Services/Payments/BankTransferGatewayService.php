<?php

namespace App\Services\Payments;

use App\Models\BankTransferSubmission;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentTransaction;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\Orders\OrderService;
use Illuminate\Support\Facades\DB;

/**
 * The only gateway in this system that isn't a real-time API call. The flow is:
 *   checkout -> pending order + pending payment created, bank details shown
 *   customer -> submits a transfer reference (and optionally a proof upload)
 *   admin    -> reviews it in the dashboard and confirms or rejects
 * `handleWebhook()` is a no-op here — nothing calls it — kept only so this
 * class still honours the shared PaymentGatewayInterface contract.
 */
class BankTransferGatewayService implements PaymentGatewayInterface
{
    public function __construct(protected OrderService $orderService)
    {
    }

    public function name(): string
    {
        return 'bank_transfer';
    }

    public function initiate(Order $order, array $options = []): array
    {
        $payment = Payment::create([
            'order_id' => $order->id,
            'gateway' => 'bank_transfer',
            'amount' => $order->total,
            'currency' => $order->currency,
            'status' => 'pending',
        ]);

        PaymentTransaction::create([
            'payment_id' => $payment->id,
            'type' => 'authorize',
            'amount' => $order->total,
            'status' => 'awaiting_transfer',
            'gateway_response' => [],
        ]);

        return [
            'payment' => $payment,
            'bank_details' => [
                'bank_name' => config('services.bank_transfer.bank_name'),
                'account_name' => config('services.bank_transfer.account_name'),
                'account_number' => config('services.bank_transfer.account_number'),
                'iban' => config('services.bank_transfer.iban'),
                'swift' => config('services.bank_transfer.swift'),
            ],
        ];
    }

    /** Customer-facing step: they submit their transfer reference (+ optional proof). */
    public function submitProof(Payment $payment, string $reference, ?string $proofPath = null): BankTransferSubmission
    {
        return BankTransferSubmission::create([
            'payment_id' => $payment->id,
            'reference' => $reference,
            'proof_path' => $proofPath,
            'submitted_at' => now(),
            'verification_status' => 'pending',
        ]);
    }

    /**
     * Admin-facing step. Wrapped in a transaction because confirming payment
     * has three side effects that must all succeed together: the payment
     * status flips, the order's payment_status flips, and it's logged.
     */
    public function confirm(BankTransferSubmission $submission, User $admin): void
    {
        DB::transaction(function () use ($submission, $admin) {
            $payment = $submission->payment;

            $submission->update([
                'verification_status' => 'confirmed',
                'verified_by' => $admin->id,
                'verified_at' => now(),
            ]);

            $payment->update(['status' => 'paid', 'paid_at' => now()]);

            PaymentTransaction::create([
                'payment_id' => $payment->id,
                'type' => 'capture',
                'amount' => $payment->amount,
                'status' => 'confirmed_manually',
                'gateway_response' => ['verified_by' => $admin->id, 'reference' => $submission->reference],
            ]);

            $this->orderService->applyPaymentResult($payment->order, 'paid');

            app(ActivityLogger::class)->log(
                $admin,
                'Payment verified',
                'Payments',
                $payment,
                $payment->order->order_number
            );
        });
    }

    public function reject(BankTransferSubmission $submission, User $admin, ?string $reason = null): void
    {
        DB::transaction(function () use ($submission, $admin, $reason) {
            $payment = $submission->payment;

            $submission->update([
                'verification_status' => 'rejected',
                'verified_by' => $admin->id,
                'verified_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $payment->update(['status' => 'failed']);
            $this->orderService->applyPaymentResult($payment->order, 'failed');

            app(ActivityLogger::class)->log(
                $admin,
                'Payment rejected',
                'Payments',
                $payment,
                $payment->order->order_number,
                ['reason' => $reason]
            );
        });
    }

    public function handleWebhook(array $payload, ?string $signature = null): void
    {
        // No webhook exists for manual bank transfers — verification is always
        // an explicit admin action via confirm()/reject() above.
    }

    public function refund(Payment $payment, float $amount, ?string $reason = null): void
    {
        // Bank transfer refunds happen out-of-band (an actual bank transfer back
        // to the customer). We only record that a refund was issued.
        PaymentTransaction::create([
            'payment_id' => $payment->id,
            'type' => 'refund',
            'amount' => $amount,
            'status' => 'manual',
            'gateway_response' => ['reason' => $reason, 'note' => 'Processed manually outside the platform'],
        ]);
    }
}
