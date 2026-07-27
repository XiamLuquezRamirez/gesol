<?php
namespace Tests\Feature;

use App\Models\Usuario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GestionUsuariosTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $admin;
    private Usuario $noAdmin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin   = Usuario::where('email', 'admin@demo.test')->firstOrFail();
        $this->noAdmin = Usuario::where('email', 'lider.area@demo.test')->firstOrFail();
    }

    public function test_admin_puede_crear_usuario_con_roles(): void
    {
        $respuesta = $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'name'                  => 'Nuevo Usuario',
            'email'                 => 'nuevo@demo.test',
            'password'              => 'clave12345',
            'password_confirmation' => 'clave12345',
            'roles'                 => ['rrhh', 'contador'],
        ]);

        $respuesta->assertRedirect();
        $usuario = Usuario::where('email', 'nuevo@demo.test')->firstOrFail();
        $this->assertTrue($usuario->hasRole('rrhh'));
        $this->assertTrue($usuario->hasRole('contador'));
    }

    public function test_admin_puede_cambiar_roles_de_usuario(): void
    {
        $this->actingAs($this->admin)->put(route('usuarios.update', $this->noAdmin), [
            'name'  => $this->noAdmin->name,
            'email' => $this->noAdmin->email,
            'roles' => ['contabilidad_lider'],
        ])->assertRedirect();

        $this->noAdmin->refresh();
        $this->assertTrue($this->noAdmin->hasRole('contabilidad_lider'));
        $this->assertFalse($this->noAdmin->hasRole('lider_area')); // syncRoles reemplaza, no acumula
    }

    public function test_no_admin_recibe_403_en_gestion_usuarios(): void
    {
        $this->actingAs($this->noAdmin)->get(route('usuarios.index'))->assertForbidden();
    }

    public function test_email_duplicado_es_rechazado(): void
    {
        $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'name'                  => 'Duplicado',
            'email'                 => 'lider.area@demo.test', // ya existe
            'password'              => 'clave12345',
            'password_confirmation' => 'clave12345',
            'roles'                 => ['rrhh'],
        ])->assertSessionHasErrors('email');
    }

    public function test_rol_inexistente_es_rechazado(): void
    {
        $this->actingAs($this->admin)->post(route('usuarios.store'), [
            'name'                  => 'Con Rol Malo',
            'email'                 => 'rolmalo@demo.test',
            'password'              => 'clave12345',
            'password_confirmation' => 'clave12345',
            'roles'                 => ['superusuario_inexistente'],
        ])->assertSessionHasErrors('roles.0');
    }

    public function test_ultimo_admin_no_puede_auto_degradarse(): void
    {
        // admin@demo.test es el unico con rol admin tras el seed.
        $this->actingAs($this->admin)->put(route('usuarios.update', $this->admin), [
            'name'  => $this->admin->name,
            'email' => $this->admin->email,
            'roles' => ['rrhh'], // intenta quitarse admin
        ]);

        $this->admin->refresh();
        $this->assertTrue($this->admin->hasRole('admin'), 'El ultimo admin no debe poder quitarse el rol.');
    }

    public function test_admin_puede_degradarse_si_hay_otro_admin(): void
    {
        // Promover a un segundo admin.
        $this->noAdmin->syncRoles(['admin']);

        $this->actingAs($this->admin)->put(route('usuarios.update', $this->admin), [
            'name'  => $this->admin->name,
            'email' => $this->admin->email,
            'roles' => ['rrhh'],
        ])->assertRedirect();

        $this->admin->refresh();
        $this->assertFalse($this->admin->hasRole('admin'), 'Con otro admin presente, debe poder quitarse el rol.');
    }
}
