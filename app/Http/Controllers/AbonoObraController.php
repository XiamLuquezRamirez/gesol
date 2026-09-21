<?php
namespace App\Http\Controllers;

use App\Http\Requests\RegistrarAbonoObraRequest;
use App\Models\{AbonoObra, Solicitud};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};

class AbonoObraController extends Controller
{
    public function store(RegistrarAbonoObraRequest $request, Solicitud $solicitud)
    {
        $this->authorize('pagarObra', $solicitud);
        $cab = $solicitud->solicitable;
        $soportePath   = $request->file('soporte')->store('soportes_obra', 'local');
        $soporteNombre = $request->file('soporte')->getClientOriginalName();

        DB::transaction(function () use ($cab, $solicitud, $request, $soportePath, $soporteNombre) {
            if ($cab->total_a_pagar !== null && (float) $request->monto > $cab->fresh()->saldoPendiente()) {
                Storage::disk('local')->delete($soportePath);
                abort(422, 'El monto supera el saldo pendiente.');
            }
            $cab->abonos()->create([
                'monto' => $request->monto, 'fecha_pago' => $request->fecha_pago,
                'soporte_path' => $soportePath, 'soporte_nombre' => $soporteNombre,
                'usuario_id' => auth()->id(), 'observacion' => $request->observacion,
            ]);
            if ($solicitud->estado === 'aprobada') {
                $solicitud->update(['estado' => 'pendiente_cierre']);
            }
        });

        return back()->with('success', 'Pago registrado.');
    }

    public function aplicarRetencion(Request $request, Solicitud $solicitud, AbonoObra $abono)
    {
        $this->authorize('gestionarRetencionObra', $solicitud);
        abort_unless($abono->solicitud_obra_id === $solicitud->solicitable_id, 404);
        $request->validate([
            'retencion_tipo'  => 'required|in:porcentaje,valor',
            'retencion_valor' => 'required|numeric|min:0',
        ]);
        $monto = $request->retencion_tipo === 'porcentaje'
            ? round((float) $abono->monto * ((float) $request->retencion_valor / 100), 2)
            : round((float) $request->retencion_valor, 2);
        $abono->update([
            'retencion_tipo' => $request->retencion_tipo,
            'retencion_valor' => $request->retencion_valor,
            'retencion_monto' => $monto,
            'retencion_por' => auth()->id(),
        ]);
        return back()->with('success', 'Retención aplicada.');
    }

    public function descargarSoporte(Solicitud $solicitud, AbonoObra $abono)
    {
        $this->authorize('verDetalle', $solicitud);
        abort_unless($abono->solicitud_obra_id === $solicitud->solicitable_id, 404);
        abort_unless(Storage::disk('local')->exists($abono->soporte_path), 404);
        return Storage::disk('local')->download($abono->soporte_path, $abono->soporte_nombre);
    }
}
