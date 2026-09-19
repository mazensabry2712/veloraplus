<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('platform_refunds', function (Blueprint $table): void {
            $table->string('idempotency_key', 190)
                ->nullable()
                ->unique('platform_refunds_idempotency_unique')
                ->after('payment_id');
        });
    }

    public function down(): void
    {
        Schema::table('platform_refunds', function (Blueprint $table): void {
            $table->dropUnique('platform_refunds_idempotency_unique');
            $table->dropColumn('idempotency_key');
        });
    }
};
