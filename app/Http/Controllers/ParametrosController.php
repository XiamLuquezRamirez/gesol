<?php
namespace App\Http\Controllers;

use App\Models\{Area, Contrato, Empleados, Municipio, TarifaViatico};
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class ParametrosController extends Controller
{
    public function index()
    {
        return Inertia::render('Parametros/Index', [
            'tarifas'   => TarifaViatico::all(),
            'empleados' => Empleados::with('area:id,nombre')->orderBy('apellidos')->orderBy('nombres')->get(),
            'areas'     => Area::where('es_general', false)->orderBy('nombre')->get(['id','nombre']),
            'contratos'  => Contrato::with('municipios:id,nombre')->orderBy('descripcion')->get(),
            'municipios' => Municipio::orderBy('nombre')->get(['id','nombre']),
        ]);
    }

    public function storeTarifa(Request $request)
    {
        $data = $request->validate([
            'rubro'          => 'required|string|max:100|unique:tarifas_viaticos,rubro',
            'valor_sugerido' => 'required|numeric|min:0',
        ]);
        TarifaViatico::create($data);
        return back()->with('success', 'Tarifa creada.');
    }

    public function updateTarifa(Request $request, TarifaViatico $tarifa)
    {
        $data = $request->validate([
            'rubro'          => 'required|string|max:100|unique:tarifas_viaticos,rubro,'.$tarifa->id,
            'valor_sugerido' => 'required|numeric|min:0',
        ]);
        $tarifa->update($data);
        return back()->with('success', 'Tarifa actualizada.');
    }

    public function destroyTarifa(TarifaViatico $tarifa)
    {
        $tarifa->delete();
        return back()->with('success', 'Tarifa eliminada.');
    }

    public function storeEmpleado(Request $request)
    {
        $data = $request->validate([
            'area_id'        => ['nullable', Rule::exists('areas', 'id')->where('es_general', 0)],
            'identificacion' => 'required|string|max:20|unique:empleados,identificacion',
            'nombres'        => 'required|string|max:100',
            'apellidos'      => 'required|string|max:100',
            'email'          => 'nullable|email|max:255|unique:empleados,email',
            'telefono'       => 'nullable|string|max:20',
        ]);
        Empleados::create($data);
        return back()->with('success', 'Empleado creado.');
    }

    public function updateEmpleado(Request $request, Empleados $empleado)
    {
        $data = $request->validate([
            'area_id'        => ['nullable', Rule::exists('areas', 'id')->where('es_general', 0)],
            'identificacion' => 'required|string|max:20|unique:empleados,identificacion,'.$empleado->id,
            'nombres'        => 'required|string|max:100',
            'apellidos'      => 'required|string|max:100',
            'email'          => 'nullable|email|max:255|unique:empleados,email,'.$empleado->id,
            'telefono'       => 'nullable|string|max:20',
        ]);
        $empleado->update($data);
        return back()->with('success', 'Empleado actualizado.');
    }

    public function destroyEmpleado(Empleados $empleado)
    {
        $empleado->delete();
        return back()->with('success', 'Empleado eliminado.');
    }

    /** Reglas compartidas por crear y editar un contrato. */
    private function reglasContrato(): array
    {
        return [
            'descripcion'  => 'required|string|max:255',
            'objeto'       => 'required|string|max:2000',
            'municipios'   => 'required|array|min:1',
            'municipios.*' => 'exists:municipios,id',
        ];
    }

    public function storeContrato(Request $request)
    {
        $data = $request->validate($this->reglasContrato());
        $contrato = Contrato::create(['descripcion' => $data['descripcion'], 'objeto' => $data['objeto']]);
        $contrato->municipios()->sync($data['municipios']);
        return back()->with('success', 'Contrato creado.');
    }

    public function updateContrato(Request $request, Contrato $contrato)
    {
        $data = $request->validate($this->reglasContrato());
        $contrato->update(['descripcion' => $data['descripcion'], 'objeto' => $data['objeto']]);
        $contrato->municipios()->sync($data['municipios']);
        return back()->with('success', 'Contrato actualizado.');
    }

    public function destroyContrato(Contrato $contrato)
    {
        if ($contrato->viajeros()->exists()) {
            return back()->with('error', 'No se puede eliminar: el contrato tiene viajeros asociados.');
        }
        $contrato->delete();
        return back()->with('success', 'Contrato eliminado.');
    }
}
