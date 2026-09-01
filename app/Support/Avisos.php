<?php

namespace App\Support;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Envio de notificaciones a prueba de fallos.
 *
 * Las notificaciones usan canal database (campana) + mail. Con envio sincrono
 * (QUEUE_CONNECTION=sync), un fallo del SMTP propagaria la excepcion y romperia
 * la accion del usuario (aprobar, enviar, etc.). Este helper aisla ese riesgo:
 * la notificacion se intenta enviar; si falla (p. ej. SMTP caido), se registra
 * en el log y la ejecucion continua. La campana (database) normalmente ya quedo
 * persistida antes del intento de correo.
 */
class Avisos
{
    /**
     * Notifica a uno o varios notifiables de forma segura.
     *
     * @param  mixed  $destinatarios  Un notifiable o un iterable de ellos.
     */
    public static function enviar($destinatarios, Notification $notificacion): void
    {
        $lista = is_iterable($destinatarios) ? $destinatarios : [$destinatarios];

        foreach ($lista as $destinatario) {
            if (! $destinatario) {
                continue;
            }
            try {
                $destinatario->notify($notificacion);
            } catch (\Throwable $e) {
                // No romper la accion por un fallo de correo/notificacion.
                Log::warning('Fallo al enviar notificacion', [
                    'destinatario' => method_exists($destinatario, 'getKey') ? $destinatario->getKey() : null,
                    'error'        => $e->getMessage(),
                ]);
            }
        }
    }
}
