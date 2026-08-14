# Total a pagar definido en el primer abono de oficina — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Al registrar el primer pago de una solicitud de oficina, el líder de contabilidad ingresa el valor total real a pagar; a partir de ese valor se calculan el saldo y los abonos (parciales o pago único), sin exceder el total. Los pagos y sus soportes se muestran a RR. HH.

**Architecture:** Se añade `solicitudes_oficina.total_a_pagar` (nullable). El saldo pasa a calcularse contra ese valor (`saldoPendiente()`), reemplazando `saldo()` (que usaba el estimado). El request de abono valida condicionalmente: `total_a_pagar` requerido en el primer pago y monto ≤ total; en los siguientes, monto ≤ saldo pendiente. La UI pide el total (con atajo "pago total") solo en el primer pago.

**Tech Stack:** PHP 8.2, Laravel 10.50, Inertia 0.6, React 18, PHPUnit + SQLite `:memory:`, Storage disk `local`.

**Spec:** [docs/superpowers/specs/2026-08-12-total-a-pagar-en-primer-abono-oficina-design.md](../specs/2026-08-12-total-a-pagar-en-primer-abono-oficina-design.md)

---

## Estructura de archivos

**Crear:**
- `database/migrations/2026_08_12_100000_add_total_a_pagar_to_solicitudes_oficina_table.php`
- `tests/Feature/TotalAPagarOficinaTest.php`

**Modificar:**
- `app/Models/SolicitudOficina.php` — `total_a_pagar` en fillable/cast; `saldoPendiente()`, `estaPagadaCompleta()`; eliminar `saldo()`.
- `app/Http/Requests/RegistrarAbonoOficinaRequest.php` — reglas condicionales + `withValidator`.
- `app/Http/Controllers/AbonoOficinaController.php` — guardar `total_a_pagar` en el primer abono.
- `app/Http/Resources/SolicitudDetalleResource.php` — exponer `total_a_pagar`/`total_estimado`/`tiene_total`/saldo real.
- `app/Http/Controllers/ComisionesRrhhController.php` — usar `saldoPendiente()` y `total_a_pagar`.
- `resources/js/Pages/Solicitudes/Detalle.jsx` — campo "Total a pagar" + atajo en el primer pago; resumen.
- `tests/Feature/AbonoOficinaTest.php` — actualizar POSTs para incluir `total_a_pagar` y reemplazar `saldo()`.

---

# FASE 1 — Modelo de datos

## Task 1: Migración y helpers de `total_a_pagar`

**Files:**
- Create: `database/migrations/2026_08_12_100000_add_total_a_pagar_to_solicitudes_oficina_table.php`
- Modify: `app/Models/SolicitudOficina.php`
- Test: `tests/Feature/TotalAPagarOficinaTest.php`

- [ ] **Step 1: Escribir la migración**

Crear `database/migrations/2026_08_12_100000_add_total_a_pagar_to_solicitudes_oficina_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('solicitudes_oficina', 'total_a_pagar')) {
            Schema::table('solicitudes_oficina', function (Blueprint $table) {
                $table->decimal('total_a_pagar', 14, 2)->nullable()->after('total');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('solicitudes_oficina', 'total_a_pagar')) {
            Schema::table('solicitudes_oficina', function (Blueprint $table) {
                $table->dropColumn('total_a_pagar');
            });
        }
    }
};
```

- [ ] **Step 2: Actualizar el modelo `SolicitudOficina`**

En `app/Models/SolicitudOficina.php`:

Añadir `total_a_pagar` a `$fillable` (la línea actual es
`protected $fillable = ['beneficiario', 'urgencia', 'justificacion', 'total', 'valor_pagado', 'fecha_pago', 'comprobante', 'cotizacion_path', 'comentario_contador'];`):

