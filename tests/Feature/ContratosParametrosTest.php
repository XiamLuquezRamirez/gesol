<?php
namespace Tests\Feature;

use App\Models\{Contrato, Municipio};
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
}
