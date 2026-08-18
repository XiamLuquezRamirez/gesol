<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MunicipiosSeeder extends Seeder
{
    public function run(): void
    {
        $municipios = [
            'Valledupar', 'Aguachica', 'Agustín Codazzi', 'Bosconia', 'Chimichagua',
            'Chiriguaná', 'Curumaní', 'El Copey', 'El Paso', 'La Jagua de Ibirico',
            'Becerril', 'La Paz', 'Manaure', 'Pailitas', 'Pelaya', 'Pueblo Bello',
            'San Alberto', 'San Diego', 'San Martín', 'Tamalameque',
        ];
        foreach ($municipios as $nombre) {
            DB::table('municipios')->insertOrIgnore([
                'nombre' => $nombre, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
