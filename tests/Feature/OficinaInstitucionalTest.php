<?php
namespace Tests\Feature;

use App\Models\{Area, Empleados, SolicitudOficina, Usuario};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OficinaInstitucionalTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $liderArea;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->liderArea = Usuario::where('email', 'lider.area@demo.test')->firstOrFail();
    }

    private function areaConEmpleados(): array
    {
        $area = Area::where('es_general', false)->whereHas('empleados')->first();
        $emp  = $area->empleados()->pluck('id')->all();
        return [$area, $emp];
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'urgencia'      => 'media',
            'justificacion' => 'Insumos.',
            'items'         => [['nombre'=>'Mouse','categoria'=>'producto','cantidad'=>1,'costo_estimado'=>1000,'notas'=>'']],
        ], $override);
    }

    public function test_solicitud_normal_con_beneficiarios_del_area_es_valida(): void
    {
        [$area, $emp] = $this->areaConEmpleados();

        $this->actingAs($this->liderArea)
            ->post(route('oficina.store'), $this->payload(['area_id'=>$area->id,'beneficiarios'=>$emp]))
            ->assertRedirect();
    }

    public function test_solicitud_normal_rechaza_beneficiario_de_otra_area(): void
    {
        [$area, ] = $this->areaConEmpleados();
        // Empleado de un area distinta a la elegida.
        $ajeno = Empleados::where('area_id', '!=', $area->id)->whereNotNull('area_id')->first();

        $this->actingAs($this->liderArea)
            ->from(route('oficina.crear'))
            ->post(route('oficina.store'), $this->payload(['area_id'=>$area->id,'beneficiarios'=>[$ajeno->id]]))
            ->assertSessionHasErrors('beneficiarios');
    }

    public function test_solicitud_normal_sin_beneficiarios_es_invalida(): void
    {
        [$area, ] = $this->areaConEmpleados();

        $this->actingAs($this->liderArea)
            ->from(route('oficina.crear'))
            ->post(route('oficina.store'), $this->payload(['area_id'=>$area->id]))
            ->assertSessionHasErrors('beneficiarios');
    }

    public function test_solicitud_general_sin_beneficiarios_es_valida(): void
    {
        $general = Area::where('es_general', true)->firstOrFail();

        $this->actingAs($this->liderArea)
            ->post(route('oficina.store'), $this->payload(['area_id'=>$general->id]))
            ->assertRedirect();

        $cabecera = SolicitudOficina::latest('id')->first();
        $this->assertEquals(0, $cabecera->beneficiarios()->count());
    }

    public function test_solicitud_general_ignora_beneficiarios_enviados(): void
    {
        $general = Area::where('es_general', true)->firstOrFail();
        $algun   = Empleados::first();

        $this->actingAs($this->liderArea)
            ->post(route('oficina.store'), $this->payload(['area_id'=>$general->id,'beneficiarios'=>[$algun->id]]))
            ->assertRedirect();

        $cabecera = SolicitudOficina::latest('id')->first();
        $this->assertEquals(0, $cabecera->beneficiarios()->count());
    }

    public function test_editar_de_area_normal_a_general_limpia_los_beneficiarios(): void
    {
        [$area, $emp] = $this->areaConEmpleados();

        // Se crea con beneficiarios en un area normal.
        $this->actingAs($this->liderArea)
            ->post(route('oficina.store'), $this->payload(['area_id'=>$area->id,'beneficiarios'=>$emp]))
            ->assertRedirect();

        $solicitud = \App\Models\Solicitud::latest('id')->first();
        $this->assertGreaterThan(0, $solicitud->solicitable->beneficiarios()->count());

        // Al editar hacia el area General (institucional), los beneficiarios se limpian.
        $general = Area::where('es_general', true)->firstOrFail();
        $this->actingAs($this->liderArea)
            ->put(route('oficina.update', $solicitud), $this->payload(['area_id'=>$general->id]))
            ->assertRedirect();

        $this->assertEquals(0, $solicitud->solicitable->fresh()->beneficiarios()->count());
    }
}
