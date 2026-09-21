<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@nefrochoco.co'],
            [
                'name' => 'Administrador NefroChoco',
                'password' => Hash::make($this->initialPassword()),
                'email_verified_at' => now(),
            ]
        );

        $admin->assignRole(Role::Admin->value);
    }

    /**
     * En local/testing se mantiene 'password' para no romper la demo. Fuera de
     * ahí, la contraseña del admin inicial tiene que venir de
     * ADMIN_INITIAL_PASSWORD: sin ella, el seeder falla en vez de dejar un
     * admin con una contraseña pública y conocida en producción.
     */
    private function initialPassword(): string
    {
        if (app()->environment(['local', 'testing'])) {
            return 'password';
        }

        $password = config('services.admin.initial_password');

        if (blank($password)) {
            throw new RuntimeException(
                'Falta ADMIN_INITIAL_PASSWORD. Defínela en las variables de entorno de este servidor antes de sembrar '.
                'la base de datos: fuera de local, el admin inicial no se puede crear con la contraseña de la demo.'
            );
        }

        return $password;
    }
}
