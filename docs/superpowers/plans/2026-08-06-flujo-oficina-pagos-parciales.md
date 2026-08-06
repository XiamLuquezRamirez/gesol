# Mejoras al flujo de solicitudes de oficina — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Añadir al flujo de solicitudes de oficina (OFI): beneficiarios múltiples (empleados), cotizaciones gestionables por su autor, aviso a contadores, pagos parciales por abonos con soporte adjunto, estado "pendiente por cerrar", renombrado de "Aprobar" a "Enviar a gerencia", y visibilidad de solicitudes pagadas en RR. HH.

**Architecture:** Laravel 10 + Inertia/React + MariaDB. Motor de workflow dirigido por datos (`TipoSolicitudSeeder`, `MotorWorkflow`). El pago deja de ser una transición y pasa a ser una acción propia (endpoint dedicado) que registra abonos en una tabla nueva; el paso a `pendiente_cierre` lo dispara el primer abono. Beneficiarios y abonos son tablas hijas con `cascadeOnDelete`. Totales pagado/saldo se calculan, no se cachean.

**Tech Stack:** PHP 8.2, Laravel 10.50, Inertia 0.6, React 18, spatie/laravel-permission 6, PHPUnit + SQLite `:memory:` en tests (MariaDB en dev), Storage disk `local`.

**Spec:** [docs/superpowers/specs/2026-08-06-flujo-oficina-pagos-parciales-design.md](../specs/2026-08-06-flujo-oficina-pagos-parciales-design.md)

---

## Estructura de archivos

**Crear:**
- `database/migrations/2026_08_06_100000_create_beneficiarios_oficina_table.php`
- `database/migrations/2026_08_06_100100_add_usuario_id_to_cotizaciones_oficina_table.php`
- `database/migrations/2026_08_06_100200_create_abonos_oficina_table.php`
- `app/Models/BeneficiarioOficina.php`
- `app/Models/AbonoOficina.php`
- `app/Http/Controllers/AbonoOficinaController.php`
- `app/Http/Requests/RegistrarAbonoOficinaRequest.php`
- `tests/Feature/BeneficiariosOficinaTest.php`
- `tests/Feature/AbonoOficinaTest.php`
- `tests/Feature/OficinaRrhhTest.php`

**Modificar:**
- `database/seeders/TipoSolicitudSeeder.php` — transiciones OFI (label, notificar, estado `pendiente_cierre`, quitar `pagar`).
- `app/Models/SolicitudOficina.php` — relaciones `beneficiarios()`, `abonos()`, helpers `totalPagado()`, `saldo()`.
- `app/Models/CotizacionOficina.php` — `usuario_id` en fillable, relación `usuario()`.
- `app/Policies/SolicitudPolicy.php` — `gestionarCotizacion()`, `registrarAbono()`.
- `app/Http/Controllers/OficinaController.php` — beneficiarios en create/store/edit/update; autoría al anexar; `actualizarCotizacion()`; usar policy nueva al eliminar.
- `app/Http/Requests/GuardarSolicitudOficinaRequest.php` — reglas de `beneficiarios`.
- `app/Http/Resources/SolicitudDetalleResource.php` — beneficiarios, autor y `puede_gestionar` por cotización, bloque `pagos`.
- `app/Http/Controllers/ComisionesRrhhController.php` — pestaña "Solicitudes de oficina" (o controlador nuevo — ver Task 15).
- `app/Http/Controllers/SolicitudController.php` — tab `pendientes_cierre` en `index`.
- `routes/web.php` — rutas de abonos, `actualizarCotizacion`, RR. HH. oficina.
- `resources/js/Components/BadgeEstado.jsx` — etiquetas `aprobada` y `pendiente_cierre`.
- `resources/js/Pages/Oficina/Crear.jsx` — multi-select de empleados.
- `resources/js/Pages/Solicitudes/Detalle.jsx` — lista de cotizaciones con autor + sección Pagos.
- `resources/js/Pages/Solicitudes/Index.jsx` — pestaña "Pendientes por cerrar".
- `resources/js/Pages/Rrhh/Comisiones.jsx` — pestañas (comisiones + oficina).
- `tests/Feature/MotorWorkflowOficinaTest.php` — actualizar por nuevo flujo.
- `tests/Feature/CotizacionOficinaTest.php` — autoría.

---

# FASE 1 — Modelo de datos

## Task 1: Migración y modelo de beneficiarios múltiples

**Files:**
- Create: `database/migrations/2026_08_06_100000_create_beneficiarios_oficina_table.php`
- Create: `app/Models/BeneficiarioOficina.php`
- Modify: `app/Models/SolicitudOficina.php`
- Test: `tests/Feature/BeneficiariosOficinaTest.php`

- [ ] **Step 1: Escribir la migración**

Crear `database/migrations/2026_08_06_100000_create_beneficiarios_oficina_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('beneficiarios_oficina', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_oficina_id')->constrained('solicitudes_oficina')->cascadeOnDelete();
            $table->foreignId('empleado_id')->constrained('empleados');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('beneficiarios_oficina');
    }
};
```

- [ ] **Step 2: Crear el modelo**

Crear `app/Models/BeneficiarioOficina.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BeneficiarioOficina extends Model
{
    protected $table = 'beneficiarios_oficina';
    protected $fillable = ['solicitud_oficina_id', 'empleado_id'];

    public function solicitudOficina()
    {
        return $this->belongsTo(SolicitudOficina::class, 'solicitud_oficina_id');
    }

    public function empleado()
    {
        return $this->belongsTo(Empleados::class, 'empleado_id');
    }
}
```

- [ ] **Step 3: Añadir la relación en SolicitudOficina**

En `app/Models/SolicitudOficina.php`, tras el método `cotizaciones()` (línea ~21), añadir:

```php
    public function beneficiarios()
    {
        return $this->belongsToMany(Empleados::class, 'beneficiarios_oficina', 'solicitud_oficina_id', 'empleado_id')
            ->withTimestamps();
    }
```

- [ ] **Step 4: Escribir el test**

Crear `tests/Feature/BeneficiariosOficinaTest.php`:

```php
<?php
namespace Tests\Feature;

use App\Models\{Empleados, SolicitudOficina};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BeneficiariosOficinaTest extends TestCase
{
    use RefreshDatabase;

    public function test_una_solicitud_puede_tener_varios_empleados_beneficiarios(): void
    {
        $this->seed();
        $cabecera = SolicitudOficina::create([
            'beneficiario' => '', 'urgencia' => 'media', 'justificacion' => 'x',
        ]);
        $ids = Empleados::take(2)->pluck('id')->all();

        $cabecera->beneficiarios()->sync($ids);

        $this->assertEquals(2, $cabecera->fresh()->beneficiarios()->count());
        $this->assertEqualsCanonicalizing($ids, $cabecera->fresh()->beneficiarios->pluck('id')->all());
    }
}
```

- [ ] **Step 5: Ejecutar el test (debe fallar sin migración aplicada, luego pasar)**

Run: `php artisan test --filter=BeneficiariosOficinaTest`
Expected: PASS (RefreshDatabase corre las migraciones; el `sync` guarda 2 filas).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_06_100000_create_beneficiarios_oficina_table.php app/Models/BeneficiarioOficina.php app/Models/SolicitudOficina.php tests/Feature/BeneficiariosOficinaTest.php
git commit -m "feat(oficina): tabla y relacion de beneficiarios multiples"
```

---

## Task 2: Migración `usuario_id` en cotizaciones + relación

**Files:**
- Create: `database/migrations/2026_08_06_100100_add_usuario_id_to_cotizaciones_oficina_table.php`
- Modify: `app/Models/CotizacionOficina.php`
- Test: `tests/Feature/CotizacionOficinaTest.php` (se amplía en Fase 3; aquí solo migración+modelo)

- [ ] **Step 1: Escribir la migración**

Crear `database/migrations/2026_08_06_100100_add_usuario_id_to_cotizaciones_oficina_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cotizaciones_oficina', function (Blueprint $table) {
            $table->foreignId('usuario_id')->nullable()->after('solicitud_oficina_id')
                ->constrained('usuarios')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('cotizaciones_oficina', function (Blueprint $table) {
            $table->dropConstrainedForeignId('usuario_id');
        });
    }
};
```

- [ ] **Step 2: Actualizar el modelo CotizacionOficina**

Reemplazar el contenido de `app/Models/CotizacionOficina.php` por:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CotizacionOficina extends Model
{
    protected $table = 'cotizaciones_oficina';
    protected $fillable = ['solicitud_oficina_id', 'usuario_id', 'path', 'nombre_original'];

    public function solicitudOficina()
    {
        return $this->belongsTo(SolicitudOficina::class, 'solicitud_oficina_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
```

