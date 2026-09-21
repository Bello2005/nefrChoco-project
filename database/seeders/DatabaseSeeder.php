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
            // El material educativo no es dato de prueba: es contenido real del
            // programa y la plataforma arranca con él en cualquier entorno.
            EducationalContentSeeder::class,
        ]);

        // Los datos de demostración solo tienen sentido fuera de producción.
        if (app()->environment('local')) {
            $this->call(DemoDataSeeder::class);
        }
    }
}
