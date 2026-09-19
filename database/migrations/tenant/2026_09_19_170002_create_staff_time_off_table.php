<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff_time_off', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('staff_id')->constrained('staff')->cascadeOnDelete();
            $table->dateTimeTz('starts_at');
            $table->dateTimeTz('ends_at');
            $table->string('reason', 255)->nullable();
            $table->timestamps();

            $table->index(['staff_id', 'starts_at']);
            $table->index(['staff_id', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff_time_off');
    }
};
