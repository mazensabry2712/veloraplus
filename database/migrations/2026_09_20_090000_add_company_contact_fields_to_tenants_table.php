<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->string('phone', 50)->nullable()->after('locale');
            $table->string('email')->nullable()->after('phone');
            $table->string('website', 2048)->nullable()->after('email');
            $table->string('city', 255)->nullable()->after('website');
            $table->text('address')->nullable()->after('city');
            $table->string('business_type', 255)->nullable()->after('industry');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table): void {
            $table->dropColumn([
                'phone',
                'email',
                'website',
                'city',
                'address',
                'business_type',
            ]);
        });
    }
};
