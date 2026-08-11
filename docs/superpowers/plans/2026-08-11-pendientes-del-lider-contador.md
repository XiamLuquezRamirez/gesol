# Pestaña "Pendientes del líder" para el contador — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Dar al rol `contador` una pestaña de solo lectura ("Pendientes del líder") con las solicitudes que esperan acción del líder de contabilidad (oficina `verificada`, viáticos `revisada`), para que el contador dé seguimiento y el proceso no se demore.

**Architecture:** Se amplía `SolicitudPolicy::verDetalle` para que el contador pueda abrir esos detalles en solo lectura (sin ganar transiciones, ya que el motor filtra acciones por el campo `roles`). Se añade un tab `pendientes_lider` en `SolicitudController::index` (solo devuelve resultados al contador). El frontend añade la pestaña condicionada al rol.

**Tech Stack:** PHP 8.2, Laravel 10.50, Inertia 0.6, React 18, spatie/laravel-permission 6, PHPUnit + SQLite `:memory:`.

**Spec:** [docs/superpowers/specs/2026-08-11-pendientes-del-lider-contador-design.md](../specs/2026-08-11-pendientes-del-lider-contador-design.md)

---

## Estructura de archivos

**Crear:**
- `tests/Feature/PendientesLiderContadorTest.php` — cubre policy + tab.

**Modificar:**
- `app/Policies/SolicitudPolicy.php` — `verDetalle` permite al contador ver OFI `verificada` / VIA `revisada`.
- `app/Http/Controllers/SolicitudController.php` — rama `pendientes_lider` en `index()`.
- `resources/js/Pages/Solicitudes/Index.jsx` — pestaña condicional + empty state.

---

# FASE 1 — Permiso de solo lectura

## Task 1: Ampliar `verDetalle` para el contador

**Files:**
- Modify: `app/Policies/SolicitudPolicy.php`
- Test: `tests/Feature/PendientesLiderContadorTest.php`

- [ ] **Step 1: Escribir el test de la policy**

Crear `tests/Feature/PendientesLiderContadorTest.php`:

```php
<?php
namespace Tests\Feature;

use App\Models\{Area, Empleados, ItemOficina, Solicitud, SolicitudOficina, SolicitudViaticos, TipoSolicitud, Usuario, ViajeroComision};
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PendientesLiderContadorTest extends TestCase
{
    use RefreshDatabase;

    private MotorWorkflow $motor;
    private Usuario $liderArea;
    private Usuario $liderComite;
    private Usuario $rrhh;
    private Usuario $contador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->motor       = app(MotorWorkflow::class);
        $this->liderArea   = Usuario::where('email', 'lider.area@demo.test')->firstOrFail();
        $this->liderComite = Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();
        $this->rrhh        = Usuario::where('email', 'rrhh@demo.test')->firstOrFail();
        $this->contador    = Usuario::where('email', 'contador@demo.test')->firstOrFail();
    }

    /** Crea una solicitud de oficina en estado 'verificada'. */
    private function oficinaVerificada(): Solicitud
    {
        $tipo = TipoSolicitud::where('clave', 'OFI')->firstOrFail();
        $cab  = SolicitudOficina::create(['beneficiario' => '', 'urgencia' => 'media', 'justificacion' => 'x']);
        ItemOficina::create([
            'solicitud_oficina_id' => $cab->id, 'nombre' => 'Mouse',
            'categoria' => 'producto', 'cantidad' => 1, 'costo_estimado' => 1000, 'subtotal' => 1000,
        ]);
        $s = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $this->liderArea->id, 'area_id' => Area::first()->id,
            'solicitable_type' => SolicitudOficina::class, 'solicitable_id' => $cab->id, 'estado' => 'borrador',
            'radicado' => Solicitud::generarRadicado($tipo),
        ]);
        $this->motor->aplicarTransicion($s, 'enviar', $this->liderArea);
        $this->motor->aplicarTransicion($s->fresh(), 'verificar', $this->rrhh);
        return $s->fresh();
    }

    /** Crea una comision de viaticos en estado 'revisada'. */
    private function viaticosRevisada(): Solicitud
    {
        $tipo = TipoSolicitud::where('clave', 'VIA')->firstOrFail();
        $cab  = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => 'Medellín', 'observacion' => 'x']);
        ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => Empleados::first()->id,
            'motivo' => 'x', 'fecha_salida' => '2026-08-10', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-12', 'hora_regreso' => '17:00', 'tipo_pago' => 'efectivo',
        ]);
        $s = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $this->liderComite->id,
            'solicitable_type' => SolicitudViaticos::class, 'solicitable_id' => $cab->id, 'estado' => 'borrador',
            'radicado' => Solicitud::generarRadicado($tipo),
        ]);
        $this->motor->aplicarTransicion($s, 'enviar', $this->liderComite);
        $this->motor->aplicarTransicion($s->fresh(), 'liquidar', $this->contador);
        $this->motor->aplicarTransicion($s->fresh(), 'enviar_revision', $this->contador);
        return $s->fresh();
    }

    public function test_contador_puede_ver_detalle_de_oficina_verificada(): void
    {
        $s = $this->oficinaVerificada();
        $this->assertEquals('verificada', $s->estado);
        $this->assertTrue($this->contador->can('verDetalle', $s));

        $this->actingAs($this->contador)->get(route('solicitudes.show', $s))->assertOk();
    }

    public function test_contador_puede_ver_detalle_de_viaticos_revisada(): void
    {
        $s = $this->viaticosRevisada();
        $this->assertEquals('revisada', $s->estado);
        $this->assertTrue($this->contador->can('verDetalle', $s));
    }

    public function test_contador_no_gana_acciones_sobre_oficina_verificada(): void
    {
        $s = $this->oficinaVerificada();
        // Solo lectura: el motor no ofrece transiciones al contador en ese estado.
        $this->assertEmpty($this->motor->accionesDisponibles($s, $this->contador));
    }
}
```

