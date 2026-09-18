<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_runtime', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('tenant_id')->unique();
            $table->string('schema_version', 20)->default('1.0');
            $table->timestamp('provisioned_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_runtime');
    }
};