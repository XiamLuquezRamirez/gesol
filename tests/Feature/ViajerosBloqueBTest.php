<?php
namespace Tests\Feature;

use App\Models\Contrato;
use App\Models\Empleados;
use App\Models\Municipio;
use App\Models\SolicitudViaticos;
use App\Models\ViajeroComision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViajerosBloqueBTest extends TestCase
{
    use RefreshDatabase;

    public function test_nombre_mostrado_usa_empleado_o_externo(): void
    {
        $this->seed();
        $cab = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => '', 'observacion' => 'x']);
        $emp = Empleados::first();

        $conEmpleado = ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => $emp->id,
            'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
        ]);
        $externo = ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => null,
            'nombre_externo' => 'Juan Externo', 'identificacion_externo' => '999',
            'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
        ]);

        $this->assertEquals(
            trim($emp->nombres.' '.$emp->apellidos),
            $conEmpleado->fresh()->nombreMostrado
        );
        $this->assertEquals('Juan Externo', $externo->fresh()->nombreMostrado);
        $this->assertEquals('999', $externo->fresh()->identificacionMostrada);
    }

    public function test_contrato_tiene_viajeros(): void
    {
        $this->seed();
        $contrato = Contrato::create(['descripcion' => 'D', 'objeto' => 'O']);
        $contrato->municipios()->sync(Municipio::take(1)->pluck('id')->all());
        $cab = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => '', 'observacion' => 'x']);
        ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => Empleados::first()->id,
            'contrato_id' => $contrato->id,
            'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
        ]);

        $this->assertEquals(1, $contrato->fresh()->viajeros()->count());
    }

    private function payloadBase(array $viajero): array
    {
        return [
            'nombre_comision' => 'C',
            'municipios'      => Municipio::take(1)->pluck('id')->all(),
            'observacion'     => 'x',
            'viajeros'        => [$viajero],
        ];
    }

    public function test_externo_sin_nombre_es_invalido(): void
    {
        $this->seed();
        $lider = \App\Models\Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();
        $this->actingAs($lider)->from(route('viaticos.crear'))
            ->post(route('viaticos.store'), $this->payloadBase([
                'es_externo' => true, 'identificacion_externo' => '123',
                'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
                'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
            ]))
            ->assertSessionHasErrors('viajeros.0.nombre_externo');
    }

    public function test_no_externo_sin_empleado_es_invalido(): void
    {
        $this->seed();
        $lider = \App\Models\Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();
        $this->actingAs($lider)->from(route('viaticos.crear'))
            ->post(route('viaticos.store'), $this->payloadBase([
                'es_externo' => false,
                'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
                'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
            ]))
            ->assertSessionHasErrors('viajeros.0.empleado_id');
    }
}
