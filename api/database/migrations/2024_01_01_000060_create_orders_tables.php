<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->string('order_number')->unique(); // e.g. ORD-1025, generated in OrderService
            $table->foreignId('customer_id')->constrained()->restrictOnDelete();
            $table->decimal('subtotal', 12, 2);
            $table->decimal('shipping_amount', 12, 2)->default(0);
            $table->decimal('tax_amount', 12, 2)->default(0);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('currency', 3)->default('USD');

            // Payment status and fulfillment status are DELIBERATELY separate columns.
            // A refunded payment does not by itself mean the order is "returned" —
            // the two lifecycles are tracked independently per the product brief.
            $table->enum('payment_status', [
                'pending', 'authorized', 'paid', 'failed', 'cancelled', 'refunded', 'partially_refunded',
            ])->default('pending');

            $table->enum('status', [
                'pending', 'confirmed', 'processing', 'ready_to_ship', 'shipped',
                'delivered', 'cancelled', 'returned', 'completed',
            ])->default('pending');

            $table->string('shipping_address_line1')->nullable();
            $table->string('shipping_city')->nullable();
            $table->string('shipping_country')->nullable();
            $table->string('shipping_postal_code')->nullable();
            $table->boolean('billing_same_as_shipping')->default(true);

            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['status', 'payment_status']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variation_id')->nullable()->constrained('product_variations')->nullOnDelete();
            // Snapshots: product name/SKU/price at time of sale, so later edits to the
            // catalog never rewrite what the customer was actually charged for.
            $table->string('product_name');
            $table->string('sku')->nullable();
            $table->decimal('price', 12, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });

        Schema::create('order_status_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('field'); // "status" or "payment_status"
            $table->string('from_value')->nullable();
            $table->string('to_value');
            $table->string('note')->nullable();
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_histories');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
