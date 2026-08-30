<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // One Payment record per order (the "current" payment state).
        // PAYMENT SYSTEM
        //     |
        //     +-- Stripe / PayPal / Bank Transfer  (gateway-specific services)
        //     |
        //     Payment record  <-- what the rest of the app queries
        //     |
        //     Order
        // Adding a new gateway later never touches the Order model.
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->enum('gateway', ['stripe', 'paypal', 'bank_transfer'])->index();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('USD');
            $table->enum('status', ['pending', 'authorized', 'paid', 'failed', 'cancelled', 'refunded', 'partially_refunded'])->default('pending');
            $table->string('transaction_reference')->nullable()->index(); // gateway's own ID (Stripe PI id, PayPal capture id, bank ref)
            $table->timestamp('paid_at')->nullable();
            $table->json('meta')->nullable(); // gateway-specific extra fields
            $table->timestamps();
        });

        // Every attempt/action against a payment (authorize, capture, refund) — an audit trail
        // distinct from the Payment's current status, so disputed charges can be reconstructed.
        Schema::create('payment_transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['authorize', 'capture', 'refund', 'failure']);
            $table->decimal('amount', 12, 2);
            $table->string('status');
            $table->json('gateway_response')->nullable();
            $table->timestamps();
        });

        // Raw inbound webhook payloads, kept regardless of whether processing succeeded —
        // essential for replaying/debugging a missed Stripe/PayPal event.
        Schema::create('payment_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->enum('gateway', ['stripe', 'paypal']);
            $table->string('event_type')->nullable();
            $table->string('event_id')->nullable()->index(); // for idempotency checks
            $table->json('payload');
            $table->boolean('processed')->default(false);
            $table->text('processing_error')->nullable();
            $table->timestamps();
        });

        // Bank transfer is the one gateway that needs a manual admin verification step,
        // so it gets its own small table rather than overloading `payments.meta`.
        Schema::create('bank_transfer_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('reference'); // customer-submitted transfer reference
            $table->string('proof_path')->nullable(); // uploaded receipt/screenshot
            $table->timestamp('submitted_at');
            $table->enum('verification_status', ['pending', 'confirmed', 'rejected'])->default('pending');
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            $table->string('rejection_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('refund_number')->unique(); // e.g. REF-10092
            $table->foreignId('order_id')->constrained()->restrictOnDelete();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('reason')->nullable();
            $table->enum('status', ['pending', 'completed', 'rejected'])->default('pending');
            $table->foreignId('processed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('bank_transfer_submissions');
        Schema::dropIfExists('payment_webhook_logs');
        Schema::dropIfExists('payment_transactions');
        Schema::dropIfExists('payments');
    }
};
