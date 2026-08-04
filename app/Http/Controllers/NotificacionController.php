<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class NotificacionController extends Controller
{
    public function index()
    {
        //solo mostrar notificvaciones no leidas
        $notificaciones = auth()->user()
            ->notifications()
            ->whereNull('read_at')
            ->latest()
            ->limit(20)
            ->get()
            ->map(fn ($n) => [
                'id'         => $n->id,
                'leida'      => $n->read_at !== null,
                'creada_en'  => $n->created_at->format('Y-m-d H:i'),
                ...$n->data,
            ]);

        return response()->json([
            'notificaciones' => $notificaciones,
            'no_leidas'      => auth()->user()->unreadNotifications()->count(),
        ]);
    }

    public function marcarLeida(string $id)
    {
        $notificacion = auth()->user()->notifications()->findOrFail($id);
        $notificacion->markAsRead();

        return response()->json(['ok' => true]);
    }

    public function marcarTodasLeidas()
    {
        auth()->user()->unreadNotifications->markAsRead();

        return response()->json(['ok' => true]);
    }
}
