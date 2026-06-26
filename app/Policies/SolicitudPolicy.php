<?php

namespace App\Policies;

use App\Models\{Solicitud, Usuario};
use App\Services\MotorWorkflow;

class SolicitudPolicy
{
    public function __construct(private MotorWorkflow $motor) {}

    public function verDetalle(Usuario $usuario, Solicitud $solicitud): bool
    {
        if ($usuario->id === $solicitud->solicitante_id) return true;
        $rolesUsuario = $usuario->getRoleNames()->toArray();
        return collect($solicitud->tipoSolicitud->transiciones)
            ->pluck('roles')->flatten()->unique()
            ->intersect($rolesUsuario)->isNotEmpty();
    }

    public function ejecutarTransicion(Usuario $usuario, Solicitud $solicitud, string $accion): bool
    {
        return $this->motor->puede($solicitud, $accion, $usuario);
    }

    public function editar(Usuario $usuario, Solicitud $solicitud): bool
    {
        return $usuario->id === $solicitud->solicitante_id &&
            in_array($solicitud->estado, ['borrador']);
    }
}
