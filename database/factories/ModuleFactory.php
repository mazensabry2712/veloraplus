<?php

namespace Database\Factories;

use App\Models\Module;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Module> */
class ModuleFactory extends Factory
{
    protected $model = Module::class;

    public function definition(): array
    {
        $key = 'module.'.Str::lower(Str::random(8));

        return [
            'key' => $key,
            'name' => Str::headline($key),
            'status' => 'draft',
            'is_core' => false,
            'sort_order' => 0,
            'metadata' => null,
        ];
    }
}