- [ ] **Step 3: Ejecutar la suite de cotizaciones para verificar que la migración no rompe nada**

Run: `php artisan test --filter=CotizacionOficinaTest`
Expected: PASS (los tests existentes siguen verdes; `usuario_id` es nullable).

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_08_06_100100_add_usuario_id_to_cotizaciones_oficina_table.php app/Models/CotizacionOficina.php
git commit -m "feat(oficina): registrar autor de cada cotizacion (usuario_id)"
```

---

## Task 3: Migración y modelo de abonos

**Files:**
- Create: `database/migrations/2026_08_06_100200_create_abonos_oficina_table.php`
- Create: `app/Models/AbonoOficina.php`
- Modify: `app/Models/SolicitudOficina.php`
- Test: `tests/Feature/AbonoOficinaTest.php` (se amplía en Fase 4; aquí solo modelo+helpers)

- [ ] **Step 1: Escribir la migración**

Crear `database/migrations/2026_08_06_100200_create_abonos_oficina_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abonos_oficina', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_oficina_id')->constrained('solicitudes_oficina')->cascadeOnDelete();
            $table->decimal('monto', 14, 2);
            $table->date('fecha_pago');
            $table->string('soporte_path');
            $table->string('soporte_nombre');
            $table->foreignId('usuario_id')->constrained('usuarios');
            $table->string('observacion')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('abonos_oficina');
    }
};
```

- [ ] **Step 2: Crear el modelo AbonoOficina**

Crear `app/Models/AbonoOficina.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AbonoOficina extends Model
{
    protected $table = 'abonos_oficina';
    protected $fillable = [
        'solicitud_oficina_id', 'monto', 'fecha_pago',
        'soporte_path', 'soporte_nombre', 'usuario_id', 'observacion',
    ];
    protected $casts = [
        'fecha_pago' => 'date',
        'monto'      => 'decimal:2',
    ];

    public function solicitudOficina()
    {
        return $this->belongsTo(SolicitudOficina::class, 'solicitud_oficina_id');
    }

    public function usuario()
    {
        return $this->belongsTo(Usuario::class, 'usuario_id');
    }
}
```

- [ ] **Step 3: Añadir relación y helpers en SolicitudOficina**

En `app/Models/SolicitudOficina.php`, tras `beneficiarios()` (Task 1), añadir:

```php
    public function abonos()
    {
        return $this->hasMany(AbonoOficina::class, 'solicitud_oficina_id');
    }

    public function totalPagado(): float
    {
        return (float) $this->abonos()->sum('monto');
    }

    public function saldo(): float
    {
        return (float) $this->total - $this->totalPagado();
    }
```

- [ ] **Step 4: Escribir el test de suma/saldo**

Crear `tests/Feature/AbonoOficinaTest.php`:

```php
<?php
namespace Tests\Feature;

use App\Models\{AbonoOficina, SolicitudOficina, Usuario};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AbonoOficinaTest extends TestCase
{
    use RefreshDatabase;

    public function test_total_pagado_suma_abonos_y_saldo_es_total_menos_pagado(): void
    {
        $this->seed();
        $usuario = Usuario::where('email', 'contabilidad.lider@demo.test')->firstOrFail();
        $cabecera = SolicitudOficina::create([
            'beneficiario' => '', 'urgencia' => 'media', 'justificacion' => 'x', 'total' => 100000,
        ]);

        AbonoOficina::create([
            'solicitud_oficina_id' => $cabecera->id, 'monto' => 40000, 'fecha_pago' => '2026-08-06',
            'soporte_path' => 'soportes_pago/a.pdf', 'soporte_nombre' => 'a.pdf', 'usuario_id' => $usuario->id,
        ]);
        AbonoOficina::create([
            'solicitud_oficina_id' => $cabecera->id, 'monto' => 25000, 'fecha_pago' => '2026-08-07',
            'soporte_path' => 'soportes_pago/b.pdf', 'soporte_nombre' => 'b.pdf', 'usuario_id' => $usuario->id,
        ]);

        $this->assertEquals(65000.0, $cabecera->fresh()->totalPagado());
        $this->assertEquals(35000.0, $cabecera->fresh()->saldo());
    }
}
```

- [ ] **Step 5: Ejecutar el test**

Run: `php artisan test --filter=AbonoOficinaTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_06_100200_create_abonos_oficina_table.php app/Models/AbonoOficina.php app/Models/SolicitudOficina.php tests/Feature/AbonoOficinaTest.php
git commit -m "feat(oficina): tabla de abonos con total pagado y saldo"
```

---

# FASE 2 — Workflow y estados

## Task 4: Actualizar el seeder de transiciones OFI

**Files:**
- Modify: `database/seeders/TipoSolicitudSeeder.php`
- Test: `tests/Feature/MotorWorkflowOficinaTest.php`

- [ ] **Step 1: Reemplazar el bloque de OFI en el seeder**

En `database/seeders/TipoSolicitudSeeder.php`, reemplazar la lista `'estados'` y `'transiciones'` del tipo `OFI` (líneas 16-27) por:

```php
                    'estados'       => json_encode(['borrador','enviada','verificada','aprobada','pendiente_cierre','pagada','cerrada','rechazada']),
                    'transiciones'  => json_encode([
                        ['origen'=>'borrador',        'accion'=>'enviar',    'destino'=>'enviada',          'roles'=>['lider_area'],                                'label'=>'Enviar a RR. HH.'],
                        ['origen'=>'enviada',         'accion'=>'verificar', 'destino'=>'verificada',       'roles'=>['rrhh'], 'notificar'=>['contador'],           'label'=>'Verificar'],
                        ['origen'=>'enviada',         'accion'=>'devolver',  'destino'=>'borrador',         'roles'=>['rrhh'],                                      'label'=>'Devolver'],
                        ['origen'=>'verificada',      'accion'=>'aprobar',   'destino'=>'aprobada',         'roles'=>['contabilidad_lider'],                        'label'=>'Enviar a gerencia'],
                        ['origen'=>'verificada',      'accion'=>'rechazar',  'destino'=>'rechazada',        'roles'=>['contabilidad_lider'],                        'label'=>'Rechazar'],
                        ['origen'=>'rechazada',       'accion'=>'reenviar',  'destino'=>'verificada',       'roles'=>['rrhh'], 'notificar'=>['contabilidad_lider'], 'label'=>'Reenviar a contabilidad'],
                        ['origen'=>'pendiente_cierre','accion'=>'cerrar',    'destino'=>'cerrada',          'roles'=>['contabilidad_lider','lider_area'],           'label'=>'Cerrar'],
                    ]),
```

> Nota: se eliminó la transición `aprobada → pagar → pagada`. `pagada` queda en la lista de estados por
> compatibilidad histórica, pero el flujo nuevo va `aprobada → (abono) → pendiente_cierre → cerrada`.
> El paso `aprobada → pendiente_cierre` lo dispara el endpoint de abonos (Task 9), no el seeder.

- [ ] **Step 2: Actualizar `MotorWorkflowOficinaTest::test_flujo_completo_oficina`**

En `tests/Feature/MotorWorkflowOficinaTest.php`, reemplazar el método `test_flujo_completo_oficina` (líneas 59-84) por (el pago ya no es transición; se cierra desde `pendiente_cierre`, al que llegamos poniendo el estado directamente porque el abono se prueba en AbonoOficinaTest):

```php
    public function test_flujo_completo_oficina(): void
    {
        $solicitud = $this->crearSolicitudOficina();
        $this->assertEquals('borrador', $solicitud->estado);

        $this->motor->aplicarTransicion($solicitud, 'enviar', $this->liderArea);
        $this->assertEquals('enviada', $solicitud->fresh()->estado);

        $this->motor->aplicarTransicion($solicitud->fresh(), 'verificar', $this->rrhh);
        $this->assertEquals('verificada', $solicitud->fresh()->estado);

        $this->motor->aplicarTransicion($solicitud->fresh(), 'aprobar', $this->contabilidadLider);
        $this->assertEquals('aprobada', $solicitud->fresh()->estado);

        // El abono (aprobada -> pendiente_cierre) se cubre en AbonoOficinaTest;
        // aqui llevamos el estado a pendiente_cierre para probar el cierre del motor.
        $solicitud->update(['estado' => 'pendiente_cierre']);

        $this->motor->aplicarTransicion($solicitud->fresh(), 'cerrar', $this->contabilidadLider);
        $this->assertEquals('cerrada', $solicitud->fresh()->estado);

        // enviar, verificar, aprobar, cerrar = 4 transiciones registradas.
        $this->assertDatabaseCount('transiciones_solicitud', 4);
    }
