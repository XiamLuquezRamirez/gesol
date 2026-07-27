<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesSeeder extends Seeder
{
    public function run(): void
    {
        $roles = [
            'admin',
            'lider_area',
            'lider_comite',
            'rrhh',
            'contabilidad_lider',
            'contador',
        ];

        foreach ($roles as $rol) {
            Role::firstOrCreate(['name' => $rol, 'guard_name' => 'web']);
        }
    }
}
