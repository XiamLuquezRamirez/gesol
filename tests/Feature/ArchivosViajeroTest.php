<?php
namespace Tests\Feature;

use App\Models\ArchivoViajero;
use App\Models\Empleados;
use App\Models\SolicitudViaticos;
use App\Models\ViajeroComision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchivosViajeroTest extends TestCase
{
    use RefreshDatabase;

    private function viajero(): ViajeroComision
    {
        $cab = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => '', 'observacion' => 'x']);
        return ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => Empleados::first()->id,
            'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
        ]);
    }

    public function test_viajero_tiene_archivos(): void
    {
        $this->seed();
        $v = $this->viajero();
        ArchivoViajero::create(['viajero_comision_id' => $v->id, 'tipo' => 'comprobante', 'path' => 'x/a.pdf', 'nombre' => 'a.pdf']);
        ArchivoViajero::create(['viajero_comision_id' => $v->id, 'tipo' => 'soporte', 'path' => 'x/b.pdf', 'nombre' => 'b.pdf']);

        $this->assertEquals(2, $v->fresh()->archivos()->count());
        $this->assertEquals(1, $v->fresh()->archivos()->where('tipo', 'comprobante')->count());
    }

    private function comisionEnviada(): array
    {
        $tipo = \App\Models\TipoSolicitud::where('clave', 'VIA')->firstOrFail();
        $cab = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => '', 'observacion' => 'x']);
        $viajero = ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => Empleados::first()->id,
            'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
        ]);
        $solicitud = \App\Models\Solicitud::create([
            'tipo_solicitud_id' => $tipo->id,
            'solicitante_id'    => \App\Models\Usuario::first()->id,
            'solicitable_type'  => SolicitudViaticos::class,
            'solicitable_id'    => $cab->id,
            'estado'            => 'enviada',
            'radicado'          => \App\Models\Solicitud::generarRadicado($tipo),
        ]);
        $contador = \App\Models\Usuario::role('contador')->first()
            ?? \App\Models\Usuario::where('email', 'admin@demo.test')->firstOrFail();
        return [$solicitud, $viajero, $contador];
    }

    public function test_contador_sube_comprobante(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->seed();
        [$solicitud, $viajero, $contador] = $this->comisionEnviada();

        $this->actingAs($contador)->post(
            route('viaticos.archivos.store', [$solicitud, $viajero]),
            ['tipo' => 'comprobante', 'archivos' => [\Illuminate\Http\UploadedFile::fake()->create('t.pdf', 100, 'application/pdf')]]
        )->assertRedirect();

        $this->assertEquals(1, $viajero->archivos()->where('tipo', 'comprobante')->count());
        $archivo = $viajero->archivos()->first();
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($archivo->path);
    }

    public function test_sube_varios_soportes(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->seed();
        [$solicitud, $viajero, $contador] = $this->comisionEnviada();

        $this->actingAs($contador)->post(
            route('viaticos.archivos.store', [$solicitud, $viajero]),
            ['tipo' => 'soporte', 'archivos' => [
                \Illuminate\Http\UploadedFile::fake()->image('a.jpg'),
                \Illuminate\Http\UploadedFile::fake()->image('b.jpg'),
            ]]
        )->assertRedirect();

        $this->assertEquals(2, $viajero->archivos()->where('tipo', 'soporte')->count());
    }

    public function test_usuario_no_contador_no_sube(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->seed();
        [$solicitud, $viajero] = $this->comisionEnviada();
        $otro = \App\Models\Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();

        $this->actingAs($otro)->post(
            route('viaticos.archivos.store', [$solicitud, $viajero]),
            ['tipo' => 'comprobante', 'archivos' => [\Illuminate\Http\UploadedFile::fake()->create('t.pdf', 10, 'application/pdf')]]
        )->assertForbidden();
    }

    public function test_contador_elimina_archivo(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->seed();
        [$solicitud, $viajero, $contador] = $this->comisionEnviada();
        $path = \Illuminate\Support\Facades\Storage::disk('local')->putFile('archivos_viajero', \Illuminate\Http\UploadedFile::fake()->create('t.pdf', 10, 'application/pdf'));
        $archivo = ArchivoViajero::create(['viajero_comision_id' => $viajero->id, 'tipo' => 'soporte', 'path' => $path, 'nombre' => 't.pdf']);

        $this->actingAs($contador)->delete(route('viaticos.archivos.destroy', [$solicitud, $viajero, $archivo]))
            ->assertRedirect();

        $this->assertDatabaseMissing('archivos_viajero', ['id' => $archivo->id]);
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($path);
    }

    public function test_archivo_de_otra_comision_da_404(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->seed();
        [$solicitud, $viajero, $contador] = $this->comisionEnviada();
        $otroViajero = $this->viajero();
        $archivoAjeno = ArchivoViajero::create(['viajero_comision_id' => $otroViajero->id, 'tipo' => 'soporte', 'path' => 'x/z.pdf', 'nombre' => 'z.pdf']);

        $this->actingAs($contador)->delete(route('viaticos.archivos.destroy', [$solicitud, $viajero, $archivoAjeno]))
            ->assertNotFound();
    }
}
