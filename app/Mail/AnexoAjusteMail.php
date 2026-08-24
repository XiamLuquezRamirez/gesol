<?php
namespace App\Mail;

use App\Models\{AjusteComision, Solicitud};
use App\Services\AnexoAjustePdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\{Attachment, Content, Envelope};
use Illuminate\Queue\SerializesModels;

class AnexoAjusteMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Solicitud $solicitud,
        public readonly AjusteComision $ajuste,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Ajuste de liquidación: '.$this->solicitud->radicado,
        );
    }

    public function content(): Content
    {
        $this->ajuste->loadMissing('viajero.empleado');
        return new Content(
            htmlString: '<p>Adjunto encontrará el anexo con el ajuste de liquidación de su comisión de servicio, radicado '
                .e($this->solicitud->radicado).'.</p>'
                .'<p>Empleado: '.e($this->ajuste->viajero->nombreMostrado).'</p>'
                .'<p>Se adjunta el PDF del anexo con el detalle del ajuste.</p>',
        );
    }

    public function attachments(): array
    {
        $pdf = app(AnexoAjustePdf::class);
        return [
            Attachment::fromData(
                fn () => $pdf->generar($this->solicitud, $this->ajuste)->output(),
                $pdf->nombreArchivo($this->solicitud, $this->ajuste),
            )->withMime('application/pdf'),
        ];
    }
}
