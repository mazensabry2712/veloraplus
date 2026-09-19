<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->string('slug', 180)->nullable()->unique()->after('name');
        });

        DB::table('services')
            ->whereNull('slug')
            ->orderBy('id')
            ->chunkById(100, function ($services): void {
                $used = DB::table('services')
                    ->whereNotNull('slug')
                    ->pluck('slug')
                    ->all();

                $used = array_fill_keys($used, true);

                foreach ($services as $service) {
                    $base = Str::slug((string) $service->name);

                    if ($base === '') {
                        $base = 'service';
                    }

                    $slug = $base;
                    $suffix = 2;

                    while (isset($used[$slug])) {
                        $slug = $base.'-'.$suffix;
                        $suffix++;
                    }

                    DB::table('services')
                        ->where('id', $service->id)
                        ->update(['slug' => $slug]);

                    $used[$slug] = true;
                }
            });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table): void {
            $table->dropUnique(['slug']);
            $table->dropColumn('slug');
        });
    }
};
