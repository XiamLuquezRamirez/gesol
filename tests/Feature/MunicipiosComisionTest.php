<?php
namespace Tests\Feature;

use App\Models\Municipio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MunicipiosComisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_seeder_crea_el_catalogo_de_municipios(): void
    {
        $this->seed();
        $this->assertGreaterThan(0, Municipio::count());
        $this->assertDatabaseHas('municipios', ['nombre' => 'Valledupar']);
    }

    public function test_una_comision_sincroniza_varios_municipios(): void
    {
        $this->seed();
        $cab = \App\Models\SolicitudViaticos::create([
            'nombre_comision' => 'C', 'municipio_destino' => '', 'observacion' => 'x',
        ]);
        $ids = \App\Models\Municipio::take(3)->pluck('id')->all();
        $cab->municipios()->sync($ids);

        $this->assertEquals(3, $cab->fresh()->municipios()->count());
        $this->assertEqualsCanonicalizing($ids, $cab->fresh()->municipios->pluck('id')->all());
    }

    public function test_crear_comision_via_http_guarda_municipios(): void
    {
        $this->seed();
        $lider = \App\Models\Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();
        $emp   = \App\Models\Empleados::first();
        $muni  = \App\Models\Municipio::take(2)->pluck('id')->all();

        $this->actingAs($lider)->post(route('viaticos.store'), [
            'nombre_comision' => 'Comisión técnica',
            'municipios'      => $muni,
            'observacion'     => 'x',
            'viajeros'        => [[
                'empleado_id' => $emp->id, 'motivo' => 'Visita',
                'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
                'fecha_regreso' => '2026-08-22', 'hora_regreso' => '17:00',
            ]],
        ])->assertRedirect();

        $cab = \App\Models\SolicitudViaticos::latest('id')->first();
        $this->assertEqualsCanonicalizing($muni, $cab->municipios->pluck('id')->all());
    }

    public function test_comision_sin_municipios_es_invalida(): void
    {
        $this->seed();
        $lider = \App\Models\Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();
        $emp   = \App\Models\Empleados::first();

        $this->actingAs($lider)
            ->from(route('viaticos.crear'))
            ->post(route('viaticos.store'), [
                'nombre_comision' => 'Comisión técnica',
                'observacion'     => 'x',
                'viajeros'        => [[
                    'empleado_id' => $emp->id, 'motivo' => 'Visita',
                    'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
                    'fecha_regreso' => '2026-08-22', 'hora_regreso' => '17:00',
                ]],
            ])->assertSessionHasErrors('municipios');
    }

    public function test_municipio_destino_se_deriva_de_los_municipios(): void
    {
        // El campo texto municipio_destino (que leen correo/PDF/notificacion/RR.HH.)
        // se llena con los nombres de los municipios seleccionados.
        $this->seed();
        $lider = \App\Models\Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();
        $emp   = \App\Models\Empleados::first();
        $muni  = \App\Models\Municipio::whereIn('nombre', ['Valledupar', 'Becerril'])->pluck('id')->all();

        $this->actingAs($lider)->post(route('viaticos.store'), [
            'nombre_comision' => 'C', 'municipios' => $muni, 'observacion' => 'x',
            'viajeros' => [[
                'empleado_id' => $emp->id, 'motivo' => 'Visita',
                'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
                'fecha_regreso' => '2026-08-22', 'hora_regreso' => '17:00',
            ]],
        ])->assertRedirect();

        $cab = \App\Models\SolicitudViaticos::latest('id')->first();
        // Orden alfabetico por nombre: Becerril, Valledupar.
        $this->assertEquals('Becerril, Valledupar', $cab->municipio_destino);
    }

    public function test_editar_comision_resincroniza_municipios(): void
    {
        $this->seed();
        $lider = \App\Models\Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();
        $emp   = \App\Models\Empleados::first();
        $muni  = \App\Models\Municipio::take(3)->pluck('id')->all();

        // Crea con un municipio.
        $this->actingAs($lider)->post(route('viaticos.store'), [
            'nombre_comision' => 'C', 'municipios' => [$muni[0]], 'observacion' => 'x',
            'viajeros' => [[
                'empleado_id' => $emp->id, 'motivo' => 'Visita',
                'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
                'fecha_regreso' => '2026-08-22', 'hora_regreso' => '17:00',
            ]],
        ])->assertRedirect();

        $solicitud = \App\Models\Solicitud::latest('id')->first();

        // Edita reemplazando por otros dos municipios.
        $this->actingAs($lider)->put(route('viaticos.update', $solicitud), [
            'nombre_comision' => 'C editada', 'municipios' => [$muni[1], $muni[2]], 'observacion' => 'x',
            'viajeros' => [[
                'empleado_id' => $emp->id, 'motivo' => 'Visita',
                'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
                'fecha_regreso' => '2026-08-22', 'hora_regreso' => '17:00',
            ]],
        ])->assertRedirect();

        $this->assertEqualsCanonicalizing(
            [$muni[1], $muni[2]],
            $solicitud->solicitable->fresh()->municipios->pluck('id')->all()
        );
    }
}
