<?php
namespace App\Http\Controllers;

use App\Http\Requests\GuardarArchivoViajeroRequest;
use App\Models\ArchivoViajero;
use App\Models\Solicitud;
use App\Models\ViajeroComision;
use Illuminate\Support\Facades\Storage;

class ArchivoViajeroController extends Controller
{
    public function store(GuardarArchivoViajeroRequest $request, Solicitud $solicitud, ViajeroComision $viajero)
    {
        $this->authorize('editarLiquidacion', $solicitud);
        abort_unless($viajero->solicitud_viaticos_id === $solicitud->solicitable_id, 404);

        foreach ($request->file('archivos') as $archivo) {
            $path = $archivo->store('archivos_viajero', 'local');
            $viajero->archivos()->create([
                'tipo'       => $request->tipo,
                'path'       => $path,
                'nombre'     => $archivo->getClientOriginalName(),
                'usuario_id' => auth()->id(),
            ]);
        }

        return back()->with('success', 'Archivo(s) adjuntado(s).');
    }

    public function descargar(Solicitud $solicitud, ViajeroComision $viajero, ArchivoViajero $archivo)
    {
        $this->authorize('verDetalle', $solicitud);
        abort_unless($viajero->solicitud_viaticos_id === $solicitud->solicitable_id, 404);
        abort_unless($archivo->viajero_comision_id === $viajero->id, 404);
        abort_unless(Storage::disk('local')->exists($archivo->path), 404);

        return Storage::disk('local')->download($archivo->path, $archivo->nombre);
    }

    public function destroy(Solicitud $solicitud, ViajeroComision $viajero, ArchivoViajero $archivo)
    {
        $this->authorize('editarLiquidacion', $solicitud);
        abort_unless($viajero->solicitud_viaticos_id === $solicitud->solicitable_id, 404);
        abort_unless($archivo->viajero_comision_id === $viajero->id, 404);

        Storage::disk('local')->delete($archivo->path);
        $archivo->delete();

        return back()->with('success', 'Archivo eliminado.');
    }
}
