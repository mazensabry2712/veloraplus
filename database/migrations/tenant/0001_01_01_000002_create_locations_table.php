<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locations', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name', 120);
            $table->string('code', 50)->nullable()->unique();
            $table->text('address')->nullable();
            $table->char('country_code', 2)->nullable();
            $table->string('city', 100)->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->string('status', 30)->default('active');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locations');
    }
};
