# Viáticos — Ajustes v2 Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** (1) Permitir ajustar comisiones ya cerradas (anexo, no reabre) y mostrar los ajustes en una tabla separada en el detalle; (2) añadir el rubro `transporte` y permitir que el líder solicite un reajuste de gasolina/transporte por viajero que el contador aplica.

**Architecture:** Se enriquece la transición `ajustar` con `metadatos` (JSON) que describe cada ajuste, base para la tabla. La policy `ajustar` deja de excluir `cerrada`. La tabla de ajustes filtra `transiciones` por `accion='ajustar'`. El reajuste de rubro es un endpoint nuevo (líder solicita → transición `ajustar` con metadatos tipo rubro → contador reincorpora al liquidar). Rubro `transporte`: enum PHP + tarifa + ALTER del enum de la columna (driver-aware).

**Tech Stack:** Laravel 10.50, PHP 8.2 (`/c/xampp/php/php.exe` en CLI), Inertia/React 18, PHPUnit + SQLite `:memory:`. Migraciones idempotentes; MariaDB en dev necesita `db:seed` y `migrate` tras cambios de enum/seeder.

**Spec:** `docs/superpowers/specs/2026-08-20-viaticos-ajustes-v2-design.md`

---

## Bloque 1 — Metadatos del ajuste

### Task 1.1: Guardar metadatos en la transición `ajustar`

**Files:**
- Modify: `app/Http/Controllers/ViaticosController.php`
- Modify: `tests/Feature/AjustarComisionTest.php`

**Contexto:** El método `ajustar` (líneas ~234-273) edita fechas/horas por viajero y crea `TransicionSolicitud` con `accion='ajustar'` y `comentario=motivo`, pero SIN `metadatos`. Hay que capturar el "antes" (leyendo el viajero antes de actualizar) y el "después" (del request), y guardarlos en `metadatos`.

- [ ] **Step 1: Test del metadato**

Añadir a `AjustarComisionTest`:
```php
    public function test_ajuste_guarda_metadatos_de_fechas(): void
    {
        $this->seed();
        [$s, $v, $lider] = $this->comisionConViajero('liquidada');
        $this->ajustar($s, $v, $lider)->assertRedirect();

        $t = \App\Models\TransicionSolicitud::where('solicitud_id', $s->id)->where('accion', 'ajustar')->latest('id')->first();
        $this->assertNotNull($t);
        $this->assertEquals('fechas', $t->metadatos['tipo'] ?? null);
        $this->assertEquals($v->id, $t->metadatos['viajeros'][0]['viajero_comision_id'] ?? null);
        // El "despues" refleja el regreso extendido del helper ajustar() (2026-08-24).
        $this->assertEquals('2026-08-24', $t->metadatos['viajeros'][0]['despues']['fecha_regreso'] ?? null);
    }
```

- [ ] **Step 2: Ejecutar (falla: metadatos null).**

Run: `/c/xampp/php/php.exe artisan test --filter=test_ajuste_guarda_metadatos_de_fechas`
Expected: FAIL.

- [ ] **Step 3: Modificar `ajustar`**

