<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_breaks', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('staff_working_hour_id')->constrained('staff_working_hours')->cascadeOnDelete();
            $table->time('starts_at');
            $table->time('ends_at');
            $table->string('label', 150)->nullable();
            $table->timestamps();

            $table->index('staff_working_hour_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_breaks');
    }
};
