# Contadores en las pestañas de solicitudes pendientes — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Mostrar un badge con el número de pendientes en las pestañas "Pendientes de acción", "Pendientes por cerrar" y "Pendientes del líder", para identificar de un vistazo dónde hay trabajo pendiente.

**Architecture:** Se extraen las tres colas de `SolicitudController::index` a métodos privados reutilizables (para no duplicar la lógica entre "datos del tab" y "conteo"), se calculan los tres conteos en cada carga respetando el gate de rol, y se exponen como prop `conteos`. El frontend renderiza un badge por pestaña cuando su conteo es > 0.

**Tech Stack:** PHP 8.2, Laravel 10.50, Inertia 0.6, React 18, spatie/laravel-permission 6, PHPUnit + SQLite `:memory:`.

**Spec:** [docs/superpowers/specs/2026-08-12-contadores-en-pestanas-pendientes-design.md](../specs/2026-08-12-contadores-en-pestanas-pendientes-design.md)

---

## Estructura de archivos

**Crear:**
- `tests/Feature/ConteosPestanasSolicitudesTest.php`

**Modificar:**
- `app/Http/Controllers/SolicitudController.php` — extraer colas a métodos privados, `conteos` en `index`.
- `resources/js/Pages/Solicitudes/Index.jsx` — badge de conteo por pestaña.

---

# FASE 1 — Backend

## Task 1: Extraer colas a métodos privados y exponer `conteos`

**Files:**
- Modify: `app/Http/Controllers/SolicitudController.php`
- Test: `tests/Feature/ConteosPestanasSolicitudesTest.php`

- [ ] **Step 1: Escribir el test**

Crear `tests/Feature/ConteosPestanasSolicitudesTest.php`:

```php
<?php
namespace Tests\Feature;

use App\Models\{Area, Empleados, ItemOficina, Solicitud, SolicitudOficina, SolicitudViaticos, TipoSolicitud, Usuario, ViajeroComision};
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConteosPestanasSolicitudesTest extends TestCase
{
    use RefreshDatabase;

    private MotorWorkflow $motor;
    private Usuario $liderArea;
    private Usuario $liderComite;
    private Usuario $rrhh;
    private Usuario $contabilidadLider;
    private Usuario $contador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->motor             = app(MotorWorkflow::class);
        $this->liderArea         = Usuario::where('email', 'lider.area@demo.test')->firstOrFail();
        $this->liderComite       = Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();
        $this->rrhh              = Usuario::where('email', 'rrhh@demo.test')->firstOrFail();
        $this->contabilidadLider = Usuario::where('email', 'contabilidad.lider@demo.test')->firstOrFail();
        $this->contador          = Usuario::where('email', 'contador@demo.test')->firstOrFail();
    }

    /** Crea una solicitud de oficina y la deja en el estado indicado avanzando el flujo. */
    private function oficina(string $hasta): Solicitud
    {
        $tipo = TipoSolicitud::where('clave', 'OFI')->firstOrFail();
        $cab  = SolicitudOficina::create(['beneficiario' => '', 'urgencia' => 'media', 'justificacion' => 'x', 'total' => 1000]);
        ItemOficina::create([
            'solicitud_oficina_id' => $cab->id, 'nombre' => 'Mouse',
            'categoria' => 'producto', 'cantidad' => 1, 'costo_estimado' => 1000, 'subtotal' => 1000,
        ]);
        $s = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $this->liderArea->id, 'area_id' => Area::first()->id,
            'solicitable_type' => SolicitudOficina::class, 'solicitable_id' => $cab->id, 'estado' => 'borrador',
            'radicado' => Solicitud::generarRadicado($tipo),
        ]);
        $this->motor->aplicarTransicion($s, 'enviar', $this->liderArea);       // enviada
        if ($hasta === 'enviada') return $s->fresh();
        $this->motor->aplicarTransicion($s->fresh(), 'verificar', $this->rrhh); // verificada
        if ($hasta === 'verificada') return $s->fresh();
        $this->motor->aplicarTransicion($s->fresh(), 'aprobar', $this->contabilidadLider); // aprobada
        $s->update(['estado' => 'pendiente_cierre']); // el abono la llevaria aqui; se simula
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

    public function test_index_expone_conteos_con_las_tres_claves(): void
    {
        $this->actingAs($this->liderArea)
            ->get(route('solicitudes.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Solicitudes/Index')
                ->has('conteos.pendientes')
                ->has('conteos.pendientes_cierre')
                ->has('conteos.pendientes_lider')
            );
    }

    public function test_conteo_pendientes_cierre_solo_para_quien_ve_la_cola(): void
    {
        $this->oficina('cerrar'); // queda en pendiente_cierre

        // El lider de contabilidad ve 1.
        $this->actingAs($this->contabilidadLider)
            ->get(route('solicitudes.index'))
            ->assertInertia(fn ($page) => $page->where('conteos.pendientes_cierre', 1));

        // El contador NO ve esa cola: 0.
        $this->actingAs($this->contador)
            ->get(route('solicitudes.index'))
            ->assertInertia(fn ($page) => $page->where('conteos.pendientes_cierre', 0));
    }

    public function test_conteo_pendientes_lider_solo_para_contador(): void
    {
        $this->oficina('verificada');   // OFI verificada
        $this->viaticosRevisada();      // VIA revisada

        // El contador ve 2 (OFI verificada + VIA revisada).
        $this->actingAs($this->contador)
            ->get(route('solicitudes.index'))
            ->assertInertia(fn ($page) => $page->where('conteos.pendientes_lider', 2));

        // RR. HH. no ve esa cola: 0.
        $this->actingAs($this->rrhh)
            ->get(route('solicitudes.index'))
            ->assertInertia(fn ($page) => $page->where('conteos.pendientes_lider', 0));
    }

    public function test_conteo_pendientes_de_accion_cuenta_las_accionables(): void
    {
        // Una OFI 'enviada' es accionable para RR. HH. (puede verificar/devolver).
        $this->oficina('enviada');

        $this->actingAs($this->rrhh)
            ->get(route('solicitudes.index'))
            ->assertInertia(fn ($page) => $page->where('conteos.pendientes', 1));
    }
}
```

