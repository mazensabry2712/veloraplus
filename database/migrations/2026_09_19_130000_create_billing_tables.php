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
            $table->string('billing_cycle', 16)->index();
            $table->char('currency', 3);
            $table->char('country_code', 2)->nullable();
            $table->timestamp('starts_at');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_start');
            $table->timestamp('current_period_end');
            $table->timestamp('next_billed_at')->nullable();
            $table->boolean('cancel_at_period_end')->default(false);
            $table->timestamp('cancelled_at')->nullable();
            $table->string('provider', 64)->nullable();
            $table->string('provider_reference', 191)->nullable();
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'current_period_end']);
            $table->index('provider_reference');
        });

        Schema::create('subscription_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('subscription_id')->constrained('subscriptions')->cascadeOnDelete();
            $table->string('item_type', 32)->default('recurring');
            $table->string('catalog_type', 32);
            $table->string('catalog_key', 191);
            $table->ulid('catalog_price_id')->nullable();
            $table->unsignedBigInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_amount_minor');
            $table->unsignedBigInteger('discount_amount_minor')->default(0);
            $table->unsignedBigInteger('tax_amount_minor')->default(0);
            $table->unsignedBigInteger('line_total_minor')->default(0);
            $table->char('currency', 3);
            $table->string('status', 32)->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('activation_source', 32)->default('subscription');
            $table->string('provider_reference', 191)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['subscription_id', 'status']);
            $table->index(['catalog_type', 'catalog_key']);
            $table->index('catalog_price_id');
        });

        Schema::create('platform_invoices', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('subscription_id')->nullable()->nullOnDelete();
            $table->string('number', 64)->unique();
            $table->string('status', 32)->index();
            $table->char('currency', 3);
            $table->unsignedBigInteger('subtotal_minor')->default(0);
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('total_minor')->default(0);
            $table->timestamp('issued_at');
            $table->timestamp('due_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('voided_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'issued_at']);
            $table->index(['tenant_id', 'status']);
            $table->index('subscription_id');
        });

        Schema::create('platform_invoice_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('invoice_id')->constrained('platform_invoices')->cascadeOnDelete();
            $table->foreignUlid('subscription_item_id')->nullable()->nullOnDelete();
            $table->string('description');
            $table->string('catalog_type', 32);
            $table->string('catalog_key', 191);
            $table->unsignedBigInteger('quantity')->default(1);
            $table->unsignedBigInteger('unit_amount_minor');
            $table->unsignedBigInteger('discount_minor')->default(0);
            $table->unsignedBigInteger('tax_minor')->default(0);
            $table->unsignedBigInteger('line_total_minor')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['invoice_id', 'catalog_type', 'catalog_key']);
            $table->index('subscription_item_id');
        });

        Schema::create('platform_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('invoice_id')->constrained('platform_invoices')->cascadeOnDelete();
            $table->string('provider', 64)->nullable();
            $table->string('provider_payment_id', 191)->nullable();
            $table->string('provider_event_id', 191)->nullable();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 32)->index();
            $table->timestamp('paid_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->index('invoice_id');
            $table->unique(['provider', 'provider_payment_id'], 'platform_payments_provider_payment_unique');
            $table->unique(['provider', 'provider_event_id'], 'platform_payments_provider_event_unique');
        });

        Schema::create('platform_refunds', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignUlid('payment_id')->constrained('platform_payments')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('status', 32)->index();
            $table->string('reason', 500)->nullable();
            $table->string('provider_refund_id', 191)->nullable();
            $table->ulid('initiated_by_account_id')->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->index('payment_id');
        });

        Schema::create('platform_credits', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->unsignedBigInteger('amount_minor');
            $table->unsignedBigInteger('remaining_minor');
            $table->char('currency', 3);
            $table->string('status', 32)->index();
            $table->string('source', 191)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'status']);
            $table->index('expires_at');
        });

        Schema::create('billing_audit_events', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->ulid('actor_account_id')->nullable()->index();
            $table->string('action', 128)->index();
            $table->string('subject_type', 128)->nullable();
            $table->string('subject_id', 191)->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();
            $table->index(['subject_type', 'subject_id']);
            $table->index(['tenant_id', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_audit_events');
        Schema::dropIfExists('platform_credits');
        Schema::dropIfExists('platform_refunds');
        Schema::dropIfExists('platform_payments');
        Schema::dropIfExists('platform_invoice_items');
        Schema::dropIfExists('platform_invoices');
        Schema::dropIfExists('subscription_items');
        Schema::dropIfExists('subscriptions');
    }
};
