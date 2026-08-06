<?php
namespace Tests\Feature;

use App\Models\{Area, Empleados, Usuario};
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
            'beneficiarios' => Empleados::take(1)->pluck('id')->all(),
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
        // Falta el departamento (area_id) y los beneficiarios.
        $payload = $this->payloadBase();
        unset($payload['area_id'], $payload['beneficiarios']);

        $response = $this->actingAs($this->liderArea)
            ->from(route('oficina.crear'))
            ->post(route('oficina.store'), $payload);

        $response->assertSessionHasErrors(['area_id', 'beneficiarios']);

        $errores = session('errors');
        // El mensaje usa el nombre legible, no el nombre tecnico del campo.
        $this->assertStringContainsString('departamento', mb_strtolower($errores->first('area_id')));
        $this->assertStringNotContainsString('area_id', $errores->first('area_id'));
        $this->assertStringContainsString('beneficiario', mb_strtolower($errores->first('beneficiarios')));
    }

    public function test_crea_solicitud_con_varios_beneficiarios(): void
    {
        $empleados = Empleados::take(2)->pluck('id')->all();

        $this->actingAs($this->liderArea)->post(route('oficina.store'), [
            'area_id'       => Area::first()->id,
            'beneficiarios' => $empleados,
            'urgencia'      => 'media',
            'justificacion' => 'Material para el equipo.',
            'items'         => [['nombre'=>'Mouse','categoria'=>'producto','cantidad'=>1,'costo_estimado'=>1000,'notas'=>'']],
        ])->assertRedirect();

        $cabecera = \App\Models\SolicitudOficina::latest('id')->first();
        $this->assertEqualsCanonicalizing($empleados, $cabecera->beneficiarios->pluck('id')->all());
    }

    public function test_editar_sincroniza_los_beneficiarios(): void
    {
        // Crea la solicitud con un beneficiario.
        $unEmpleado = Empleados::take(1)->pluck('id')->all();
        $this->actingAs($this->liderArea)->post(route('oficina.store'), [
            'area_id'       => Area::first()->id,
            'beneficiarios' => $unEmpleado,
            'urgencia'      => 'media',
            'justificacion' => 'Version inicial.',
            'items'         => [['nombre'=>'Mouse','categoria'=>'producto','cantidad'=>1,'costo_estimado'=>1000,'notas'=>'']],
        ])->assertRedirect();

        $solicitud = \App\Models\Solicitud::latest('id')->first();
        $otrosDos  = Empleados::take(2)->pluck('id')->all();

        // Al editar, se reemplaza el conjunto de beneficiarios por los dos nuevos.
        $this->actingAs($this->liderArea)->put(route('oficina.update', $solicitud), [
            'area_id'       => Area::first()->id,
            'beneficiarios' => $otrosDos,
            'urgencia'      => 'alta',
            'justificacion' => 'Version editada.',
            'items'         => [['nombre'=>'Teclado','categoria'=>'producto','cantidad'=>1,'costo_estimado'=>2000,'notas'=>'']],
        ])->assertRedirect();

        $this->assertEqualsCanonicalizing(
            $otrosDos,
            $solicitud->solicitable->fresh()->beneficiarios->pluck('id')->all()
        );
    }
}
