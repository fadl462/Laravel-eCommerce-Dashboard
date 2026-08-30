<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->enum('type', ['percentage', 'fixed', 'free_shipping']);
            $table->decimal('value', 10, 2)->nullable(); // null when type = free_shipping
            $table->unsignedInteger('usage_limit')->nullable();
            $table->unsignedInteger('used_count')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->enum('status', ['active', 'expired', 'disabled'])->default('active');
            $table->timestamps();
        });

        Schema::create('discounts', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->enum('type', ['percentage', 'fixed']);
            $table->decimal('value', 10, 2);
            $table->json('conditions')->nullable(); // e.g. {"category_id":3,"min_qty":2}
            $table->enum('status', ['active', 'inactive'])->default('active');
            $table->timestamps();
        });

        // Every sensitive admin action gets a row here — permission-gated actions in the
        // controllers call ActivityLogger::log() so nothing has to remember to do this manually.
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action'); // e.g. "Updated Product"
            $table->string('module'); // e.g. "Products"
            $table->nullableMorphs('subject'); // polymorphic link to the affected record
            $table->string('subject_label')->nullable(); // human-readable snapshot, e.g. product name
            $table->string('ip_address')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique(); // e.g. "localization.default_language"
            $table->string('group')->default('general');
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('discounts');
        Schema::dropIfExists('coupons');
    }
};
