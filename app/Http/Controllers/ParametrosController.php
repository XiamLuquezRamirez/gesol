<?php
namespace App\Http\Controllers;

use App\Models\{Empleados, TarifaViatico};
use Illuminate\Http\Request;
use Inertia\Inertia;

class ParametrosController extends Controller
{
    public function index()
    {
        return Inertia::render('Parametros/Index', [
            'tarifas'   => TarifaViatico::all(),
            'empleados' => Empleados::orderBy('apellidos')->orderBy('nombres')->get(),
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
}