```php
    protected $fillable = ['beneficiario', 'urgencia', 'justificacion', 'total', 'total_a_pagar', 'valor_pagado', 'fecha_pago', 'comprobante', 'cotizacion_path', 'comentario_contador'];
    protected $casts = ['urgencia' => UrgenciaOficina::class, 'fecha_pago' => 'date', 'total_a_pagar' => 'decimal:2'];
```

Reemplazar el método `saldo()` (actualmente líneas 45-48) por `saldoPendiente()` y `estaPagadaCompleta()`:

```php
    public function saldoPendiente(): float
    {
        // Antes de fijar el total real (sin pagos aun), no hay saldo aplicable.
        if ($this->total_a_pagar === null) {
            return 0.0;
        }
        return (float) $this->total_a_pagar - $this->totalPagado();
    }

    public function estaPagadaCompleta(): bool
    {
        return $this->total_a_pagar !== null && $this->totalPagado() >= (float) $this->total_a_pagar;
    }
```

- [ ] **Step 3: Escribir el test del modelo**

Crear `tests/Feature/TotalAPagarOficinaTest.php`:

```php
<?php
namespace Tests\Feature;

use App\Models\{AbonoOficina, SolicitudOficina, Usuario};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TotalAPagarOficinaTest extends TestCase
{
    use RefreshDatabase;

    public function test_saldo_pendiente_se_calcula_contra_total_a_pagar(): void
    {
        $this->seed();
        $u = Usuario::where('email', 'contabilidad.lider@demo.test')->firstOrFail();
        $c = SolicitudOficina::create([
            'beneficiario' => '', 'urgencia' => 'media', 'justificacion' => 'x',
            'total' => 999999, 'total_a_pagar' => 100000,
        ]);
        AbonoOficina::create([
            'solicitud_oficina_id' => $c->id, 'monto' => 40000, 'fecha_pago' => '2026-08-12',
            'soporte_path' => 'soportes_pago/a.pdf', 'soporte_nombre' => 'a.pdf', 'usuario_id' => $u->id,
        ]);

        // Saldo contra total_a_pagar (100000), no contra el estimado (999999).
        $this->assertEquals(60000.0, $c->fresh()->saldoPendiente());
        $this->assertFalse($c->fresh()->estaPagadaCompleta());
    }

    public function test_sin_total_a_pagar_no_hay_saldo(): void
    {
        $c = SolicitudOficina::create([
            'beneficiario' => '', 'urgencia' => 'media', 'justificacion' => 'x', 'total' => 50000,
        ]);
        $this->assertNull($c->total_a_pagar);
        $this->assertEquals(0.0, $c->saldoPendiente());
        $this->assertFalse($c->estaPagadaCompleta());
    }

    public function test_esta_pagada_completa_cuando_pagado_alcanza_el_total(): void
    {
        $this->seed();
        $u = Usuario::where('email', 'contabilidad.lider@demo.test')->firstOrFail();
        $c = SolicitudOficina::create([
            'beneficiario' => '', 'urgencia' => 'media', 'justificacion' => 'x',
            'total' => 0, 'total_a_pagar' => 100000,
        ]);
        AbonoOficina::create([
            'solicitud_oficina_id' => $c->id, 'monto' => 100000, 'fecha_pago' => '2026-08-12',
            'soporte_path' => 'soportes_pago/a.pdf', 'soporte_nombre' => 'a.pdf', 'usuario_id' => $u->id,
        ]);

        $this->assertEquals(0.0, $c->fresh()->saldoPendiente());
        $this->assertTrue($c->fresh()->estaPagadaCompleta());
    }
}
```

- [ ] **Step 4: Ejecutar el test**

Run: `php artisan test --filter=TotalAPagarOficinaTest`
Expected: PASS (3 tests).

- [ ] **Step 5: Commit**

```bash
git add database/migrations/2026_08_12_100000_add_total_a_pagar_to_solicitudes_oficina_table.php app/Models/SolicitudOficina.php tests/Feature/TotalAPagarOficinaTest.php
git commit -m "feat(oficina): total a pagar y saldo pendiente contra el total real"
```

---

