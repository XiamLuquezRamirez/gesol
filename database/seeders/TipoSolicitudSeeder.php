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
                    'estados'       => json_encode(['borrador','enviada','verificada','aprobada','pendiente_cierre','pagada','cerrada','rechazada']),
                    'transiciones'  => json_encode([
                        ['origen'=>'borrador',        'accion'=>'enviar',    'destino'=>'enviada',          'roles'=>['lider_area'],                                'label'=>'Enviar a RR. HH.'],
                        ['origen'=>'enviada',         'accion'=>'verificar', 'destino'=>'verificada',       'roles'=>['rrhh'], 'notificar'=>['contador'],           'label'=>'Verificar'],
                        ['origen'=>'enviada',         'accion'=>'devolver',  'destino'=>'borrador',         'roles'=>['rrhh'],                                      'label'=>'Devolver'],
                        // Contabilidad envia a gerencia; el pago se registra por abonos (no es transicion del motor).
                        ['origen'=>'verificada',      'accion'=>'aprobar',   'destino'=>'aprobada',         'roles'=>['contabilidad_lider'],                        'label'=>'Enviar a gerencia'],
                        ['origen'=>'verificada',      'accion'=>'rechazar',  'destino'=>'rechazada',        'roles'=>['contabilidad_lider'],                        'label'=>'Rechazar'],
                        // Rechazada por falta de cotizacion: RR. HH. anexa y reenvia a contabilidad.
                        ['origen'=>'rechazada',       'accion'=>'reenviar',  'destino'=>'verificada',       'roles'=>['rrhh'], 'notificar'=>['contabilidad_lider'], 'label'=>'Reenviar a contabilidad'],
                        // El primer abono lleva la solicitud a 'pendiente_cierre'; desde ahi se cierra.
                        ['origen'=>'pendiente_cierre','accion'=>'cerrar',    'destino'=>'cerrada',          'roles'=>['contabilidad_lider','lider_area'],           'label'=>'Cerrar'],
                    ]),
                    'created_at' => now(), 'updated_at' => now(),
                ],
                [
                    'clave'         => 'VIA',
                    'nombre'        => 'Viáticos',
                    'estado_inicial'=> 'borrador',
                    'estados'       => json_encode(['borrador','enviada','liquidada','revisada','cerrada','rechazada']),
                    'transiciones'  => json_encode([
                        // El solicitante envia la comision directamente al contador.
                        ['origen'=>'borrador',  'accion'=>'enviar',        'destino'=>'enviada',   'roles'=>['lider_area','lider_comite'], 'notificar'=>['contador'], 'label'=>'Enviar al contador y RR. HH.'],
                        // El contador presenta el informe (liquida).
                        ['origen'=>'enviada',   'accion'=>'liquidar',      'destino'=>'liquidada', 'roles'=>['contador'],                  'label'=>'Presentar informe'],
                        ['origen'=>'enviada',   'accion'=>'devolver',      'destino'=>'borrador',  'roles'=>['contador'],                  'label'=>'Devolver'],
                        // Ya liquidada, el contador la envia al lider de contabilidad.
                        ['origen'=>'liquidada', 'accion'=>'enviar_revision','destino'=>'revisada', 'roles'=>['contador'], 'notificar'=>['contabilidad_lider'], 'label'=>'Enviar a líder de contabilidad'],
                        // El lider de contabilidad aprueba y cierra (esto notifica a RR. HH.).
                        ['origen'=>'revisada',  'accion'=>'cerrar',        'destino'=>'cerrada',   'roles'=>['contabilidad_lider'],        'label'=>'Aprobar y cerrar comisión'],
                        ['origen'=>'revisada',  'accion'=>'devolver',      'destino'=>'liquidada', 'roles'=>['contabilidad_lider'],        'label'=>'Devolver al contador'],
                        ['origen'=>'revisada',  'accion'=>'rechazar',      'destino'=>'rechazada', 'roles'=>['contabilidad_lider'],        'label'=>'Rechazar'],
                    ]),
                    'created_at' => now(), 'updated_at' => now(),
                ],
            ],
            ['clave'],
            ['nombre','estado_inicial','estados','transiciones','updated_at']
        );
    }
}
// 