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
            DB::table('areas')->insertOrIgnore(['nombre' => $nombre, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
