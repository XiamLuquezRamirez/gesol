<?php

namespace App\Http\Controllers;

use App\Models\AjusteComision;
use App\Models\Solicitud;
use App\Services\MotorWorkflow;
use Inertia\Inertia;

class InicioController extends Controller
{
    public function __construct(private MotorWorkflow $motor) {}

    public function index()
    {
        $usuario = auth()->user();

        $misActivas = Solicitud::where('solicitante_id', $usuario->id)
            ->whereNotIn('estado', ['cerrada', 'rechazada'])
            ->count();

        $completadas = Solicitud::where('solicitante_id', $usuario->id)
            ->where('estado', 'cerrada')
            ->whereMonth('created_at', now()->month)
            ->whereYear('created_at', now()->year)
            ->count();

        // Comisiones con un ajuste (reajuste post-cierre) pendiente relevante al rol
        // del usuario. El reajuste NO es una transicion del motor y ademas vive sobre
        // comisiones ya 'cerrada', por eso se cuentan aparte del filtro por estado.
        $idsConAjuste = AjusteComision::solicitudesConPendientePara($usuario)->flip();

        $pendientes = Solicitud::with('tipoSolicitud')
            ->where(fn($q) => $q
                ->whereNotIn('estado', ['cerrada', 'rechazada', 'borrador'])
                ->orWhereIn('id', $idsConAjuste->keys()->all()))
            ->get()
            ->filter(fn($s) => !empty($this->motor->accionesDisponibles($s, $usuario))
                || $idsConAjuste->has($s->id))
            ->count();

        $recientes = Solicitud::with('tipoSolicitud')
            ->where('solicitante_id', $usuario->id)
            ->latest()
            ->limit(5)
            ->get()
            ->map(fn($s) => [
                'id'      => $s->id,
                'radicado'=> $s->radicado,
                'estado'  => $s->estado,
                'tipo'    => $s->tipoSolicitud->nombre,
                'fecha'   => $s->created_at->format('d/m/Y'),
            ]);

        return Inertia::render('Inicio/Index', [
            'stats' => [
                'mis_solicitudes' => $misActivas,
                'pendientes'      => $pendientes,
                'completadas'     => $completadas,
            ],
            'recientes' => $recientes,
        ]);
    }
}
