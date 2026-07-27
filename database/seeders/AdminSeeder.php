<?php

namespace Database\Seeders;

use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Usuario::firstOrCreate(
            ['email' => 'admin@demo.test'],
            [
                'name'              => 'Administrador Demo',
                'password'          => Hash::make('password'),
                'email_verified_at' => now(),
            ]
        );

        $admin->syncRoles([
            'admin',
            'lider_area',
            'lider_comite',
            'rrhh',
            'contabilidad_lider',
            'contador',
        ]);
    }
}