- [ ] **Step 2: Ejecutar el test (debe fallar)**

Run: `php artisan test --filter=PendientesLiderContadorTest`
Expected: FAIL — `test_contador_puede_ver_detalle_de_oficina_verificada` da 403 / `can('verDetalle')` es false, porque el contador aún no está autorizado. (Los tests de viáticos y de "no gana acciones" pueden pasar ya; el de oficina falla.)

- [ ] **Step 3: Ampliar `verDetalle`**

En `app/Policies/SolicitudPolicy.php`, reemplazar el método `verDetalle` (líneas 17-24) por:

```php
    public function verDetalle(Usuario $usuario, Solicitud $solicitud): bool
    {
        if ($usuario->id === $solicitud->solicitante_id) return true;

        // El contador puede consultar (solo lectura) lo que espera al lider de
        // contabilidad: oficina 'verificada' y viaticos 'revisada'.
        $clave = $solicitud->tipoSolicitud->clave;
        if ($usuario->hasRole('contador')
            && (($clave === 'OFI' && $solicitud->estado === 'verificada')
                || ($clave === 'VIA' && $solicitud->estado === 'revisada'))) {
            return true;
        }

        $rolesUsuario = $usuario->getRoleNames()->toArray();
        return collect($solicitud->tipoSolicitud->transiciones)
            ->pluck('roles')->flatten()->unique()
            ->intersect($rolesUsuario)->isNotEmpty();
    }
```

- [ ] **Step 4: Ejecutar el test (debe pasar)**