```

- [ ] **Step 3: Ejecutar el test (debe fallar antes de re-seedear, luego pasar)**

Run: `php artisan test --filter=MotorWorkflowOficinaTest`
Expected: PASS (RefreshDatabase re-ejecuta seeders, tomando las transiciones nuevas).

- [ ] **Step 4: Re-seedear la base de desarrollo**

Run: `php artisan db:seed --class=TipoSolicitudSeeder`
Expected: "Database seeding completed successfully." (upsert actualiza el tipo OFI).

- [ ] **Step 5: Commit**

```bash
git add database/seeders/TipoSolicitudSeeder.php tests/Feature/MotorWorkflowOficinaTest.php
git commit -m "feat(oficina): flujo con enviar a gerencia y estado pendiente por cerrar"
```

---

## Task 5: Etiquetas de estado en el badge

**Files:**
- Modify: `resources/js/Components/BadgeEstado.jsx`

- [ ] **Step 1: Añadir colores y etiquetas de los estados nuevos**

En `resources/js/Components/BadgeEstado.jsx`, en el objeto `COLORES` añadir tras `aprobada` (línea 5):

```js
    pendiente_cierre: 'bg-amber-50 text-amber-700 border-amber-200',
```

Y en `ETIQUETAS` reemplazar la entrada `aprobada:'Aprobada'` (línea 15) y añadir `pendiente_cierre`:

```js
    aprobada:'En gerencia · pendiente por pagar', aprobada_monto:'Monto aprobado', pagada:'Pagada',
    pendiente_cierre:'Pendiente por cerrar',
```

- [ ] **Step 2: Compilar el frontend**

Run: `npm run build`
Expected: build sin errores.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Components/BadgeEstado.jsx
git commit -m "feat(oficina): etiquetas de estado en gerencia y pendiente por cerrar"
```

---

# FASE 3 — Cotizaciones (autoría) y beneficiarios (backend)

## Task 6: Política `gestionarCotizacion` (solo autor, no cerrada)

**Files:**
- Modify: `app/Policies/SolicitudPolicy.php`
- Test: `tests/Feature/CotizacionOficinaTest.php`

- [ ] **Step 1: Añadir el método a la policy**

En `app/Policies/SolicitudPolicy.php`, tras `anexarCotizacion()` (línea ~47), añadir. Nota: recibe la
`CotizacionOficina` para comparar autoría; se importa el modelo arriba.

Añadir el import al bloque `use` superior:

```php
use App\Models\{CotizacionOficina, Solicitud, Usuario};
```

Y el método:

```php
    /**
     * Solo el usuario que subio la cotizacion puede eliminarla o reemplazarla,
     * y solo mientras la solicitud de oficina no este cerrada.
     */
    public function gestionarCotizacion(Usuario $usuario, Solicitud $solicitud, CotizacionOficina $cotizacion): bool
    {
        return $solicitud->tipoSolicitud->clave === 'OFI'
            && $cotizacion->usuario_id === $usuario->id
            && $solicitud->estado !== 'cerrada';
    }
```

- [ ] **Step 2: Escribir el test de autoría**

En `tests/Feature/CotizacionOficinaTest.php`, añadir estos métodos al final de la clase (antes del `}` de
cierre). Reemplazan la semántica antigua "cualquiera con rol puede eliminar" por "solo el autor":

```php
    public function test_solo_el_autor_puede_eliminar_su_cotizacion(): void
    {
        Storage::fake('local');
        $solicitud = $this->enviada();

        // RR. HH. sube la cotizacion (queda como autor).
        $this->actingAs($this->rrhh)->post(route('oficina.cotizacion.anexar', $solicitud), [
            'cotizaciones' => [$this->pdf('a.pdf')],
        ]);
        $cotiz = $solicitud->solicitable->fresh()->cotizaciones()->first();

        // El lider de area (otro usuario con rol de anexar) NO puede eliminarla.
        $this->actingAs($this->liderArea)
            ->delete(route('oficina.cotizacion.eliminar', [$solicitud, $cotiz->id]))
            ->assertForbidden();

        // El autor si puede.
        $this->actingAs($this->rrhh)
            ->delete(route('oficina.cotizacion.eliminar', [$solicitud, $cotiz->id]))
            ->assertRedirect();
        $this->assertEquals(0, $solicitud->solicitable->fresh()->cotizaciones()->count());
    }

    public function test_actualizar_reemplaza_el_archivo_del_autor(): void
    {
        Storage::fake('local');
        $solicitud = $this->enviada();
        $this->actingAs($this->rrhh)->post(route('oficina.cotizacion.anexar', $solicitud), [
            'cotizaciones' => [$this->pdf('viejo.pdf')],
        ]);
        $cotiz = $solicitud->solicitable->fresh()->cotizaciones()->first();
        $pathViejo = $cotiz->path;

        $this->actingAs($this->rrhh)
            ->post(route('oficina.cotizacion.actualizar', [$solicitud, $cotiz->id]), [
                'cotizacion' => $this->pdf('nuevo.pdf'),
            ])
            ->assertRedirect();

        $cotiz->refresh();
        $this->assertEquals('nuevo.pdf', $cotiz->nombre_original);
        $this->assertNotEquals($pathViejo, $cotiz->path);
        Storage::disk('local')->assertMissing($pathViejo);
        Storage::disk('local')->assertExists($cotiz->path);
    }
```

> `test_anexar_multiples_archivos_se_acumulan` y `test_lider_area_puede_anexar_cuando_esta_rechazada` siguen
> válidos (anexar no cambia). Verificar que `test_eliminar_una_cotizacion_individual` (que usa `$this->rrhh`
> como quien sube y elimina) sigue verde tras el cambio de policy — el autor coincide, así que pasa.

- [ ] **Step 3: Ejecutar (fallará hasta implementar el controlador en Task 7)**

Run: `php artisan test --filter=CotizacionOficinaTest`
Expected: FAIL en `test_solo_el_autor...` y `test_actualizar...` (ruta/lógica aún no existe). Se resuelven en Task 7.

- [ ] **Step 4: Commit (policy)**

```bash
git add app/Policies/SolicitudPolicy.php tests/Feature/CotizacionOficinaTest.php
git commit -m "test(oficina): autoria de cotizaciones (solo el autor gestiona)"
```

---

## Task 7: Autoría al anexar + endpoint actualizar + eliminar por autor

**Files:**
- Modify: `app/Http/Controllers/OficinaController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/CotizacionOficinaTest.php` (de Task 6)

- [ ] **Step 1: Grabar `usuario_id` al anexar**

En `app/Http/Controllers/OficinaController.php`, dentro de `anexarCotizacion()`, en el `foreach` (líneas 126-131), añadir `usuario_id`:

```php
        foreach ((array) $request->file('cotizaciones') as $archivo) {
            $cabecera->cotizaciones()->create([
                'usuario_id'      => auth()->id(),
                'path'            => $archivo->store('cotizaciones', 'local'),
                'nombre_original' => $archivo->getClientOriginalName(),
            ]);
        }
```

- [ ] **Step 2: Cambiar `eliminarCotizacion` para usar la policy de autoría**

Reemplazar `eliminarCotizacion()` (líneas 143-152) por:

```php
    public function eliminarCotizacion(Solicitud $solicitud, CotizacionOficina $cotizacion)
    {
        abort_unless($cotizacion->solicitud_oficina_id === $solicitud->solicitable->id, 404);
        $this->authorize('gestionarCotizacion', [$solicitud, $cotizacion]);

        Storage::disk('local')->delete($cotizacion->path);
        $cotizacion->delete();

        return back()->with('success', 'Cotización eliminada.');
    }
```

- [ ] **Step 3: Añadir `actualizarCotizacion` (reemplazo de archivo)**

En `app/Http/Controllers/OficinaController.php`, tras `eliminarCotizacion()`, añadir:

```php
    /**
     * Reemplaza el archivo de una cotizacion. Solo su autor, mientras no este cerrada.
     */
    public function actualizarCotizacion(Request $request, Solicitud $solicitud, CotizacionOficina $cotizacion)
    {
        abort_unless($cotizacion->solicitud_oficina_id === $solicitud->solicitable->id, 404);
        $this->authorize('gestionarCotizacion', [$solicitud, $cotizacion]);

        $request->validate([
            'cotizacion' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ], [], ['cotizacion' => 'cotización']);

        Storage::disk('local')->delete($cotizacion->path);
        $cotizacion->update([
            'path'            => $request->file('cotizacion')->store('cotizaciones', 'local'),
            'nombre_original' => $request->file('cotizacion')->getClientOriginalName(),
        ]);

        return back()->with('success', 'Cotización actualizada.');
    }
```

- [ ] **Step 4: Registrar la ruta**

En `routes/web.php`, tras la ruta `oficina.cotizacion.eliminar` (línea 24), añadir:

```php
    Route::post('/oficina/{solicitud}/cotizacion/{cotizacion}/actualizar', [OficinaController::class, 'actualizarCotizacion'])->name('oficina.cotizacion.actualizar');
```

- [ ] **Step 5: Ejecutar la suite de cotizaciones completa**

Run: `php artisan test --filter=CotizacionOficinaTest`
Expected: PASS (todos, incluidos los de Task 6).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/OficinaController.php routes/web.php
git commit -m "feat(oficina): anexar con autor, actualizar y eliminar cotizacion por su autor"
```

---

## Task 8: Beneficiarios múltiples en create/store/edit/update + request

**Files:**
- Modify: `app/Http/Controllers/OficinaController.php`
- Modify: `app/Http/Requests/GuardarSolicitudOficinaRequest.php`
- Test: `tests/Feature/CrearSolicitudOficinaTest.php`

- [ ] **Step 1: Actualizar las reglas del request**

En `app/Http/Requests/GuardarSolicitudOficinaRequest.php`, en `rules()` reemplazar la regla `beneficiario` (línea 17) por:

```php
            'beneficiarios'          => 'required|array|min:1',
            'beneficiarios.*'        => 'exists:empleados,id',
```

Y en `attributes()` reemplazar la entrada `beneficiario` (línea 37) por:

```php
            'beneficiarios'          => 'beneficiarios',
            'beneficiarios.*'        => 'beneficiario',
```

- [ ] **Step 2: Pasar empleados como prop y sincronizar en el controlador**

En `app/Http/Controllers/OficinaController.php`:

Añadir `Empleados` al `use` de modelos (línea 5):

```php
use App\Models\{Area, CotizacionOficina, Empleados, Solicitud, SolicitudOficina, ItemOficina, TipoSolicitud, Usuario};
```

En `create()` (líneas 16-19) añadir el prop `empleados`:

```php
        return Inertia::render('Oficina/Crear', [
            'areas'     => Area::orderBy('nombre')->get(['id','nombre']),
            'usuarios'  => Usuario::orderBy('name')->get(['id','name']),
            'empleados' => Empleados::orderBy('nombres')->get(['id','nombres','apellidos','identificacion']),
        ]);
```

En `store()`, dentro de la transacción, tras crear `$cabecera` (línea 34) y antes de crear la `Solicitud`, sincronizar beneficiarios (mantener `beneficiario` string vacío por compatibilidad):

```php
            $cabecera = SolicitudOficina::create([
                'beneficiario' => '',
                'urgencia'     => $request->urgencia,
                'justificacion'=> $request->justificacion,
            ]);
            $cabecera->beneficiarios()->sync($request->beneficiarios);
```

En `edit()` (líneas 64-69): cargar beneficiarios y pasar empleados:

```php
        $solicitud->load('solicitable.items', 'solicitable.beneficiarios');
        return Inertia::render('Oficina/Crear', [
            'solicitud' => $solicitud,
            'areas'     => Area::orderBy('nombre')->get(['id','nombre']),
            'usuarios'  => Usuario::orderBy('name')->get(['id','name']),
            'empleados' => Empleados::orderBy('nombres')->get(['id','nombres','apellidos','identificacion']),
            'editar'    => true,
        ]);
```

En `update()`, dentro de la transacción, tras `$cabecera->update([...])` (línea 83), sincronizar:

```php
            $cabecera->update([
                'beneficiario'  => '',
                'urgencia'      => $request->urgencia,
                'justificacion' => $request->justificacion,
            ]);
            $cabecera->beneficiarios()->sync($request->beneficiarios);
```

- [ ] **Step 3: Actualizar el test de creación**

En `tests/Feature/CrearSolicitudOficinaTest.php`, los tests que envían `beneficiario` como string deben pasar
`beneficiarios` como array de IDs. Localizar cada `post(route('oficina.store'), [...])` y reemplazar la clave
`'beneficiario' => '...'` por:

```php
            'beneficiarios' => \App\Models\Empleados::take(1)->pluck('id')->all(),
```

Y añadir un test específico:

```php
    public function test_crea_solicitud_con_varios_beneficiarios(): void
    {
        $this->seed();
        $lider = \App\Models\Usuario::where('email','lider.area@demo.test')->firstOrFail();
        $area  = \App\Models\Area::first();
        $empleados = \App\Models\Empleados::take(2)->pluck('id')->all();

        $this->actingAs($lider)->post(route('oficina.store'), [
            'area_id'       => $area->id,
            'beneficiarios' => $empleados,
            'urgencia'      => 'media',
            'justificacion' => 'Material para el equipo.',
            'items'         => [['nombre'=>'Mouse','categoria'=>'producto','cantidad'=>1,'costo_estimado'=>1000,'notas'=>'']],
        ])->assertRedirect();

        $cabecera = \App\Models\SolicitudOficina::latest('id')->first();
        $this->assertEqualsCanonicalizing($empleados, $cabecera->beneficiarios->pluck('id')->all());
    }
```

- [ ] **Step 4: Ejecutar el test**

Run: `php artisan test --filter=CrearSolicitudOficinaTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/OficinaController.php app/Http/Requests/GuardarSolicitudOficinaRequest.php tests/Feature/CrearSolicitudOficinaTest.php
git commit -m "feat(oficina): beneficiarios multiples (empleados) en crear y editar"
```

---

# FASE 4 — Abonos (backend)

## Task 9: Política `registrarAbono` + controlador de abonos + rutas

**Files:**
- Modify: `app/Policies/SolicitudPolicy.php`
- Create: `app/Http/Requests/RegistrarAbonoOficinaRequest.php`
- Create: `app/Http/Controllers/AbonoOficinaController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/AbonoOficinaTest.php`

- [ ] **Step 1: Añadir la política `registrarAbono`**

En `app/Policies/SolicitudPolicy.php`, tras `gestionarCotizacion()` (Task 6), añadir:

```php
    /**
     * El lider de contabilidad registra abonos mientras la solicitud de oficina
     * este aprobada (en gerencia) o pendiente de cierre. Cerrada => inmutable.
     */
    public function registrarAbono(Usuario $usuario, Solicitud $solicitud): bool
    {
        return $solicitud->tipoSolicitud->clave === 'OFI'
            && $usuario->hasRole('contabilidad_lider')
            && in_array($solicitud->estado, ['aprobada', 'pendiente_cierre']);
    }
```

- [ ] **Step 2: Crear el FormRequest de abono**

Crear `app/Http/Requests/RegistrarAbonoOficinaRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegistrarAbonoOficinaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // la autorizacion real la hace la policy en el controlador
    }

    public function rules(): array
    {
        return [
            'monto'       => 'required|numeric|min:0.01',
            'fecha_pago'  => 'required|date',
            'soporte'     => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'observacion' => 'nullable|string|max:500',
        ];
    }

    public function attributes(): array
    {
        return [
            'monto'       => 'monto',
            'fecha_pago'  => 'fecha de pago',
            'soporte'     => 'soporte de pago',
            'observacion' => 'observación',
        ];
    }
}
```

- [ ] **Step 3: Crear el controlador de abonos**

Crear `app/Http/Controllers/AbonoOficinaController.php`:

```php
<?php
namespace App\Http\Controllers;

