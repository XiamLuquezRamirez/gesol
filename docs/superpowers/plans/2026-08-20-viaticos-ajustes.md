# Viáticos — Ajustes (confirmar salida, cancelar/reactivar, ajustar, notificar) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Cuatro ajustes al módulo de viáticos: (1) RR.HH. confirma salida por viajero; (2) el solicitante cancela/reactiva la comisión; (3) el líder ajusta fechas/horas por viajero con motivo; (4) la notificación de comisión enviada se muestra como "pendiente por revisar".

**Architecture:** Cancelar/reactivar/ajustar son endpoints propios en `ViaticosController` (fuera del MotorWorkflow, porque ocurren "en cualquier momento"), cada uno con su método de policy y registro en el historial (`TransicionSolicitud`) y notificaciones. Confirmar salida es una columna booleana + endpoint en el panel de RR.HH. El estado `cancelada` se añade a la lista de estados VIA (sin transiciones de motor) y guarda `estado_previo` para reactivar.

**Tech Stack:** Laravel 10.50, PHP 8.2 (`/c/xampp/php/php.exe` en CLI), Inertia/React 18, PHPUnit + SQLite `:memory:`. Migraciones idempotentes; MariaDB en dev necesita `db:seed --class=TipoSolicitudSeeder --force` tras cambiar el seeder.

**Spec:** `docs/superpowers/specs/2026-08-20-viaticos-ajustes-rrhh-cancelar-ajustar-design.md`

---

## Bloque 4 — Notificación "pendiente por revisar" (primero, sin backend)

### Task 4.1: Copy y estilo del tipo `comision_reportada` en el panel

**Files:**
- Modify: `resources/js/Components/PanelNotificaciones.jsx`

**Contexto:** Al enviar una comisión, RR.HH. ya recibe `ComisionCerradaNotification` con `tipo => 'comision_reportada'`, pero el panel no tiene caso para ese tipo (cae en default "Actualización en {radicado}"). Leer el archivo: `mensajeNotificacion(n)` es un `switch (n.tipo)` (~líneas 45-56) y `ESTILO_TIPO` es un mapa (~líneas 38-43).

- [ ] **Step 1: Añadir el caso de mensaje**

En `mensajeNotificacion(n)`, añadir antes del `default`:
```jsx
        case 'comision_reportada':
            return `Comisión pendiente por revisar: ${n.radicado}`;
```

- [ ] **Step 2: Añadir el estilo**

En `ESTILO_TIPO`, añadir una entrada (usar el estilo ámbar/pendiente que ya exista para acción_requerida como referencia; si hay un objeto `{ icono, clase }`, replicar la forma exacta):
```jsx
    comision_reportada: /* misma forma que las demás entradas: icono + clases ámbar */,
```
Leer una entrada existente (p.ej. `accion_requerida`) y copiar su estructura, cambiando el texto/color a un tono "pendiente" (ámbar).

- [ ] **Step 3: Build**

Run: `npm run build`
Expected: `✓ built` sin errores.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Components/PanelNotificaciones.jsx
git commit -m "feat(viaticos): notificacion de comision enviada como 'pendiente por revisar'"
```

---

## Bloque 1 — Confirmar salida (RR.HH.)

### Task 1.1: Migración + modelo `salida_confirmada`

**Files:**
- Create: `database/migrations/2026_08_20_100000_add_salida_confirmada_to_viajeros_comision_table.php`
- Modify: `app/Models/ViajeroComision.php`

- [ ] **Step 1: Migración idempotente**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('viajeros_comision', 'salida_confirmada')) {
            Schema::table('viajeros_comision', function (Blueprint $table) {
                $table->boolean('salida_confirmada')->default(false)->after('tipo_pago');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('viajeros_comision', 'salida_confirmada')) {
            Schema::table('viajeros_comision', function (Blueprint $table) {
                $table->dropColumn('salida_confirmada');
            });
        }
    }
};
```

- [ ] **Step 2: Modelo** — añadir a `$fillable` `'salida_confirmada'` y al `$casts` `'salida_confirmada' => 'boolean'`.

- [ ] **Step 3: Test del modelo**

