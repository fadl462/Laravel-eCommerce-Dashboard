<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmBankTransferRequest;
use App\Models\BankTransferSubmission;
use App\Models\Payment;
use App\Services\Payments\BankTransferGatewayService;
use Illuminate\Http\Request;

class BankTransferController extends Controller
{
    public function __construct(protected BankTransferGatewayService $bankTransfer)
    {
    }

    /** Admin dashboard: list of transfers awaiting manual review. */
    public function pending()
    {
        $submissions = BankTransferSubmission::with('payment.order.customer')
            ->where('verification_status', 'pending')
            ->orderBy('submitted_at')
            ->get();

        return response()->json($submissions->map(fn (BankTransferSubmission $s) => [
            'id' => $s->id,
            'order_number' => $s->payment->order->order_number,
            'customer' => $s->payment->order->customer->name,
            'amount' => (float) $s->payment->amount,
            'reference' => $s->reference,
            'proof_url' => $s->proof_path ? asset('storage/'.$s->proof_path) : null,
            'submitted_at' => $s->submitted_at->toIso8601String(),
        ]));
    }

    /** Storefront: customer submits their transfer reference after paying at their bank. */
    public function submit(Request $request, Payment $payment)
    {
        abort_unless($payment->gateway === 'bank_transfer', 422, 'This payment is not a bank transfer.');

        $data = $request->validate([
            'reference' => ['required', 'string', 'max:100'],
            'proof' => ['nullable', 'image', 'max:4096'],
        ]);

        $proofPath = null;
        if ($request->hasFile('proof')) {
            $proofPath = $request->file('proof')->store('bank-transfer-proofs', 'public');
        }

        $submission = $this->bankTransfer->submitProof($payment, $data['reference'], $proofPath);

        return response()->json(['message' => 'Reference submitted, awaiting verification.', 'submission_id' => $submission->id]);
    }

    public function confirm(ConfirmBankTransferRequest $request, BankTransferSubmission $submission)
    {
        $this->bankTransfer->confirm($submission, $request->user());

        return response()->json(['message' => 'Payment confirmed.']);
    }

    public function reject(ConfirmBankTransferRequest $request, BankTransferSubmission $submission)
    {
        $this->bankTransfer->reject($submission, $request->user(), $request->validated('reason'));

        return response()->json(['message' => 'Payment rejected.']);
    }
}
