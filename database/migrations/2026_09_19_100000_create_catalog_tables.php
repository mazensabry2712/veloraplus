<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('modules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('draft')->index();
            $table->boolean('is_core')->default(false)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('features', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('module_id')->constrained('modules')->cascadeOnDelete();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('draft')->index();
            $table->string('billing_mode')->default('flat')->index();
            $table->boolean('is_required')->default(false);
            $table->boolean('is_individually_purchasable')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['module_id', 'status']);
        });

        Schema::create('catalog_dependencies', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('dependent_type', 32);
            $table->ulid('dependent_id');
            $table->string('dependency_type', 32);
            $table->ulid('dependency_id');
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->unique(
                ['dependent_type', 'dependent_id', 'dependency_type', 'dependency_id'],
                'catalog_dependencies_unique'
            );
            $table->index(['dependent_type', 'dependent_id']);
            $table->index(['dependency_type', 'dependency_id']);
        });

        Schema::create('bundles', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('key')->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('draft')->index();
            $table->unsignedSmallInteger('discount_bps')->default(0);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('bundle_modules', function (Blueprint $table) {
            $table->foreignUlid('bundle_id')->constrained('bundles')->cascadeOnDelete();
            $table->foreignUlid('module_id')->constrained('modules')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['bundle_id', 'module_id']);
        });

        Schema::create('bundle_features', function (Blueprint $table) {
            $table->foreignUlid('bundle_id')->constrained('bundles')->cascadeOnDelete();
            $table->foreignUlid('feature_id')->constrained('features')->cascadeOnDelete();
            $table->timestamps();
            $table->primary(['bundle_id', 'feature_id']);
        });

        Schema::create('catalog_prices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('priceable_type', 32);
            $table->ulid('priceable_id');
            $table->string('billing_cycle', 16);
            $table->char('currency', 3);
            $table->char('country_code', 2)->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->string('status')->default('active')->index();
            $table->timestamp('effective_from');
            $table->timestamp('effective_to')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(
                ['priceable_type', 'priceable_id', 'billing_cycle', 'currency', 'country_code'],
                'catalog_prices_lookup_index'
            );
            $table->index(['effective_from', 'effective_to']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_prices');
        Schema::dropIfExists('bundle_features');
        Schema::dropIfExists('bundle_modules');
        Schema::dropIfExists('bundles');
        Schema::dropIfExists('catalog_dependencies');
        Schema::dropIfExists('features');
        Schema::dropIfExists('modules');
    }
};
