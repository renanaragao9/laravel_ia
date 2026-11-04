<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => config('app.admin.mail')],
            [
                'name' => config('app.admin.name'),
                'password' => config('app.admin.password'),
                'is_master' => true,
            ],
        );
    }
}
