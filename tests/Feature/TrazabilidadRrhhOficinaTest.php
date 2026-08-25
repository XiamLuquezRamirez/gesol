<?php
namespace Tests\Feature;

use App\Models\{AbonoOficina, Area, ItemOficina, Solicitud, SolicitudOficina, TipoSolicitud, Usuario};
use App\Notifications\AvisoTransicionNotification;
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * RR. HH. debe enterarse (aviso informativo) cuando una solicitud de oficina se
 * envia a gerencia (accion 'aprobar') y cuando se cierra (accion 'cerrar'), para
 * tener trazabilidad completa del proceso.
 */
class TrazabilidadRrhhOficinaTest extends TestCase
{
    use RefreshDatabase;

    private function solicitudVerificada(): array
    {
        $motor = app(MotorWorkflow::class);
        $lider = Usuario::where('email', 'lider.area@demo.test')->firstOrFail();
        $rrhh  = Usuario::where('email', 'rrhh@demo.test')->firstOrFail();
        $cl    = Usuario::where('email', 'contabilidad.lider@demo.test')->firstOrFail();
        $tipo  = TipoSolicitud::where('clave', 'OFI')->firstOrFail();

        $cab = SolicitudOficina::create(['beneficiario' => '', 'urgencia' => 'media', 'justificacion' => 'x', 'total' => 100000]);
        ItemOficina::create(['solicitud_oficina_id' => $cab->id, 'nombre' => 'Mouse', 'categoria' => 'producto', 'cantidad' => 1, 'costo_estimado' => 100000, 'subtotal' => 100000]);
        $s = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $lider->id, 'area_id' => Area::first()->id,
            'solicitable_type' => SolicitudOficina::class, 'solicitable_id' => $cab->id, 'estado' => 'borrador',
            'radicado' => Solicitud::generarRadicado($tipo),
        ]);
        $motor->aplicarTransicion($s, 'enviar', $lider);
        $motor->aplicarTransicion($s->fresh(), 'verificar', $rrhh);

        return [$s->fresh(), $rrhh, $cl];
    }

    public function test_al_enviar_a_gerencia_se_notifica_a_rrhh(): void
    {
        $this->seed();
        Notification::fake();
        [$s, $rrhh, $cl] = $this->solicitudVerificada();

        app(MotorWorkflow::class)->aplicarTransicion($s, 'aprobar', $cl);

        Notification::assertSentTo($rrhh, AvisoTransicionNotification::class, function ($n) {
            return $n->tipo === 'informativo' && $n->accion === 'aprobar';
        });
    }

    public function test_al_cerrar_se_notifica_a_rrhh(): void
    {
        $this->seed();
        Storage::fake('local');
        Notification::fake();
        [$s, $rrhh, $cl] = $this->solicitudVerificada();

        // Enviar a gerencia y registrar un abono total para llegar a pendiente_cierre.
        app(MotorWorkflow::class)->aplicarTransicion($s, 'aprobar', $cl);
        $this->actingAs($cl)->post(route('oficina.abono.store', $s->fresh()), [
            'monto' => 100000, 'total_a_pagar' => 100000, 'fecha_pago' => '2026-08-06',
            'soporte' => \Illuminate\Http\UploadedFile::fake()->create('pago.pdf', 100, 'application/pdf'),
        ])->assertRedirect();
        $this->assertEquals('pendiente_cierre', $s->fresh()->estado);

        // Cerrar la solicitud: RR. HH. debe recibir el aviso de cierre.
        app(MotorWorkflow::class)->aplicarTransicion($s->fresh(), 'cerrar', $cl);

        Notification::assertSentTo($rrhh, AvisoTransicionNotification::class, function ($n) {
            return $n->tipo === 'informativo' && $n->accion === 'cerrar';
        });
    }
}
