<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->foreignUlid('service_id')->constrained('services')->restrictOnDelete();
            $table->string('service_name', 150);
            $table->unsignedInteger('duration_minutes');
            $table->unsignedInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_price_minor')->default(0);
            $table->char('currency', 3);
            $table->unsignedBigInteger('line_total_minor')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['appointment_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_items');
    }
};