En el método, antes/dentro de la transacción, construir el detalle. Reemplazar el bucle de actualización para capturar antes/después:
```php
        $detalle = [];
        DB::transaction(function () use ($request, $solicitud, $cabecera, $origen, $destino, $yaLiquidada, &$detalle) {
            foreach ($request->viajeros as $datos) {
                $viajero = $cabecera->viajeros()->where('id', $datos['viajero_comision_id'])->first();
                if (! $viajero) continue;
                $detalle[] = [
                    'viajero_comision_id' => $viajero->id,
                    'nombre' => $viajero->nombreMostrado,
                    'antes' => [
                        'fecha_salida'  => optional($viajero->fecha_salida)->toDateString() ?? $viajero->fecha_salida,
                        'hora_salida'   => $viajero->hora_salida,
                        'fecha_regreso' => optional($viajero->fecha_regreso)->toDateString() ?? $viajero->fecha_regreso,
                        'hora_regreso'  => $viajero->hora_regreso,
                    ],
                    'despues' => [
                        'fecha_salida'  => $datos['fecha_salida'],  'hora_salida'  => $datos['hora_salida'],
                        'fecha_regreso' => $datos['fecha_regreso'], 'hora_regreso' => $datos['hora_regreso'],
                    ],
                ];
                $viajero->update([
                    'fecha_salida'  => $datos['fecha_salida'],  'hora_salida'  => $datos['hora_salida'],
                    'fecha_regreso' => $datos['fecha_regreso'], 'hora_regreso' => $datos['hora_regreso'],
                ]);
            }
            if ($destino !== $origen) {
                $solicitud->update(['estado' => $destino]);
            }
            if ($yaLiquidada) {
                $cabecera->updateQuietly(['requiere_reliquidacion' => true]);
            }
            TransicionSolicitud::create([
                'solicitud_id' => $solicitud->id, 'estado_origen' => $origen,
                'estado_destino' => $destino, 'accion' => 'ajustar',
                'usuario_id' => auth()->id(), 'comentario' => $request->motivo,
                'metadatos' => ['tipo' => 'fechas', 'viajeros' => $detalle],
            ]);
        });
```

- [ ] **Step 4: Ejecutar (pasa).** Suite de ajuste verde.

Run: `/c/xampp/php/php.exe artisan test --filter=AjustarComisionTest`
Expected: PASS todos.

- [ ] **Step 5: Commit**
```bash
git add app/Http/Controllers/ViaticosController.php tests/Feature/AjustarComisionTest.php
git commit -m "feat(viaticos): la transicion ajustar guarda metadatos (viajero, antes/despues)"
```

---

## Bloque 2 — Ajustar tras cerrada + tabla de ajustes

### Task 2.1: Policy permite ajustar comisión cerrada

**Files:**
- Modify: `app/Policies/SolicitudPolicy.php`
- Modify: `tests/Feature/AjustarComisionTest.php`

- [ ] **Step 1: Reemplazar el test que bloquea cerrada**

En `AjustarComisionTest`, reemplazar `test_no_ajusta_cerrada` por:
```php
    public function test_lider_ajusta_comision_cerrada_como_anexo(): void
    {
        $this->seed();
        [$s, $v, $lider] = $this->comisionConViajero('cerrada');

        $this->ajustar($s, $v, $lider)->assertRedirect();

        // Sigue cerrada (anexo, no reabre) y NO exige reliquidacion.
        $this->assertEquals('cerrada', $s->fresh()->estado);
        $this->assertFalse($s->solicitable->fresh()->requiere_reliquidacion);
        $this->assertDatabaseHas('transiciones_solicitud', ['solicitud_id' => $s->id, 'accion' => 'ajustar']);
    }
```

- [ ] **Step 2: Ejecutar (falla: hoy 403 en cerrada).**

- [ ] **Step 3: Ajustar la policy**

En `SolicitudPolicy::ajustar`, cambiar:
```php
        && ! in_array($solicitud->estado, ['cerrada', 'cancelada']);
```
por:
```php
        && $solicitud->estado !== 'cancelada';
```
(El ajuste se permite en cualquier estado salvo `cancelada` — incluido `cerrada`.)

- [ ] **Step 4: Ejecutar (pasa).** Confirmar que `test_ajuste_desde_enviada_no_cambia_estado` y demás siguen verdes.

Run: `/c/xampp/php/php.exe artisan test --filter=AjustarComisionTest`
Expected: PASS.

- [ ] **Step 5: Commit**
```bash
git add app/Policies/SolicitudPolicy.php tests/Feature/AjustarComisionTest.php
git commit -m "feat(viaticos): permitir ajustar comision cerrada (queda como anexo)"
```

### Task 2.2: Tabla de ajustes en el detalle

