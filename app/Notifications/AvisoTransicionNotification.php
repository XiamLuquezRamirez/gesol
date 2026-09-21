<?php

namespace App\Notifications;

use App\Models\Solicitud;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AvisoTransicionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Solicitud $solicitud,
        public readonly string $tipo,  // 'accion_requerida' | 'informativo' | 'rechazada' | 'devuelta'
        public readonly ?string $accion = null,
        public readonly ?string $comentario = null,
        public readonly ?string $actorNombre = null,
    ) {}

    /**
     * Canales: la campana (database) SIEMPRE. El correo (mail) solo si el
     * destinatario tiene un email valido; asi evitamos intentos de envio a
     * usuarios sin correo. El fallo de SMTP se maneja aparte (ver notify seguro).
     */
    public function via(object $notifiable): array
    {
        $canales = ['database'];
        if (! empty($notifiable->email) && filter_var($notifiable->email, FILTER_VALIDATE_EMAIL)) {
            $canales[] = 'mail';
        }

        return $canales;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'solicitud_id' => $this->solicitud->id,
            'radicado'     => $this->solicitud->radicado,
            'estado'       => $this->solicitud->estado,
            'tipo'         => $this->tipo,
            'tipo_nombre'  => $this->solicitud->tipoSolicitud->nombre,
            'accion'       => $this->accion,
            'comentario'   => $this->comentario,
            'actor_nombre' => $this->actorNombre,
            'solicitante'  => $this->solicitud->solicitante->name,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $radicado = $this->solicitud->radicado;
        $tipoNombre = $this->solicitud->tipoSolicitud->nombre;
        $estado = str_replace('_', ' ', ucfirst($this->solicitud->estado));

        $mail = (new MailMessage)->subject($this->asunto().': '.$radicado);

        // Saludo y cuerpo segun el tipo de aviso.
        switch ($this->tipo) {
            case 'rechazada':
                $mail->greeting('Solicitud rechazada')
                    ->line(($this->actorNombre ?? 'Un revisor').' rechazó tu solicitud '.$radicado.'.');
                if ($this->comentario) {
                    $mail->line('Motivo: '.$this->comentario);
                }
                break;

            case 'devuelta':
                $mail->greeting('Solicitud devuelta')
                    ->line(($this->actorNombre ?? 'Un revisor').' devolvió tu solicitud '.$radicado.' para corrección.');
                if ($this->comentario) {
                    $mail->line('Motivo: '.$this->comentario);
                }
                break;

            case 'accion_requerida':
                $mail->greeting('Tienes una acción pendiente')
                    ->line('La solicitud '.$radicado.' ('.$tipoNombre.') requiere tu atención.');
                if ($this->comentario) {
                    $mail->line('Comentario: '.$this->comentario);
                }
                break;

            case 'seguimiento':
                // Aviso al solicitante del avance de SU solicitud, en cada cambio de estado.
                $mail->greeting('Tu solicitud avanzó')
                    ->line('Tu solicitud '.$radicado.' ('.$tipoNombre.') cambió de estado.')
                    ->line('Estado actual: '.$estado.'.');
                break;

            default: // 'informativo', 'ajustada', 'comision_reportada', etc.
                $mail->greeting('Actualización de solicitud')
                    ->line('Hubo una actualización en la solicitud '.$radicado.' ('.$tipoNombre.').')
                    ->line('Estado actual: '.$estado.'.');
                if ($this->comentario) {
                    $mail->line('Comentario: '.$this->comentario);
                }
                break;
        }

        // Enlace al detalle de la solicitud (usa la URL con prefijo de subcarpeta).
        return $mail
            ->action('Ver solicitud', url(route('solicitudes.show', $this->solicitud->id, false)))
            ->line('Radicado: '.$radicado);
    }

    /** Asunto corto segun el tipo. */
    private function asunto(): string
    {
        return match ($this->tipo) {
            'rechazada'        => 'Solicitud rechazada',
            'devuelta'         => 'Solicitud devuelta',
            'accion_requerida' => 'Acción requerida',
            'seguimiento'      => 'Tu solicitud avanzó',
            default            => 'Actualización de solicitud',
        };
    }
}
