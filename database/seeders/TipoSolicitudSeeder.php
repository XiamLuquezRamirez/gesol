<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoSolicitudSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('tipos_solicitud')->insertOrIgnore([
            [
                'clave'         => 'OFI',
                'nombre'        => 'Elementos de oficina',
                'estado_inicial'=> 'borrador',
                'estados'       => json_encode(['borrador','enviada','verificada','aprobada','pagada','cerrada','rechazada']),
                'transiciones'  => json_encode([
                    ['origen'=>'borrador',   'accion'=>'enviar',    'destino'=>'enviada',    'roles'=>['lider_area']],
                    ['origen'=>'enviada',    'accion'=>'verificar', 'destino'=>'verificada', 'roles'=>['rrhh']],
                    ['origen'=>'enviada',    'accion'=>'devolver',  'destino'=>'borrador',   'roles'=>['rrhh']],
                    ['origen'=>'verificada', 'accion'=>'aprobar',   'destino'=>'aprobada',   'roles'=>['contabilidad_lider']],
                    ['origen'=>'verificada', 'accion'=>'rechazar',  'destino'=>'rechazada',  'roles'=>['contabilidad_lider']],
                    ['origen'=>'aprobada',   'accion'=>'pagar',     'destino'=>'pagada',     'roles'=>['contabilidad_lider']],
                    ['origen'=>'pagada',     'accion'=>'cerrar',    'destino'=>'cerrada',    'roles'=>['contabilidad_lider','lider_area']],
                ]),
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'clave'         => 'VIA',
                'nombre'        => 'Viáticos',
                'estado_inicial'=> 'borrador',
                'estados'       => json_encode(['borrador','enviada','aprobada_monto','liquidada','cerrada','rechazada']),
                'transiciones'  => json_encode([
                    ['origen'=>'borrador',       'accion'=>'enviar',   'destino'=>'enviada',        'roles'=>['lider_comite']],
                    ['origen'=>'enviada',         'accion'=>'aprobar',  'destino'=>'aprobada_monto', 'roles'=>['contabilidad_lider'], 'notificar'=>['rrhh']],
                    ['origen'=>'enviada',         'accion'=>'rechazar', 'destino'=>'rechazada',      'roles'=>['contabilidad_lider']],
                    ['origen'=>'enviada',         'accion'=>'devolver', 'destino'=>'borrador',       'roles'=>['contabilidad_lider']],
                    ['origen'=>'aprobada_monto',  'accion'=>'liquidar', 'destino'=>'liquidada',      'roles'=>['contador']],
                    ['origen'=>'liquidada',       'accion'=>'cerrar',   'destino'=>'cerrada',        'roles'=>['contador','lider_comite']],
                ]),
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
    }
}