Test: `tests/Feature/ConfirmarSalidaTest.php` (nuevo)
```php
<?php
namespace Tests\Feature;

use App\Models\{Empleados, SolicitudViaticos, ViajeroComision};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConfirmarSalidaTest extends TestCase
{
    use RefreshDatabase;

    public function test_salida_confirmada_por_defecto_false(): void
    {
        $this->seed();
        $cab = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => '', 'observacion' => 'x']);
        $v = ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => Empleados::first()->id,
            'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
        ]);
        $this->assertFalse($v->fresh()->salida_confirmada);
    }
}
```

- [ ] **Step 4: Ejecutar** — `/c/xampp/php/php.exe artisan test --filter=test_salida_confirmada_por_defecto_false` ⇒ PASS.

- [ ] **Step 5: Commit**
```bash
git add database/migrations/2026_08_20_100000_add_salida_confirmada_to_viajeros_comision_table.php app/Models/ViajeroComision.php tests/Feature/ConfirmarSalidaTest.php
git commit -m "feat(viaticos): columna salida_confirmada en viajeros_comision"
```

### Task 1.2: Policy + endpoint + ruta de confirmar salida

**Files:**
- Modify: `app/Policies/SolicitudPolicy.php`
- Modify: `app/Http/Controllers/ComisionesRrhhController.php`
- Modify: `routes/web.php`

**Contexto:** El endpoint lo maneja `ComisionesRrhhController` (mismo controlador del panel). Policy nueva `confirmarSalida`: rol `rrhh`, VIA, estado NOT IN `['borrador','rechazada','cancelada']`.

- [ ] **Step 1: Test HTTP**

Añadir a `ConfirmarSalidaTest`:
```php
    private function comisionConViajero(): array
    {
        $tipo = \App\Models\TipoSolicitud::where('clave','VIA')->firstOrFail();
        $cab = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => '', 'observacion' => 'x']);
        $v = ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => Empleados::first()->id,
            'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
        ]);
        $s = \App\Models\Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => \App\Models\Usuario::first()->id,
            'solicitable_type' => SolicitudViaticos::class, 'solicitable_id' => $cab->id,
            'estado' => 'enviada', 'radicado' => \App\Models\Solicitud::generarRadicado($tipo),
        ]);
        return [$s, $v];
    }

    public function test_rrhh_confirma_salida(): void
    {
        $this->seed();
        [$s, $v] = $this->comisionConViajero();
        $rrhh = \App\Models\Usuario::where('email','rrhh@demo.test')->firstOrFail();

        $this->actingAs($rrhh)->patch(route('viaticos.salida.confirmar', [$s, $v]), ['confirmada' => true])
            ->assertRedirect();
        $this->assertTrue($v->fresh()->salida_confirmada);

        $this->actingAs($rrhh)->patch(route('viaticos.salida.confirmar', [$s, $v]), ['confirmada' => false])
            ->assertRedirect();
        $this->assertFalse($v->fresh()->salida_confirmada);
    }

    public function test_no_rrhh_no_confirma_salida(): void
    {
        $this->seed();
        [$s, $v] = $this->comisionConViajero();
        $otro = \App\Models\Usuario::where('email','lider.comite@demo.test')->firstOrFail();
        $this->actingAs($otro)->patch(route('viaticos.salida.confirmar', [$s, $v]), ['confirmada' => true])
            ->assertForbidden();
    }
```

- [ ] **Step 2: Ejecutar (falla: ruta/policy no existen).**

- [ ] **Step 3: Policy** — en `SolicitudPolicy` añadir:
```php
    public function confirmarSalida(Usuario $usuario, Solicitud $solicitud): bool
    {
        return $solicitud->tipoSolicitud->clave === 'VIA'
            && $usuario->hasRole('rrhh')
            && ! in_array($solicitud->estado, ['borrador', 'rechazada', 'cancelada']);
    }
```

- [ ] **Step 4: Método en `ComisionesRrhhController`**
```php
    public function confirmarSalida(\Illuminate\Http\Request $request, \App\Models\Solicitud $solicitud, \App\Models\ViajeroComision $viajero)
    {
        $this->authorize('confirmarSalida', $solicitud);
        abort_unless($viajero->solicitud_viaticos_id === $solicitud->solicitable_id, 404);
        $viajero->update(['salida_confirmada' => (bool) $request->boolean('confirmada')]);
        return back()->with('success', 'Salida actualizada.');
    }
```
(Asegurar `use Illuminate\Foundation\Auth\Access\AuthorizesRequests;` — el Controller base ya lo trae.)

