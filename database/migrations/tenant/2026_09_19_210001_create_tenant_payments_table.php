<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_payments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->foreignUlid('customer_id')->nullable()->constrained('customers')->nullOnDelete();
            $table->string('provider', 64);
            $table->string('merchant_order_id', 191)->unique();
            $table->string('provider_payment_id', 191)->nullable();
            $table->string('provider_order_id', 191)->nullable();
            $table->string('provider_session_id', 191)->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 32)->index();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('refunded_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['appointment_id', 'status']);
            $table->index(['customer_id', 'created_at']);
            $table->index(['provider', 'provider_payment_id']);
            $table->index(['provider', 'provider_order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_payments');
    }
};