## Task 2: Migrar los consumidores de `saldo()` a `saldoPendiente()`

**Files:**
- Modify: `app/Http/Resources/SolicitudDetalleResource.php`
- Modify: `app/Http/Controllers/ComisionesRrhhController.php`
- Modify: `tests/Feature/AbonoOficinaTest.php`

- [ ] **Step 1: Resource — exponer total real, estimado y saldo pendiente**

En `app/Http/Resources/SolicitudDetalleResource.php`, reemplazar el bloque `'pagos' => $this->when($esOficina, fn () => [...])`
(líneas 40-52) por:

```php
            'pagos'       => $this->when($esOficina, fn () => [
                'total_a_pagar'   => $this->solicitable->total_a_pagar !== null ? (float) $this->solicitable->total_a_pagar : null,
                'total_estimado'  => (float) $this->solicitable->total,
                'tiene_total'     => $this->solicitable->total_a_pagar !== null,
                'pagado'          => $this->solicitable->totalPagado(),
                'saldo'           => $this->solicitable->saldoPendiente(),
                'puede_registrar' => $usuario?->can('registrarAbono', $this->resource) ?? false,
                'abonos'          => $this->solicitable->abonos->map(fn ($a) => [
                    'id'          => $a->id,
                    'monto'       => (float) $a->monto,
                    'fecha_pago'  => optional($a->fecha_pago)->toDateString(),
                    'autor'       => $a->usuario?->name,
                    'observacion' => $a->observacion,
                ])->values(),
            ]),
```

- [ ] **Step 2: RR. HH. — usar total real y saldo pendiente**

En `app/Http/Controllers/ComisionesRrhhController.php`, en el `map` de `$oficina` (líneas 64-78), reemplazar
las claves `total` y `saldo`:

```php
                    'total'         => $c->total_a_pagar !== null ? (float) $c->total_a_pagar : null,
                    'pagado'        => $c->totalPagado(),
                    'saldo'         => $c->saldoPendiente(),
```

Y en el `with(...)` de la consulta (línea 57), no hace falta cambio (ya carga `solicitable.abonos`).

- [ ] **Step 3: Actualizar `AbonoOficinaTest` — reemplazar `saldo()` y añadir `total_a_pagar` a los POSTs**

En `tests/Feature/AbonoOficinaTest.php`:

En `test_total_pagado_suma_abonos_y_saldo_es_total_menos_pagado` (líneas 15-34): la cabecera se crea con
`'total' => 100000`; añadir `'total_a_pagar' => 100000` y cambiar el assert `saldo()` por `saldoPendiente()`:

```php
        $cabecera = SolicitudOficina::create([
            'beneficiario' => '', 'urgencia' => 'media', 'justificacion' => 'x', 'total' => 100000, 'total_a_pagar' => 100000,
        ]);
```
```php
        $this->assertEquals(35000.0, $cabecera->fresh()->saldoPendiente());
```

En `test_primer_abono_pasa_la_solicitud_a_pendiente_cierre` (líneas 58-72): el POST debe incluir
`'total_a_pagar' => 100000` (es el primer abono), y el assert de saldo usa `saldoPendiente()`:

```php
        $this->actingAs($cl)->post(route('oficina.abono.store', $s), [
            'monto' => 40000, 'total_a_pagar' => 100000, 'fecha_pago' => '2026-08-06',
            'soporte' => UploadedFile::fake()->create('pago1.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $this->assertEquals('pendiente_cierre', $s->fresh()->estado);
        $this->assertEquals(40000.0, $s->solicitable->fresh()->totalPagado());
        $this->assertEquals(60000.0, $s->solicitable->fresh()->saldoPendiente());
```

En `test_un_abono_puede_cubrir_la_totalidad` (líneas 74-87): POST con `'total_a_pagar' => 100000` y assert
`saldoPendiente()`:

```php
        $this->actingAs($cl)->post(route('oficina.abono.store', $s), [
            'monto' => 100000, 'total_a_pagar' => 100000, 'fecha_pago' => '2026-08-06',
            'soporte' => UploadedFile::fake()->create('total.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $this->assertEquals(0.0, $s->solicitable->fresh()->saldoPendiente());
        $this->assertEquals('pendiente_cierre', $s->fresh()->estado);
```

En los tests restantes que hacen POST de un primer abono, añadir `'total_a_pagar'` al payload para que la
validación (Task 3) no falle:
- `test_solo_contabilidad_lider_registra_abonos` (línea 95): añadir `'total_a_pagar' => 100000` al POST.
- `test_descarga_de_soporte_disponible_para_quien_ve_el_detalle` (línea 108): añadir `'total_a_pagar' => 100000`.
- `test_eliminar_abono_borra_registro_y_soporte` (línea 125): añadir `'total_a_pagar' => 100000`.
- `test_no_se_registra_abono_en_estado_no_permitido` (línea 149): añadir `'total_a_pagar' => 100000` (aunque
  el 403 salta antes por estado, mantener el payload completo).

> Nota: estos POST envían monto ≤ 100000 y total_a_pagar 100000, así que cumplen la nueva regla monto ≤ total.

- [ ] **Step 4: Ejecutar los tests afectados**

Run: `php artisan test --filter=AbonoOficinaTest`
Expected: puede FALLAR aún porque la validación de `total_a_pagar` requerido se implementa en Task 3, pero los
asserts de `saldoPendiente()` y los payloads ya están alineados. Registrar el resultado; se resolverá al
completar Task 3. (Si el controlador aún no guarda `total_a_pagar`, el saldo saldrá contra null → 0; por eso
este paso se cierra junto con Task 3.)

- [ ] **Step 5: Commit**

```bash
git add app/Http/Resources/SolicitudDetalleResource.php app/Http/Controllers/ComisionesRrhhController.php tests/Feature/AbonoOficinaTest.php
git commit -m "refactor(oficina): consumir saldoPendiente y exponer total real en pagos"
```

---

# FASE 2 — Backend de abonos

## Task 3: Validación condicional + guardar el total en el primer abono

**Files:**
- Modify: `app/Http/Requests/RegistrarAbonoOficinaRequest.php`
- Modify: `app/Http/Controllers/AbonoOficinaController.php`
- Test: `tests/Feature/TotalAPagarOficinaTest.php`, `tests/Feature/AbonoOficinaTest.php`

- [ ] **Step 1: Reescribir el request con reglas condicionales**

Reemplazar el contenido de `app/Http/Requests/RegistrarAbonoOficinaRequest.php` por:

```php
<?php

namespace App\Http\Requests;

use App\Models\Solicitud;
use Illuminate\Foundation\Http\FormRequest;

class RegistrarAbonoOficinaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autorizacion real la hace la policy en el controlador
    }

    /** La solicitud del route binding; su cabecera de oficina tiene el estado de pago. */
    private function cabecera()
    {
        $solicitud = $this->route('solicitud');
        return $solicitud instanceof Solicitud ? $solicitud->solicitable : null;
    }

    /** ¿Es el primer abono? (la cabecera aun no tiene total_a_pagar) */
    private function esPrimerAbono(): bool
    {
        return $this->cabecera()?->total_a_pagar === null;
    }

    public function rules(): array
    {
        $reglas = [
            'monto'       => 'required|numeric|min:0.01',
            'fecha_pago'  => 'required|date',
            'soporte'     => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'observacion' => 'nullable|string|max:500',
        ];

        if ($this->esPrimerAbono()) {
            // En el primer pago se define el total real a pagar.
            $reglas['total_a_pagar'] = 'required|numeric|min:0.01';
        }

        return $reglas;
    }

    /**
     * Regla de saldo: el monto no puede exceder el total (primer pago) ni el saldo
     * pendiente (abonos siguientes). Mensajes claros con el limite concreto.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $monto = (float) $this->input('monto', 0);
            if ($monto <= 0) {
                return; // ya lo cubre min:0.01
            }

            if ($this->esPrimerAbono()) {
                $total = (float) $this->input('total_a_pagar', 0);
                if ($total > 0 && $monto > $total) {
                    $validator->errors()->add('monto',
                        'El monto no puede superar el total a pagar de $'.number_format($total, 2).'.');
                }
            } else {
                $saldo = $this->cabecera()?->saldoPendiente() ?? 0.0;
                if ($monto > $saldo) {
                    $validator->errors()->add('monto',
                        'El monto no puede superar el saldo pendiente de $'.number_format($saldo, 2).'.');
                }
            }
        });
    }

    public function attributes(): array
    {
        return [
            'monto'         => 'monto',
            'total_a_pagar' => 'total a pagar',
            'fecha_pago'    => 'fecha de pago',
            'soporte'       => 'soporte de pago',
            'observacion'   => 'observación',
        ];
    }
}
```

