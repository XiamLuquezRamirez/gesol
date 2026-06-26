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

        $transicion = collect($solicitud->tipoSolicitud->transiciones)
            ->firstWhere('accion', $accion);

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

            $this->notificarSiguientePaso($solicitud->fresh(), $transicion);
        });

        return $solicitud->fresh();
    }

    private function notificarSiguientePaso(Solicitud $solicitud, array $transicion): void
    {
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
                $u->notify(new AvisoTransicionNotification($solicitud, 'informativo'));
            }
        }
    }
}
