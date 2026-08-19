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

    public function test_externo_sin_identificacion_es_valido(): void
    {
        // La identificación del viajero externo es opcional: basta el nombre.
        $this->seed();
        $lider = \App\Models\Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();

        $this->actingAs($lider)->post(route('viaticos.store'), $this->payloadBase([
            'es_externo' => true, 'nombre_externo' => 'Pedro Externo',
            'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
        ]))->assertRedirect()->assertSessionHasNoErrors();

        $cab = SolicitudViaticos::latest('id')->first();
        $viajero = $cab->viajeros()->first();
        $this->assertEquals('Pedro Externo', $viajero->nombre_externo);
        $this->assertNull($viajero->identificacion_externo);
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

    public function test_persiste_contrato_y_viajero_externo(): void
    {
        $this->seed();
        $lider = \App\Models\Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();
        $contrato = Contrato::create(['descripcion' => 'D', 'objeto' => 'O']);
        $emp = Empleados::first();

        $this->actingAs($lider)->post(route('viaticos.store'), [
            'nombre_comision' => 'C',
            'municipios'      => Municipio::take(1)->pluck('id')->all(),
            'observacion'     => 'x',
            'viajeros'        => [
                [
                    'es_externo' => false, 'empleado_id' => $emp->id, 'contrato_id' => $contrato->id,
                    'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
                    'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
                ],
                [
                    'es_externo' => true, 'nombre_externo' => 'Ana Externa', 'identificacion_externo' => '555',
                    'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
                    'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
                ],
            ],
        ])->assertRedirect();

        $cab = SolicitudViaticos::latest('id')->first();
        $viajeros = $cab->viajeros()->orderBy('id')->get();

        $this->assertEquals($emp->id, $viajeros[0]->empleado_id);
        $this->assertEquals($contrato->id, $viajeros[0]->contrato_id);
        $this->assertNull($viajeros[1]->empleado_id);
        $this->assertNull($viajeros[1]->contrato_id);
        $this->assertEquals('Ana Externa', $viajeros[1]->nombre_externo);
        $this->assertEquals('555', $viajeros[1]->identificacion_externo);
    }

    public function test_no_se_borra_contrato_con_viajeros(): void
    {
        $this->seed();
        $usuario = \App\Models\Usuario::where('email', 'admin@demo.test')->firstOrFail();
        $contrato = Contrato::create(['descripcion' => 'D', 'objeto' => 'O']);
        $cab = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => '', 'observacion' => 'x']);
        ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => Empleados::first()->id,
            'contrato_id' => $contrato->id,
            'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
        ]);

        $this->actingAs($usuario)->delete(route('parametros.contratos.destroy', $contrato))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('contratos', ['id' => $contrato->id]);
    }

    public function test_se_borra_contrato_sin_viajeros(): void
    {
        $this->seed();
        $usuario = \App\Models\Usuario::where('email', 'admin@demo.test')->firstOrFail();
        $contrato = Contrato::create(['descripcion' => 'D', 'objeto' => 'O']);

        $this->actingAs($usuario)->delete(route('parametros.contratos.destroy', $contrato))
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('contratos', ['id' => $contrato->id]);
    }
}