**Files:**
- Modify: `resources/js/Pages/Solicitudes/Detalle.jsx`

**Contexto:** En `DetalleViaticos`, debajo de la tabla de viajeros, añadir una tabla "Ajustes" que filtra las transiciones `ajustar`. Las transiciones llegan en `solicitud.transiciones` (con `metadatos`, `comentario`, `created_at`, `usuario`). `DetalleViaticos` hoy recibe `solicitable`, `solicitudId`, `cerrada`, `puedeGestionarComprobante` — necesita también las `transiciones` y los viajeros para resolver acciones. Pasar `transiciones={solicitud.transiciones}` desde el componente `Detalle` (que ya las tiene).

- [ ] **Step 1: Pasar transiciones a DetalleViaticos**

En el componente `Detalle`, en la invocación de `<DetalleViaticos .../>`, añadir:
```jsx
transiciones={solicitud.transiciones ?? []}
```
Y en la firma de `DetalleViaticos`, añadir `transiciones = []`.

- [ ] **Step 2: Construir la lista de ajustes y helpers**

Dentro de `DetalleViaticos`, tras `const viajeros = ...`:
```jsx
    const ajustes = (transiciones ?? []).filter((t) => t.accion === 'ajustar');
    const viajeroDe = (id) => viajeros.find((v) => v.id === id) ?? null;
    const describeCambio = (m) => {
        if (!m) return '—';
        if (m.tipo === 'rubro') {
            return `${etiquetaRubro(m.rubro)} × ${m.cantidad} (solicitado)`;
        }
        // tipo fechas: resumen del primer viajero (o "N viajeros")
        const n = (m.viajeros ?? []).length;
        if (n === 0) return 'Ajuste de fechas';
        const v0 = m.viajeros[0];
        const resumen = `${v0.antes?.fecha_regreso ?? '?'} → ${v0.despues?.fecha_regreso ?? '?'}`;
        return n === 1 ? `Fechas: ${resumen}` : `Fechas (${n} viajeros)`;
    };
```

- [ ] **Step 3: Renderizar la tabla de ajustes (solo si hay)**

