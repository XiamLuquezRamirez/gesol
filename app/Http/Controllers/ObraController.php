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

    public function cotizar(\Illuminate\Http\Request $request, Solicitud $solicitud)
    {
        $this->authorize('cotizarObra', $solicitud);
        $request->validate([
            'total_a_pagar' => 'nullable|numeric|min:0',
            'items' => 'array',
            'items.*.id' => 'required|integer',
            'items.*.valor_unitario' => 'nullable|numeric|min:0',
        ]);
        $cab = $solicitud->solicitable;
        DB::transaction(function () use ($request, $solicitud, $cab) {
            if ($request->filled('total_a_pagar')) {
                $cab->update(['total_a_pagar' => $request->total_a_pagar]);
            }
            foreach ($request->input('items', []) as $it) {
                $item = ItemObra::where('id', $it['id'])
                    ->where('solicitud_obra_id', $cab->id)->first();
                if ($item) {
                    $item->valor_unitario = $it['valor_unitario'] ?? null;
                    $item->save(); // dispara saving() -> recalcula subtotal
                }
            }
            if ($solicitud->estado === 'enviada' && $this->motor->puede($solicitud, 'cotizar', auth()->user())) {
                $this->motor->aplicarTransicion($solicitud, 'cotizar', auth()->user());
            }
        });
        return back()->with('success', 'Cotización guardada.');
    }

    public function relacionarContrato(\Illuminate\Http\Request $request, Solicitud $solicitud)
    {
        $this->authorize('cotizarObra', $solicitud);
        $request->validate(['contrato_id' => 'required|exists:contratos,id']);
        $solicitud->solicitable->update(['contrato_id' => $request->contrato_id]);
        return back()->with('success', 'Contrato relacionado.');
    }

    public function anexarDocumento(\Illuminate\Http\Request $request, Solicitud $solicitud)
    {
        $this->authorize('cotizarObra', $solicitud);
        $request->validate(['documento' => 'required|file|mimes:pdf,jpg,jpeg,png,xlsx,xls|max:5120']);
        $path = $request->file('documento')->store('cotizaciones_obra', 'local');
        CotizacionObra::create([
            'solicitud_obra_id' => $solicitud->solicitable_id, 'tipo' => 'documento',
            'path' => $path, 'nombre_original' => $request->file('documento')->getClientOriginalName(),
            'usuario_id' => auth()->id(),
        ]);
        return back()->with('success', 'Documento anexado.');
    }
}
