<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmpleadosSeeder extends Seeder
{
    public function run(): void
    {
        // Mapa nombre de area -> id (solo areas reales, no la General).
        $areas = DB::table('areas')->where('es_general', false)->pluck('id', 'nombre');

        $empleados = [
            ['identificacion'=>'10001','nombres'=>'Ana','apellidos'=>'Martínez','email'=>'ana.martinez@demo.test','telefono'=>'3001000001','area'=>'Tecnología'],
            ['identificacion'=>'10002','nombres'=>'Carlos','apellidos'=>'López','email'=>'carlos.lopez@demo.test','telefono'=>'3001000002','area'=>'Tecnología'],
            ['identificacion'=>'10003','nombres'=>'Luisa','apellidos'=>'Ramírez','email'=>'luisa.ramirez@demo.test','telefono'=>'3001000003','area'=>'Contabilidad'],
            ['identificacion'=>'10004','nombres'=>'Jorge','apellidos'=>'Herrera','email'=>'jorge.herrera@demo.test','telefono'=>'3001000004','area'=>'Recursos Humanos'],
            ['identificacion'=>'10005','nombres'=>'María','apellidos'=>'Gómez','email'=>'maria.gomez@demo.test','telefono'=>'3001000005','area'=>'Gerencia'],
        ];

        foreach ($empleados as $e) {
            DB::table('empleados')->insertOrIgnore([
                'area_id'        => $areas[$e['area']] ?? null,
                'identificacion' => $e['identificacion'],
                'nombres'        => $e['nombres'],
                'apellidos'      => $e['apellidos'],
                'email'          => $e['email'],
                'telefono'       => $e['telefono'],
                'created_at'     => now(), 'updated_at' => now(),
            ]);
        }
    }
}