Justo después del bloque de la tabla de viajeros (tras su `</>`), añadir:
```jsx
{ajustes.length > 0 && (
    <div className="border-t border-slate-100 pt-4">
        <p className="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-3">
            Ajustes ({ajustes.length})
        </p>
        <div className="overflow-x-auto rounded-lg border border-amber-100">
            <table className="w-full text-sm">
                <thead className="bg-amber-50 border-b border-amber-100">
                    <tr className="text-left text-xs text-amber-700">
                        <th className="px-3 py-2 font-medium">Ajuste</th>
                        <th className="px-3 py-2 font-medium">Viajero</th>
                        <th className="px-3 py-2 font-medium">Cambio</th>
                        <th className="px-3 py-2 font-medium">Motivo</th>
                        <th className="px-3 py-2 font-medium whitespace-nowrap">Fecha</th>
                        <th className="px-3 py-2 font-medium">Por</th>
                        <th className="px-3 py-2 font-medium text-left whitespace-nowrap">Acciones</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-slate-50">
                    {ajustes.flatMap((t) => {
                        const m = t.metadatos ?? {};
                        // Un ajuste puede afectar a varios viajeros (tipo fechas) o a uno (rubro).
                        const filas = m.tipo === 'rubro'
                            ? [{ viajero_comision_id: m.viajero_comision_id, nombre: m.nombre }]
                            : (m.viajeros ?? [{ nombre: '—' }]);
                        return filas.map((f, i) => {
                            const v = viajeroDe(f.viajero_comision_id);
                            return (
                                <tr key={`${t.id}-${i}`} className="hover:bg-amber-50/40">
                                    {i === 0 && (
                                        <td rowSpan={filas.length} className="px-3 py-2.5 align-top">
                                            <span className="inline-flex items-center px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 text-xs font-medium">Ajuste</span>
                                        </td>
                                    )}
                                    <td className="px-3 py-2.5 font-medium text-slate-800 whitespace-nowrap">{f.nombre ?? '—'}</td>
                                    <td className="px-3 py-2.5 text-slate-600">{i === 0 ? describeCambio(m) : ''}</td>
                                    <td className="px-3 py-2.5 text-slate-600 max-w-xs">{i === 0 ? <p className="truncate" title={t.comentario}>{t.comentario || '—'}</p> : ''}</td>
                                    <td className="px-3 py-2.5 text-slate-500 whitespace-nowrap">{i === 0 ? formatearFechaHoraCompleta(t.created_at) : ''}</td>
                                    <td className="px-3 py-2.5 text-slate-500 whitespace-nowrap">{i === 0 ? (t.usuario?.name ?? '—') : ''}</td>
                                    <td className="px-3 py-2.5 whitespace-nowrap">
                                        {v ? (
                                            <div className="flex items-center gap-1">
                                                <button type="button" onClick={() => setRubrosDe(v)}
                                                    className="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-lg text-blue-600 border border-blue-300 hover:bg-blue-50" title="Ver rubros">
                                                    <EyeIcon className="w-4 h-4" /> Rubros
                                                </button>
                                                <button type="button" onClick={() => setComprobantesDeId(v.id)}
                                                    className="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-lg text-slate-600 border border-slate-300 hover:bg-slate-50" title="Comprobantes">
                                                    <PaperClipIcon className="w-4 h-4" />
                                                </button>
                                                {cerrada && (
                                                    <>
                                                        <a href={route('liquidacion.pdf', [solicitudId, v.id])}
                                                            className="inline-flex items-center gap-1 px-2 py-1 text-xs font-medium rounded-lg text-slate-600 border border-slate-300 hover:bg-slate-50" title="PDF">
                                                            <PrinterIcon className="w-4 h-4" />
                                                        </a>
                                                        <button type="button" onClick={() => enviarCorreo(v.id)} disabled={!v.empleado?.email}
                                                            className="inline-flex items-center gap-1 px-2 py-1 text-xs text-blue-600 rounded-lg border border-blue-300 hover:bg-blue-50 disabled:opacity-40" title="Correo">
                                                            <EnvelopeIcon className="w-4 h-4" />
                                                        </button>
                                                    </>
                                                )}
                                            </div>
                                        ) : <span className="text-slate-400 text-xs">—</span>}
                                    </td>
                                </tr>
                            );
                        });
                    })}
                </tbody>
            </table>
        </div>
    </div>
)}
```

> Verificar que `formatearFechaHoraCompleta`, `etiquetaRubro`, `EyeIcon`, `PaperClipIcon`, `PrinterIcon`, `EnvelopeIcon` ya estén importados en el archivo (lo están, se usan en la tabla de viajeros y el historial). `setRubrosDe`, `setComprobantesDeId`, `enviarCorreo` ya existen en `DetalleViaticos`.

- [ ] **Step 4: Build**

Run: `npm run build`
Expected: `✓ built`.

- [ ] **Step 5: Commit**
```bash
git add resources/js/Pages/Solicitudes/Detalle.jsx
git commit -m "feat(viaticos): tabla de ajustes en el detalle con las acciones del viajero"
```

---

## Bloque 3 — Rubro transporte + reajuste de rubro por el líder

### Task 3.1: Rubro `transporte` (enum, tarifa, migración)

**Files:**
- Modify: `app/Enums/Rubro.php`
- Modify: `database/seeders/TarifaViaticosSeeder.php`
- Create: `database/migrations/2026_08_20_130000_add_transporte_to_asignaciones_rubro_enum.php`
- Modify: `tests/Feature/` (nuevo test)

- [ ] **Step 1: Enum** — en `app/Enums/Rubro.php` añadir:
```php
    case Transporte = 'transporte';
```

