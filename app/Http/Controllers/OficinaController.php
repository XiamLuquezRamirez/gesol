<?php
namespace App\Http\Controllers;

use App\Http\Requests\GuardarSolicitudOficinaRequest;
use App\Models\{Area, CotizacionOficina, Empleados, Solicitud, SolicitudOficina, ItemOficina, TipoSolicitud, Usuario};
use App\Services\MotorWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class OficinaController extends Controller
{
    public function create()
    {
        $this->authorize('create', Solicitud::class);
        return Inertia::render('Oficina/Crear', [
            'areas'     => Area::orderBy('nombre')->get(['id','nombre','es_general']),
            'usuarios'  => Usuario::orderBy('name')->get(['id','name']),
            'empleados' => Empleados::orderBy('nombres')->get(['id','nombres','apellidos','identificacion','area_id']),
            // ¿El usuario puede, ademas de crear, enviar de una vez la solicitud a RR. HH.?
            // (la transicion 'enviar' desde 'borrador' pide el rol lider_area.)
            'puedeEnviar' => $this->puedeEnviarOficina(auth()->user()),
        ]);
    }

    /** ¿El usuario tiene un rol habilitado para la transicion 'enviar' de una solicitud OFI en borrador? */
    private function puedeEnviarOficina(Usuario $usuario): bool
    {
        $tipo = TipoSolicitud::where('clave', 'OFI')->first();
        if (! $tipo) return false;
        $roles = $usuario->getRoleNames()->toArray();
        return collect($tipo->transiciones)
            ->contains(fn ($t) => $t['origen'] === 'borrador'
                && $t['accion'] === 'enviar'
                && ! empty(array_intersect($t['roles'], $roles)));
    }

    public function store(GuardarSolicitudOficinaRequest $request)
    {
        $this->authorize('create', Solicitud::class);
        $tipo = TipoSolicitud::where('clave','OFI')->firstOrFail();

        $solicitud = DB::transaction(function () use ($request, $tipo) {
            $cabecera = SolicitudOficina::create([
                'beneficiario' => '',
                'urgencia'     => $request->urgencia,
                'justificacion'=> $request->justificacion,
            ]);
            $cabecera->beneficiarios()->sync($this->beneficiariosASincronizar($request));

            $solicitud = Solicitud::create([
                'tipo_solicitud_id' => $tipo->id,
                'solicitante_id'    => auth()->id(),
                'area_id'           => $request->area_id,
                'solicitable_type'  => SolicitudOficina::class,
                'solicitable_id'    => $cabecera->id,
                'estado'            => $tipo->estado_inicial,
                'radicado'          => Solicitud::generarRadicado($tipo),
            ]);

            foreach ($request->items as $item) {
                ItemOficina::create(array_merge($this->normalizarItem($item), [
                    'solicitud_oficina_id' => $cabecera->id,
                    'subtotal'             => 0,
                ]));
            }

            return $solicitud;
        });

        // Envio inmediato a RR. HH. si el usuario lo pidio ("Crear y enviar").
        // Reusa el motor (borrador -> enviada), que valida rol y notifica a RR. HH.
        if ($request->boolean('enviar')) {
            $motor = app(MotorWorkflow::class);
            if ($motor->puede($solicitud, 'enviar', auth()->user())) {
                $motor->aplicarTransicion($solicitud, 'enviar', auth()->user());
                return redirect()->route('solicitudes.show', $solicitud)
                    ->with('success', 'Solicitud creada y enviada a RR. HH.: '.$solicitud->radicado);
            }
            return redirect()->route('solicitudes.show', $solicitud)
                ->with('success', 'Solicitud creada: '.$solicitud->radicado.'. Quedó en borrador (tu rol no puede enviarla a RR. HH.).');
        }

        return redirect()->route('solicitudes.show', $solicitud)
            ->with('success', 'Solicitud creada: '.$solicitud->radicado);
    }

    public function edit(Solicitud $solicitud)
    {
        $this->authorize('editar', $solicitud);
        $solicitud->load('solicitable.items', 'solicitable.beneficiarios');
        return Inertia::render('Oficina/Crear', [
            'solicitud' => $solicitud,
            'areas'     => Area::orderBy('nombre')->get(['id','nombre','es_general']),
            'usuarios'  => Usuario::orderBy('name')->get(['id','name']),
            'empleados' => Empleados::orderBy('nombres')->get(['id','nombres','apellidos','identificacion','area_id']),
            'editar'    => true,
        ]);
    }

    public function update(GuardarSolicitudOficinaRequest $request, Solicitud $solicitud)
    {
        $this->authorize('editar', $solicitud);
        $cabecera = $solicitud->solicitable;

        DB::transaction(function () use ($request, $cabecera) {
            $cabecera->update([
                'beneficiario'  => '',
                'urgencia'      => $request->urgencia,
                'justificacion' => $request->justificacion,
            ]);
            $cabecera->beneficiarios()->sync($this->beneficiariosASincronizar($request));
            $cabecera->items()->delete();
            foreach ($request->items as $item) {
                ItemOficina::create(array_merge($this->normalizarItem($item), [
                    'solicitud_oficina_id' => $cabecera->id,
                    'subtotal'             => 0,
                ]));
            }
        });

        return redirect()->route('solicitudes.show', $solicitud)
            ->with('success', 'Solicitud actualizada.');
    }

    /**
     * Normaliza un item del formulario: el costo estimado vacio se guarda como null.
     */
    private function normalizarItem(array $item): array
    {
        $costo = $item['costo_estimado'] ?? null;
        $item['costo_estimado'] = ($costo === '' || $costo === null) ? null : $costo;
        return $item;
    }

    /**
     * En un area institucional (General) la solicitud no lleva beneficiarios.
     */
    private function beneficiariosASincronizar($request): array
    {
        if (Area::esGeneral($request->area_id)) {
            return [];
        }
        return (array) $request->beneficiarios;
    }

    /**
     * RR. HH. o el solicitante anexa cotizaciones (acumulativas) y el comentario
     * para el contador. Cada subida agrega archivos; no reemplaza los anteriores.
     */
    public function anexarCotizacion(Request $request, Solicitud $solicitud)
    {
        $this->authorize('anexarCotizacion', $solicitud);

        $request->validate([
            'cotizaciones'        => 'nullable|array',
            'cotizaciones.*'      => 'file|mimes:pdf,jpg,jpeg,png|max:5120', // 5 MB c/u
            'comentario_contador' => 'nullable|string|max:2000',
        ], [], [
            'cotizaciones.*'      => 'cotización',
            'comentario_contador' => 'comentario',
        ]);

        $cabecera = $solicitud->solicitable;

        foreach ((array) $request->file('cotizaciones') as $archivo) {
            $cabecera->cotizaciones()->create([
                'usuario_id'      => auth()->id(),
                'path'            => $archivo->store('cotizaciones', 'local'),
                'nombre_original' => $archivo->getClientOriginalName(),
            ]);
        }

        if ($request->filled('comentario_contador')) {
            $cabecera->update(['comentario_contador' => $request->comentario_contador]);
        }

        return back()->with('success', 'Cotización y comentario guardados.');
    }

    /**
     * Elimina una cotizacion individual (solo su autor, mientras no este cerrada).
     */
    public function eliminarCotizacion(Solicitud $solicitud, CotizacionOficina $cotizacion)
    {
        abort_unless($cotizacion->solicitud_oficina_id === $solicitud->solicitable->id, 404);
        $this->authorize('gestionarCotizacion', [$solicitud, $cotizacion]);

        Storage::disk('local')->delete($cotizacion->path);
        $cotizacion->delete();

        return back()->with('success', 'Cotización eliminada.');
    }

    /**
     * Reemplaza el archivo de una cotizacion. Solo su autor, mientras no este cerrada.
     */
    public function actualizarCotizacion(Request $request, Solicitud $solicitud, CotizacionOficina $cotizacion)
    {
        abort_unless($cotizacion->solicitud_oficina_id === $solicitud->solicitable->id, 404);
        $this->authorize('gestionarCotizacion', [$solicitud, $cotizacion]);

        $request->validate([
            'cotizacion' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [], ['cotizacion' => 'cotización']);

        Storage::disk('local')->delete($cotizacion->path);
        $cotizacion->update([
            'path'            => $request->file('cotizacion')->store('cotizaciones', 'local'),
            'nombre_original' => $request->file('cotizacion')->getClientOriginalName(),
        ]);

        return back()->with('success', 'Cotización actualizada.');
    }

    /**
     * Descarga controlada de una cotizacion: solo quien puede ver la solicitud.
     */
    public function descargarCotizacion(Solicitud $solicitud, CotizacionOficina $cotizacion)
    {
        $this->authorize('verDetalle', $solicitud);
        abort_unless($cotizacion->solicitud_oficina_id === $solicitud->solicitable->id, 404);
        abort_unless(Storage::disk('local')->exists($cotizacion->path), 404);

        return Storage::disk('local')->download($cotizacion->path, $cotizacion->nombre_original);
    }
}
