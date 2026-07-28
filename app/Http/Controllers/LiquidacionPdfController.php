<?php
namespace App\Http\Controllers;

use App\Mail\LiquidacionViajeroMail;
use App\Models\{Solicitud, ViajeroComision};
use App\Services\LiquidacionPdf;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\HttpFoundation\Response;

class LiquidacionPdfController extends Controller
{
    public function __construct(private LiquidacionPdf $pdf) {}

    /**
     * Descarga el formato de liquidación (PDF) de un viajero de la comisión.
     */
    public function descargar(Solicitud $solicitud, ViajeroComision $viajero)
    {
        $this->authorize('imprimirLiquidacion', $solicitud);
        $this->asegurarPertenece($solicitud, $viajero);

        return $this->pdf->generar($solicitud, $viajero)
            ->download($this->pdf->nombreArchivo($solicitud, $viajero));
    }

    /**
     * Envía el formato de liquidación al correo del empleado.
     */
    public function enviarCorreo(Solicitud $solicitud, ViajeroComision $viajero)
    {
        $this->authorize('imprimirLiquidacion', $solicitud);
        $this->asegurarPertenece($solicitud, $viajero);

        $email = $viajero->empleado->email ?? null;
        if (empty($email)) {
            return back()->with('error', 'El empleado no tiene correo registrado.');
        }

        try {
            Mail::to($email)->send(new LiquidacionViajeroMail($solicitud, $viajero));
        } catch (\Throwable $e) {
            report($e);
            return back()->with('error', 'No se pudo enviar el correo. Intente más tarde.');
        }

        return back()->with('success', 'Liquidación enviada a '.$email.'.');
    }

    /**
     * El viajero debe pertenecer a la comisión de la solicitud.
     */
    private function asegurarPertenece(Solicitud $solicitud, ViajeroComision $viajero): void
    {
        abort_unless(
            $viajero->solicitud_viaticos_id === $solicitud->solicitable_id
                && $solicitud->solicitable_type === \App\Models\SolicitudViaticos::class,
            Response::HTTP_NOT_FOUND
        );
    }
}