- [ ] **Step 2: Seeder** — en `TarifaViaticosSeeder`, añadir al array:
```php
    ['rubro' => 'transporte', 'valor_sugerido' => 0],
```

- [ ] **Step 3: Migración driver-aware del enum**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE asignaciones_viaticos MODIFY rubro ENUM('desayuno','almuerzo','cena','merienda','gasolina','transporte')");
        }
        // SQLite (tests): la columna se crea sin CHECK estricto del enum, acepta 'transporte' sin ALTER.
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE asignaciones_viaticos MODIFY rubro ENUM('desayuno','almuerzo','cena','merienda','gasolina')");
        }
    }
};
```

- [ ] **Step 4: Test de que transporte se puede asignar**

`tests/Feature/RubroTransporteTest.php`:
```php
<?php
namespace Tests\Feature;

use App\Enums\Rubro;
use App\Models\{AsignacionViatico, Empleados, SolicitudViaticos, TarifaViatico, ViajeroComision};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RubroTransporteTest extends TestCase
{
    use RefreshDatabase;

    public function test_transporte_existe_como_tarifa(): void
    {
        $this->seed();
        $this->assertDatabaseHas('tarifas_viaticos', ['rubro' => 'transporte']);
        $this->assertTrue(Rubro::tryFrom('transporte') === Rubro::Transporte);
    }

    public function test_se_puede_asignar_transporte_a_un_viajero(): void
    {
        $this->seed();
        $cab = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => '', 'observacion' => 'x']);
        $v = ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => Empleados::first()->id,
            'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
        ]);
        $a = AsignacionViatico::create([
            'viajero_comision_id' => $v->id, 'rubro' => 'transporte',
            'valor_unitario' => 30000, 'dias' => 2,
        ]);
        $this->assertEquals(Rubro::Transporte, $a->fresh()->rubro);
        $this->assertEquals(60000, $a->fresh()->subtotal);
    }
}
```

- [ ] **Step 5: Ejecutar** — `/c/xampp/php/php.exe artisan test --filter=RubroTransporteTest` ⇒ PASS. Suite completa verde.

- [ ] **Step 6: Commit**
```bash
git add app/Enums/Rubro.php database/seeders/TarifaViaticosSeeder.php database/migrations/2026_08_20_130000_add_transporte_to_asignaciones_rubro_enum.php tests/Feature/RubroTransporteTest.php
git commit -m "feat(viaticos): nuevo rubro transporte (enum, tarifa 0, enum BD driver-aware)"
```

### Task 3.2: Endpoint reajustar-rubro (líder solicita)

**Files:**
- Create: `app/Http/Requests/ReajustarRubroRequest.php`
- Modify: `app/Http/Controllers/ViaticosController.php`
- Modify: `routes/web.php`
- Create: `tests/Feature/ReajustarRubroTest.php`

- [ ] **Step 1: Tests**

`tests/Feature/ReajustarRubroTest.php`:
```php
<?php
namespace Tests\Feature;

