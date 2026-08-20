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

    /** Crea un fichero real en disco (fake) y su registro ArchivoViajero para un viajero. */
    private function archivoEnDisco(ViajeroComision $viajero, string $tipo = 'soporte'): ArchivoViajero
    {
        $path = \Illuminate\Support\Facades\Storage::disk('local')->putFile(
            'archivos_viajero',
            \Illuminate\Http\UploadedFile::fake()->create('f.pdf', 10, 'application/pdf')
        );
        return ArchivoViajero::create([
            'viajero_comision_id' => $viajero->id, 'tipo' => $tipo, 'path' => $path, 'nombre' => 'f.pdf',
        ]);
    }

    public function test_borrar_viajero_elimina_sus_archivos_del_disco(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->seed();
        $viajero = $this->viajero();
        $archivo = $this->archivoEnDisco($viajero);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($archivo->path);

        $viajero->delete();

        $this->assertDatabaseMissing('archivos_viajero', ['id' => $archivo->id]);
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($archivo->path);
    }

    public function test_editar_comision_elimina_archivos_de_viajeros_recreados(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->seed();
        $lider = \App\Models\Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();
        $emp   = Empleados::first();
        $muni  = \App\Models\Municipio::take(1)->pluck('id')->all();

        // Crear la comision con un viajero.
        $this->actingAs($lider)->post(route('viaticos.store'), [
            'nombre_comision' => 'C', 'municipios' => $muni, 'observacion' => 'x',
            'viajeros' => [[
                'empleado_id' => $emp->id, 'motivo' => 'm',
                'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
                'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
            ]],
        ])->assertRedirect();

        $solicitud = \App\Models\Solicitud::latest('id')->first();
        $viajero   = $solicitud->solicitable->viajeros()->first();
        $archivo   = $this->archivoEnDisco($viajero);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($archivo->path);

        // Editar la comision (delete-and-recreate de viajeros): el archivo debe limpiarse del disco.
        $this->actingAs($lider)->put(route('viaticos.update', $solicitud), [
            'nombre_comision' => 'C editada', 'municipios' => $muni, 'observacion' => 'x',
            'viajeros' => [[
                'empleado_id' => $emp->id, 'motivo' => 'm2',
                'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
                'fecha_regreso' => '2026-08-22', 'hora_regreso' => '17:00',
            ]],
        ])->assertRedirect();

        $this->assertDatabaseMissing('archivos_viajero', ['id' => $archivo->id]);
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($archivo->path);
    }

    public function test_comando_limpiar_borra_archivos_de_viajero_del_disco(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->seed();
        $viajero = $this->viajero();
        $archivo = $this->archivoEnDisco($viajero);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($archivo->path);

        $this->artisan('solicitudes:limpiar --force')->assertSuccessful();

        $this->assertDatabaseMissing('archivos_viajero', ['id' => $archivo->id]);
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($archivo->path);
    }

    private function solicitudEnEstado(string $estado): \App\Models\Solicitud
    {
        $tipo = \App\Models\TipoSolicitud::where('clave', 'VIA')->firstOrFail();
        $cab = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => '', 'observacion' => 'x']);
        return \App\Models\Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => \App\Models\Usuario::first()->id,
            'solicitable_type' => SolicitudViaticos::class, 'solicitable_id' => $cab->id,
            'estado' => $estado, 'radicado' => \App\Models\Solicitud::generarRadicado($tipo),
        ]);
    }

    /**
     * @dataProvider proveedorGestionComprobante
     */
    public function test_policy_gestionar_comprobante(string $email, string $estado, bool $permitido): void
    {
        $this->seed();
        $usuario = \App\Models\Usuario::where('email', $email)->firstOrFail();
        $solicitud = $this->solicitudEnEstado($estado);

        $this->assertSame(
            $permitido,
            \Illuminate\Support\Facades\Gate::forUser($usuario)->allows('gestionarComprobante', $solicitud),
            "$email en $estado deberia " . ($permitido ? 'PODER' : 'NO poder')
        );
    }

    public static function proveedorGestionComprobante(): array
    {
        $contador = 'contador@demo.test';
        $lider    = 'contabilidad.lider@demo.test';
        $ajeno    = 'lider.comite@demo.test';
        return [
            // Contador: enviada, liquidada, revisada y cerrada.
            'contador enviada'   => [$contador, 'enviada', true],
            'contador liquidada' => [$contador, 'liquidada', true],
            'contador revisada'  => [$contador, 'revisada', true],
            'contador cerrada'   => [$contador, 'cerrada', true],
            'contador borrador'  => [$contador, 'borrador', false],
            // Lider de contabilidad: solo revisada y cerrada.
            'lider revisada'     => [$lider, 'revisada', true],
            'lider cerrada'      => [$lider, 'cerrada', true],
            'lider enviada'      => [$lider, 'enviada', false],
            'lider liquidada'    => [$lider, 'liquidada', false],
            // Rol ajeno: nunca.
            'ajeno liquidada'    => [$ajeno, 'liquidada', false],
        ];
    }

    public function test_lider_contabilidad_sube_comprobante_en_revisada(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->seed();
        $solicitud = $this->solicitudEnEstado('revisada');
        $viajero = ViajeroComision::create([
            'solicitud_viaticos_id' => $solicitud->solicitable_id, 'empleado_id' => Empleados::first()->id,
            'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
        ]);
        $lider = \App\Models\Usuario::where('email', 'contabilidad.lider@demo.test')->firstOrFail();

        $this->actingAs($lider)->post(
            route('viaticos.archivos.store', [$solicitud, $viajero]),
            ['tipo' => 'comprobante', 'archivos' => [\Illuminate\Http\UploadedFile::fake()->create('t.pdf', 10, 'application/pdf')]]
        )->assertRedirect();

        $this->assertEquals(1, $viajero->archivos()->where('tipo', 'comprobante')->count());
    }
}
