<?php

namespace Database\Seeders;

use App\Models\Animal;
use App\Support\Bicho\Bicho;
use Illuminate\Database\Seeder;

class AnimalSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Bicho::ANIMALS as $number => $name) {
            Animal::query()->updateOrCreate(
                ['number' => $number],
                ['name' => $name, 'tens' => Bicho::tensOf($number)],
            );
        }
    }
}
