<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_payment_refunds', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_payment_id')->constrained('tenant_payments')->cascadeOnDelete();
            $table->string('idempotency_key', 190)->nullable()->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 32)->index();
            $table->string('provider_refund_id', 191)->nullable()->unique();
            $table->string('reason', 500)->nullable();
            $table->timestamp('requested_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_payment_id', 'status']);
            $table->index(['currency', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_payment_refunds');
    }
};
