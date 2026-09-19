<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queue_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('queue_id')->constrained('queues')->cascadeOnDelete();
            $table->foreignUlid('customer_id')->constrained('customers')->restrictOnDelete();
            $table->foreignUlid('appointment_id')->nullable()->constrained('appointments')->nullOnDelete();
            $table->unsignedInteger('position');
            $table->string('status', 20)->default('waiting')->index();
            $table->string('idempotency_key', 190)->nullable()->unique();
            $table->timestamp('joined_at');
            $table->timestamp('called_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('skipped_at')->nullable();
            $table->timestamp('no_show_at')->nullable();
            $table->text('notes')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['queue_id', 'position'],
                'queue_entries_queue_position_unique',
            );
            $table->index(['queue_id', 'status', 'position']);
            $table->index(['customer_id', 'created_at']);
            $table->index(['appointment_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queue_entries');
    }
};
