<?php

namespace App\Notifications;

use App\Models\Solicitud;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AvisoTransicionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Solicitud $solicitud,
        public readonly string $tipo  // 'accion_requerida' | 'informativo'
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'solicitud_id' => $this->solicitud->id,
            'radicado'     => $this->solicitud->radicado,
            'estado'       => $this->solicitud->estado,
            'tipo'         => $this->tipo,
            'tipo_nombre'  => $this->solicitud->tipoSolicitud->nombre,
        ];
    }
}
