<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('status', 32)->index();
            $table->string('billing_cycle', 16);
            $table->char('currency', 3);
            $table->timestamp('starts_at');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start');
            $table->timestamp('current_period_end');
            $table->timestamp('next_billed_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
            $table->index(['status', 'current_period_end']);
        });

        Schema::create('subscription_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->string('item_type', 16);
            $table->string('catalog_type', 32)->nullable();
            $table->string('catalog_key', 191)->nullable();
            $table->foreignUlid('catalog_price_id')->nullable()->constrained('catalog_prices')->nullOnDelete();
            $table->unsignedBigInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_amount_minor');
            $table->char('currency', 3);
            $table->unsignedBigInteger('discount_amount_minor')->default(0);
            $table->unsignedBigInteger('tax_amount_minor')->default(0);
            $table->unsignedBigInteger('total_amount_minor');
            $table->string('status', 32)->index();
            $table->string('activation_source', 32)->default('subscription');
            $table->timestamp('starts_at');
            $table->timestamp('ends_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['subscription_id', 'status']);
            $table->index(['catalog_type', 'catalog_key']);
        });

        Schema::create('platform_invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('subscription_id')->nullable()->constrained('subscriptions')->nullOnDelete();
            $table->string('number', 64)->unique();
            $table->string('status', 32)->index();
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('credit_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'issued_at']);
        });

        Schema::create('platform_invoice_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('invoice_id')->constrained('platform_invoices')->cascadeOnDelete();
            $table->foreignUlid('subscription_item_id')->nullable()->constrained('subscription_items')->nullOnDelete();
            $table->string('description');
            $table->string('catalog_type', 32)->nullable();
            $table->string('catalog_key', 191)->nullable();
            $table->unsignedBigInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_amount_minor');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('line_total_minor');
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'catalog_type', 'catalog_key']);
        });

        Schema::create('platform_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('invoice_id')->constrained('platform_invoices')->cascadeOnDelete();
            $table->string('gateway_key', 64)->nullable();
            $table->string('external_payment_id', 191)->nullable();
            $table->string('idempotency_key', 191)->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 32)->index();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['invoice_id', 'status']);
            $table->index(['gateway_key', 'external_payment_id']);
        });

        Schema::create('platform_refunds', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('payment_id')->constrained('platform_payments')->cascadeOnDelete();
            $table->foreignUlid('initiated_by_account_id')->nullable()->constrained('platform_accounts')->nullOnDelete();
            $table->string('gateway_key', 64)->nullable();
            $table->string('external_ref', 191)->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 32)->index();
            $table->string('reason')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['payment_id', 'status']);
        });

        Schema::create('platform_credits', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('entry_type', 16);
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('source_type', 64)->nullable();
            $table->string('source_id', 191)->nullable();
            $table->string('reference_type', 64)->nullable();
            $table->string('reference_id', 191)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'entry_type']);
            $table->index(['reference_type', 'reference_id']);
        });

        Schema::create('billing_audits', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('actor_account_id')->nullable()->constrained('platform_accounts')->nullOnDelete();
            $table->string('action', 64);
            $table->string('auditable_type', 64);
            $table->string('auditable_id', 191);
            $table->string('from_status', 32)->nullable();
            $table->string('to_status', 32)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'action']);
            $table->index(['auditable_type', 'auditable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_audits');
        Schema::dropIfExists('platform_credits');
        Schema::dropIfExists('platform_refunds');
        Schema::dropIfExists('platform_payments');
        Schema::dropIfExists('platform_invoice_items');
        Schema::dropIfExists('platform_invoices');
        Schema::dropIfExists('subscription_items');
        Schema::dropIfExists('subscriptions');
    }
};