- [ ] **Step 5: Ruta** en `routes/web.php` (grupo auth):
```php
Route::patch('/viaticos/{solicitud}/viajeros/{viajero}/salida', [ComisionesRrhhController::class, 'confirmarSalida'])->name('viaticos.salida.confirmar');
```
(Verificar import de `ComisionesRrhhController` arriba.)

- [ ] **Step 6: Ejecutar tests** ⇒ PASS. Suite completa verde.

- [ ] **Step 7: Commit**
```bash
git add app/Policies/SolicitudPolicy.php app/Http/Controllers/ComisionesRrhhController.php routes/web.php tests/Feature/ConfirmarSalidaTest.php
git commit -m "feat(viaticos): RR.HH. confirma la salida real de cada viajero"
```

### Task 1.3: UI de confirmar salida en el panel de RR.HH.

**Files:**
- Modify: `app/Http/Controllers/ComisionesRrhhController.php` (exponer `solicitud_id` y `salida_confirmada` en el mapeo)
- Modify: `resources/js/Pages/Rrhh/Comisiones.jsx`

- [ ] **Step 1: Backend — exponer datos.** En el mapeo por viajero de `index()`, añadir:
```php
'solicitud_id'       => $solicitud->id,
'salida_confirmada'  => (bool) $v->salida_confirmada,
```
(Ya se resuelve `$solicitud = $v->solicitudViaticos?->solicitud` en el map; usar su `->id`.)

- [ ] **Step 2: Frontend — columna "Salió".** En el `<thead>` añadir `<th>Salió</th>`; en la fila, una celda con checkbox:
```jsx
<td className="px-4 py-3">
    <input
        type="checkbox"
        checked={!!c.salida_confirmada}
        onChange={(e) => router.patch(
            route('viaticos.salida.confirmar', [c.solicitud_id, c.id]),
            { confirmada: e.target.checked },
            { preserveScroll: true }
        )}
        className="rounded border-slate-300"
    />
</td>
```
(Verificar el nombre de la variable de iteración de la fila — probablemente `c`.)

- [ ] **Step 3: Build** ⇒ `✓ built`.

- [ ] **Step 4: Commit**
```bash
git add app/Http/Controllers/ComisionesRrhhController.php resources/js/Pages/Rrhh/Comisiones.jsx
git commit -m "feat(viaticos): checkbox de salida confirmada en el panel de RR.HH."
```

---

## Bloque 2 — Cancelar y reactivar

### Task 2.1: Migración `estado_previo` + estado `cancelada` en seeder

**Files:**
- Create: `database/migrations/2026_08_20_110000_add_estado_previo_to_solicitudes_table.php`
- Modify: `app/Models/Solicitud.php`
- Modify: `database/seeders/TipoSolicitudSeeder.php`

- [ ] **Step 1: Migración idempotente**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('solicitudes', 'estado_previo')) {
            Schema::table('solicitudes', function (Blueprint $table) {
                $table->string('estado_previo')->nullable()->after('estado');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('solicitudes', 'estado_previo')) {
            Schema::table('solicitudes', function (Blueprint $table) {
                $table->dropColumn('estado_previo');
            });
        }
    }
};
```

- [ ] **Step 2: Modelo `Solicitud`** — añadir `'estado_previo'` a `$fillable`.

- [ ] **Step 3: Seeder** — en el bloque VIA, añadir `'cancelada'` al array de `estados` (al final):
```php
'estados' => json_encode(['borrador','enviada','liquidada','revisada','en_gerencia','cerrada','rechazada','cancelada']),
```
(NO añadir transiciones de motor para cancelada; se maneja fuera del motor.)

- [ ] **Step 4: Ejecutar suite** ⇒ verde (RefreshDatabase re-siembra). Commit.
```bash
git add database/migrations/2026_08_20_110000_add_estado_previo_to_solicitudes_table.php app/Models/Solicitud.php database/seeders/TipoSolicitudSeeder.php
git commit -m "feat(viaticos): estado 'cancelada' y columna estado_previo para reactivar"
```

### Task 2.2: Policy cancelar/reactivar + endpoints + notificación

**Files:**
- Modify: `app/Policies/SolicitudPolicy.php`
- Modify: `app/Http/Controllers/ViaticosController.php`
- Modify: `app/Notifications/AvisoTransicionNotification.php` (aceptar tipos `cancelada`/`reactivada`/`ajustada` — ya acepta `string $tipo`, solo documentar)
- Modify: `routes/web.php`

- [ ] **Step 1: Tests HTTP** (`tests/Feature/CancelarComisionTest.php` nuevo)
```php
<?php
namespace Tests\Feature;