use App\Http\Requests\RegistrarAbonoOficinaRequest;
use App\Models\{AbonoOficina, Solicitud};
use Illuminate\Support\Facades\Storage;

class AbonoOficinaController extends Controller
{
    /**
     * Registra un abono. Al primer abono, la solicitud pasa de 'aprobada'
     * a 'pendiente_cierre' automaticamente.
     */
    public function store(RegistrarAbonoOficinaRequest $request, Solicitud $solicitud)
    {
        $this->authorize('registrarAbono', $solicitud);
        $cabecera = $solicitud->solicitable;

        $cabecera->abonos()->create([
            'monto'          => $request->monto,
            'fecha_pago'     => $request->fecha_pago,
            'soporte_path'   => $request->file('soporte')->store('soportes_pago', 'local'),
            'soporte_nombre' => $request->file('soporte')->getClientOriginalName(),
            'usuario_id'     => auth()->id(),
            'observacion'    => $request->observacion,
        ]);

        if ($solicitud->estado === 'aprobada') {
            $solicitud->update(['estado' => 'pendiente_cierre']);
        }

        return back()->with('success', 'Abono registrado.');
    }

    /**
     * Elimina un abono (correccion). Solo mientras la solicitud no este cerrada.
     */
    public function destroy(Solicitud $solicitud, AbonoOficina $abono)
    {
        abort_unless($abono->solicitud_oficina_id === $solicitud->solicitable->id, 404);
        $this->authorize('registrarAbono', $solicitud);

        Storage::disk('local')->delete($abono->soporte_path);
        $abono->delete();

        return back()->with('success', 'Abono eliminado.');
    }

    /**
     * Descarga controlada del soporte de pago: cualquiera que pueda ver el detalle.
     */
    public function descargarSoporte(Solicitud $solicitud, AbonoOficina $abono)
    {
        $this->authorize('verDetalle', $solicitud);
        abort_unless($abono->solicitud_oficina_id === $solicitud->solicitable->id, 404);
        abort_unless(Storage::disk('local')->exists($abono->soporte_path), 404);

        return Storage::disk('local')->download($abono->soporte_path, $abono->soporte_nombre);
    }
}
```

- [ ] **Step 4: Registrar las rutas**

En `routes/web.php`, tras la ruta `oficina.cotizacion.actualizar` (Task 7), añadir:

```php
    Route::post('/oficina/{solicitud}/abono',                 [AbonoOficinaController::class, 'store'])->name('oficina.abono.store');
    Route::delete('/oficina/{solicitud}/abono/{abono}',       [AbonoOficinaController::class, 'destroy'])->name('oficina.abono.eliminar');
    Route::get('/oficina/{solicitud}/abono/{abono}/soporte',  [AbonoOficinaController::class, 'descargarSoporte'])->name('oficina.abono.soporte');
```

Y añadir el import del controlador al inicio de `routes/web.php`, junto a los otros `use ...Controller` (buscar el bloque de imports de controladores, p.ej. donde está `use App\Http\Controllers\OficinaController;`):

```php
use App\Http\Controllers\AbonoOficinaController;
```

> Si `routes/web.php` referencia los controladores con nombre completo inline en lugar de `use`, seguir ese
> mismo estilo y usar `\App\Http\Controllers\AbonoOficinaController::class` en las tres rutas.

- [ ] **Step 5: Escribir los tests de abono (flujo HTTP)**

En `tests/Feature/AbonoOficinaTest.php`, reemplazar el bloque `use` superior por:

```php
use App\Models\{AbonoOficina, Area, ItemOficina, Solicitud, SolicitudOficina, TipoSolicitud, Usuario};
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
```

Añadir estos helpers y tests dentro de la clase:

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

    public function test_primer_abono_pasa_la_solicitud_a_pendiente_cierre(): void
    {
        Storage::fake('local');
        $s  = $this->aprobada();
        $cl = Usuario::where('email','contabilidad.lider@demo.test')->firstOrFail();

        $this->actingAs($cl)->post(route('oficina.abono.store', $s), [
            'monto' => 40000, 'fecha_pago' => '2026-08-06',
            'soporte' => UploadedFile::fake()->create('pago1.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $this->assertEquals('pendiente_cierre', $s->fresh()->estado);
        $this->assertEquals(40000.0, $s->solicitable->fresh()->totalPagado());
        $this->assertEquals(60000.0, $s->solicitable->fresh()->saldo());
    }

    public function test_un_abono_puede_cubrir_la_totalidad(): void
    {
        Storage::fake('local');
        $s  = $this->aprobada();
        $cl = Usuario::where('email','contabilidad.lider@demo.test')->firstOrFail();

        $this->actingAs($cl)->post(route('oficina.abono.store', $s), [
            'monto' => 100000, 'fecha_pago' => '2026-08-06',
            'soporte' => UploadedFile::fake()->create('total.pdf', 100, 'application/pdf'),
        ])->assertRedirect();

        $this->assertEquals(0.0, $s->solicitable->fresh()->saldo());
        $this->assertEquals('pendiente_cierre', $s->fresh()->estado);
    }

    public function test_solo_contabilidad_lider_registra_abonos(): void
    {
        Storage::fake('local');
        $s   = $this->aprobada();
        $cont = Usuario::where('email','contador@demo.test')->firstOrFail();

        $this->actingAs($cont)->post(route('oficina.abono.store', $s), [
            'monto' => 1000, 'fecha_pago' => '2026-08-06',
            'soporte' => UploadedFile::fake()->create('x.pdf', 100, 'application/pdf'),
        ])->assertForbidden();
    }

    public function test_descarga_de_soporte_disponible_para_quien_ve_el_detalle(): void
    {
        Storage::fake('local');
        $s  = $this->aprobada();
        $cl = Usuario::where('email','contabilidad.lider@demo.test')->firstOrFail();
        $rrhh = Usuario::where('email','rrhh@demo.test')->firstOrFail();

        $this->actingAs($cl)->post(route('oficina.abono.store', $s), [
            'monto' => 50000, 'fecha_pago' => '2026-08-06',
            'soporte' => UploadedFile::fake()->create('soporte.pdf', 100, 'application/pdf'),
        ]);
        $abono = $s->solicitable->fresh()->abonos()->first();

        $this->actingAs($rrhh)
            ->get(route('oficina.abono.soporte', [$s, $abono->id]))
            ->assertOk();
    }
```

- [ ] **Step 6: Ejecutar los tests de abono**

Run: `php artisan test --filter=AbonoOficinaTest`
Expected: PASS (los del modelo de Task 3 y los HTTP nuevos).

- [ ] **Step 7: Commit**

```bash
git add app/Policies/SolicitudPolicy.php app/Http/Requests/RegistrarAbonoOficinaRequest.php app/Http/Controllers/AbonoOficinaController.php routes/web.php tests/Feature/AbonoOficinaTest.php
git commit -m "feat(oficina): abonos con soporte, paso a pendiente por cerrar y descarga"
```

---

## Task 10: Exponer beneficiarios, autor de cotizaciones y pagos en el Resource

**Files:**
- Modify: `app/Http/Resources/SolicitudDetalleResource.php`
- Modify: `app/Http/Controllers/SolicitudController.php`

- [ ] **Step 1: Ampliar `SolicitudDetalleResource`**

Reemplazar el método `toArray` completo de `app/Http/Resources/SolicitudDetalleResource.php` por:

```php
    public function toArray($request): array
    {
        $esOficina = $this->tipoSolicitud->clave === 'OFI';
        $usuario   = $request->user();

        return [
            'id'          => $this->id,
            'radicado'    => $this->radicado,
            'estado'      => $this->estado,
            'total'       => $this->total,
            'tipo'        => ['clave' => $this->tipoSolicitud->clave, 'nombre' => $this->tipoSolicitud->nombre],
            'solicitante' => ['id' => $this->solicitante->id, 'name' => $this->solicitante->name],
            'area'        => $this->area ? ['id' => $this->area->id, 'nombre' => $this->area->nombre] : null,
            'solicitable' => $this->solicitable,
            'transiciones' => TransicionResource::collection($this->transiciones),
            'beneficiarios' => $this->when($esOficina, fn () => $this->solicitable->beneficiarios->map(fn ($e) => [
                'id'             => $e->id,
                'nombre'         => trim($e->nombres.' '.$e->apellidos),
                'identificacion' => $e->identificacion,
            ])->values()),
            'cotizacion'  => $this->when($esOficina, fn () => [
                'comentario'   => $this->solicitable->comentario_contador,
                'archivos'     => $this->solicitable->cotizaciones->map(fn ($c) => [
                    'id'              => $c->id,
                    'nombre'          => $c->nombre_original,
                    'autor'           => $c->usuario?->name,
                    'puede_gestionar' => $usuario?->can('gestionarCotizacion', [$this->resource, $c]) ?? false,
                ])->values(),
                'puede_anexar' => $usuario?->can('anexarCotizacion', $this->resource) ?? false,
            ]),
            'pagos'       => $this->when($esOficina, fn () => [
                'total'           => (float) $this->solicitable->total,
                'pagado'          => $this->solicitable->totalPagado(),
                'saldo'           => $this->solicitable->saldo(),
                'puede_registrar' => $usuario?->can('registrarAbono', $this->resource) ?? false,
                'abonos'          => $this->solicitable->abonos->map(fn ($a) => [
                    'id'          => $a->id,
                    'monto'       => (float) $a->monto,
                    'fecha_pago'  => optional($a->fecha_pago)->toDateString(),
                    'autor'       => $a->usuario?->name,
                    'observacion' => $a->observacion,
                ])->values(),
            ]),
            'created_at'  => $this->created_at->format('Y-m-d H:i'),
        ];
    }
```

- [ ] **Step 2: Cargar las relaciones nuevas en `SolicitudController::show`**

En `app/Http/Controllers/SolicitudController.php`, en `show()`, en el `morphWith` para `SolicitudOficina`
(línea 57), reemplazar por:

```php
                SolicitudOficina::class  => ['items', 'cotizaciones.usuario', 'beneficiarios', 'abonos.usuario'],
```

- [ ] **Step 3: Verificar que el detalle carga sin error**

