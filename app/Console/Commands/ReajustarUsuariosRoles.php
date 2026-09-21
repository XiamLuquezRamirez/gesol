<?php

namespace App\Console\Commands;

use App\Models\{Empleados, Usuario};
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Reajusta usuarios y roles a partir de una lista (identificacion => rol):
 *   1. Limpia las solicitudes (delega en solicitudes:limpiar) para soltar las FK
 *      hacia usuarios (solicitante_id, transiciones, abonos, ajustes, notificaciones).
 *   2. Borra todos los usuarios EXCEPTO el admin.
 *   3. Cambia el email del admin al indicado.
 *   4. Crea un usuario por cada identificacion de la lista tomando nombre y email
 *      del empleado correspondiente; la contrasena es su identificacion; se le
 *      asigna el rol mapeado.
 *
 * Todo el reajuste de usuarios corre en una transaccion. La lista y el mapeo de
 * roles viven aqui para que el comando sea idempotente y repetible (tambien en prod).
 */
class ReajustarUsuariosRoles extends Command
{
    protected $signature = 'gesol:reajustar-usuarios
        {--admin-email=administracion@ingeer.co : Nuevo email del usuario admin}
        {--dry-run : Solo mostrar el plan, sin escribir}';

    protected $description = 'Limpia solicitudes, deja solo el admin y crea usuarios desde empleados con su rol';

    /** Lista (identificacion => etiqueta de rol de la lista recibida). */
    private array $lista = [
        '1067593353' => 'lider de area',
        '40878945'   => 'lider de area',
        '77097205'   => 'lider de area',
        '1065663256' => 'lider de area',
        '26996399'   => 'lider de area',
        '1120748603' => 'lider de rrhh',
        '1003316134' => 'lider de area',
        '1065840539' => 'contador',
        '1065834622' => 'lider contable',
        '1065836871' => 'lider de area',
    ];

    /** Mapeo de las etiquetas de la lista a los roles internos del sistema. */
    private array $mapaRoles = [
        'lider de area'  => 'lider_area',
        'lider de rrhh'  => 'rrhh',
        'contador'       => 'contador',
        'lider contable' => 'contabilidad_lider',
    ];

    public function handle(): int
    {
        $adminEmail = $this->option('admin-email');
        $dry = (bool) $this->option('dry-run');

        // Resolver el plan (empleado + rol) validando ANTES de tocar nada.
        $plan = [];
        $errores = [];
        foreach ($this->lista as $identificacion => $etiqueta) {
            $empleado = Empleados::where('identificacion', $identificacion)->first();
            $rol = $this->mapaRoles[$etiqueta] ?? null;

            if (! $empleado) {
                $errores[] = "Identificacion {$identificacion}: no existe como empleado.";
                continue;
            }
            if (! $rol) {
                $errores[] = "Identificacion {$identificacion}: rol '{$etiqueta}' no reconocido.";
                continue;
            }
            if (empty($empleado->email)) {
                $errores[] = "Identificacion {$identificacion} ({$empleado->nombres}): sin email; no se puede crear usuario.";
                continue;
            }

            $plan[] = [
                'identificacion' => (string) $identificacion,
                'nombre'         => trim($empleado->nombres.' '.$empleado->apellidos),
                'email'          => $empleado->email,
                'rol'            => $rol,
            ];
        }

        if (! empty($errores)) {
            $this->error('No se puede continuar. Corrige estos problemas:');
            foreach ($errores as $e) {
                $this->line('  • '.$e);
            }
            return self::FAILURE;
        }

        $admin = Usuario::role('admin')->orderBy('id')->first();
        if (! $admin) {
            $this->error('No hay ningun usuario con rol admin. Aborto para no dejar el sistema sin acceso.');
            return self::FAILURE;
        }

        // Mostrar el plan.
        $this->info('Se conservara el admin (y se le cambiara el email):');
        $this->line("  • #{$admin->id} {$admin->name} — {$admin->email}  →  {$adminEmail}");
        $this->newLine();
        $aBorrar = Usuario::where('id', '!=', $admin->id)->count();
        $this->warn("Se ELIMINARAN {$aBorrar} usuario(s) (todos menos el admin) y se limpiaran las solicitudes.");
        $this->newLine();
        $this->info('Se crearan '.count($plan).' usuario(s) desde empleados:');
        foreach ($plan as $p) {
            $this->line(sprintf('  • %-34s | %-28s | %s', $p['nombre'], $p['email'], $p['rol']));
        }
        $this->newLine();

        if ($dry) {
            $this->info('DRY-RUN: no se escribio nada. Quita --dry-run para aplicar.');
            return self::SUCCESS;
        }

        // 1) Limpiar solicitudes (suelta las FK hacia usuarios). Usa el comando ya probado.
        $this->call('solicitudes:limpiar', ['--force' => true]);

        // 2-4) Reajuste de usuarios en una transaccion.
        DB::transaction(function () use ($admin, $adminEmail, $plan) {
            // Borrar todos menos el admin. Con las solicitudes limpias, ningun usuario
            // queda referenciado; el pivote de roles se limpia por cascada.
            Usuario::where('id', '!=', $admin->id)->get()->each(fn ($u) => $u->delete());

            // Cambiar email del admin.
            $admin->update(['email' => $adminEmail]);

            // Crear los usuarios desde empleados.
            foreach ($plan as $p) {
                $usuario = Usuario::create([
                    'name'     => $p['nombre'],
                    'email'    => $p['email'],
                    'password' => Hash::make($p['identificacion']),
                ]);
                $usuario->syncRoles([$p['rol']]);
            }
        });

        $this->newLine();
        $this->info('Reajuste completado. Usuarios actuales:');
        Usuario::with('roles')->orderBy('id')->get()->each(function ($u) {
            $this->line(sprintf('  • #%d %-34s | %-28s | %s', $u->id, $u->name, $u->email, $u->getRoleNames()->join(',')));
        });

        return self::SUCCESS;
    }
}
