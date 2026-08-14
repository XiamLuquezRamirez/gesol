<?php
namespace App\Http\Controllers;

use App\Http\Requests\RegistrarAbonoOficinaRequest;
use App\Models\{AbonoOficina, SolicitudOficina, Solicitud};
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class AbonoOficinaController extends Controller
{
    /**
     * Registra un abono. Al primer abono, la solicitud pasa de 'aprobada'
     * a 'pendiente_cierre' automaticamente.
     */
    public function store(RegistrarAbonoOficinaRequest $request, Solicitud $solicitud)
    {
        $this->authorize('registrarAbono', $solicitud);
        $cabecera = $solicitud->solicitable;

        // El archivo se guarda fuera de la transaccion de BD.
        $soportePath   = $request->file('soporte')->store('soportes_pago', 'local');
        $soporteNombre = $request->file('soporte')->getClientOriginalName();

        // El abono y el avance de estado son una sola unidad atomica.
        DB::transaction(function () use ($cabecera, $solicitud, $request, $soportePath, $soporteNombre) {
            // Bloqueo pesimista de la cabecera: relee el estado de pago con lock
            // para que dos abonos simultaneos no sobrepasen el total (defensa en
            // profundidad; la validacion amigable ya corrio en el FormRequest).
            $cabecera = SolicitudOficina::whereKey($cabecera->id)->lockForUpdate()->firstOrFail();

            // El primer abono define el total real a pagar de la solicitud.
            if ($cabecera->total_a_pagar === null) {
                $cabecera->update(['total_a_pagar' => $request->total_a_pagar]);
            }

            // Red de seguridad ante concurrencia: el monto no puede exceder el saldo.
            abort_if((float) $request->monto > $cabecera->fresh()->saldoPendiente(), 422,
                'El monto supera el saldo pendiente.');

            $cabecera->abonos()->create([
                'monto'          => $request->monto,
                'fecha_pago'     => $request->fecha_pago,
                'soporte_path'   => $soportePath,
                'soporte_nombre' => $soporteNombre,
                'usuario_id'     => auth()->id(),
                'observacion'    => $request->observacion,
            ]);

            // El primer abono lleva la solicitud de 'aprobada' a 'pendiente_cierre'.
            if ($solicitud->estado === 'aprobada') {
                $solicitud->update(['estado' => 'pendiente_cierre']);
            }
        });

        return back()->with('success', 'Abono registrado.');
    }

    /**
     * Elimina un abono (correccion). Solo mientras la solicitud no este cerrada.
     */
    public function destroy(Solicitud $solicitud, AbonoOficina $abono)
    {
        abort_unless($abono->solicitud_oficina_id === $solicitud->solicitable->id, 404);
        $this->authorize('registrarAbono', $solicitud);

        Storage::disk('local')->delete($abono->soporte_path);
        $abono->delete();

        return back()->with('success', 'Abono eliminado.');
    }

    /**
     * Descarga controlada del soporte de pago: cualquiera que pueda ver el detalle.
     */
    public function descargarSoporte(Solicitud $solicitud, AbonoOficina $abono)
    {
        $this->authorize('verDetalle', $solicitud);
        abort_unless($abono->solicitud_oficina_id === $solicitud->solicitable->id, 404);
        abort_unless(Storage::disk('local')->exists($abono->soporte_path), 404);

        return Storage::disk('local')->download($abono->soporte_path, $abono->soporte_nombre);
    }
}