use App\Models\{Empleados, Solicitud, SolicitudViaticos, TipoSolicitud, Usuario, ViajeroComision};
use App\Notifications\AvisoTransicionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CancelarComisionTest extends TestCase
{
    use RefreshDatabase;

    private function comision(string $estado = 'enviada'): Solicitud
    {
        $tipo = TipoSolicitud::where('clave','VIA')->firstOrFail();
        $lider = Usuario::where('email','lider.comite@demo.test')->firstOrFail();
        $cab = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => '', 'observacion' => 'x']);
        ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => Empleados::first()->id,
            'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
        ]);
        return Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $lider->id,
            'solicitable_type' => SolicitudViaticos::class, 'solicitable_id' => $cab->id,
            'estado' => $estado, 'radicado' => Solicitud::generarRadicado($tipo),
        ]);
    }

    public function test_solicitante_cancela_y_guarda_estado_previo(): void
    {
        Notification::fake();
        $this->seed();
        $s = $this->comision('liquidada');
        $lider = Usuario::where('email','lider.comite@demo.test')->firstOrFail();

        $this->actingAs($lider)->post(route('viaticos.cancelar', $s), ['motivo' => 'reprogramada'])
            ->assertRedirect();

        $this->assertEquals('cancelada', $s->fresh()->estado);
        $this->assertEquals('liquidada', $s->fresh()->estado_previo);
        $this->assertDatabaseHas('transiciones_solicitud', ['solicitud_id' => $s->id, 'accion' => 'cancelar']);
        // RR.HH. y contabilidad reciben aviso.
        $rrhh = Usuario::where('email','rrhh@demo.test')->firstOrFail();
        Notification::assertSentTo($rrhh, AvisoTransicionNotification::class);
    }

    public function test_reactivar_vuelve_al_estado_previo(): void
    {
        $this->seed();
        $s = $this->comision('liquidada');
        $lider = Usuario::where('email','lider.comite@demo.test')->firstOrFail();
        $this->actingAs($lider)->post(route('viaticos.cancelar', $s), ['motivo' => 'x'])->assertRedirect();

        $this->actingAs($lider)->post(route('viaticos.reactivar', $s))->assertRedirect();
        $this->assertEquals('liquidada', $s->fresh()->estado);
        $this->assertNull($s->fresh()->estado_previo);
    }

    public function test_no_solicitante_no_cancela(): void
    {
        $this->seed();
        $s = $this->comision('enviada');
        $otro = Usuario::where('email','contador@demo.test')->firstOrFail();
        $this->actingAs($otro)->post(route('viaticos.cancelar', $s), ['motivo' => 'x'])->assertForbidden();
    }

    public function test_no_cancela_cerrada(): void
    {
        $this->seed();
        $s = $this->comision('cerrada');
        $lider = Usuario::where('email','lider.comite@demo.test')->firstOrFail();
        $this->actingAs($lider)->post(route('viaticos.cancelar', $s), ['motivo' => 'x'])->assertForbidden();
    }

    public function test_cancelada_no_aparece_en_panel_rrhh(): void
    {
        $this->seed();
        $s = $this->comision('enviada');
        $lider = Usuario::where('email','lider.comite@demo.test')->firstOrFail();
        $rrhh = Usuario::where('email','rrhh@demo.test')->firstOrFail();
        $this->actingAs($lider)->post(route('viaticos.cancelar', $s), ['motivo' => 'x'])->assertRedirect();

        $this->actingAs($rrhh)->get(route('rrhh.comisiones', ['todos' => 1]))
            ->assertInertia(fn ($page) => $page->where('comisionados', fn ($d) => count($d) === 0));
    }
}
```

- [ ] **Step 2: Ejecutar (falla).**

- [ ] **Step 3: Policy** — añadir:
```php
    public function cancelar(Usuario $usuario, Solicitud $solicitud): bool
    {
        return $solicitud->tipoSolicitud->clave === 'VIA'
            && $usuario->id === $solicitud->solicitante_id
            && ! in_array($solicitud->estado, ['cerrada', 'cancelada']);
    }

    public function reactivar(Usuario $usuario, Solicitud $solicitud): bool
    {
        return $solicitud->tipoSolicitud->clave === 'VIA'
            && $usuario->id === $solicitud->solicitante_id
            && $solicitud->estado === 'cancelada';
    }
