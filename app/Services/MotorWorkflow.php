<?php
namespace App\Services;

use App\Exceptions\TransicionNoPermitidaException;
use App\Models\{Solicitud, TransicionSolicitud, Usuario};
use App\Notifications\AvisoTransicionNotification;
use Illuminate\Support\Facades\DB;

class MotorWorkflow
{
    public function accionesDisponibles(Solicitud $solicitud, Usuario $usuario): array
    {
        $rolesUsuario = $usuario->getRoleNames()->toArray();

        return collect($solicitud->tipoSolicitud->transiciones)
            ->filter(fn($t) =>
                $t['origen'] === $solicitud->estado &&
                !empty(array_intersect($t['roles'], $rolesUsuario))
            )
            ->values()
            ->toArray();
    }

    public function puede(Solicitud $solicitud, string $accion, Usuario $usuario): bool
    {
        return collect($this->accionesDisponibles($solicitud, $usuario))
            ->contains('accion', $accion);
    }

    public function aplicarTransicion(
        Solicitud $solicitud,
        string $accion,
        Usuario $usuario,
        ?string $comentario = null,
        array $metadatos = []
    ): Solicitud {
        if (!$this->puede($solicitud, $accion, $usuario)) {
            throw new TransicionNoPermitidaException(
                "La acción «{$accion}» no está permitida en el estado «{$solicitud->estado}» para tu rol."
            );
        }

        // Resolver por (origen, accion): una misma accion (p. ej. 'devolver' o
        // 'cerrar') puede existir desde varios estados con distinto destino, asi que
        // debe tomarse la del estado ACTUAL, no la primera del JSON.
        $transicion = collect($solicitud->tipoSolicitud->transiciones)
            ->first(fn ($t) => $t['accion'] === $accion && $t['origen'] === $solicitud->estado);

        DB::transaction(function () use ($solicitud, $transicion, $accion, $usuario, $comentario, $metadatos) {
            $estadoAnterior = $solicitud->estado;
            $solicitud->update(['estado' => $transicion['destino']]);

            TransicionSolicitud::create([
                'solicitud_id'   => $solicitud->id,
                'estado_origen'  => $estadoAnterior,
                'estado_destino' => $transicion['destino'],
                'accion'         => $accion,
                'usuario_id'     => $usuario->id,
                'comentario'     => $comentario,
                'metadatos'      => $metadatos ?: null,
            ]);

            $this->notificarSiguientePaso($solicitud->fresh(), $transicion, $accion, $usuario, $comentario);
        });

        return $solicitud->fresh();
    }

    private function notificarSiguientePaso(
        Solicitud $solicitud,
        array $transicion,
        string $accion,
        Usuario $actor,
        ?string $comentario
    ): void {
        $rolesActores = collect($solicitud->tipoSolicitud->transiciones)
            ->filter(fn($t) => $t['origen'] === $transicion['destino'])
            ->pluck('roles')
            ->flatten()
            ->unique()
            ->toArray();

        if (!empty($rolesActores)) {
            $usuarios = Usuario::role($rolesActores)->get();
            foreach ($usuarios as $u) {
                $u->notify(new AvisoTransicionNotification($solicitud, 'accion_requerida'));
            }
        }

        if (!empty($transicion['notificar'])) {
            $observadores = Usuario::role($transicion['notificar'])->get();
            foreach ($observadores as $u) {
                // Se pasa la accion para que el aviso informativo pueda mostrar un
                // mensaje especifico (p. ej. "enviada a gerencia" / "cerrada") y no
                // solo un texto generico. Da trazabilidad al observador (RR. HH.).
                $u->notify(new AvisoTransicionNotification($solicitud, 'informativo', $accion));
            }
        }

        if (in_array($accion, ['rechazar', 'devolver']) && $solicitud->solicitante_id !== $actor->id) {
            $tipoAviso = $accion === 'rechazar' ? 'rechazada' : 'devuelta';
            $solicitud->solicitante->notify(new AvisoTransicionNotification(
                $solicitud,
                $tipoAviso,
                $accion,
                $comentario,
                $actor->name,
            ));
        }
    }
}