- [ ] **Step 2: Guardar `total_a_pagar` en el controlador (primer abono)**

En `app/Http/Controllers/AbonoOficinaController.php`, dentro de `store()`, en la transacción, antes de crear el
abono, fijar el total la primera vez. Reemplazar el cuerpo de la closure de `DB::transaction` (líneas 25-39)
por:

```php
        DB::transaction(function () use ($cabecera, $solicitud, $request, $soportePath, $soporteNombre) {
            // El primer abono define el total real a pagar de la solicitud.
            if ($cabecera->total_a_pagar === null) {
                $cabecera->update(['total_a_pagar' => $request->total_a_pagar]);
            }

            $cabecera->abonos()->create([
                'monto'          => $request->monto,
                'fecha_pago'     => $request->fecha_pago,
                'soporte_path'   => $soportePath,
                'soporte_nombre' => $soporteNombre,
                'usuario_id'     => auth()->id(),
                'observacion'    => $request->observacion,
            ]);

            // El primer abono lleva la solicitud de 'aprobada' a 'pendiente_cierre'.
            if ($solicitud->estado === 'aprobada') {
                $solicitud->update(['estado' => 'pendiente_cierre']);
            }
        });
```

- [ ] **Step 3: Añadir los tests HTTP de validación**

Añadir al final de la clase `TotalAPagarOficinaTest` (antes del `}` de cierre). Reemplazar el bloque `use`
superior por:

```php
use App\Models\{AbonoOficina, Area, ItemOficina, Solicitud, SolicitudOficina, TipoSolicitud, Usuario};
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
```

Añadir el helper y los tests:

```php
    private function aprobada(): Solicitud
    {
        $motor = app(MotorWorkflow::class);
        $lider = Usuario::where('email','lider.area@demo.test')->firstOrFail();
        $rrhh  = Usuario::where('email','rrhh@demo.test')->firstOrFail();
        $cl    = Usuario::where('email','contabilidad.lider@demo.test')->firstOrFail();
        $tipo  = TipoSolicitud::where('clave','OFI')->firstOrFail();

        $cab = SolicitudOficina::create(['beneficiario'=>'','urgencia'=>'media','justificacion'=>'x','total'=>100000]);
        ItemOficina::create(['solicitud_oficina_id'=>$cab->id,'nombre'=>'Mouse','categoria'=>'producto','cantidad'=>1,'costo_estimado'=>100000,'subtotal'=>100000]);
        $s = Solicitud::create([
            'tipo_solicitud_id'=>$tipo->id,'solicitante_id'=>$lider->id,'area_id'=>Area::first()->id,
            'solicitable_type'=>SolicitudOficina::class,'solicitable_id'=>$cab->id,'estado'=>'borrador',
            'radicado'=>Solicitud::generarRadicado($tipo),
        ]);
        $motor->aplicarTransicion($s, 'enviar', $lider);
        $motor->aplicarTransicion($s->fresh(), 'verificar', $rrhh);
        $motor->aplicarTransicion($s->fresh(), 'aprobar', $cl);
        return $s->fresh();
    }

    private function cl(): Usuario
    {
        return Usuario::where('email','contabilidad.lider@demo.test')->firstOrFail();
    }

    public function test_primer_abono_guarda_el_total_a_pagar(): void
    {
        Storage::fake('local');
        $s = $this->aprobada();

        $this->actingAs($this->cl())->post(route('oficina.abono.store', $s), [
            'total_a_pagar' => 80000, 'monto' => 30000, 'fecha_pago' => '2026-08-12',
            'soporte' => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $cab = $s->solicitable->fresh();
        $this->assertEquals(80000.0, (float) $cab->total_a_pagar);
        $this->assertEquals(50000.0, $cab->saldoPendiente());
    }

    public function test_primer_abono_requiere_total_a_pagar(): void
    {
        Storage::fake('local');
        $s = $this->aprobada();

        $this->actingAs($this->cl())
            ->from(route('solicitudes.show', $s))
            ->post(route('oficina.abono.store', $s), [
                'monto' => 30000, 'fecha_pago' => '2026-08-12',
                'soporte' => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'),
            ])->assertSessionHasErrors('total_a_pagar');
    }

    public function test_primer_abono_monto_no_puede_superar_el_total(): void
    {
        Storage::fake('local');
        $s = $this->aprobada();

        $this->actingAs($this->cl())
            ->from(route('solicitudes.show', $s))
            ->post(route('oficina.abono.store', $s), [
                'total_a_pagar' => 50000, 'monto' => 60000, 'fecha_pago' => '2026-08-12',
                'soporte' => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'),
            ])->assertSessionHasErrors('monto');

        $this->assertEquals(0, $s->solicitable->fresh()->abonos()->count());
    }

    public function test_abono_siguiente_no_puede_superar_el_saldo(): void
    {
        Storage::fake('local');
        $s = $this->aprobada();

        // Primer abono: total 100000, paga 40000 -> saldo 60000.
        $this->actingAs($this->cl())->post(route('oficina.abono.store', $s), [
            'total_a_pagar' => 100000, 'monto' => 40000, 'fecha_pago' => '2026-08-12',
            'soporte' => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'),
        ]);

        // Segundo abono de 70000 excede el saldo (60000) -> error.
        $this->actingAs($this->cl())
            ->from(route('solicitudes.show', $s))
            ->post(route('oficina.abono.store', $s), [
                'monto' => 70000, 'fecha_pago' => '2026-08-13',
                'soporte' => UploadedFile::fake()->create('b.pdf', 100, 'application/pdf'),
            ])->assertSessionHasErrors('monto');

        $this->assertEquals(40000.0, $s->solicitable->fresh()->totalPagado());
    }

    public function test_pago_unico_deja_saldo_cero_y_no_admite_mas(): void
    {
        Storage::fake('local');
        $s = $this->aprobada();

        // Pago total: monto = total_a_pagar.
        $this->actingAs($this->cl())->post(route('oficina.abono.store', $s), [
            'total_a_pagar' => 100000, 'monto' => 100000, 'fecha_pago' => '2026-08-12',
            'soporte' => UploadedFile::fake()->create('a.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $this->assertEquals(0.0, $s->solicitable->fresh()->saldoPendiente());
        $this->assertTrue($s->solicitable->fresh()->estaPagadaCompleta());

        // Un abono adicional se rechaza (saldo 0).
        $this->actingAs($this->cl())
            ->from(route('solicitudes.show', $s))
            ->post(route('oficina.abono.store', $s), [
                'monto' => 1, 'fecha_pago' => '2026-08-13',
                'soporte' => UploadedFile::fake()->create('b.pdf', 100, 'application/pdf'),
            ])->assertSessionHasErrors('monto');

        $this->assertEquals(1, $s->solicitable->fresh()->abonos()->count());
    }
```

- [ ] **Step 4: Ejecutar los tests**

