<?php
namespace Tests\Feature;

use App\Models\{Area, Usuario};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrearSolicitudOficinaTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $liderArea;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->liderArea = Usuario::where('email', 'lider.area@demo.test')->firstOrFail();
    }

    private function payloadBase(array $itemOverride = []): array
    {
        return [
            'area_id'       => Area::first()->id,
            'beneficiario'  => 'Juan Pérez',
            'urgencia'      => 'media',
            'justificacion' => 'Se requieren insumos.',
            'items'         => [array_merge([
                'nombre'         => 'Resma de papel',
                'categoria'      => 'producto',
                'cantidad'       => 2,
                'costo_estimado' => 15000,
                'notas'          => '',
            ], $itemOverride)],
        ];
    }

    public function test_costo_estimado_es_opcional_y_se_guarda_como_null(): void
    {
        $this->actingAs($this->liderArea)
            ->post(route('oficina.store'), $this->payloadBase(['costo_estimado' => '']))
            ->assertRedirect();

        $this->assertDatabaseHas('items_oficina', [
            'nombre'         => 'Resma de papel',
            'costo_estimado' => null,
        ]);
    }

    public function test_costo_estimado_con_valor_se_guarda(): void
    {
        $this->actingAs($this->liderArea)
            ->post(route('oficina.store'), $this->payloadBase(['costo_estimado' => 15000]))
            ->assertRedirect();

        $this->assertDatabaseHas('items_oficina', [
            'nombre'         => 'Resma de papel',
            'costo_estimado' => 15000,
        ]);
    }

    public function test_campos_obligatorios_muestran_nombre_legible(): void
    {
        // Falta el departamento (area_id) y el beneficiario.
        $payload = $this->payloadBase();
        unset($payload['area_id'], $payload['beneficiario']);

        $response = $this->actingAs($this->liderArea)
            ->from(route('oficina.crear'))
            ->post(route('oficina.store'), $payload);

        $response->assertSessionHasErrors(['area_id', 'beneficiario']);

        $errores = session('errors');
        // El mensaje usa el nombre legible, no el nombre tecnico del campo.
        $this->assertStringContainsString('departamento', mb_strtolower($errores->first('area_id')));
        $this->assertStringNotContainsString('area_id', $errores->first('area_id'));
        $this->assertStringContainsString('beneficiario', mb_strtolower($errores->first('beneficiario')));
    }
}
