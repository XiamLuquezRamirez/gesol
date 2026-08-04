<?php
namespace Tests\Feature;

use App\Models\{Empleados, Solicitud, SolicitudViaticos, TipoSolicitud, Usuario, ViajeroComision};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NotificacionRrhhViaticosTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $liderComite;
    private Usuario $rrhh;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->liderComite = Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();
        $this->rrhh        = Usuario::where('email', 'rrhh@demo.test')->firstOrFail();
    }

    private function crearYEnviar(): Solicitud
    {
        $cabecera = SolicitudViaticos::create([
            'nombre_comision' => 'Comité técnico', 'municipio_destino' => 'Medellín', 'observacion' => 'x',
        ]);
        ViajeroComision::create([
            'solicitud_viaticos_id' => $cabecera->id, 'empleado_id' => Empleados::first()->id,
            'motivo' => 'x', 'fecha_salida' => '2026-08-10', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-12', 'hora_regreso' => '17:00', 'tipo_pago' => 'efectivo',
        ]);
        $tipo = TipoSolicitud::where('clave', 'VIA')->firstOrFail();
        $solicitud = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $this->liderComite->id,
            'solicitable_type' => SolicitudViaticos::class, 'solicitable_id' => $cabecera->id,
            'estado' => 'borrador', 'radicado' => Solicitud::generarRadicado($tipo),
        ]);

        $this->actingAs($this->liderComite)
            ->post(route('solicitudes.transicion', $solicitud), ['accion' => 'enviar'])
            ->assertRedirect();

        return $solicitud->fresh();
    }

    public function test_al_enviar_viaticos_rrhh_recibe_notificacion_en_bd(): void
    {
        $this->crearYEnviar();

        // La notificacion queda persistida (alimenta la campana).
        $this->assertDatabaseHas('notifications', [
            'notifiable_id'   => $this->rrhh->id,
            'notifiable_type' => Usuario::class,
        ]);
        $this->assertEquals(1, $this->rrhh->unreadNotifications()->count());
    }

    public function test_el_endpoint_de_notificaciones_la_devuelve_a_rrhh(): void
    {
        $solicitud = $this->crearYEnviar();

        $this->actingAs($this->rrhh)
            ->getJson(route('notificaciones.index'))
            ->assertOk()
            ->assertJsonPath('no_leidas', 1)
            ->assertJsonPath('notificaciones.0.tipo', 'comision_reportada')
            ->assertJsonPath('notificaciones.0.radicado', $solicitud->radicado);
    }
}
