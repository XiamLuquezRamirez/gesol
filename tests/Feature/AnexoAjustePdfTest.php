<?php
namespace Tests\Feature;

use App\Models\{AjusteComision, AsignacionViatico, Empleados, Solicitud, SolicitudViaticos, TipoSolicitud, Usuario, ViajeroComision};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnexoAjustePdfTest extends TestCase
{
    use RefreshDatabase;

    /** Crea una comision VIA cerrada con un viajero. Devuelve [$solicitud, $viajero, $lider]. */
    private function comisionCerrada(): array
    {
        $tipo  = TipoSolicitud::where('clave', 'VIA')->firstOrFail();
        $lider = Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();

        $cabecera = SolicitudViaticos::create([
            'nombre_comision'   => 'Comision cerrada',
            'municipio_destino' => 'X',
            'observacion'       => 'x',
        ]);
        $viajero = ViajeroComision::create([
            'solicitud_viaticos_id' => $cabecera->id,
            'empleado_id'           => Empleados::first()->id,
            'motivo'                => 'm',
            'fecha_salida'          => '2026-01-10',
            'hora_salida'           => '08:00',
            'fecha_regreso'         => '2026-01-10',
            'hora_regreso'          => '15:00',
        ]);
        $solicitud = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id,
            'solicitante_id'    => $lider->id,
            'solicitable_type'  => SolicitudViaticos::class,
            'solicitable_id'    => $cabecera->id,
            'estado'            => 'cerrada',
            'radicado'          => Solicitud::generarRadicado($tipo),
        ]);

        return [$solicitud, $viajero, $lider];
    }

    /** Crea un ajuste con una asignacion anexa en el estado indicado. */
    private function ajusteConAsignacion(Solicitud $solicitud, ViajeroComision $viajero, Usuario $lider, string $estado): AjusteComision
    {
        $ajuste = AjusteComision::create([
            'solicitud_id'        => $solicitud->id,
            'viajero_comision_id' => $viajero->id,
            'solicitado_por'      => $lider->id,
            'tipo'                => 'fechas',
            'motivo'              => 'Se extendio la comision',
            'estado'              => $estado,
        ]);
        AsignacionViatico::create([
            'ajuste_comision_id' => $ajuste->id,
            'viajero_comision_id' => $viajero->id,
            'rubro'              => 'gasolina',
            'valor_unitario'     => 50000,
            'dias'               => 1,
        ]);

        return $ajuste->fresh();
    }

    public function test_anexo_pdf_de_ajuste_aprobado_devuelve_pdf(): void
    {
        $this->seed();
        [$solicitud, $viajero, $lider] = $this->comisionCerrada();
        $ajuste = $this->ajusteConAsignacion($solicitud, $viajero, $lider, 'aprobado');

        $resp = $this->actingAs($lider)
            ->get(route('viaticos.ajuste.pdf', [$solicitud, $ajuste]));

        $resp->assertOk();
        $this->assertStringContainsString('application/pdf', $resp->headers->get('content-type'));
    }

    public function test_anexo_pdf_de_ajuste_no_aprobado_devuelve_403(): void
    {
        $this->seed();
        [$solicitud, $viajero, $lider] = $this->comisionCerrada();
        $ajuste = $this->ajusteConAsignacion($solicitud, $viajero, $lider, 'liquidado');

        $this->actingAs($lider)
            ->get(route('viaticos.ajuste.pdf', [$solicitud, $ajuste]))
            ->assertForbidden();
    }
}