use App\Models\{Empleados, Solicitud, SolicitudViaticos, TipoSolicitud, Usuario, ViajeroComision};
use App\Notifications\AvisoTransicionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ReajustarRubroTest extends TestCase
{
    use RefreshDatabase;

    private function comision(string $estado = 'liquidada'): array
    {
        $tipo = TipoSolicitud::where('clave','VIA')->firstOrFail();
        $lider = Usuario::where('email','lider.comite@demo.test')->firstOrFail();
        $cab = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => '', 'observacion' => 'x']);
        $v = ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => Empleados::first()->id,
            'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
        ]);
        $s = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $lider->id,
            'solicitable_type' => SolicitudViaticos::class, 'solicitable_id' => $cab->id,
            'estado' => $estado, 'radicado' => Solicitud::generarRadicado($tipo),
        ]);
        return [$s, $v, $lider];
    }

    public function test_lider_reajusta_gasolina_y_notifica(): void
    {
        Notification::fake();
        $this->seed();
        [$s, $v, $lider] = $this->comision('liquidada');

        $this->actingAs($lider)->post(route('viaticos.reajustar-rubro', $s), [
            'viajero_comision_id' => $v->id, 'rubro' => 'gasolina', 'cantidad' => 3, 'motivo' => 'Camioneta empresa',
        ])->assertRedirect();

        $t = \App\Models\TransicionSolicitud::where('solicitud_id', $s->id)->where('accion','ajustar')->latest('id')->first();
        $this->assertEquals('rubro', $t->metadatos['tipo'] ?? null);
        $this->assertEquals('gasolina', $t->metadatos['rubro'] ?? null);
        $this->assertEquals(3, $t->metadatos['cantidad'] ?? null);
        // Regresa a liquidada y exige reliquidacion.
        $this->assertEquals('liquidada', $s->fresh()->estado);
        $this->assertTrue($s->solicitable->fresh()->requiere_reliquidacion);
        Notification::assertSentTo(Usuario::where('email','contador@demo.test')->firstOrFail(), AvisoTransicionNotification::class);
    }

    public function test_reajuste_de_rubro_en_cerrada_queda_anexo(): void
    {
        $this->seed();
        [$s, $v, $lider] = $this->comision('cerrada');
        $this->actingAs($lider)->post(route('viaticos.reajustar-rubro', $s), [
            'viajero_comision_id' => $v->id, 'rubro' => 'transporte', 'cantidad' => 1, 'motivo' => 'x',
        ])->assertRedirect();

        $this->assertEquals('cerrada', $s->fresh()->estado);
        $this->assertFalse($s->solicitable->fresh()->requiere_reliquidacion);
        $this->assertDatabaseHas('transiciones_solicitud', ['solicitud_id' => $s->id, 'accion' => 'ajustar']);
    }

    public function test_no_solicitante_no_reajusta_rubro(): void
    {
        $this->seed();
        [$s, $v] = $this->comision('liquidada');
        $otro = Usuario::where('email','contador@demo.test')->firstOrFail();
        $this->actingAs($otro)->post(route('viaticos.reajustar-rubro', $s), [
            'viajero_comision_id' => $v->id, 'rubro' => 'gasolina', 'cantidad' => 1, 'motivo' => 'x',
        ])->assertForbidden();
    }

    public function test_rubro_invalido_es_rechazado(): void
    {
        $this->seed();
        [$s, $v, $lider] = $this->comision('liquidada');
        $this->actingAs($lider)->from(route('solicitudes.show', $s))->post(route('viaticos.reajustar-rubro', $s), [
            'viajero_comision_id' => $v->id, 'rubro' => 'desayuno', 'cantidad' => 1, 'motivo' => 'x',
        ])->assertSessionHasErrors('rubro');
    }
}
```

- [ ] **Step 2: Ejecutar (falla: ruta no existe).**

- [ ] **Step 3: Request `ReajustarRubroRequest`**
```php
<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReajustarRubroRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'viajero_comision_id' => 'required|exists:viajeros_comision,id',
            'rubro'               => 'required|in:gasolina,transporte',
            'cantidad'            => 'required|integer|min:1',
            'motivo'              => 'required|string|max:2000',
        ];
    }

    public function messages(): array
    {
        return [
            'rubro.in'        => 'El reajuste de rubro solo aplica a gasolina o transporte.',
            'motivo.required' => 'Indique el motivo del reajuste.',
        ];
    }
}
```

- [ ] **Step 4: Método `reajustarRubro` en `ViaticosController`**
```php
    public function reajustarRubro(\App\Http\Requests\ReajustarRubroRequest $request, Solicitud $solicitud)
    {
        $this->authorize('ajustar', $solicitud);
        $cabecera = $solicitud->solicitable;
        $viajero = $cabecera->viajeros()->where('id', $request->viajero_comision_id)->firstOrFail();

        $origen = $solicitud->estado;
        $yaLiquidada = in_array($origen, ['liquidada', 'revisada', 'en_gerencia']);
        $destino = $yaLiquidada ? 'liquidada' : $origen;

        DB::transaction(function () use ($request, $solicitud, $cabecera, $viajero, $origen, $destino, $yaLiquidada) {
            if ($destino !== $origen) {
                $solicitud->update(['estado' => $destino]);
            }
            if ($yaLiquidada) {
                $cabecera->updateQuietly(['requiere_reliquidacion' => true]);
            }
            TransicionSolicitud::create([
                'solicitud_id' => $solicitud->id, 'estado_origen' => $origen,
                'estado_destino' => $destino, 'accion' => 'ajustar',
                'usuario_id' => auth()->id(), 'comentario' => $request->motivo,
                'metadatos' => [
                    'tipo' => 'rubro',
                    'viajero_comision_id' => $viajero->id,
                    'nombre' => $viajero->nombreMostrado,
                    'rubro' => $request->rubro,
                    'cantidad' => (int) $request->cantidad,
                ],
            ]);
        });

        $this->avisarAjuste($solicitud->fresh(), $request->motivo, $yaLiquidada);
        return back()->with('success', 'Reajuste de rubro registrado. El contador lo aplicará en la liquidación.');
    }
