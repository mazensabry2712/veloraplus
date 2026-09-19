<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('services', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->unsignedInteger('duration_minutes');
            $table->unsignedInteger('buffer_before_minutes')->default(0);
            $table->unsignedInteger('buffer_after_minutes')->default(0);
            $table->unsignedBigInteger('price_minor')->default(0);
            $table->char('currency', 3);
            $table->unsignedBigInteger('deposit_amount_minor')->default(0);
            $table->string('status', 30)->default('active');
            $table->boolean('online_bookable')->default(true);
            $table->unsignedInteger('capacity')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'online_bookable']);
            $table->index('currency');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('services');
    }
};
