<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoSolicitudSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('tipos_solicitud')->upsert(
            [
                [
                    'clave'         => 'OFI',
                    'nombre'        => 'Elementos de oficina',
                    'estado_inicial'=> 'borrador',
                    'estados'       => json_encode(['borrador','enviada','verificada','aprobada','pagada','cerrada','rechazada']),
                    'transiciones'  => json_encode([
                        ['origen'=>'borrador',   'accion'=>'enviar',    'destino'=>'enviada',    'roles'=>['lider_area'],                             'label'=>'Enviar a RR. HH.'],
                        ['origen'=>'enviada',    'accion'=>'verificar', 'destino'=>'verificada', 'roles'=>['rrhh'],                                   'label'=>'Verificar'],
                        ['origen'=>'enviada',    'accion'=>'devolver',  'destino'=>'borrador',   'roles'=>['rrhh'],                                   'label'=>'Devolver'],
                        ['origen'=>'verificada', 'accion'=>'aprobar',   'destino'=>'aprobada',   'roles'=>['contabilidad_lider'],                     'label'=>'Aprobar'],
                        ['origen'=>'verificada', 'accion'=>'rechazar',  'destino'=>'rechazada',  'roles'=>['contabilidad_lider'],                     'label'=>'Rechazar'],
                        ['origen'=>'aprobada',   'accion'=>'pagar',     'destino'=>'pagada',     'roles'=>['contabilidad_lider'],                     'label'=>'Registrar pago'],
                        ['origen'=>'pagada',     'accion'=>'cerrar',    'destino'=>'cerrada',    'roles'=>['contabilidad_lider','lider_area'],         'label'=>'Cerrar'],
                    ]),
                    'created_at' => now(), 'updated_at' => now(),
                ],
                [
                    'clave'         => 'VIA',
                    'nombre'        => 'Viáticos',
                    'estado_inicial'=> 'borrador',
                    'estados'       => json_encode(['borrador','enviada','aprobada','liquidada','cerrada','rechazada']),
                    'transiciones'  => json_encode([
                        ['origen'=>'borrador',  'accion'=>'enviar',   'destino'=>'enviada',   'roles'=>['lider_area','lider_comite'],               'label'=>'Enviar a contabilidad'],
                        ['origen'=>'enviada',   'accion'=>'aprobar',  'destino'=>'aprobada',  'roles'=>['contabilidad_lider'], 'notificar'=>['lider_area','lider_comite'], 'label'=>'Aprobar monto'],
                        ['origen'=>'enviada',   'accion'=>'rechazar', 'destino'=>'rechazada', 'roles'=>['contabilidad_lider'],                      'label'=>'Rechazar'],
                        ['origen'=>'enviada',   'accion'=>'devolver', 'destino'=>'borrador',  'roles'=>['contabilidad_lider'],                      'label'=>'Devolver'],
                        ['origen'=>'aprobada',  'accion'=>'liquidar', 'destino'=>'liquidada', 'roles'=>['contador'],                                'label'=>'Presentar informe'],
                        ['origen'=>'liquidada', 'accion'=>'cerrar',   'destino'=>'cerrada',   'roles'=>['contador','lider_comite'],                 'label'=>'Cerrar comisión'],
                    ]),
                    'created_at' => now(), 'updated_at' => now(),
                ],
            ],
            ['clave'],
            ['nombre','estado_inicial','estados','transiciones','updated_at']
        );
    }
}
