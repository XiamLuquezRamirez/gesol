<?php
namespace Database\Seeders;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuariosDemoSeeder extends Seeder
{
    public function run(): void
    {
        $usuarios = [
            ['name'=>'Líder Área Demo',         'email'=>'lider.area@demo.test',         'roles'=>['lider_area']],
            ['name'=>'Líder Comité Demo',        'email'=>'lider.comite@demo.test',       'roles'=>['lider_comite']],
            ['name'=>'RRHH Demo',               'email'=>'rrhh@demo.test',               'roles'=>['rrhh']],
            ['name'=>'Contabilidad Líder Demo', 'email'=>'contabilidad.lider@demo.test', 'roles'=>['contabilidad_lider']],
            ['name'=>'Contador Demo',           'email'=>'contador@demo.test',           'roles'=>['contador']],
        ];

        foreach ($usuarios as $data) {
            $roles = $data['roles'];
            unset($data['roles']);
            $usuario = Usuario::firstOrCreate(
                ['email' => $data['email']],
                array_merge($data, ['password' => Hash::make('password')])
            );
            $usuario->syncRoles($roles);
        }
    }
}