```

- [ ] **Step 4: Métodos en `ViaticosController`** (usa `DB`, `TransicionSolicitud`, `Notification`, `Usuario`, `AvisoTransicionNotification` — añadir imports que falten):
```php
    public function cancelar(\Illuminate\Http\Request $request, Solicitud $solicitud)
    {
        $this->authorize('cancelar', $solicitud);
        $motivo = $request->input('motivo');
        $anterior = $solicitud->estado;

        \Illuminate\Support\Facades\DB::transaction(function () use ($solicitud, $anterior, $motivo) {
            $solicitud->update(['estado_previo' => $anterior, 'estado' => 'cancelada']);
            \App\Models\TransicionSolicitud::create([
                'solicitud_id' => $solicitud->id, 'estado_origen' => $anterior,
                'estado_destino' => 'cancelada', 'accion' => 'cancelar',
                'usuario_id' => auth()->id(), 'comentario' => $motivo,
            ]);
        });

        $this->avisarCancelacion($solicitud->fresh(), 'cancelada', $motivo);
        return back()->with('success', 'Comisión cancelada.');
    }

    public function reactivar(Solicitud $solicitud)
    {
        $this->authorize('reactivar', $solicitud);
        $destino = $solicitud->estado_previo ?: 'enviada';

        \Illuminate\Support\Facades\DB::transaction(function () use ($solicitud, $destino) {
            $solicitud->update(['estado' => $destino, 'estado_previo' => null]);
            \App\Models\TransicionSolicitud::create([
                'solicitud_id' => $solicitud->id, 'estado_origen' => 'cancelada',
                'estado_destino' => $destino, 'accion' => 'reactivar',
                'usuario_id' => auth()->id(),
            ]);
        });

        $this->avisarCancelacion($solicitud->fresh(), 'reactivada', null);
        return back()->with('success', 'Comisión reactivada.');
    }

    /** Notifica a RR.HH. y contabilidad de una cancelacion/reactivacion/ajuste. */
    private function avisarCancelacion(Solicitud $solicitud, string $tipo, ?string $comentario): void
    {
        $usuarios = \App\Models\Usuario::role(['rrhh', 'contador', 'contabilidad_lider'])->get();
        foreach ($usuarios as $u) {
            $u->notify(new \App\Notifications\AvisoTransicionNotification(
                $solicitud, $tipo, $tipo, $comentario, auth()->user()->name
            ));
        }
    }
