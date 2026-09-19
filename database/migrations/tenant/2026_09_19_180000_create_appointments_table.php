<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignUlid('staff_id')->constrained('staff')->restrictOnDelete();
            $table->foreignUlid('location_id')->constrained('locations')->restrictOnDelete();
            $table->dateTimeTz('starts_at');
            $table->dateTimeTz('ends_at');
            $table->dateTimeTz('blocked_starts_at');
            $table->dateTimeTz('blocked_ends_at');
            $table->string('status', 30)->default('confirmed');
            $table->string('payment_status', 30)->default('unpaid');
            $table->string('idempotency_key', 190)->nullable()->unique();
            $table->string('cancellation_reason', 255)->nullable();
            $table->dateTimeTz('cancelled_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['staff_id', 'blocked_starts_at']);
            $table->index(['staff_id', 'blocked_ends_at']);
            $table->index(['customer_id', 'starts_at']);
            $table->index(['location_id', 'starts_at']);
            $table->index('status');
            $table->index('payment_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointments');
    }
};
