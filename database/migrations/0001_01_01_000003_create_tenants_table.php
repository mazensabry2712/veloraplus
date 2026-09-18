<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('legal_name')->nullable();
            $table->string('slug')->unique();
            $table->string('status')->default('provisioning')->index();
            $table->string('industry')->nullable();
            $table->char('country_code', 2)->nullable();
            $table->char('default_currency', 3)->default('USD');
            $table->string('timezone')->default('UTC');
            $table->string('locale', 10)->default('en');
            $table->string('database_name')->unique();
            $table->string('database_host')->nullable();
            $table->unsignedInteger('database_port')->nullable();
            $table->string('database_status')->default('pending')->index();
            $table->timestamp('database_ready_at')->nullable();
            $table->text('database_provisioning_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenants');
    }
};