```

> `avisarAjuste($solicitud, $motivo, $regresoAlContador)` ya existe (Bloque anterior): notifica al contador acción-requerida cuando `$regresoAlContador` es true, y a rrhh/contabilidad informativo. Aquí pasamos `$yaLiquidada`.

- [ ] **Step 5: Ruta**
```php
Route::post('/viaticos/{solicitud}/reajustar-rubro', [ViaticosController::class, 'reajustarRubro'])->name('viaticos.reajustar-rubro');
```

- [ ] **Step 6: Ejecutar tests** ⇒ PASS. Suite completa verde.

- [ ] **Step 7: Commit**
```bash
git add app/Http/Requests/ReajustarRubroRequest.php app/Http/Controllers/ViaticosController.php routes/web.php tests/Feature/ReajustarRubroTest.php
git commit -m "feat(viaticos): el lider solicita reajuste de gasolina/transporte por viajero"
```

### Task 3.3: Modal de reajuste de rubro en el detalle

**Files:**
- Modify: `resources/js/Pages/Solicitudes/Detalle.jsx`

- [ ] **Step 1: Estado y form del reajuste**

En el componente `Detalle`, añadir estado `const [reajustando, setReajustando] = useState(false)` y un `useForm`:
```jsx
    const viajerosReaj = solicitud.solicitable?.viajeros ?? [];
    const formRubro = useForm({ viajero_comision_id: '', rubro: 'gasolina', cantidad: 1, motivo: '' });
    const guardarReajusteRubro = () => formRubro.post(route('viaticos.reajustar-rubro', solicitud.id), {
        preserveScroll: true, onSuccess: () => { setReajustando(false); formRubro.reset(); },
    });