Run: `php artisan test --filter=TotalAPagarOficinaTest`
Expected: PASS (los 3 de modelo + los 5 HTTP nuevos).

- [ ] **Step 5: Ejecutar `AbonoOficinaTest` (regresión de Task 2)**

Run: `php artisan test --filter=AbonoOficinaTest`
Expected: PASS (ya con `total_a_pagar` en los POST y `saldoPendiente()` en los asserts).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Requests/RegistrarAbonoOficinaRequest.php app/Http/Controllers/AbonoOficinaController.php tests/Feature/TotalAPagarOficinaTest.php
git commit -m "feat(oficina): total a pagar en el primer abono y monto acotado al saldo"
```

---

# FASE 3 — UI

## Task 4: Campo "Total a pagar" y atajo "Pago total" en el primer pago

**Files:**
- Modify: `resources/js/Pages/Solicitudes/Detalle.jsx` (componente `SeccionPagos`)

- [ ] **Step 1: Ajustar el estado del formulario y el resumen**

En `resources/js/Pages/Solicitudes/Detalle.jsx`, en `SeccionPagos`, reemplazar el `useForm` inicial (líneas
487-489) para incluir `total_a_pagar`:

```jsx
    const { data, setData, post, processing, errors, reset } = useForm({
        total_a_pagar: '', monto: '', fecha_pago: '', soporte: null, observacion: '',
    });
```

Reemplazar el bloque del resumen (el `<div className="grid grid-cols-3 gap-4 mb-4">`, líneas 511-515) por uno
que muestre el total real cuando existe, o el estimado con nota cuando no:

```jsx
            <div className="grid grid-cols-3 gap-4 mb-4">
                <div>
                    <p className="text-xs text-slate-500">{pagos.tiene_total ? 'Total a pagar' : 'Total estimado'}</p>
                    <p className="text-sm font-semibold text-slate-800">
                        {formatearMoneda(pagos.tiene_total ? pagos.total_a_pagar : pagos.total_estimado)}
                    </p>
                    {!pagos.tiene_total && (
                        <p className="text-[11px] text-slate-400">Se confirmará al registrar el primer pago.</p>
                    )}
                </div>
                <div><p className="text-xs text-slate-500">Pagado</p><p className="text-sm font-semibold text-emerald-700">{formatearMoneda(pagos.pagado)}</p></div>
                <div>
                    <p className="text-xs text-slate-500">Saldo</p>
                    <p className={`text-sm font-semibold ${pagos.saldo > 0 ? 'text-amber-700' : 'text-slate-500'}`}>
                        {pagos.tiene_total ? formatearMoneda(pagos.saldo) : '—'}
                    </p>
                </div>
            </div>
```

- [ ] **Step 2: Campo "Total a pagar" + atajo en el formulario del primer pago**

En el `<form onSubmit={registrar}>` (dentro de `pagos.puede_registrar`), antes del `<div className="grid grid-cols-2 gap-3">`
del monto/fecha, insertar el campo de total (solo primer pago) y, si ya hay total, una nota de saldo:

```jsx
                    {!pagos.tiene_total ? (
                        <div>
                            <label className="block text-xs text-slate-600 mb-1">Total a pagar</label>
                            <div className="flex gap-2">
                                <input type="number" step="0.01" min="0.01" value={data.total_a_pagar}
                                    onChange={(e) => setData('total_a_pagar', e.target.value)}
                                    className="w-full rounded-lg border-slate-300 text-sm" />
                                <button type="button"
                                    onClick={() => setData('monto', data.total_a_pagar)}
                                    className="shrink-0 px-3 py-2 text-xs font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100 rounded-lg">
                                    Pago total
                                </button>
                            </div>
                            {errors.total_a_pagar && <p className="text-red-500 text-xs mt-1">{errors.total_a_pagar}</p>}
                        </div>
                    ) : (
                        <p className="text-xs text-slate-500">Saldo pendiente: <span className="font-semibold">{formatearMoneda(pagos.saldo)}</span></p>
                    )}