```

- [ ] **Step 5: Rutas** en `routes/web.php`:
```php
Route::post('/viaticos/{solicitud}/cancelar',  [ViaticosController::class, 'cancelar'])->name('viaticos.cancelar');
Route::post('/viaticos/{solicitud}/reactivar', [ViaticosController::class, 'reactivar'])->name('viaticos.reactivar');
```

- [ ] **Step 6: Exclusión de paneles.**
  - `ComisionesRrhhController` línea 27: `whereNotIn('estado', ['borrador', 'rechazada', 'cancelada'])`.
  - `SolicitudPolicy::verDetalle`: en la rama de `rrhh`, cambiar `! in_array($solicitud->estado, ['borrador', 'rechazada'])` por `['borrador', 'rechazada', 'cancelada']`.

- [ ] **Step 7: Ejecutar tests** ⇒ PASS. Suite completa verde.

- [ ] **Step 8: Commit**
```bash
git add app/Policies/SolicitudPolicy.php app/Http/Controllers/ViaticosController.php app/Http/Controllers/ComisionesRrhhController.php routes/web.php tests/Feature/CancelarComisionTest.php
git commit -m "feat(viaticos): cancelar y reactivar comision (solo solicitante) con aviso a RR.HH./contabilidad"
```

### Task 2.3: UI cancelar/reactivar + BadgeEstado

**Files:**
- Modify: `resources/js/Components/BadgeEstado.jsx`
- Modify: `app/Http/Controllers/SolicitudController.php` (flags `puedeCancelar`/`puedeReactivar`)
- Modify: `resources/js/Pages/Solicitudes/Detalle.jsx`

- [ ] **Step 1: BadgeEstado** — en `COLORES` añadir `cancelada: 'bg-red-50 text-red-700 border-red-200'`; en `ETIQUETAS` `cancelada:'Cancelada'`; en `ETIQUETAS_CORTAS` `cancelada:'Cancelada'`.

- [ ] **Step 2: Flags backend.** En `SolicitudController::show`, añadir a las props:
```php
'puedeCancelar'  => $usuario->can('cancelar', $solicitud),
'puedeReactivar' => $usuario->can('reactivar', $solicitud),
```

- [ ] **Step 3: Frontend.** En `Detalle.jsx` firma añadir `puedeCancelar = false, puedeReactivar = false`. En la zona de acciones (donde están los botones de workflow), añadir:
```jsx
{puedeCancelar && (
    <button type="button" onClick={() => router.post(route('viaticos.cancelar', solicitud.id), { motivo: '' }, { preserveScroll: true })}
        className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-red-600 hover:bg-red-700 rounded-lg">
        Cancelar comisión
    </button>
)}
{puedeReactivar && (
    <button type="button" onClick={() => router.post(route('viaticos.reactivar', solicitud.id), {}, { preserveScroll: true })}
        className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-emerald-600 hover:bg-emerald-700 rounded-lg">
        Reactivar comisión
    </button>
)}
```
(Idealmente el "Cancelar" abre un modal para capturar el motivo; para el MVP se envía motivo vacío. Si se implementa modal, capturar `motivo` y enviarlo. Verificar que `router` esté importado — sí lo está.)

- [ ] **Step 4: Build** ⇒ `✓ built`.

- [ ] **Step 5: Commit**
```bash
git add resources/js/Components/BadgeEstado.jsx app/Http/Controllers/SolicitudController.php resources/js/Pages/Solicitudes/Detalle.jsx
git commit -m "feat(viaticos): botones cancelar/reactivar en el detalle y badge 'Cancelada'"
```

---

## Bloque 3 — Ajustar comisión (líder)

### Task 3.1: Policy + Request + endpoint de ajuste + notificación

**Files:**
- Modify: `app/Policies/SolicitudPolicy.php`
- Create: `app/Http/Requests/AjustarComisionRequest.php`
- Modify: `app/Http/Controllers/ViaticosController.php`
- Modify: `routes/web.php`

- [ ] **Step 1: Tests HTTP** (`tests/Feature/AjustarComisionTest.php` nuevo)
```php
<?php
namespace Tests\Feature;