- [ ] **Step 2: Ejecutar el test (debe fallar)**

Run: `php artisan test --filter=ConteosPestanasSolicitudesTest`
Expected: FAIL — el prop `conteos` no existe todavía (`->has('conteos...')` falla).

- [ ] **Step 3: Extraer las colas a métodos privados**

En `app/Http/Controllers/SolicitudController.php`, añadir el import de `Usuario` y `Collection` al bloque `use`
superior si no están. El `use` de modelos actual incluye `Usuario`
(`use App\Models\{Solicitud, SolicitudOficina, SolicitudViaticos, Usuario};`). Añadir además:

```php
use Illuminate\Database\Eloquent\Collection;
```

Añadir estos tres métodos privados a la clase (p. ej. tras `index()`):

```php
    /**
     * Solicitudes donde el usuario tiene alguna accion disponible. Se resuelve en
     * PHP porque "accion disponible" depende del motor de workflow, no de SQL.
     */
    private function colaPendientes(Usuario $usuario): Collection
    {
        return Solicitud::with(['tipoSolicitud','solicitante'])
            ->get()
            ->filter(fn ($s) => !empty($this->motor->accionesDisponibles($s, $usuario)))
            ->values();
    }

    /**
     * Query de la cola "pendientes por cerrar" (OFI en pendiente_cierre), o null si
     * el usuario no puede verla. Devolver el query permite contar o listar sin repetir el gate.
     */
    private function queryPendientesCierre(Usuario $usuario)
    {
        if (! $usuario->hasAnyRole(['contabilidad_lider', 'lider_area'])) {
            return null;
        }
        return Solicitud::with(['tipoSolicitud','solicitante'])
            ->whereHas('tipoSolicitud', fn ($q) => $q->where('clave', 'OFI'))
            ->where('estado', 'pendiente_cierre');
    }

    /**
     * Query de la cola "pendientes del lider" (OFI verificada u VIA revisada), o null
     * si el usuario no es contador.
     */
    private function queryPendientesLider(Usuario $usuario)
    {
        if (! $usuario->hasRole('contador')) {
            return null;
        }
        return Solicitud::with(['tipoSolicitud','solicitante'])
            ->where(function ($q) {
                $q->where(fn ($q) => $q->whereHas('tipoSolicitud', fn ($t) => $t->where('clave', 'OFI'))
                        ->where('estado', 'verificada'))
                  ->orWhere(fn ($q) => $q->whereHas('tipoSolicitud', fn ($t) => $t->where('clave', 'VIA'))
                        ->where('estado', 'revisada'));
            });
    }
```

- [ ] **Step 4: Reescribir `index()` usando los métodos y añadir `conteos`**

Reemplazar el cuerpo de `index()` (la cadena if/elseif/else y el `return`) por:

```php
    public function index()
    {
        $usuario = auth()->user();
        $tab     = request('tab', 'mias');

        if ($tab === 'pendientes') {
            $solicitudes = $this->colaPendientes($usuario);
        } elseif ($tab === 'pendientes_cierre') {
            $q = $this->queryPendientesCierre($usuario);
            $solicitudes = $q ? $q->latest()->get() : collect();
        } elseif ($tab === 'pendientes_lider') {
            $q = $this->queryPendientesLider($usuario);
            $solicitudes = $q ? $q->oldest()->get() : collect();
        } elseif ($tab === 'revisadas') {
            $solicitudes = Solicitud::with(['tipoSolicitud','solicitante'])
                ->whereHas('transiciones', fn ($q) => $q->where('usuario_id', $usuario->id))
                ->latest()
                ->get();
        } else {
            $solicitudes = Solicitud::with(['tipoSolicitud','solicitante'])
                ->where('solicitante_id', $usuario->id)
                ->latest()
                ->get();
        }

        $conteos = [
            'pendientes'        => $this->colaPendientes($usuario)->count(),
            'pendientes_cierre' => optional($this->queryPendientesCierre($usuario))->count() ?? 0,
            'pendientes_lider'  => optional($this->queryPendientesLider($usuario))->count() ?? 0,
        ];

        return Inertia::render('Solicitudes/Index', [
            'solicitudes' => ['data' => SolicitudResource::collection($solicitudes)->resolve()],
            'filtros'     => ['tab' => $tab],
            'conteos'     => $conteos,
        ]);
    }
```

> Nota: cuando el tab activo es `pendientes`, `colaPendientes` se llama dos veces (datos + conteo). Es
> aceptable al volumen actual (decisión del usuario: mantenerlo simple). Las otras dos colas usan `count()` SQL
> barato aunque el tab activo sea otro.

- [ ] **Step 5: Ejecutar el test (debe pasar)**

Run: `php artisan test --filter=ConteosPestanasSolicitudesTest`
Expected: PASS (4 tests).

- [ ] **Step 6: Ejecutar los tests existentes de la lista (regresión)**

Run: `php artisan test --filter=PendientesLiderContadorTest`
Expected: PASS (el refactor a métodos privados no cambia el comportamiento de los tabs).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/SolicitudController.php tests/Feature/ConteosPestanasSolicitudesTest.php
git commit -m "feat(solicitudes): exponer conteos de las colas pendientes en el index"
```

---

# FASE 2 — UI

## Task 2: Badge de conteo por pestaña

**Files:**
- Modify: `resources/js/Pages/Solicitudes/Index.jsx`

- [ ] **Step 1: Recibir `conteos` y renderizar el badge**

En `resources/js/Pages/Solicitudes/Index.jsx`, cambiar la firma del componente para recibir `conteos` con
default:

```jsx
export default function Index({ solicitudes, filtros, conteos = {} }) {
```

En el `map` de `tabs`, dentro del `<button>`, añadir el badge tras `{label}`. El bloque actual del botón termina
con `{label}` antes de `</button>`; reemplazar ese contenido por:

```jsx
                            {label}
                            {conteos[key] > 0 && (
                                <span className={[
                                    'ml-2 inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full text-xs font-semibold',
                                    tab === key ? 'bg-indigo-100 text-indigo-700' : 'bg-slate-100 text-slate-600',
                                ].join(' ')}>
                                    {conteos[key]}
                                </span>
                            )}
```

> `conteos[key]` es `undefined` para `mias` y `revisadas` (no están en el objeto), así que `> 0` es falso y no
> se renderiza badge en esas pestañas. Para las tres colas, el badge aparece solo si el conteo es ≥ 1.

- [ ] **Step 2: Compilar**

Run: `npm run build`
Expected: build sin errores.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Solicitudes/Index.jsx
git commit -m "feat(solicitudes): badge con el numero de pendientes en cada pestana"
```

---

# FASE 3 — Verificación final

## Task 3: Suite completa y build

- [ ] **Step 1: Ejecutar toda la suite**

Run: `php artisan test`
Expected: todos verdes (incluye `ConteosPestanasSolicitudesTest` y sin regresiones en los tests de solicitudes).

- [ ] **Step 2: Build de producción**

Run: `npm run build`
Expected: build sin errores.

- [ ] **Step 3: Verificar árbol limpio**

Run: `git status --short`
Expected: sin cambios pendientes (los assets de `public/build` no se versionan).

---

## Cobertura del spec (checklist de auto-revisión)

- Extraer colas a métodos privados (limpia `index`, evita duplicar lógica): Task 1. ✔
- Conteos calculados en cada carga respetando el gate de rol: Task 1 (`conteos` + tests de rol). ✔
- Prop `conteos` con las tres claves: Task 1 (test `expone_conteos`). ✔
- Badge con el número por pestaña, oculto en 0: Task 2. ✔
- `mias`/`revisadas` sin badge: Task 2 (nota `undefined > 0` es falso). ✔