```

- [ ] **Step 2: Botón "Reajustar transporte/gasolina"**

Junto al botón "Ajustar comisión" (gated por `puedeAjustar && esViaticos`), añadir:
```jsx
{puedeAjustar && esViaticos && (
    <button type="button" onClick={() => setReajustando(true)}
        className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-amber-600 hover:bg-amber-700 rounded-lg">
        Reajustar transporte/gasolina
    </button>
)}
```

- [ ] **Step 3: Modal**

Junto a los otros modales, añadir:
```jsx
<Modal show={reajustando} onClose={() => setReajustando(false)} maxWidth="md">
    <div className="p-6">
        <div className="flex items-start justify-between mb-4">
            <div>
                <h3 className="text-base font-semibold text-slate-800">Reajustar rubro de transporte</h3>
                <p className="text-sm text-slate-500 mt-0.5">Solicita gasolina (camioneta) o transporte para un viajero. El contador aplicará el valor.</p>
            </div>
            <button type="button" onClick={() => setReajustando(false)} className="text-slate-400 hover:text-slate-600 text-xl leading-none">×</button>
        </div>
        <form onSubmit={(e) => { e.preventDefault(); guardarReajusteRubro(); }} className="space-y-4">
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Viajero</label>
                <select value={formRubro.data.viajero_comision_id}
                    onChange={(e) => formRubro.setData('viajero_comision_id', e.target.value)}
                    className="w-full rounded-lg border border-slate-300 text-sm px-3 py-2">
                    <option value="">— Seleccionar —</option>
                    {viajerosReaj.map((v) => (
                        <option key={v.id} value={v.id}>{v.empleado ? `${v.empleado.nombres} ${v.empleado.apellidos}` : (v.nombre_externo || 'Viajero')}</option>
                    ))}
                </select>
                {formRubro.errors.viajero_comision_id && <p className="text-red-500 text-xs mt-1">{formRubro.errors.viajero_comision_id}</p>}
            </div>
            <div className="grid grid-cols-2 gap-3">
                <div>
                    <label className="block text-xs font-medium text-slate-600 mb-1">Rubro</label>
                    <select value={formRubro.data.rubro} onChange={(e) => formRubro.setData('rubro', e.target.value)}
                        className="w-full rounded-lg border border-slate-300 text-sm px-3 py-2">
                        <option value="gasolina">Gasolina (camioneta)</option>
                        <option value="transporte">Transporte</option>
                    </select>
                    {formRubro.errors.rubro && <p className="text-red-500 text-xs mt-1">{formRubro.errors.rubro}</p>}
                </div>
                <div>
                    <label className="block text-xs font-medium text-slate-600 mb-1">Cantidad</label>
                    <input type="number" min={1} value={formRubro.data.cantidad}
                        onChange={(e) => formRubro.setData('cantidad', parseInt(e.target.value) || 1)}
                        className="w-full rounded-lg border border-slate-300 text-sm px-3 py-2" />
                </div>
            </div>
            <div>
                <label className="block text-xs font-medium text-slate-600 mb-1">Motivo</label>
                <textarea rows={2} value={formRubro.data.motivo} onChange={(e) => formRubro.setData('motivo', e.target.value)}
                    className="w-full rounded-lg border border-slate-300 text-sm px-3 py-2" placeholder="Describe el motivo del reajuste…" />
                {formRubro.errors.motivo && <p className="text-red-500 text-xs mt-1">{formRubro.errors.motivo}</p>}
            </div>
            <div className="flex justify-end gap-3">
                <button type="button" onClick={() => setReajustando(false)} className="px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">Cancelar</button>
                <button type="submit" disabled={formRubro.processing} className="px-4 py-2 text-sm font-medium text-white bg-amber-600 hover:bg-amber-700 rounded-lg disabled:opacity-50">
                    {formRubro.processing ? 'Enviando…' : 'Solicitar reajuste'}
                </button>
            </div>
        </form>
    </div>
</Modal>
```

- [ ] **Step 4: Build** ⇒ `✓ built`.

- [ ] **Step 5: Commit**
```bash
git add resources/js/Pages/Solicitudes/Detalle.jsx
git commit -m "feat(viaticos): modal para solicitar reajuste de gasolina/transporte"
```

---

## Task Final: verificación y despliegue en dev

- [ ] **Step 1: Suite completa** — `/c/xampp/php/php.exe artisan test` ⇒ todos verdes.
- [ ] **Step 2: Build** — `npm run build` ⇒ `✓ built`.
- [ ] **Step 3: Migración enum en MariaDB** — `/c/xampp/php/php.exe artisan migrate --force`.
- [ ] **Step 4: Re-seed de tarifas** — `/c/xampp/php/php.exe artisan db:seed --class=TarifaViaticosSeeder --force` (añade `transporte`).
- [ ] **Step 5: git status** limpio.
