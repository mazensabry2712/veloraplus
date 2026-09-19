<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('webhook_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('provider', 64);
            $table->string('provider_event_id', 191);
            $table->string('event_type', 128);
            $table->string('status', 32)->index();
            $table->timestamp('received_at');
            $table->timestamp('processed_at')->nullable();
            $table->string('payload_hash', 64);
            $table->json('payload')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_event_id'], 'webhook_events_provider_event_unique');
            $table->index(['provider', 'event_type']);
            $table->index(['provider', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_events');
    }
};
