<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('queues', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('location_id')->constrained('locations')->restrictOnDelete();
            $table->foreignUlid('service_id')->constrained('services')->restrictOnDelete();
            $table->date('business_date');
            $table->string('status', 16)->default('open')->index();
            $table->unsignedInteger('next_position')->default(1);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(
                ['location_id', 'service_id', 'business_date'],
                'queues_location_service_business_date_unique',
            );
            $table->index(['location_id', 'business_date']);
            $table->index(['service_id', 'business_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queues');
    }
};