use App\Models\{Empleados, Solicitud, SolicitudViaticos, TipoSolicitud, Usuario, ViajeroComision};
use App\Notifications\AvisoTransicionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AjustarComisionTest extends TestCase
{
    use RefreshDatabase;

    private function comisionConViajero(string $estado = 'liquidada'): array
    {
        $tipo = TipoSolicitud::where('clave','VIA')->firstOrFail();
        $lider = Usuario::where('email','lider.comite@demo.test')->firstOrFail();
        $cab = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => '', 'observacion' => 'x']);
        $v = ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => Empleados::first()->id,
            'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-22', 'hora_regreso' => '17:00',
        ]);
        $s = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $lider->id,
            'solicitable_type' => SolicitudViaticos::class, 'solicitable_id' => $cab->id,
            'estado' => $estado, 'radicado' => Solicitud::generarRadicado($tipo),
        ]);
        return [$s, $v, $lider];
    }

    public function test_lider_ajusta_fechas_y_notifica(): void
    {
        Notification::fake();
        $this->seed();
        [$s, $v, $lider] = $this->comisionConViajero('liquidada');

        $this->actingAs($lider)->put(route('viaticos.ajustar', $s), [
            'motivo' => 'Se queda 2 dias mas',
            'viajeros' => [[
                'viajero_comision_id' => $v->id,
                'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
                'fecha_regreso' => '2026-08-24', 'hora_regreso' => '17:00',
            ]],
        ])->assertRedirect();

        $this->assertEquals('2026-08-24', $v->fresh()->fecha_regreso->toDateString());
        $this->assertDatabaseHas('transiciones_solicitud', ['solicitud_id' => $s->id, 'accion' => 'ajustar']);
        $contador = Usuario::where('email','contador@demo.test')->firstOrFail();
        Notification::assertSentTo($contador, AvisoTransicionNotification::class);
    }

    public function test_no_solicitante_no_ajusta(): void
    {
        $this->seed();
        [$s, $v] = $this->comisionConViajero('liquidada');
        $otro = Usuario::where('email','contador@demo.test')->firstOrFail();
        $this->actingAs($otro)->put(route('viaticos.ajustar', $s), [
            'motivo' => 'x', 'viajeros' => [['viajero_comision_id' => $v->id,
                'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
                'fecha_regreso' => '2026-08-24', 'hora_regreso' => '17:00']],
        ])->assertForbidden();
    }

    public function test_no_ajusta_cerrada(): void
    {
        $this->seed();
        [$s, $v, $lider] = $this->comisionConViajero('cerrada');
        $this->actingAs($lider)->put(route('viaticos.ajustar', $s), [
            'motivo' => 'x', 'viajeros' => [['viajero_comision_id' => $v->id,
                'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
                'fecha_regreso' => '2026-08-24', 'hora_regreso' => '17:00']],
        ])->assertForbidden();
    }

    public function test_ajuste_sin_motivo_es_invalido(): void
    {
        $this->seed();
        [$s, $v, $lider] = $this->comisionConViajero('liquidada');
        $this->actingAs($lider)->from(route('solicitudes.show', $s))->put(route('viaticos.ajustar', $s), [
            'viajeros' => [['viajero_comision_id' => $v->id,
                'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
                'fecha_regreso' => '2026-08-24', 'hora_regreso' => '17:00']],
        ])->assertSessionHasErrors('motivo');
    }
}
```

- [ ] **Step 2: Ejecutar (falla).**

- [ ] **Step 3: Policy**
```php
    public function ajustar(Usuario $usuario, Solicitud $solicitud): bool
    {
        return $solicitud->tipoSolicitud->clave === 'VIA'
            && $usuario->id === $solicitud->solicitante_id
            && ! in_array($solicitud->estado, ['cerrada', 'cancelada']);
    }
```

- [ ] **Step 4: Request `AjustarComisionRequest`**
```php
<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class AjustarComisionRequest extends FormRequest
{
    public function authorize(): bool { return true; } // la policy actua en el controlador

    public function rules(): array
    {
        return [
            'motivo'                          => 'required|string|max:2000',
            'viajeros'                        => 'required|array|min:1',
            'viajeros.*.viajero_comision_id'  => 'required|exists:viajeros_comision,id',
            'viajeros.*.fecha_salida'         => 'required|date',
            'viajeros.*.hora_salida'          => 'required|string|max:5',
            'viajeros.*.fecha_regreso'        => 'required|date',
            'viajeros.*.hora_regreso'         => 'required|string|max:5',
        ];
    }

    public function messages(): array
    {
        return ['motivo.required' => 'Indique el motivo del ajuste.'];
    }
}
```

- [ ] **Step 5: Método `ajustar` en `ViaticosController`**
```php
    public function ajustar(\App\Http\Requests\AjustarComisionRequest $request, Solicitud $solicitud)
    {
        $this->authorize('ajustar', $solicitud);
        $cabecera = $solicitud->solicitable;

        \Illuminate\Support\Facades\DB::transaction(function () use ($request, $solicitud, $cabecera) {
            foreach ($request->viajeros as $datos) {
                $viajero = $cabecera->viajeros()->where('id', $datos['viajero_comision_id'])->first();
                if (! $viajero) continue; // ignora ids ajenos a la comision
                $viajero->update([
                    'fecha_salida'  => $datos['fecha_salida'],  'hora_salida'  => $datos['hora_salida'],
                    'fecha_regreso' => $datos['fecha_regreso'], 'hora_regreso' => $datos['hora_regreso'],
                ]);
            }
            \App\Models\TransicionSolicitud::create([
                'solicitud_id' => $solicitud->id, 'estado_origen' => $solicitud->estado,
                'estado_destino' => $solicitud->estado, 'accion' => 'ajustar',
                'usuario_id' => auth()->id(), 'comentario' => $request->motivo,
            ]);
        });

        $this->avisarCancelacion($solicitud->fresh(), 'ajustada', $request->motivo);
        return back()->with('success', 'Comisión ajustada. Se notificó a contabilidad y RR. HH.');
    }
