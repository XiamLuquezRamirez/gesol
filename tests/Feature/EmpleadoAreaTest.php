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
}
