<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('staff', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('account_id')->nullable();
            $table->foreignUlid('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->string('name', 150);
            $table->string('phone', 50)->nullable();
            $table->string('email', 190)->nullable();
            $table->string('status', 30)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('account_id');
            $table->index('email');
            $table->index('phone');
            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('staff');
    }
};
