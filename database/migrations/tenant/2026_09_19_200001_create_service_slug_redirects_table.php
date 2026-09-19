<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_slug_redirects', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('old_slug', 180)->unique();
            $table->string('new_slug', 180);
            $table->timestamps();

            $table->index(['service_id', 'new_slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_slug_redirects');
    }
};
