<?php
namespace Tests\Feature;

use App\Models\{Area, ItemOficina, Solicitud, SolicitudOficina, TipoSolicitud, Usuario};
use App\Notifications\AvisoTransicionNotification;
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class NotificacionContadorOficinaTest extends TestCase
{
    use RefreshDatabase;

    public function test_al_verificar_una_solicitud_de_oficina_se_notifica_al_contador(): void
    {
        $this->seed();
        Notification::fake();

        $motor = app(MotorWorkflow::class);
        $lider = Usuario::where('email', 'lider.area@demo.test')->firstOrFail();
        $rrhh  = Usuario::where('email', 'rrhh@demo.test')->firstOrFail();
        $contador = Usuario::where('email', 'contador@demo.test')->firstOrFail();
        $tipo  = TipoSolicitud::where('clave', 'OFI')->firstOrFail();

        $cab = SolicitudOficina::create(['beneficiario' => '', 'urgencia' => 'media', 'justificacion' => 'x']);
        ItemOficina::create([
            'solicitud_oficina_id' => $cab->id, 'nombre' => 'Mouse',
            'categoria' => 'producto', 'cantidad' => 1, 'costo_estimado' => 1000, 'subtotal' => 1000,
        ]);
        $solicitud = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $lider->id, 'area_id' => Area::first()->id,
            'solicitable_type' => SolicitudOficina::class, 'solicitable_id' => $cab->id, 'estado' => 'borrador',
            'radicado' => Solicitud::generarRadicado($tipo),
        ]);

        $motor->aplicarTransicion($solicitud, 'enviar', $lider);
        // Al verificar (RR. HH.), el contador debe recibir el aviso informativo.
        $motor->aplicarTransicion($solicitud->fresh(), 'verificar', $rrhh);

        Notification::assertSentTo($contador, AvisoTransicionNotification::class, function ($n) {
            return $n->tipo === 'informativo';
        });
    }
}
