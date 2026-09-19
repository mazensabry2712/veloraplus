<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_entitlements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('catalog_type', 32);
            $table->string('catalog_key', 191);
            $table->string('status', 32)->default('active')->index();
            $table->string('source', 32)->default('manual')->index();
            $table->string('source_reference', 191)->nullable();
            $table->unsignedBigInteger('quantity')->nullable();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['tenant_id', 'catalog_type', 'catalog_key'],
                'tenant_entitlements_tenant_item_unique'
            );
            $table->index(['tenant_id', 'status']);
            $table->index(['catalog_type', 'catalog_key', 'status']);
            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_entitlements');
    }
};
