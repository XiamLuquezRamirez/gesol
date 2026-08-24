<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TarifaViaticosSeeder extends Seeder
{
    public function run(): void
    {
        $tarifas = [
            ['rubro' => 'desayuno', 'valor_sugerido' => 15000],
            ['rubro' => 'almuerzo', 'valor_sugerido' => 25000],
            ['rubro' => 'cena', 'valor_sugerido' => 20000],
            ['rubro' => 'merienda', 'valor_sugerido' => 10000],
            ['rubro' => 'gasolina', 'valor_sugerido' => 50000],
            ['rubro' => 'transporte', 'valor_sugerido' => 0],
        ];

        foreach ($tarifas as $t) {
            DB::table('tarifas_viaticos')->insertOrIgnore(array_merge($t, ['created_at' => now(), 'updated_at' => now()]));
        }
    }
}
