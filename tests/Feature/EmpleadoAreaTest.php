<?php
namespace Tests\Feature;

use App\Models\{Area, Empleados};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmpleadoAreaTest extends TestCase
{
    use RefreshDatabase;

    public function test_empleado_pertenece_a_un_area_y_el_area_lista_sus_empleados(): void
    {
        $area = Area::create(['nombre' => 'Sistemas']);
        $empleado = Empleados::create([
            'area_id' => $area->id, 'identificacion' => '99001',
            'nombres' => 'Pedro', 'apellidos' => 'Pérez',
        ]);

        $this->assertEquals($area->id, $empleado->fresh()->area->id);
        $this->assertTrue($area->empleados->contains($empleado->id));
    }

    public function test_empleado_puede_no_tener_area(): void
    {
        $empleado = Empleados::create([
            'identificacion' => '99002', 'nombres' => 'Sin', 'apellidos' => 'Área',
        ]);

        $this->assertNull($empleado->fresh()->area_id);
    }

    public function test_seeder_crea_area_general_y_asigna_areas_a_empleados(): void
    {
        $this->seed();

        $general = \App\Models\Area::where('es_general', true)->first();
        $this->assertNotNull($general);
        $this->assertEquals('General', $general->nombre);

        // Los empleados demo quedaron con area real (no la General).
        $conArea = \App\Models\Empleados::whereNotNull('area_id')->count();
        $this->assertGreaterThan(0, $conArea);
        $this->assertEquals(0, \App\Models\Empleados::where('area_id', $general->id)->count());
    }

    public function test_crear_empleado_con_area_desde_parametros(): void
    {
        $this->seed();
        $admin = \App\Models\Usuario::where('email', 'admin@demo.test')->firstOrFail();
        $area  = \App\Models\Area::where('es_general', false)->first();

        $this->actingAs($admin)->post(route('parametros.empleados.store'), [
            'area_id'        => $area->id,
            'identificacion' => '77001',
            'nombres'        => 'Nuevo',
            'apellidos'      => 'Empleado',
        ])->assertRedirect();

        $this->assertDatabaseHas('empleados', [
            'identificacion' => '77001', 'area_id' => $area->id,
        ]);
    }

    public function test_area_invalida_al_crear_empleado_es_rechazada(): void
    {
        $this->seed();
        $admin = \App\Models\Usuario::where('email', 'admin@demo.test')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('parametros.index'))
            ->post(route('parametros.empleados.store'), [
                'area_id'        => 99999,
                'identificacion' => '77002',
                'nombres'        => 'X', 'apellidos' => 'Y',
            ])->assertSessionHasErrors('area_id');
    }
}