```

> El botón "Pago total" copia `total_a_pagar` al campo `monto`. En pagos siguientes no aparece el campo total;
> se muestra el saldo como referencia.

- [ ] **Step 3: Enviar `total_a_pagar` y ocultar el formulario cuando ya está pagada**

El `registrar` ya hace `post(route('oficina.abono.store', ...))` con `data`, que ahora incluye
`total_a_pagar` — no requiere cambio. Para no mostrar el formulario cuando el saldo es 0 (ya pagada),
envolver el `<form>` con una condición extra: cambiar `{pagos.puede_registrar && (` (línea 540) por:

```jsx
            {pagos.puede_registrar && !(pagos.tiene_total && pagos.saldo <= 0) && (
```

- [ ] **Step 4: Compilar**

Run: `npm run build`
Expected: build sin errores.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Solicitudes/Detalle.jsx
git commit -m "feat(oficina): total a pagar y atajo pago total en el primer abono (UI)"
```

---

# FASE 4 — RR. HH. (UI)

## Task 5: Ajustar cifras al total real en el panel RR. HH.

**Files:**
- Modify: `resources/js/Pages/Rrhh/Comisiones.jsx`

- [ ] **Step 1: Mostrar "—" cuando el total aún es null**

En `resources/js/Pages/Rrhh/Comisiones.jsx`, en la tabla de oficina, la celda de Total ahora puede recibir
`null` (solicitud sin primer pago aún; aunque el filtro es `pendiente_cierre`/`cerrada`, se blinda). Localizar
la celda que renderiza `o.total` (en el `map` de `oficina`) y envolverla para manejar null:

```jsx
                                            <td className="px-4 py-2 text-right">{o.total != null ? formatearMoneda(o.total) : '—'}</td>
```

> `o.pagado` y `o.saldo` ya vienen numéricos del backend (`saldoPendiente()`); no requieren cambio. El enlace de
> soporte por abono (`route('oficina.abono.soporte', [o.id, ab.id])`) ya existe y se mantiene.

- [ ] **Step 2: Compilar**

Run: `npm run build`
Expected: build sin errores.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Rrhh/Comisiones.jsx
git commit -m "feat(rrhh): mostrar el total real a pagar en el panel de oficina"
```

---

# FASE 5 — Verificación final

## Task 6: Suite completa, build y re-seed

- [ ] **Step 1: Ejecutar toda la suite**

Run: `php artisan test`
Expected: todos verdes (incluye `TotalAPagarOficinaTest`, `AbonoOficinaTest` actualizado, y sin regresiones en
el resto de oficina/RR. HH.).

- [ ] **Step 2: Build de producción**

Run: `npm run build`
Expected: build sin errores.

- [ ] **Step 3: Aplicar la migración en desarrollo**

Run: `php artisan migrate`
Expected: corre `add_total_a_pagar_to_solicitudes_oficina` (idempotente).

- [ ] **Step 4: Verificar árbol limpio**

Run: `git status --short`
Expected: sin cambios pendientes (los assets de `public/build` no se versionan).

---

## Cobertura del spec (checklist de auto-revisión)

- `total_a_pagar` nullable + saldo contra él (`saldoPendiente`, `estaPagadaCompleta`): Task 1. ✔
- Reemplazar `saldo()` y actualizar consumidores (Resource, RR. HH., AbonoOficinaTest): Task 2. ✔
- Primer abono exige `total_a_pagar`; monto ≤ total; abonos siguientes monto ≤ saldo; mensajes claros: Task 3. ✔
- Controlador guarda el total en el primer abono: Task 3. ✔
- UI: campo "Total a pagar" + atajo "Pago total" en el primer pago; saldo en siguientes; resumen con total real: Task 4. ✔
- RR. HH. ve el total real, pagado, saldo y soportes descargables por abono: Task 5 (+ soportes ya existentes). ✔
