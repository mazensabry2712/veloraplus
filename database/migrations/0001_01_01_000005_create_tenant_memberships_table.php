<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_memberships', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants');
            $table->foreignUlid('account_id')->constrained('platform_accounts');
            $table->string('role_key')->default('owner');
            $table->string('status')->default('active')->index();
            $table->timestamp('joined_at')->nullable();
            $table->json('invitation_metadata')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'account_id']);
            $table->index(['account_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_memberships');
    }
};