<?php

namespace App\Policies;

use App\Models\{CotizacionOficina, Solicitud, Usuario};
use App\Services\MotorWorkflow;

class SolicitudPolicy
{
    public function __construct(private MotorWorkflow $motor) {}

    public function create(Usuario $usuario): bool
    {
        return true;
    }

    public function verDetalle(Usuario $usuario, Solicitud $solicitud): bool
    {
        if ($usuario->id === $solicitud->solicitante_id) return true;

        // El contador puede consultar (solo lectura) lo que espera al lider de
        // contabilidad: oficina 'verificada' y viaticos 'revisada'.
        $clave = $solicitud->tipoSolicitud->clave;
        if ($usuario->hasRole('contador')
            && (($clave === 'OFI' && $solicitud->estado === 'verificada')
                || ($clave === 'VIA' && in_array($solicitud->estado, ['revisada', 'en_gerencia'])))) {
            return true;
        }

        // RR. HH. consulta las comisiones de viaticos que su panel ya lista:
        // desde que el lider las envia (cualquier estado salvo borrador/rechazada).
        if ($usuario->hasRole('rrhh')
            && $clave === 'VIA'
            && ! in_array($solicitud->estado, ['borrador', 'rechazada', 'cancelada'])) {
            return true;
        }

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
            in_array($solicitud->estado, ['borrador', 'devuelta']);
    }

    /**
     * RR. HH. (o el solicitante lider_area) puede anexar cotizaciones y el comentario
     * para el contador mientras la solicitud de oficina esta enviada, verificada o
     * rechazada (por si la rechazaron por falta de cotizacion y hay que reenviarla).
     */
    public function anexarCotizacion(Usuario $usuario, Solicitud $solicitud): bool
    {
        return $solicitud->tipoSolicitud->clave === 'OFI'
            && $usuario->hasAnyRole(['rrhh', 'lider_area'])
            && in_array($solicitud->estado, ['enviada', 'verificada', 'rechazada']);
    }

    /**
     * Solo el usuario que subio la cotizacion puede eliminarla o reemplazarla,
     * y solo mientras la solicitud de oficina no este cerrada.
     */
    public function gestionarCotizacion(Usuario $usuario, Solicitud $solicitud, CotizacionOficina $cotizacion): bool
    {
        return $solicitud->tipoSolicitud->clave === 'OFI'
            && $cotizacion->usuario_id === $usuario->id
            && $solicitud->estado !== 'cerrada';
    }

    /**
     * El lider de contabilidad registra abonos mientras la solicitud de oficina
     * este aprobada (en gerencia) o pendiente de cierre. Cerrada => inmutable.
     */
    public function registrarAbono(Usuario $usuario, Solicitud $solicitud): bool
    {
        return $solicitud->tipoSolicitud->clave === 'OFI'
            && $usuario->hasRole('contabilidad_lider')
            && in_array($solicitud->estado, ['aprobada', 'pendiente_cierre']);
    }

    /**
     * El contador puede trabajar la liquidacion de una comision de viaticos:
     * en 'enviada' presenta el informe por primera vez, y en 'liquidada' lo corrige
     * antes de enviarlo al lider de contabilidad.
     */
    public function editarLiquidacion(Usuario $usuario, Solicitud $solicitud): bool
    {
        return $solicitud->tipoSolicitud->clave === 'VIA'
            && $usuario->hasRole('contador')
            && in_array($solicitud->estado, ['enviada', 'liquidada']);
    }

    /**
     * Adjuntar/eliminar comprobantes de transferencia por viajero. Es mas amplio que
     * editarLiquidacion porque el lider de contabilidad tambien debe poder gestionarlos
     * cuando la comision llega a su ambito:
     * - contador: enviada, liquidada, revisada y cerrada.
     * - contabilidad_lider: revisada y cerrada.
     */
    public function gestionarComprobante(Usuario $usuario, Solicitud $solicitud): bool
    {
        if ($solicitud->tipoSolicitud->clave !== 'VIA') {
            return false;
        }
        if ($usuario->hasRole('contador')) {
            return in_array($solicitud->estado, ['enviada', 'liquidada', 'revisada', 'en_gerencia', 'cerrada']);
        }
        if ($usuario->hasRole('contabilidad_lider')) {
            return in_array($solicitud->estado, ['revisada', 'en_gerencia', 'cerrada']);
        }
        return false;
    }

    /**
     * RR. HH. marca la salida real de cada viajero de una comision de viaticos,
     * mientras la comision este activa (no en borrador, rechazada ni cancelada).
     */
    public function confirmarSalida(Usuario $usuario, Solicitud $solicitud): bool
    {
        return $solicitud->tipoSolicitud->clave === 'VIA'
            && $usuario->hasRole('rrhh')
            && ! in_array($solicitud->estado, ['borrador', 'rechazada', 'cancelada']);
    }

    /**
     * El solicitante puede cancelar su comision de viaticos en cualquier momento,
     * salvo que ya este cerrada o cancelada. Se maneja fuera del MotorWorkflow.
     */
    public function cancelar(Usuario $usuario, Solicitud $solicitud): bool
    {
        return $solicitud->tipoSolicitud->clave === 'VIA'
            && $usuario->id === $solicitud->solicitante_id
            && ! in_array($solicitud->estado, ['cerrada', 'cancelada']);
    }

    /**
     * El solicitante puede reactivar una comision cancelada, que vuelve al estado
     * que tenia antes de cancelarse (guardado en estado_previo).
     */
    public function reactivar(Usuario $usuario, Solicitud $solicitud): bool
    {
        return $solicitud->tipoSolicitud->clave === 'VIA'
            && $usuario->id === $solicitud->solicitante_id
            && $solicitud->estado === 'cancelada';
    }

    /**
     * El solicitante lider ajusta las fechas/horas de salida/regreso de cada viajero
     * de su comision de viaticos en cualquier momento, salvo cerrada o cancelada.
     */
    public function ajustar(Usuario $usuario, Solicitud $solicitud): bool
    {
        return $solicitud->tipoSolicitud->clave === 'VIA'
            && $usuario->id === $solicitud->solicitante_id
            && ! in_array($solicitud->estado, ['cerrada', 'cancelada']);
    }

    /**
     * Imprimir o enviar el formato de liquidacion de un viajero: solo para
     * comisiones de viaticos ya cerradas, y para quien pueda ver el detalle.
     */
    public function imprimirLiquidacion(Usuario $usuario, Solicitud $solicitud): bool
    {
        return $solicitud->tipoSolicitud->clave === 'VIA'
            && $solicitud->estado === 'cerrada'
            && $this->verDetalle($usuario, $solicitud);
    }
}
