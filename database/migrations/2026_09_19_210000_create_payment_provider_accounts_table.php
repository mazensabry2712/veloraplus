<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_provider_accounts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('provider', 64);
            $table->string('account_reference', 191);
            $table->string('status', 32)->index();
            $table->text('encrypted_credentials')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'provider']);
            $table->index(['tenant_id', 'status']);
            $table->unique(['provider', 'account_reference']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_provider_accounts');
    }
};
