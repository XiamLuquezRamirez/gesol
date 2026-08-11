<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AreaSeeder extends Seeder
{
    public function run(): void
    {
        $areas = ['Tecnología', 'Contabilidad', 'Recursos Humanos', 'Gerencia'];
        foreach ($areas as $nombre) {
            DB::table('areas')->insertOrIgnore([
                'nombre' => $nombre, 'es_general' => false,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Area institucional para solicitudes de consumo general (papeleria, aseo).
        DB::table('areas')->insertOrIgnore([
            'nombre'      => 'General',
            'descripcion' => 'Solicitudes institucionales (papelería, aseo)',
            'es_general'  => true,
            'created_at'  => now(), 'updated_at' => now(),
        ]);
    }
}
