<?php
namespace App\Mail;

use App\Models\{Solicitud, ViajeroComision};
use App\Services\LiquidacionPdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Attachment, Content, Envelope};
use Illuminate\Queue\SerializesModels;

class LiquidacionViajeroMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Solicitud $solicitud,
        public readonly ViajeroComision $viajero,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Liquidación de comisión de servicio: '.$this->solicitud->radicado,
        );
    }

    public function content(): Content
    {
        $viajero = $this->viajero->loadMissing('empleado');
        return new Content(
            htmlString: '<p>Adjunto encontrará la liquidación de gastos de su comisión de servicio ('
                .e($this->solicitud->solicitable->municipio_destino ?? '').'), radicado '
                .e($this->solicitud->radicado).'.</p>'
                .'<p>Empleado: '.e(trim(($viajero->empleado->nombres ?? '').' '.($viajero->empleado->apellidos ?? ''))).'</p>',
        );
    }

    public function attachments(): array
    {
        $pdf = app(LiquidacionPdf::class);
        return [
            Attachment::fromData(
                fn () => $pdf->generar($this->solicitud, $this->viajero)->output(),
                $pdf->nombreArchivo($this->solicitud, $this->viajero),
            )->withMime('application/pdf'),
        ];
    }
}
