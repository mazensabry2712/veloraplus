<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->string('seo_title', 180)->nullable()->after('slug');
            $table->string('seo_description', 320)->nullable()->after('seo_title');
            $table->string('social_image_url', 2048)->nullable()->after('seo_description');
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropColumn(['seo_title', 'seo_description', 'social_image_url']);
        });
    }
};