Run: `php artisan test --filter=CotizacionOficinaTest`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Resources/SolicitudDetalleResource.php app/Http/Controllers/SolicitudController.php
git commit -m "feat(oficina): exponer beneficiarios, autor de cotizacion y pagos en el detalle"
```

---

# FASE 5 — UI (React)

## Task 11: Multi-select de empleados en Crear.jsx

**Files:**
- Modify: `resources/js/Pages/Oficina/Crear.jsx`

- [ ] **Step 1: Reemplazar el campo beneficiario por un multi-select**

Cambiar la firma del componente para recibir `empleados` (línea 48):

```jsx
export default function Crear({ areas, usuarios, empleados = [], solicitud, editar }) {
```

En el `useForm` (líneas 52-64), reemplazar la clave `beneficiario` por `beneficiarios`:

```jsx
        beneficiarios:    solicitable?.beneficiarios?.map(b => b.id) ?? [],
```

Reemplazar el `<TextField label="Beneficiario(s):" .../>` (líneas 98-100) por:

```jsx
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Beneficiario(s):</label>
                                <div className="border border-slate-300 rounded-lg p-3 max-h-40 overflow-y-auto space-y-1">
                                    {empleados.length === 0 && (
                                        <p className="text-xs text-slate-400">No hay empleados registrados.</p>
                                    )}
                                    {empleados.map((e) => (
                                        <label key={e.id} className="flex items-center gap-2 text-sm text-slate-700">
                                            <input
                                                type="checkbox"
                                                className="rounded border-slate-300 text-indigo-600"
                                                checked={data.beneficiarios.includes(e.id)}
                                                onChange={(ev) => {
                                                    const next = ev.target.checked
                                                        ? [...data.beneficiarios, e.id]
                                                        : data.beneficiarios.filter((id) => id !== e.id);
                                                    setData('beneficiarios', next);
                                                }}
                                            />
                                            {e.nombres} {e.apellidos}
                                            <span className="text-xs text-slate-400">({e.identificacion})</span>
                                        </label>
                                    ))}
                                </div>
                                {errors.beneficiarios && <p className="text-red-500 text-xs mt-1">{errors.beneficiarios}</p>}
                            </div>
```

- [ ] **Step 2: Compilar**

Run: `npm run build`
Expected: build sin errores.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Oficina/Crear.jsx
git commit -m "feat(oficina): seleccionar varios empleados beneficiarios en el formulario"
```

---

## Task 12: Lista de cotizaciones con autor + botones actualizar/eliminar

**Files:**
- Modify: `resources/js/Pages/Solicitudes/Detalle.jsx` (componente `SeccionCotizacion`)

- [ ] **Step 1: Mostrar autor y usar `puede_gestionar` por archivo**

En `SeccionCotizacion`, reemplazar el `.map` sobre `cotizacion.archivos` (líneas ~368-404) por:

```jsx
                        {cotizacion.archivos.map((a) => (
                            <li key={a.id} className="flex items-center justify-between gap-2 py-1.5 border-b border-slate-50 last:border-0">
                                <div className="min-w-0">
                                    <a
                                        href={route('oficina.cotizacion.descargar', [solicitud.id, a.id])}
                                        className="text-sm text-indigo-600 hover:underline truncate"
                                    >
                                        {a.nombre}
                                    </a>
                                    {a.autor && <p className="text-xs text-slate-400">Subido por {a.autor}</p>}
                                </div>
                                {a.puede_gestionar && (
                                    <div className="flex items-center gap-2 shrink-0">
                                        <label className="text-xs text-indigo-600 hover:underline cursor-pointer">
                                            Actualizar
                                            <input
                                                type="file"
                                                className="hidden"
                                                accept=".pdf,.jpg,.jpeg,.png"
                                                onChange={(e) => {
                                                    if (!e.target.files[0]) return;
                                                    router.post(
                                                        route('oficina.cotizacion.actualizar', [solicitud.id, a.id]),
                                                        { cotizacion: e.target.files[0] },
                                                        { forceFormData: true, preserveScroll: true }
                                                    );
                                                }}
                                            />
                                        </label>
                                        <button
                                            type="button"
                                            onClick={() => router.delete(route('oficina.cotizacion.eliminar', [solicitud.id, a.id]), { preserveScroll: true })}
                                            className="text-xs text-red-500 hover:underline"
                                        >
                                            Eliminar
                                        </button>
                                    </div>
                                )}
                            </li>
                        ))}
```

> Asegurarse de que `router` esté importado en Detalle.jsx (ya se usa para eliminar cotizaciones; si no,
> añadir `import { router } from '@inertiajs/react';`).

- [ ] **Step 2: Compilar**

Run: `npm run build`
Expected: build sin errores.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Solicitudes/Detalle.jsx
git commit -m "feat(oficina): lista de cotizaciones con autor y accion de actualizar"
```

---

## Task 13: Sección "Pagos" (abonos) en el detalle

**Files:**
- Modify: `resources/js/Pages/Solicitudes/Detalle.jsx`

- [ ] **Step 1: Crear el componente `SeccionPagos`**

Añadir este componente antes del `export default` de la página (junto a `SeccionCotizacion`):

```jsx
function SeccionPagos({ solicitud }) {
    const pagos = solicitud.pagos;
    const { data, setData, post, processing, errors, reset } = useForm({
        monto: '', fecha_pago: '', soporte: null, observacion: '',
    });

    if (!pagos) return null;

    const registrar = (e) => {
        e.preventDefault();
        post(route('oficina.abono.store', solicitud.id), {
            forceFormData: true, preserveScroll: true,
            onSuccess: () => reset(),
        });
    };

    const haySaldo = pagos.saldo > 0;

    return (
        <div className="bg-white rounded-xl border border-slate-200 p-5 mt-6">
            <h3 className="text-xs font-semibold uppercase tracking-wide text-slate-400 mb-4">Pagos</h3>

            <div className="grid grid-cols-3 gap-4 mb-4">
                <div><p className="text-xs text-slate-500">Total</p><p className="text-sm font-semibold text-slate-800">{formatearMoneda(pagos.total)}</p></div>
                <div><p className="text-xs text-slate-500">Pagado</p><p className="text-sm font-semibold text-emerald-700">{formatearMoneda(pagos.pagado)}</p></div>
                <div><p className="text-xs text-slate-500">Saldo</p><p className={`text-sm font-semibold ${haySaldo ? 'text-amber-700' : 'text-slate-500'}`}>{formatearMoneda(pagos.saldo)}</p></div>
            </div>

            {pagos.abonos.length > 0 && (
                <ul className="mb-4 divide-y divide-slate-50">
                    {pagos.abonos.map((ab) => (
                        <li key={ab.id} className="flex items-center justify-between gap-2 py-2">
                            <div className="min-w-0">
                                <p className="text-sm text-slate-800">{formatearMoneda(ab.monto)} · {ab.fecha_pago}</p>
                                <p className="text-xs text-slate-400">
                                    {ab.autor}{ab.observacion ? ` — ${ab.observacion}` : ''}
                                </p>
                            </div>
                            <div className="flex items-center gap-3 shrink-0">
                                <a href={route('oficina.abono.soporte', [solicitud.id, ab.id])} className="text-xs text-indigo-600 hover:underline">Soporte</a>
                                {pagos.puede_registrar && (
                                    <button type="button"
                                        onClick={() => router.delete(route('oficina.abono.eliminar', [solicitud.id, ab.id]), { preserveScroll: true })}
                                        className="text-xs text-red-500 hover:underline">Eliminar</button>
                                )}
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {pagos.puede_registrar && (
                <form onSubmit={registrar} className="border-t border-slate-100 pt-4 space-y-3">
                    <p className="text-xs font-medium text-slate-600">Registrar abono</p>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <label className="block text-xs text-slate-600 mb-1">Monto</label>
                            <input type="number" step="0.01" min="0.01" value={data.monto}
                                onChange={(e) => setData('monto', e.target.value)}
                                className="w-full rounded-lg border-slate-300 text-sm" />
                            {errors.monto && <p className="text-red-500 text-xs mt-1">{errors.monto}</p>}
                        </div>
                        <div>
                            <label className="block text-xs text-slate-600 mb-1">Fecha de pago</label>
                            <input type="date" value={data.fecha_pago}
                                onChange={(e) => setData('fecha_pago', e.target.value)}
                                className="w-full rounded-lg border-slate-300 text-sm" />
                            {errors.fecha_pago && <p className="text-red-500 text-xs mt-1">{errors.fecha_pago}</p>}
                        </div>
                    </div>
                    <div>
                        <label className="block text-xs text-slate-600 mb-1">Soporte de pago (PDF/imagen)</label>
                        <input type="file" accept=".pdf,.jpg,.jpeg,.png"
                            onChange={(e) => setData('soporte', e.target.files[0])}
                            className="block w-full text-sm text-slate-600" />
                        {errors.soporte && <p className="text-red-500 text-xs mt-1">{errors.soporte}</p>}
                    </div>
                    <div>
                        <label className="block text-xs text-slate-600 mb-1">Observación (opcional)</label>
                        <input type="text" value={data.observacion}
                            onChange={(e) => setData('observacion', e.target.value)}
                            className="w-full rounded-lg border-slate-300 text-sm" />
                    </div>
                    <div className="flex justify-end">
                        <button type="submit" disabled={processing}
                            className="px-4 py-2 text-sm text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg disabled:opacity-50">
                            Registrar abono
                        </button>
                    </div>
                </form>
            )}
        </div>
    );
}
```

- [ ] **Step 2: Renderizar la sección en la página de detalle**

Tras la sección de cotización (donde se renderiza `<SeccionCotizacion .../>`, cerca de la línea 544-546),
añadir:

```jsx
                {esOficina && solicitud.pagos && <SeccionPagos solicitud={solicitud} />}
```

> `formatearMoneda`, `useForm` y `router` ya se usan/importan en Detalle.jsx.

- [ ] **Step 3: Compilar**

Run: `npm run build`
Expected: build sin errores.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Solicitudes/Detalle.jsx
git commit -m "feat(oficina): seccion de pagos con abonos, saldo y soporte en el detalle"
```

---

## Task 14: Pestaña "Pendientes por cerrar" en la lista

**Files:**
- Modify: `app/Http/Controllers/SolicitudController.php`
- Modify: `resources/js/Pages/Solicitudes/Index.jsx`

- [ ] **Step 1: Añadir el tab en el backend**

En `SolicitudController::index()`, añadir una rama antes del `else` final (tras la rama `revisadas`, línea ~33):

```php
        } elseif ($tab === 'pendientes_cierre') {
            $solicitudes = Solicitud::with(['tipoSolicitud','solicitante'])
                ->whereHas('tipoSolicitud', fn($q) => $q->where('clave', 'OFI'))
                ->where('estado', 'pendiente_cierre')
                ->latest()
                ->get();
```

- [ ] **Step 2: Añadir el tab en el frontend**

En `resources/js/Pages/Solicitudes/Index.jsx`, en el arreglo de tabs (líneas 19-23), reemplazar por:

```jsx
                        { key: 'mias',              label: 'Mis solicitudes' },
                        { key: 'pendientes',        label: 'Pendientes de acción' },
                        { key: 'pendientes_cierre', label: 'Pendientes por cerrar' },
                        { key: 'revisadas',         label: 'Revisadas' },
```

Y en el empty state (líneas 44-50), reemplazar la expresión del `<p>` por:

```jsx
                            {tab === 'revisadas'
                                ? 'Aún no has revisado ninguna solicitud.'
                                : tab === 'pendientes'
                                    ? 'No tienes solicitudes pendientes de acción.'
                                    : tab === 'pendientes_cierre'
                                        ? 'No hay solicitudes de oficina pendientes por cerrar.'
                                        : 'No hay solicitudes para mostrar.'}
```

- [ ] **Step 3: Compilar**

Run: `npm run build`
Expected: build sin errores.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/SolicitudController.php resources/js/Pages/Solicitudes/Index.jsx
git commit -m "feat(oficina): pestana de solicitudes pendientes por cerrar"
```

---

# FASE 6 — RR. HH. (oficina pagadas)

## Task 15: Datos de oficina pagadas para el panel RR. HH.

**Files:**
- Modify: `app/Http/Controllers/ComisionesRrhhController.php`
- Test: `tests/Feature/OficinaRrhhTest.php`

- [ ] **Step 1: Añadir la consulta de oficina al controlador**

En `app/Http/Controllers/ComisionesRrhhController.php`, reemplazar el import superior por:

```php
use App\Models\{Solicitud, ViajeroComision};
```

Antes del `return Inertia::render(...)` (línea 57), construir la lista de oficina:

```php
        $oficina = Solicitud::with(['solicitante', 'solicitable.abonos', 'solicitable.beneficiarios'])
            ->whereHas('tipoSolicitud', fn ($q) => $q->where('clave', 'OFI'))
            ->whereIn('estado', ['pendiente_cierre', 'cerrada'])
            ->latest()
            ->get()
            ->map(function ($s) {
                $c = $s->solicitable;
                return [
                    'id'            => $s->id,
                    'radicado'      => $s->radicado,
                    'estado'        => $s->estado,
                    'solicitante'   => $s->solicitante->name,
                    'beneficiarios' => $c->beneficiarios->map(fn ($e) => trim($e->nombres.' '.$e->apellidos))->values(),
                    'total'         => (float) $c->total,
                    'pagado'        => $c->totalPagado(),
                    'saldo'         => $c->saldo(),
                    'abonos'        => $c->abonos->map(fn ($a) => [
                        'id'         => $a->id,
                        'monto'      => (float) $a->monto,
                        'fecha_pago' => optional($a->fecha_pago)->toDateString(),
                    ])->values(),
                ];
            });
```

Y añadir `$oficina` al render:

```php
        return Inertia::render('Rrhh/Comisiones', [
            'comisionados' => $comisionados,
            'oficina'      => $oficina,
            'filtros'      => ['desde' => $desde, 'hasta' => $hasta, 'nombre' => $nombre, 'comision' => $comision],
        ]);
```

- [ ] **Step 2: Escribir el test**

Crear `tests/Feature/OficinaRrhhTest.php`:

```php
<?php
namespace Tests\Feature;

use App\Models\{AbonoOficina, Area, ItemOficina, Solicitud, SolicitudOficina, TipoSolicitud, Usuario};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OficinaRrhhTest extends TestCase
{
    use RefreshDatabase;

    public function test_rrhh_ve_solicitudes_de_oficina_con_abonos(): void
    {
        $this->seed();
        $rrhh  = Usuario::where('email','rrhh@demo.test')->firstOrFail();
        $lider = Usuario::where('email','lider.area@demo.test')->firstOrFail();
        $cl    = Usuario::where('email','contabilidad.lider@demo.test')->firstOrFail();
        $tipo  = TipoSolicitud::where('clave','OFI')->firstOrFail();

        $cab = SolicitudOficina::create(['beneficiario'=>'','urgencia'=>'media','justificacion'=>'x','total'=>50000]);
        ItemOficina::create(['solicitud_oficina_id'=>$cab->id,'nombre'=>'Mouse','categoria'=>'producto','cantidad'=>1,'costo_estimado'=>50000,'subtotal'=>50000]);
        $s = Solicitud::create([
            'tipo_solicitud_id'=>$tipo->id,'solicitante_id'=>$lider->id,'area_id'=>Area::first()->id,
            'solicitable_type'=>SolicitudOficina::class,'solicitable_id'=>$cab->id,'estado'=>'pendiente_cierre',
            'radicado'=>Solicitud::generarRadicado($tipo),
        ]);
        AbonoOficina::create([
            'solicitud_oficina_id'=>$cab->id,'monto'=>50000,'fecha_pago'=>'2026-08-06',
            'soporte_path'=>'soportes_pago/x.pdf','soporte_nombre'=>'x.pdf','usuario_id'=>$cl->id,
        ]);

        $this->actingAs($rrhh)
            ->get(route('rrhh.comisiones'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Rrhh/Comisiones')
                ->has('oficina', 1)
                ->where('oficina.0.radicado', $s->radicado)
                ->where('oficina.0.pagado', 50000.0)
                ->where('oficina.0.saldo', 0.0)
            );
    }
}
```

- [ ] **Step 3: Ejecutar el test**

Run: `php artisan test --filter=OficinaRrhhTest`
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/ComisionesRrhhController.php tests/Feature/OficinaRrhhTest.php
git commit -m "feat(rrhh): datos de solicitudes de oficina pagadas en el panel"
```

---

## Task 16: Pestañas en el panel RR. HH. (comisiones + oficina)

**Files:**
- Modify: `resources/js/Pages/Rrhh/Comisiones.jsx`

- [ ] **Step 1: Recibir el prop `oficina` y añadir estado de pestaña**

Cambiar la firma (línea 13):

```jsx
export default function Comisiones({ comisionados, oficina = [], filtros }) {
```

Tras los `useState` de filtros (línea 18), añadir:

```jsx
    const [pestana, setPestana] = useState('comisiones'); // 'comisiones' | 'oficina'
```

- [ ] **Step 2: Insertar la barra de pestañas y envolver el contenido**

Después del bloque de cabecera (el `</div>` que cierra el `<div>` del `<h2>Personal en comisión</h2>`, línea
~58), insertar:

```jsx
                {/* Pestañas */}
                <div className="flex gap-1 border-b border-slate-200">
                    {[
                        { key: 'comisiones', label: 'Personal en comisión' },
                        { key: 'oficina',    label: 'Solicitudes de oficina' },
                    ].map(({ key, label }) => (
                        <button key={key} onClick={() => setPestana(key)}
                            className={[
                                'px-4 py-2 text-sm font-medium border-b-2 -mb-px transition-colors',
                                pestana === key ? 'border-indigo-600 text-indigo-600' : 'border-transparent text-slate-500 hover:text-slate-700',
                            ].join(' ')}>
                            {label}
                        </button>
                    ))}
                </div>
```

Envolver el bloque de filtros + tabla de comisiones existente (el `<form>` de filtros y la tabla/modal de
comisionados) en `{pestana === 'comisiones' && ( ... )}`. Añadir el bloque de oficina:

```jsx
                {pestana === 'oficina' && (
                    <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
                        {oficina.length === 0 ? (
                            <p className="text-sm text-slate-400 text-center py-10">No hay solicitudes de oficina pagadas.</p>
                        ) : (
                            <table className="w-full text-sm">
                                <thead className="bg-slate-50 text-slate-500 text-xs uppercase">
                                    <tr>
                                        <th className="text-left px-4 py-2">Radicado</th>
                                        <th className="text-left px-4 py-2">Beneficiarios</th>
                                        <th className="text-right px-4 py-2">Total</th>
                                        <th className="text-right px-4 py-2">Pagado</th>
                                        <th className="text-right px-4 py-2">Saldo</th>
                                        <th className="text-left px-4 py-2">Estado</th>
                                        <th className="text-left px-4 py-2">Soportes</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {oficina.map((o) => (
                                        <tr key={o.id} className="border-t border-slate-100">
                                            <td className="px-4 py-2 font-mono text-xs">{o.radicado}</td>
                                            <td className="px-4 py-2">{o.beneficiarios.join(', ') || '—'}</td>
                                            <td className="px-4 py-2 text-right">{formatearMoneda(o.total)}</td>
                                            <td className="px-4 py-2 text-right text-emerald-700">{formatearMoneda(o.pagado)}</td>
                                            <td className="px-4 py-2 text-right">{formatearMoneda(o.saldo)}</td>
                                            <td className="px-4 py-2"><BadgeEstado estado={o.estado} /></td>
                                            <td className="px-4 py-2">
                                                <div className="flex flex-col gap-0.5">
                                                    {o.abonos.map((ab) => (
                                                        <a key={ab.id}
                                                            href={route('oficina.abono.soporte', [o.id, ab.id])}
                                                            className="text-xs text-indigo-600 hover:underline">
                                                            {formatearMoneda(ab.monto)} · {ab.fecha_pago}
                                                        </a>
                                                    ))}
                                                </div>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        )}
                    </div>
                )}
```

> `BadgeEstado` y `formatearMoneda` ya están importados en Comisiones.jsx.

- [ ] **Step 3: Compilar**

Run: `npm run build`
Expected: build sin errores.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Rrhh/Comisiones.jsx
git commit -m "feat(rrhh): pestana de solicitudes de oficina pagadas con descarga de soporte"
```

---

# FASE 7 — Verificación final

## Task 17: Suite completa, build y re-seed

- [ ] **Step 1: Ejecutar toda la suite**

Run: `php artisan test`
Expected: todos verdes. Atención a los tests OFI actualizados (MotorWorkflowOficinaTest, CotizacionOficinaTest,
CrearSolicitudOficinaTest) y a los nuevos (Beneficiarios, Abono, OficinaRrhh).

- [ ] **Step 2: Build de producción**

Run: `npm run build`
Expected: build sin errores.

- [ ] **Step 3: Re-seedear el tipo OFI (si no se hizo en Task 4)**

Run: `php artisan db:seed --class=TipoSolicitudSeeder`
Expected: seeding completado.

- [ ] **Step 4: Verificar árbol limpio**

Run: `git status --short`
Expected: sin cambios pendientes (los assets de `public/build` no se versionan según convención del proyecto).

---

## Cobertura del spec (checklist de auto-revisión)

- Punto 2 (beneficiarios múltiples): Tasks 1, 8, 11. ✔
- Punto 3 (cotizaciones gestionables por autor): Tasks 2, 6, 7, 10, 12. ✔
- Punto 4 (aviso a contadores al verificar): Task 4 (`notificar: contador`). ✔
- Punto 5 (soporte de pago + total pagado): Tasks 3, 9, 10, 13. ✔
- Punto 6 (pagos parciales + pendiente por cerrar): Tasks 3, 4, 9, 13, 14. ✔
- Punto 7 ("Aprobar" → "Enviar a gerencia"): Tasks 4, 5. ✔
- Punto 8 (RR. HH. ve pagadas + descarga soporte): Tasks 15, 16 (+ endpoint soporte en Task 9). ✔
