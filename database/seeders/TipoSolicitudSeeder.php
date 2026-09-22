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
                        // RR. HH. tambien puede enviar (cuando es el propio solicitante); el
                        // controlador salta la verificacion en ese caso y va directo a contabilidad.
                        ['origen'=>'borrador',        'accion'=>'enviar',    'destino'=>'enviada',          'roles'=>['lider_area','rrhh'],                         'label'=>'Enviar a RR. HH.'],
                        ['origen'=>'enviada',         'accion'=>'verificar', 'destino'=>'verificada',       'roles'=>['rrhh'], 'notificar'=>['contador'],           'label'=>'Verificar'],
                        ['origen'=>'enviada',         'accion'=>'devolver',  'destino'=>'borrador',         'roles'=>['rrhh'],                                      'label'=>'Devolver'],
                        // Contabilidad envia a gerencia; el pago se registra por abonos (no es transicion del motor).
                        // Se notifica a RR. HH. para que tenga trazabilidad del envio a gerencia.
                        ['origen'=>'verificada',      'accion'=>'aprobar',   'destino'=>'aprobada',         'roles'=>['contabilidad_lider'], 'notificar'=>['rrhh'], 'label'=>'Enviar a gerencia'],
                        ['origen'=>'verificada',      'accion'=>'rechazar',  'destino'=>'rechazada',        'roles'=>['contabilidad_lider'],                        'label'=>'Rechazar'],
                        // Rechazada por falta de cotizacion: RR. HH. anexa y reenvia a contabilidad.
                        ['origen'=>'rechazada',       'accion'=>'reenviar',  'destino'=>'verificada',       'roles'=>['rrhh'], 'notificar'=>['contabilidad_lider'], 'label'=>'Reenviar a contabilidad'],
                        // El primer abono lleva la solicitud a 'pendiente_cierre'; desde ahi se cierra.
                        // Se notifica a RR. HH. del cierre para cerrar la trazabilidad de la solicitud.
                        // El cierre lo hace SOLO el lider de contabilidad. Antes tambien lo permitia
                        // 'lider_area', lo que hacia que la solicitud apareciera como pendiente a TODOS
                        // los lideres de area (no solo al solicitante). Se acota a contabilidad_lider.
                        ['origen'=>'pendiente_cierre','accion'=>'cerrar',    'destino'=>'cerrada',          'roles'=>['contabilidad_lider'], 'notificar'=>['rrhh'], 'label'=>'Cerrar'],
                    ]),
                    'created_at' => now(), 'updated_at' => now(),
                ],
                [
                    'clave'         => 'VIA',
                    'nombre'        => 'Viáticos',
                    'estado_inicial'=> 'borrador',
                    'estados'       => json_encode(['borrador','enviada','liquidada','revisada','en_gerencia','cerrada','rechazada','cancelada']),
                    'transiciones'  => json_encode([
                        // El solicitante envia la comision directamente al contador.
                        ['origen'=>'borrador',  'accion'=>'enviar',        'destino'=>'enviada',   'roles'=>['lider_area','lider_comite'], 'notificar'=>['contador'], 'label'=>'Enviar al contador y RR. HH.'],
                        // El contador presenta el informe (liquida).
                        ['origen'=>'enviada',   'accion'=>'liquidar',      'destino'=>'liquidada', 'roles'=>['contador'],                  'label'=>'Presentar informe'],
                        ['origen'=>'enviada',   'accion'=>'devolver',      'destino'=>'borrador',  'roles'=>['contador'],                  'label'=>'Devolver'],
                        // Ya liquidada, el contador la envia al lider de contabilidad.
                        ['origen'=>'liquidada', 'accion'=>'enviar_revision','destino'=>'revisada', 'roles'=>['contador'], 'notificar'=>['contabilidad_lider'], 'label'=>'Enviar a líder de contabilidad'],
                        // El lider de contabilidad revisa y la envia a gerencia; el solicitante
                        // ve el estado "En gerencia · pendiente de cierre".
                        ['origen'=>'revisada',    'accion'=>'enviar_gerencia','destino'=>'en_gerencia','roles'=>['contabilidad_lider'], 'label'=>'Enviar a gerencia'],
                        ['origen'=>'revisada',    'accion'=>'devolver',       'destino'=>'liquidada',  'roles'=>['contabilidad_lider'], 'label'=>'Devolver al contador'],
                        ['origen'=>'revisada',    'accion'=>'rechazar',       'destino'=>'rechazada',  'roles'=>['contabilidad_lider'], 'label'=>'Rechazar'],
                        // En gerencia: el lider de contabilidad aprueba y cierra (o aun puede devolver/rechazar).
                        ['origen'=>'en_gerencia', 'accion'=>'cerrar',         'destino'=>'cerrada',    'roles'=>['contabilidad_lider'], 'label'=>'Aprobar y cerrar comisión'],
                        ['origen'=>'en_gerencia', 'accion'=>'devolver',       'destino'=>'liquidada',  'roles'=>['contabilidad_lider'], 'label'=>'Devolver al contador'],
                        ['origen'=>'en_gerencia', 'accion'=>'rechazar',       'destino'=>'rechazada',  'roles'=>['contabilidad_lider'], 'label'=>'Rechazar'],
                    ]),
                    'created_at' => now(), 'updated_at' => now(),
                ],
                [
                    'clave'         => 'OBR',
                    'nombre'        => 'Obras',
                    'estado_inicial'=> 'borrador',
                    'estados'       => json_encode(['borrador','enviada','cotizada','en_contabilidad','aprobada','pendiente_cierre','cerrada','rechazada']),
                    'transiciones'  => json_encode([
                        ['origen'=>'borrador',        'accion'=>'enviar',              'destino'=>'enviada',        'roles'=>['residente','lider_area'], 'notificar'=>['rrhh'], 'label'=>'Enviar a RR. HH.'],
                        ['origen'=>'enviada',         'accion'=>'cotizar',             'destino'=>'cotizada',       'roles'=>['rrhh'],                    'label'=>'Cotizar'],
                        ['origen'=>'enviada',         'accion'=>'devolver',            'destino'=>'borrador',       'roles'=>['rrhh'],                    'label'=>'Devolver'],
                        ['origen'=>'cotizada',        'accion'=>'enviar_contabilidad', 'destino'=>'en_contabilidad','roles'=>['rrhh'], 'notificar'=>['contabilidad_lider','contador'], 'label'=>'Enviar a contabilidad'],
                        ['origen'=>'cotizada',        'accion'=>'devolver',            'destino'=>'borrador',       'roles'=>['rrhh'],                    'label'=>'Devolver'],
                        ['origen'=>'en_contabilidad', 'accion'=>'aprobar',            'destino'=>'aprobada',        'roles'=>['contabilidad_lider'],      'label'=>'Aprobar'],
                        ['origen'=>'en_contabilidad', 'accion'=>'devolver',           'destino'=>'cotizada',        'roles'=>['contabilidad_lider'],      'label'=>'Devolver a RR. HH.'],
                        ['origen'=>'en_contabilidad', 'accion'=>'rechazar',           'destino'=>'rechazada',       'roles'=>['contabilidad_lider'],      'label'=>'Rechazar'],
                        ['origen'=>'pendiente_cierre','accion'=>'cerrar',             'destino'=>'cerrada',         'roles'=>['contabilidad_lider'],      'notificar'=>['rrhh'], 'label'=>'Cerrar'],
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