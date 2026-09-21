<?php
namespace App\Http\Controllers;

use App\Http\Requests\GuardarSolicitudObraRequest;
use App\Models\{CotizacionObra, ItemObra, Solicitud, SolicitudObra, TipoSolicitud};
use App\Services\MotorWorkflow;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ObraController extends Controller
{
    public function __construct(private MotorWorkflow $motor) {}

    public function create()
    {
        $this->authorize('crearObra', Solicitud::class);
        return Inertia::render('Obra/Crear', [
            'nombreSugerido' => auth()->user()->name,
        ]);
    }

    public function store(GuardarSolicitudObraRequest $request)
    {
        $this->authorize('crearObra', Solicitud::class);
        $tipo = TipoSolicitud::where('clave', 'OBR')->firstOrFail();

        $solicitud = DB::transaction(function () use ($request, $tipo) {
            $cab = SolicitudObra::create($request->only([
                'nombre_solicitante','fecha_solicitud','fecha_entrega','observacion',
            ]));
            foreach ($request->input('items', []) as $it) {
                ItemObra::create([
                    'solicitud_obra_id' => $cab->id,
                    'especificacion' => $it['especificacion'],
                    'unidad' => $it['unidad'] ?? null,
                    'cantidad' => $it['cantidad'],
                    'sede' => $it['sede'] ?? null,
                ]);
            }
            if ($request->hasFile('cotizacion')) {
                $path = $request->file('cotizacion')->store('cotizaciones_obra', 'local');
                CotizacionObra::create([
                    'solicitud_obra_id' => $cab->id, 'tipo' => 'cotizacion',
                    'path' => $path, 'nombre_original' => $request->file('cotizacion')->getClientOriginalName(),
                    'usuario_id' => auth()->id(),
                ]);
            }
            return Solicitud::create([
                'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => auth()->id(),
                'solicitable_type' => SolicitudObra::class, 'solicitable_id' => $cab->id,
                'estado' => 'borrador', 'radicado' => Solicitud::generarRadicado($tipo),
            ]);
        });

        if ($request->boolean('enviar') && $this->motor->puede($solicitud, 'enviar', auth()->user())) {
            $this->motor->aplicarTransicion($solicitud, 'enviar', auth()->user());
        }

        return redirect()->route('solicitudes.show', $solicitud)
            ->with('success', $request->boolean('enviar') ? 'Solicitud enviada a RR. HH.' : 'Borrador guardado.');
    }
}