Run: `php artisan test --filter=PendientesLiderContadorTest`
Expected: PASS (los 3 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Policies/SolicitudPolicy.php tests/Feature/PendientesLiderContadorTest.php
git commit -m "feat(contador): permitir ver en solo lectura lo pendiente del lider de contabilidad"
```

---

# FASE 2 — Cola del contador (backend)

## Task 2: Tab `pendientes_lider` en `SolicitudController::index`

**Files:**
- Modify: `app/Http/Controllers/SolicitudController.php`
- Test: `tests/Feature/PendientesLiderContadorTest.php`

- [ ] **Step 1: Añadir los tests del tab**

Añadir al final de la clase `PendientesLiderContadorTest` (antes del `}` de cierre):

```php
    public function test_tab_pendientes_lider_lista_oficina_verificada_y_viaticos_revisada(): void
    {
        $ofi = $this->oficinaVerificada();
        $via = $this->viaticosRevisada();

        $this->actingAs($this->contador)
            ->get(route('solicitudes.index', ['tab' => 'pendientes_lider']))
            ->assertInertia(fn ($page) => $page
                ->component('Solicitudes/Index')
                ->has('solicitudes.data', 2)
            );
    }

    public function test_tab_pendientes_lider_excluye_otros_estados(): void
    {
        // Una oficina en 'enviada' (aun no verificada) no debe aparecer.
        $tipo = TipoSolicitud::where('clave', 'OFI')->firstOrFail();
        $cab  = SolicitudOficina::create(['beneficiario' => '', 'urgencia' => 'media', 'justificacion' => 'x']);
        ItemOficina::create([
            'solicitud_oficina_id' => $cab->id, 'nombre' => 'Mouse',
            'categoria' => 'producto', 'cantidad' => 1, 'costo_estimado' => 1000, 'subtotal' => 1000,
        ]);
        $s = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $this->liderArea->id, 'area_id' => Area::first()->id,
            'solicitable_type' => SolicitudOficina::class, 'solicitable_id' => $cab->id, 'estado' => 'borrador',
            'radicado' => Solicitud::generarRadicado($tipo),
        ]);
        $this->motor->aplicarTransicion($s, 'enviar', $this->liderArea); // queda 'enviada'

        $this->actingAs($this->contador)
            ->get(route('solicitudes.index', ['tab' => 'pendientes_lider']))
            ->assertInertia(fn ($page) => $page->has('solicitudes.data', 0));
    }

    public function test_tab_pendientes_lider_vacio_para_no_contador(): void
    {
        $this->oficinaVerificada();

        // RR. HH. no ve esta cola (es exclusiva del contador).
        $this->actingAs($this->rrhh)
            ->get(route('solicitudes.index', ['tab' => 'pendientes_lider']))
            ->assertInertia(fn ($page) => $page->has('solicitudes.data', 0));
    }
