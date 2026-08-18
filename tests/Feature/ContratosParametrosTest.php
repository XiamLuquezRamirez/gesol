<?php
namespace Tests\Feature;

use App\Models\{Contrato, Municipio, Usuario};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContratosParametrosTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_contrato_tiene_varios_municipios(): void
    {
        $this->seed();
        $ids = Municipio::take(2)->pluck('id')->all();
        $c = Contrato::create(['descripcion' => 'C-001', 'objeto' => 'Mantenimiento vial']);
        $c->municipios()->sync($ids);

        $this->assertEquals(2, $c->fresh()->municipios()->count());
        $this->assertEqualsCanonicalizing($ids, $c->fresh()->municipios->pluck('id')->all());
    }

    public function test_borrar_un_contrato_borra_su_pivote_pero_no_los_municipios(): void
    {
        $this->seed();
        $ids = Municipio::take(2)->pluck('id')->all();
        $c = Contrato::create(['descripcion' => 'C-002', 'objeto' => 'Obra']);
        $c->municipios()->sync($ids);

        $c->delete();

        // cascadeOnDelete: se borran las filas de la pivote...
        $this->assertDatabaseMissing('contrato_municipio', ['contrato_id' => $c->id]);
        // ...pero los municipios del catalogo siguen intactos.
        $this->assertEquals(2, Municipio::whereIn('id', $ids)->count());
    }

    public function test_borrar_un_municipio_no_esta_permitido_si_deja_pivote_huerfano(): void
    {
        // La FK municipio_id no tiene cascade: borrar un municipio referenciado
        // por un contrato falla por restriccion de integridad (RESTRICT por defecto).
        $this->seed();
        $muni = Municipio::first();
        $c = Contrato::create(['descripcion' => 'C-003', 'objeto' => 'Obra']);
        $c->municipios()->sync([$muni->id]);

        $this->expectException(\Illuminate\Database\QueryException::class);
        $muni->delete();
    }

    public function test_crear_contrato_con_municipios_desde_parametros(): void
    {
        $this->seed();
        $admin = Usuario::where('email', 'admin@demo.test')->firstOrFail();
        $muni  = Municipio::take(2)->pluck('id')->all();

        $this->actingAs($admin)->post(route('parametros.contratos.store'), [
            'descripcion' => 'Contrato 2026-01', 'objeto' => 'Suministro de insumos',
            'municipios'  => $muni,
        ])->assertRedirect();

        $c = Contrato::latest('id')->first();
        $this->assertEquals('Contrato 2026-01', $c->descripcion);
        $this->assertEqualsCanonicalizing($muni, $c->municipios->pluck('id')->all());
    }

    public function test_editar_contrato_resincroniza_municipios(): void
    {
        $this->seed();
        $admin = Usuario::where('email', 'admin@demo.test')->firstOrFail();
        $todos = Municipio::take(3)->pluck('id')->all();
        $c = Contrato::create(['descripcion' => 'X', 'objeto' => 'Y']);
        $c->municipios()->sync([$todos[0]]);

        $this->actingAs($admin)->put(route('parametros.contratos.update', $c), [
            'descripcion' => 'X editado', 'objeto' => 'Y',
            'municipios'  => [$todos[1], $todos[2]],
        ])->assertRedirect();

        $this->assertEquals('X editado', $c->fresh()->descripcion);
        $this->assertEqualsCanonicalizing([$todos[1], $todos[2]], $c->fresh()->municipios->pluck('id')->all());
    }

    public function test_contrato_sin_municipios_es_rechazado(): void
    {
        $this->seed();
        $admin = Usuario::where('email', 'admin@demo.test')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('parametros.index'))
            ->post(route('parametros.contratos.store'), [
                'descripcion' => 'Sin municipios', 'objeto' => 'Z',
            ])->assertSessionHasErrors('municipios');
    }

    public function test_eliminar_contrato(): void
    {
        $this->seed();
        $admin = Usuario::where('email', 'admin@demo.test')->firstOrFail();
        $c = Contrato::create(['descripcion' => 'Borrar', 'objeto' => 'Z']);

        $this->actingAs($admin)->delete(route('parametros.contratos.destroy', $c))->assertRedirect();
        $this->assertDatabaseMissing('contratos', ['id' => $c->id]);
    }
}
