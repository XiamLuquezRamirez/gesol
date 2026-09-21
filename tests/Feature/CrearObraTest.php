<?php
namespace Tests\Feature;

use App\Models\{Solicitud, SolicitudObra, Usuario};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CrearObraTest extends TestCase
{
    use RefreshDatabase;

    private function residente(): Usuario
    {
        $u = Usuario::factory()->create();
        $u->assignRole('residente');
        return $u;
    }

    public function test_residente_crea_solicitud_con_items(): void
    {
        $this->seed();
        Storage::fake('local');
        $res = $this->actingAs($this->residente())->post(route('obra.store'), [
            'nombre_solicitante' => 'CAMILO (RESIDENTE)',
            'fecha_solicitud' => '2026-09-21',
            'items' => [
                ['especificacion' => 'CEMENTO 42.5KG', 'unidad' => 'BOLSA', 'cantidad' => 100, 'sede' => 'LA LOMA'],
            ],
            'enviar' => false,
        ]);
        $res->assertRedirect();
        $this->assertDatabaseHas('solicitudes_obra', ['nombre_solicitante' => 'CAMILO (RESIDENTE)']);
        $this->assertDatabaseHas('items_obra', ['especificacion' => 'CEMENTO 42.5KG']);
        $s = Solicitud::whereHasMorph('solicitable', SolicitudObra::class)->first();
        $this->assertSame('borrador', $s->estado);
    }

    public function test_crear_con_cotizacion_adjunta_y_enviar(): void
    {
        $this->seed();
        Storage::fake('local');
        $res = $this->actingAs($this->residente())->post(route('obra.store'), [
            'nombre_solicitante' => 'CAMILO',
            'fecha_solicitud' => '2026-09-21',
            'cotizacion' => UploadedFile::fake()->create('cotizacion.pdf', 100, 'application/pdf'),
            'enviar' => true,
        ]);
        $res->assertRedirect();
        $s = Solicitud::whereHasMorph('solicitable', SolicitudObra::class)->first();
        $this->assertSame('enviada', $s->estado);
        $this->assertDatabaseHas('cotizaciones_obra', ['tipo' => 'cotizacion']);
    }

    public function test_requiere_items_o_cotizacion(): void
    {
        $this->seed();
        $this->actingAs($this->residente())->post(route('obra.store'), [
            'nombre_solicitante' => 'X', 'fecha_solicitud' => '2026-09-21',
        ])->assertSessionHasErrors();
    }

    public function test_lider_area_tambien_puede_crear(): void
    {
        $this->seed();
        $u = Usuario::factory()->create();
        $u->assignRole('lider_area');
        Storage::fake('local');
        $this->actingAs($u)->post(route('obra.store'), [
            'nombre_solicitante' => 'LIDER', 'fecha_solicitud' => '2026-09-21',
            'items' => [['especificacion' => 'ARENA', 'unidad' => 'VIAJE', 'cantidad' => 2]],
            'enviar' => false,
        ])->assertRedirect();
        $this->assertDatabaseHas('solicitudes_obra', ['nombre_solicitante' => 'LIDER']);
    }
}
