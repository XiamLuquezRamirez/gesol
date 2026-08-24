<?php
namespace Tests\Feature;

use App\Models\{AjusteComision, AsignacionViatico, Empleados, Solicitud, SolicitudViaticos, TipoSolicitud, Usuario, ViajeroComision};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AjusteComisionAislamientoTest extends TestCase
{
    use RefreshDatabase;

    /** Crea una comision VIA cerrada con un viajero. Devuelve [$solicitud, $cabecera, $viajero]. */
    private function comisionCerrada(): array
    {
        $tipo  = TipoSolicitud::where('clave', 'VIA')->firstOrFail();
        $lider = Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();

        $cabecera = SolicitudViaticos::create([
            'nombre_comision'   => 'Comision de prueba',
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

        return [$solicitud, $cabecera, $viajero];
    }

    public function test_recalcular_total_excluye_asignaciones_de_ajustes(): void
    {
        $this->seed();
        [$solicitud, $cabecera, $viajero] = $this->comisionCerrada();

        // Asignacion original (sin ajuste): contribuye al total de la cabecera.
        AsignacionViatico::create([
            'viajero_comision_id' => $viajero->id,
            'rubro'               => 'almuerzo',
            'valor_unitario'      => 25000,
            'dias'                => 1,
        ]);
        $cabecera->refresh();
        $this->assertEquals(25000, $cabecera->total);

        // Ajuste (anexo) con su asignacion: NO debe alterar el total de la cabecera cerrada.
        $ajuste = AjusteComision::create([
            'solicitud_id'        => $solicitud->id,
            'viajero_comision_id' => $viajero->id,
            'solicitado_por'      => $solicitud->solicitante_id,
            'tipo'                => 'fechas',
            'motivo'              => 'x',
        ]);
        AsignacionViatico::create([
            'viajero_comision_id' => $viajero->id,
            'ajuste_comision_id'  => $ajuste->id,
            'rubro'               => 'cena',
            'valor_unitario'      => 20000,
            'dias'                => 1,
        ]);

        $cabecera->refresh();
        $this->assertEquals(25000, $cabecera->total, 'La cabecera no debe sumar anexos');

        $ajuste->refresh();
        $this->assertEquals(20000, $ajuste->total_delta, 'El ajuste suma su propio delta');
    }
}
