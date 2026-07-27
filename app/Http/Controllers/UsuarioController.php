<?php
namespace App\Http\Controllers;

use App\Http\Requests\{ActualizarUsuarioRequest, GuardarUsuarioRequest};
use App\Models\Usuario;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class UsuarioController extends Controller
{
    public function index()
    {
        return Inertia::render('Usuarios/Index', [
            'usuarios' => Usuario::with('roles:id,name')->orderBy('name')->get(['id', 'name', 'email']),
            'roles'    => Role::orderBy('name')->pluck('name'),
        ]);
    }

    public function store(GuardarUsuarioRequest $request)
    {
        $data = $request->validated();

        $usuario = Usuario::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
        ]);
        $usuario->syncRoles($data['roles']);

        return back()->with('success', 'Usuario creado.');
    }

    public function update(ActualizarUsuarioRequest $request, Usuario $usuario)
    {
        $data = $request->validated();

        // Guarda anti-auto-bloqueo: impedir que el ultimo admin se quite su propio rol admin.
        $seQuitaAdmin = $usuario->hasRole('admin') && !in_array('admin', $data['roles'], true);
        if ($seQuitaAdmin
            && $usuario->id === $request->user()->id
            && Usuario::role('admin')->count() === 1
        ) {
            return back()->with('error', 'No puedes quitarte el rol admin: eres el unico administrador.');
        }

        $usuario->update([
            'name'  => $data['name'],
            'email' => $data['email'],
        ]);
        if (!empty($data['password'])) {
            $usuario->update(['password' => Hash::make($data['password'])]);
        }
        $usuario->syncRoles($data['roles']);

        return back()->with('success', 'Usuario actualizado.');
    }
}