```

- [ ] **Step 2: Ejecutar (deben fallar los nuevos)**

Run: `php artisan test --filter=PendientesLiderContadorTest`
Expected: FAIL en los 3 tests nuevos del tab (el `index` aún no maneja `pendientes_lider`, así que cae al `else` y lista "mias" → conteos no coinciden).

- [ ] **Step 3: Añadir la rama del tab en `index()`**

En `app/Http/Controllers/SolicitudController.php`, en `index()`, añadir esta rama **antes** del `elseif ($tab === 'revisadas')` (para mantener juntas las colas por rol; el orden entre ramas no afecta la lógica):

```php
        } elseif ($tab === 'pendientes_lider') {
            // Solicitudes esperando accion del lider de contabilidad. Solo el contador
            // las ve, para dar seguimiento y evitar que el proceso se demore.
            $solicitudes = $usuario->hasRole('contador')
                ? Solicitud::with(['tipoSolicitud','solicitante'])
                    ->where(function ($q) {
                        $q->where(fn ($q) => $q->whereHas('tipoSolicitud', fn ($t) => $t->where('clave', 'OFI'))
                                ->where('estado', 'verificada'))
                          ->orWhere(fn ($q) => $q->whereHas('tipoSolicitud', fn ($t) => $t->where('clave', 'VIA'))
                                ->where('estado', 'revisada'));
                    })
                    ->oldest() // las mas antiguas primero: prioriza lo que mas se demora
                    ->get()
                : collect();
```

- [ ] **Step 4: Ejecutar (todos verdes)**

Run: `php artisan test --filter=PendientesLiderContadorTest`
Expected: PASS (los 6 tests).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/SolicitudController.php tests/Feature/PendientesLiderContadorTest.php
git commit -m "feat(contador): cola 'pendientes del lider' en la lista de solicitudes"
```

---

# FASE 3 — UI

## Task 3: Pestaña condicional en `Solicitudes/Index.jsx`

**Files:**
- Modify: `resources/js/Pages/Solicitudes/Index.jsx`

- [ ] **Step 1: Detectar el rol contador y construir las pestañas condicionalmente**

En `resources/js/Pages/Solicitudes/Index.jsx`, añadir el import de `usePage` (la línea actual de imports de
inertia es `import { Link, router } from '@inertiajs/react';`):

```jsx
import { Link, router, usePage } from '@inertiajs/react';
```

Dentro del componente `Index`, tras `const tab = filtros?.tab ?? 'mias';`, añadir:

```jsx
    const usuario = usePage().props.auth.user;
    const esContador = usuario?.roles?.some((r) => r.name === 'contador');

    const tabs = [
        { key: 'mias',              label: 'Mis solicitudes' },
        { key: 'pendientes',        label: 'Pendientes de acción' },
        { key: 'pendientes_cierre', label: 'Pendientes por cerrar' },
        ...(esContador ? [{ key: 'pendientes_lider', label: 'Pendientes del líder' }] : []),
        { key: 'revisadas',         label: 'Revisadas' },
    ];
```

- [ ] **Step 2: Usar `tabs` en el render en vez del arreglo inline**

Reemplazar el arreglo inline de pestañas (el `[{ key: 'mias', ... }, ...].map(...)`, líneas 19-24) para que
mapee sobre `tabs`:

```jsx
                    {tabs.map(({ key, label }) => (
                        <button
                            key={key}
                            onClick={() => cambiarTab(key)}
                            className={[
                                'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors',
                                tab === key
                                    ? 'border-indigo-600 text-indigo-600'
                                    : 'border-transparent text-slate-500 hover:text-slate-700',
                            ].join(' ')}
                        >
                            {label}
                        </button>
                    ))}
```

- [ ] **Step 3: Añadir el empty state del tab**

En el bloque de empty state (la expresión ternaria del `<p>`, líneas 46-52), añadir el caso
`pendientes_lider`. Reemplazar esa expresión por:

```jsx
                            {tab === 'revisadas'
                                ? 'Aún no has revisado ninguna solicitud.'
                                : tab === 'pendientes'
                                    ? 'No tienes solicitudes pendientes de acción.'
                                    : tab === 'pendientes_cierre'
                                        ? 'No hay solicitudes de oficina pendientes por cerrar.'
                                        : tab === 'pendientes_lider'
                                            ? 'No hay solicitudes pendientes de aprobación del líder de contabilidad.'
                                            : 'No hay solicitudes para mostrar.'}
```

- [ ] **Step 4: Compilar**

Run: `npm run build`
Expected: build sin errores.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Solicitudes/Index.jsx
git commit -m "feat(contador): pestana 'Pendientes del lider' visible solo para contadores"
```

---

# FASE 4 — Verificación final

## Task 4: Suite completa y build

- [ ] **Step 1: Ejecutar toda la suite**

Run: `php artisan test`
Expected: todos verdes (incluye `PendientesLiderContadorTest` y sin regresiones en los tests de solicitudes/oficina/viáticos).

- [ ] **Step 2: Build de producción**

Run: `npm run build`
Expected: build sin errores.

- [ ] **Step 3: Verificar árbol limpio**

Run: `git status --short`
Expected: sin cambios pendientes (los assets de `public/build` no se versionan).

---

## Cobertura del spec (checklist de auto-revisión)

- Permiso de solo lectura del contador (OFI verificada / VIA revisada): Task 1. ✔
- Contador no gana acciones (monitoreo): Task 1 (test `no_gana_acciones`). ✔
- Tab `pendientes_lider` que lista ambos flujos, solo para contador, más antiguos primero: Task 2. ✔
- Otros roles ven la cola vacía: Task 2 (test `vacio_para_no_contador`). ✔
- Pestaña "Pendientes del líder" visible solo para contador + empty state: Task 3. ✔
- Distinguir tipo por el badge de cada tarjeta: se reutiliza la lista existente (sin cambios), el badge ya lo muestra. ✔
