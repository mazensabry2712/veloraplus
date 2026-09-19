<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_usage_snapshots', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('metric', 100);
            $table->unsignedBigInteger('value');
            $table->timestamp('captured_at');
            $table->timestamps();

            $table->index(['metric', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_usage_snapshots');
    }
};
