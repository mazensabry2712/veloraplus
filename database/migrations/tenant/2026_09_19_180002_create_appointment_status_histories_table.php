<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appointment_status_histories', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('appointment_id')->constrained('appointments')->cascadeOnDelete();
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30);
            $table->string('reason', 255)->nullable();
            $table->dateTimeTz('changed_at');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['appointment_id', 'changed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appointment_status_histories');
    }
};
