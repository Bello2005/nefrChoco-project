<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            AdminUserSeeder::class,
        ]);

        // Los datos de demostración solo tienen sentido fuera de producción.
        if (app()->environment('local')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
