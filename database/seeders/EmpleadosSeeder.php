<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmpleadosSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('empleados')->insertOrIgnore([
            ['identificacion'=>'10001','nombres'=>'Ana','apellidos'=>'Martínez','email'=>'ana.martinez@demo.test','telefono'=>'3001000001','created_at'=>now(),'updated_at'=>now()],
            ['identificacion'=>'10002','nombres'=>'Carlos','apellidos'=>'López','email'=>'carlos.lopez@demo.test','telefono'=>'3001000002','created_at'=>now(),'updated_at'=>now()],
            ['identificacion'=>'10003','nombres'=>'Luisa','apellidos'=>'Ramírez','email'=>'luisa.ramirez@demo.test','telefono'=>'3001000003','created_at'=>now(),'updated_at'=>now()],
            ['identificacion'=>'10004','nombres'=>'Jorge','apellidos'=>'Herrera','email'=>'jorge.herrera@demo.test','telefono'=>'3001000004','created_at'=>now(),'updated_at'=>now()],
            ['identificacion'=>'10005','nombres'=>'María','apellidos'=>'Gómez','email'=>'maria.gomez@demo.test','telefono'=>'3001000005','created_at'=>now(),'updated_at'=>now()],
        ]);
    }
}
