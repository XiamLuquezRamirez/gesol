<?php

namespace App\Notifications;

use App\Models\Solicitud;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ComisionCerradaNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Solicitud $solicitud,
        /** Canales activos: la campana (database) siempre; 'mail' solo cuando se pide. */
        public readonly array $canales = ['database', 'mail'],
    ) {}

    public function via(object $notifiable): array
    {
        return $this->canales;
    }

    /**
     * Lista de comisionados con sus fechas, para el informe a RR. HH.
     */
    private function comisionados(): array
    {
        $comision = $this->solicitud->solicitable;
        $comision->loadMissing('viajeros.empleado');

        return $comision->viajeros->map(fn ($v) => [
            'empleado'      => $v->nombreMostrado,
            'identificacion'=> $v->identificacionMostrada,
            'fecha_salida'  => optional($v->fecha_salida)->toDateString() ?? $v->fecha_salida,
            'fecha_regreso' => optional($v->fecha_regreso)->toDateString() ?? $v->fecha_regreso,
        ])->all();
    }

    public function toArray(object $notifiable): array
    {
        $comision = $this->solicitud->solicitable;

        return [
            'solicitud_id'    => $this->solicitud->id,
            'radicado'        => $this->solicitud->radicado,
            'estado'          => $this->solicitud->estado,
            'tipo'            => 'comision_reportada',
            'tipo_nombre'     => $this->solicitud->tipoSolicitud->nombre,
            'nombre_comision' => $comision->nombre_comision ?? null,
            'destino'         => $comision->municipio_destino ?? null,
            'comisionados'    => $this->comisionados(),
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $comision = $this->solicitud->solicitable;

        $mail = (new MailMessage)
            ->subject('Personal en comisión: '.$this->solicitud->radicado)
            ->greeting('Informe de comisión a RR. HH.')
            ->line('Se reportó la comisión "'.($comision->nombre_comision ?? '—').'" con destino '
                .($comision->municipio_destino ?? '—').'. El siguiente personal estará por fuera:');

        foreach ($this->comisionados() as $c) {
            $mail->line('• '.$c['empleado'].' ('.($c['identificacion'] ?? 's/id').') — del '
                .$c['fecha_salida'].' al '.$c['fecha_regreso']);
        }

        return $mail->line('Radicado: '.$this->solicitud->radicado);
    }
}