```
(Reusa `avisarCancelacion` de Task 2.2 — notifica a rrhh/contador/contabilidad_lider. El nombre del helper es genérico; opcionalmente renombrarlo a `avisarCambioComision`.)

- [ ] **Step 6: Ruta**
```php
Route::put('/viaticos/{solicitud}/ajustar', [ViaticosController::class, 'ajustar'])->name('viaticos.ajustar');
```

- [ ] **Step 7: Ejecutar tests** ⇒ PASS. Suite completa verde.

- [ ] **Step 8: Commit**
```bash
git add app/Policies/SolicitudPolicy.php app/Http/Requests/AjustarComisionRequest.php app/Http/Controllers/ViaticosController.php routes/web.php tests/Feature/AjustarComisionTest.php
git commit -m "feat(viaticos): el lider ajusta fechas/horas por viajero con motivo y notifica"
```

### Task 3.2: UI de ajuste + copy de notificación

**Files:**
- Modify: `app/Http/Controllers/SolicitudController.php` (flag `puedeAjustar`)
- Modify: `resources/js/Pages/Solicitudes/Detalle.jsx` (botón + modal de ajuste)
- Modify: `resources/js/Components/PanelNotificaciones.jsx` (copy de `ajustada`, `cancelada`, `reactivada`)

- [ ] **Step 1: Flag backend.** En `SolicitudController::show`: `'puedeAjustar' => $usuario->can('ajustar', $solicitud)`.

- [ ] **Step 2: Copy notificaciones.** En `PanelNotificaciones.jsx` `mensajeNotificacion()` añadir casos:
```jsx
        case 'ajustada':   return `Comisión ajustada: ${n.radicado}`;
        case 'cancelada':  return `Comisión cancelada: ${n.radicado}`;
        case 'reactivada': return `Comisión reactivada: ${n.radicado}`;
```
Y estilos en `ESTILO_TIPO` (ajustada/reactivada ámbar-info; cancelada rojo).

- [ ] **Step 3: Modal de ajuste en Detalle.** Añadir `puedeAjustar` a la firma. Botón "Ajustar comisión" (solo si `puedeAjustar` y es viáticos) que abre un modal con la lista de viajeros (fecha/hora salida y regreso editables) + campo motivo. Estado local `useForm({ motivo: '', viajeros: [...] })` inicializado desde `solicitud.solicitable.viajeros`. Al guardar: `put(route('viaticos.ajustar', solicitud.id))`. Reutilizar el componente `Modal`. Mostrar errores de validación.

> El implementador construye el modal siguiendo el patrón de `ModalComprobantes`/`Modal` ya usado en Detalle.jsx. Cada fila: nombre del viajero + 4 inputs (fecha/hora salida, fecha/hora regreso). El motivo es un textarea obligatorio.

- [ ] **Step 4: Build** ⇒ `✓ built`.

- [ ] **Step 5: Commit**
```bash
git add app/Http/Controllers/SolicitudController.php resources/js/Pages/Solicitudes/Detalle.jsx resources/js/Components/PanelNotificaciones.jsx
git commit -m "feat(viaticos): modal de ajuste de comision y copy de notificaciones (ajustada/cancelada/reactivada)"
```

---

## Task Final: Verificación y re-seed en dev

- [ ] **Step 1: Suite completa** — `/c/xampp/php/php.exe artisan test` ⇒ todos verdes.
- [ ] **Step 2: Build** — `npm run build` ⇒ `✓ built`.
- [ ] **Step 3: Re-seed del tipo VIA en MariaDB** (el seeder cambió: nuevo estado `cancelada`) — `/c/xampp/php/php.exe artisan db:seed --class=TipoSolicitudSeeder --force`.
- [ ] **Step 4: Aplicar migraciones en MariaDB** — `/c/xampp/php/php.exe artisan migrate --force` (columnas `salida_confirmada` y `estado_previo`).
- [ ] **Step 5: git status** limpio.
