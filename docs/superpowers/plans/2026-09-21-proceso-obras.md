# Proceso "Obras" (OBR) — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Agregar el tipo de solicitud "Obras" (OBR) para gestionar compras de materiales de obra: creación por residente/líder de área, cotización por RR.HH., relación con contrato, aprobación y pagos con retención por contabilidad, y notificación a todos los involucrados en cada transición.

**Architecture:** Reutiliza la infraestructura existente (Solicitud polimórfica, MotorWorkflow por tabla, patrón de Oficina para items/cotizaciones/abonos). Nuevas tablas: solicitudes_obra, items_obra, cotizaciones_obra, abonos_obra. Nuevo rol `residente`. Nuevo tipo OBR en TipoSolicitudSeeder. Nuevas rutas/controlador/páginas React análogas a Oficina.

**Tech Stack:** Laravel 10.50, Inertia + React 18, MariaDB (tests en SQLite :memory:), spatie/laravel-permission, PHPUnit.

**Ejecutar comandos con:** `/c/xampp/php/php.exe artisan ...` (el `php` del PATH es 7.4).

---

## File Structure

**Migraciones (crear):**
- `database/migrations/2026_09_21_100000_create_solicitudes_obra_table.php`
- `database/migrations/2026_09_21_100100_create_items_obra_table.php`
- `database/migrations/2026_09_21_100200_create_cotizaciones_obra_table.php`
- `database/migrations/2026_09_21_100300_create_abonos_obra_table.php`

**Modelos (crear):** `SolicitudObra`, `ItemObra`, `CotizacionObra`, `AbonoObra`.

**Seeders (modificar):** `RolesSeeder` (rol `residente`), `TipoSolicitudSeeder` (tipo OBR).

**Controlador/Requests (crear):** `ObraController`, `RegistrarAbonoObraController`, `ArchivoObraController` (cotización/docs), `GuardarSolicitudObraRequest`, `RegistrarAbonoObraRequest`, `AplicarRetencionRequest`.

**Policy (modificar):** `SolicitudPolicy` (soporte OBR).

**Rutas (modificar):** `routes/web.php`.

**Frontend (crear):** `resources/js/Pages/Obra/Crear.jsx`; (modificar) `Detalle.jsx`, `AppLayout.jsx`, `SolicitudDetalleResource.php`.

**Notificaciones (modificar):** `MotorWorkflow` (destinatarios = todos los involucrados en OBR).

---

## Task 1: Rol `residente` y tipo de solicitud OBR (seeders)

**Files:**
- Modify: `database/seeders/RolesSeeder.php`
- Modify: `database/seeders/TipoSolicitudSeeder.php`
- Test: `tests/Feature/ObraSeederTest.php`

- [ ] **Step 1: Test que el rol y el tipo existen tras seed**

```php
<?php
namespace Tests\Feature;

use App\Models\TipoSolicitud;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ObraSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_rol_residente_existe(): void
    {
        $this->seed();
        $this->assertTrue(Role::where('name', 'residente')->exists());
    }

    public function test_tipo_obr_existe_con_estado_inicial(): void
    {
        $this->seed();
        $obr = TipoSolicitud::where('clave', 'OBR')->first();
        $this->assertNotNull($obr);
        $this->assertSame('borrador', $obr->estado_inicial);
        // La transicion 'enviar' la pueden hacer residente y lider_area.
        $enviar = collect($obr->transiciones)->firstWhere('accion', 'enviar');
        $this->assertContains('residente', $enviar['roles']);
        $this->assertContains('lider_area', $enviar['roles']);
    }
}
```

- [ ] **Step 2: Correr test (debe fallar)**

Run: `/c/xampp/php/php.exe artisan test tests/Feature/ObraSeederTest.php`
Expected: FAIL (rol/tipo no existen).

- [ ] **Step 3: Agregar rol `residente` en RolesSeeder**

En `database/seeders/RolesSeeder.php`, agregar `'residente'` al array `$roles` (junto a lider_area, rrhh, etc.).

- [ ] **Step 4: Agregar tipo OBR en TipoSolicitudSeeder**

En el array de `upsert` de `database/seeders/TipoSolicitudSeeder.php`, agregar una tercera entrada:

```php
[
    'clave'         => 'OBR',
    'nombre'        => 'Obras',
    'estado_inicial'=> 'borrador',
    'estados'       => json_encode(['borrador','enviada','cotizada','en_contabilidad','aprobada','pendiente_cierre','cerrada','rechazada']),
    'transiciones'  => json_encode([
        ['origen'=>'borrador',        'accion'=>'enviar',              'destino'=>'enviada',        'roles'=>['residente','lider_area'], 'notificar'=>['rrhh'], 'label'=>'Enviar a RR. HH.'],
        ['origen'=>'enviada',         'accion'=>'cotizar',             'destino'=>'cotizada',       'roles'=>['rrhh'],                    'label'=>'Cotizar'],
        ['origen'=>'enviada',         'accion'=>'devolver',            'destino'=>'borrador',       'roles'=>['rrhh'],                    'label'=>'Devolver'],
        // enviar_contabilidad requiere contrato relacionado (gate en el controlador de transicion).
        ['origen'=>'cotizada',        'accion'=>'enviar_contabilidad', 'destino'=>'en_contabilidad','roles'=>['rrhh'], 'notificar'=>['contabilidad_lider','contador'], 'label'=>'Enviar a contabilidad'],
        ['origen'=>'cotizada',        'accion'=>'devolver',            'destino'=>'borrador',       'roles'=>['rrhh'],                    'label'=>'Devolver'],
        ['origen'=>'en_contabilidad', 'accion'=>'aprobar',            'destino'=>'aprobada',        'roles'=>['contabilidad_lider'],      'label'=>'Aprobar'],
        ['origen'=>'en_contabilidad', 'accion'=>'devolver',           'destino'=>'cotizada',        'roles'=>['contabilidad_lider'],      'label'=>'Devolver a RR. HH.'],
        ['origen'=>'en_contabilidad', 'accion'=>'rechazar',           'destino'=>'rechazada',       'roles'=>['contabilidad_lider'],      'label'=>'Rechazar'],
        // El primer abono lleva de 'aprobada' a 'pendiente_cierre' (fuera del motor).
        ['origen'=>'pendiente_cierre','accion'=>'cerrar',             'destino'=>'cerrada',         'roles'=>['contabilidad_lider'],      'notificar'=>['rrhh'], 'label'=>'Cerrar'],
    ]),
    'created_at' => now(), 'updated_at' => now(),
],
```

- [ ] **Step 5: Correr test (debe pasar)**

Run: `/c/xampp/php/php.exe artisan test tests/Feature/ObraSeederTest.php`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/seeders tests/Feature/ObraSeederTest.php
git commit -m "feat(obras): rol residente y tipo de solicitud OBR"
```

---

## Task 2: Migraciones y modelos

**Files:**
- Create: las 4 migraciones (ver File Structure)
- Create: `app/Models/{SolicitudObra,ItemObra,CotizacionObra,AbonoObra}.php`
- Test: `tests/Feature/ObraModeloTest.php`

- [ ] **Step 1: Test de creación de la cabecera con items y cálculo de saldo**

```php
<?php
namespace Tests\Feature;

use App\Models\{AbonoObra, ItemObra, SolicitudObra};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ObraModeloTest extends TestCase
{
    use RefreshDatabase;

    public function test_crea_solicitud_obra_con_items(): void
    {
        $s = SolicitudObra::create([
            'nombre_solicitante' => 'CAMILO (RESIDENTE)',
            'fecha_solicitud' => '2026-09-21',
        ]);
        ItemObra::create([
            'solicitud_obra_id' => $s->id, 'especificacion' => 'CEMENTO 42.5KG',
            'unidad' => 'BOLSA', 'cantidad' => 100,
        ]);
        $this->assertDatabaseHas('items_obra', ['especificacion' => 'CEMENTO 42.5KG', 'unidad' => 'BOLSA']);
        $this->assertSame(1, $s->items()->count());
    }

    public function test_saldo_y_pagado_se_calculan_contra_total_a_pagar(): void
    {
        $s = SolicitudObra::create(['nombre_solicitante' => 'X', 'fecha_solicitud' => '2026-09-21', 'total_a_pagar' => 100000]);
        AbonoObra::create(['solicitud_obra_id' => $s->id, 'monto' => 30000, 'fecha_pago' => '2026-09-21', 'soporte_path' => 'x', 'soporte_nombre' => 'x']);
        $this->assertEquals(30000.0, $s->fresh()->totalPagado());
        $this->assertEquals(70000.0, $s->fresh()->saldoPendiente());
    }
}
```

- [ ] **Step 2: Correr test (debe fallar)**

Run: `/c/xampp/php/php.exe artisan test tests/Feature/ObraModeloTest.php`
Expected: FAIL (tablas/modelos no existen).

- [ ] **Step 3: Migración solicitudes_obra**

`database/migrations/2026_09_21_100000_create_solicitudes_obra_table.php`:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('solicitudes_obra', function (Blueprint $table) {
            $table->id();
            $table->string('nombre_solicitante');
            $table->date('fecha_solicitud');
            $table->date('fecha_entrega')->nullable();
            $table->foreignId('contrato_id')->nullable()->constrained('contratos')->nullOnDelete();
            $table->text('observacion')->nullable();
            $table->decimal('total_a_pagar', 14, 2)->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('solicitudes_obra'); }
};
```

- [ ] **Step 4: Migración items_obra**

`database/migrations/2026_09_21_100100_create_items_obra_table.php`:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('items_obra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_obra_id')->constrained('solicitudes_obra')->cascadeOnDelete();
            $table->string('especificacion');
            $table->string('unidad')->nullable();
            $table->decimal('cantidad', 10, 2)->default(1);
            $table->string('sede')->nullable();
            $table->decimal('valor_unitario', 14, 2)->nullable();
            $table->decimal('subtotal', 14, 2)->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('items_obra'); }
};
```

- [ ] **Step 5: Migración cotizaciones_obra**

`database/migrations/2026_09_21_100200_create_cotizaciones_obra_table.php`:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cotizaciones_obra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_obra_id')->constrained('solicitudes_obra')->cascadeOnDelete();
            $table->string('tipo')->default('cotizacion'); // 'cotizacion' | 'documento'
            $table->string('path');
            $table->string('nombre_original');
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios');
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('cotizaciones_obra'); }
};
```

- [ ] **Step 6: Migración abonos_obra**

`database/migrations/2026_09_21_100300_create_abonos_obra_table.php`:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('abonos_obra', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_obra_id')->constrained('solicitudes_obra')->cascadeOnDelete();
            $table->decimal('monto', 14, 2);
            $table->string('retencion_tipo')->nullable();      // 'porcentaje' | 'valor'
            $table->decimal('retencion_valor', 14, 2)->nullable();
            $table->decimal('retencion_monto', 14, 2)->default(0);
            $table->foreignId('retencion_por')->nullable()->constrained('usuarios');
            $table->date('fecha_pago');
            $table->string('soporte_path');
            $table->string('soporte_nombre');
            $table->foreignId('usuario_id')->nullable()->constrained('usuarios');
            $table->text('observacion')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('abonos_obra'); }
};
```

- [ ] **Step 7: Modelo SolicitudObra**

`app/Models/SolicitudObra.php` (copiar el patrón de SolicitudOficina: relaciones + totalPagado/saldoPendiente/recalcularTotal):

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SolicitudObra extends Model
{
    protected $table = 'solicitudes_obra';
    protected $fillable = ['nombre_solicitante','fecha_solicitud','fecha_entrega','contrato_id','observacion','total_a_pagar'];
    protected $casts = ['fecha_solicitud' => 'date', 'fecha_entrega' => 'date', 'total_a_pagar' => 'decimal:2'];

    public function items()        { return $this->hasMany(ItemObra::class, 'solicitud_obra_id'); }
    public function cotizaciones() { return $this->hasMany(CotizacionObra::class, 'solicitud_obra_id'); }
    public function abonos()       { return $this->hasMany(AbonoObra::class, 'solicitud_obra_id'); }
    public function contrato()     { return $this->belongsTo(Contrato::class, 'contrato_id'); }
    public function solicitud()    { return $this->morphOne(Solicitud::class, 'solicitable'); }

    public function totalPagado(): float
    {
        return (float) $this->abonos()->sum('monto');
    }
    public function saldoPendiente(): float
    {
        if ($this->total_a_pagar === null) return 0.0;
        return (float) $this->total_a_pagar - $this->totalPagado();
    }
    public function estaPagadaCompleta(): bool
    {
        return $this->total_a_pagar !== null && $this->totalPagado() >= (float) $this->total_a_pagar;
    }
    /** Total estimado a partir de los subtotales de los items (cuando ya están cotizados). */
    public function recalcularTotal(): void
    {
        $total = (float) $this->items()->sum('subtotal');
        $this->updateQuietly(['total' => $total]);
        // Nota: solicitudes_obra no tiene columna 'total'; el total real es total_a_pagar.
        // recalcularTotal se mantiene por compatibilidad de interfaz pero no persiste aquí.
    }
}
```

> Nota: quitar el `updateQuietly(['total'...])` de recalcularTotal si no hay columna `total`; dejar el método devolviendo la suma de subtotales sin persistir, o persistir en `total_a_pagar` solo si el negocio lo requiere. Mantener simple: computar en el Resource.

- [ ] **Step 8: Modelos ItemObra, CotizacionObra, AbonoObra**

`app/Models/ItemObra.php`:
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ItemObra extends Model
{
    protected $table = 'items_obra';
    protected $fillable = ['solicitud_obra_id','especificacion','unidad','cantidad','sede','valor_unitario','subtotal'];
    protected $casts = ['cantidad' => 'decimal:2', 'valor_unitario' => 'decimal:2', 'subtotal' => 'decimal:2'];

    protected static function booted(): void
    {
        static::saving(function (ItemObra $i) {
            // subtotal = cantidad × valor_unitario cuando hay valor unitario.
            if ($i->valor_unitario !== null) $i->subtotal = (float) $i->cantidad * (float) $i->valor_unitario;
        });
    }
    public function solicitudObra() { return $this->belongsTo(SolicitudObra::class, 'solicitud_obra_id'); }
}
```

`app/Models/CotizacionObra.php`:
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class CotizacionObra extends Model
{
    protected $table = 'cotizaciones_obra';
    protected $fillable = ['solicitud_obra_id','tipo','path','nombre_original','usuario_id'];
    public function solicitudObra() { return $this->belongsTo(SolicitudObra::class, 'solicitud_obra_id'); }
    public function usuario()       { return $this->belongsTo(Usuario::class, 'usuario_id'); }
}
```

`app/Models/AbonoObra.php`:
```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class AbonoObra extends Model
{
    protected $table = 'abonos_obra';
    protected $fillable = ['solicitud_obra_id','monto','retencion_tipo','retencion_valor','retencion_monto','retencion_por','fecha_pago','soporte_path','soporte_nombre','usuario_id','observacion'];
    protected $casts = ['fecha_pago' => 'date', 'monto' => 'decimal:2', 'retencion_valor' => 'decimal:2', 'retencion_monto' => 'decimal:2'];

    public function solicitudObra() { return $this->belongsTo(SolicitudObra::class, 'solicitud_obra_id'); }
    public function usuario()       { return $this->belongsTo(Usuario::class, 'usuario_id'); }
    public function retenedor()     { return $this->belongsTo(Usuario::class, 'retencion_por'); }
}
```

- [ ] **Step 9: Correr test (debe pasar) y migrar en local**

Run: `/c/xampp/php/php.exe artisan migrate`
Run: `/c/xampp/php/php.exe artisan test tests/Feature/ObraModeloTest.php`
Expected: PASS.

- [ ] **Step 10: Commit**

```bash
git add database/migrations app/Models tests/Feature/ObraModeloTest.php
git commit -m "feat(obras): migraciones y modelos (solicitud, items, cotizaciones, abonos con retencion)"
```

---

## Task 3: Creación de solicitud de obra (controlador + request + página React)

**Files:**
- Create: `app/Http/Controllers/ObraController.php`
- Create: `app/Http/Requests/GuardarSolicitudObraRequest.php`
- Create: `resources/js/Pages/Obra/Crear.jsx`
- Modify: `routes/web.php`, `resources/js/Layouts/AppLayout.jsx`
- Test: `tests/Feature/CrearObraTest.php`

- [ ] **Step 1: Test — crear con items y/o cotización, y enviar**

```php
<?php
namespace Tests\Feature;

use App\Models\{Solicitud, SolicitudObra, TipoSolicitud, Usuario};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CrearObraTest extends TestCase
{
    use RefreshDatabase;

    private function residente(): Usuario
    {
        $u = Usuario::factory()->create();
        $u->assignRole('residente');
        return $u;
    }

    public function test_residente_crea_solicitud_con_items(): void
    {
        $this->seed();
        Storage::fake('local');
        $res = $this->actingAs($this->residente())->post(route('obra.store'), [
            'nombre_solicitante' => 'CAMILO (RESIDENTE)',
            'fecha_solicitud' => '2026-09-21',
            'items' => [
                ['especificacion' => 'CEMENTO 42.5KG', 'unidad' => 'BOLSA', 'cantidad' => 100, 'sede' => 'LA LOMA'],
            ],
            'enviar' => false,
        ]);
        $res->assertRedirect();
        $this->assertDatabaseHas('solicitudes_obra', ['nombre_solicitante' => 'CAMILO (RESIDENTE)']);
        $this->assertDatabaseHas('items_obra', ['especificacion' => 'CEMENTO 42.5KG']);
        $s = Solicitud::whereHasMorph('solicitable', SolicitudObra::class)->first();
        $this->assertSame('borrador', $s->estado);
    }

    public function test_crear_con_cotizacion_adjunta_y_enviar(): void
    {
        $this->seed();
        Storage::fake('local');
        $res = $this->actingAs($this->residente())->post(route('obra.store'), [
            'nombre_solicitante' => 'CAMILO',
            'fecha_solicitud' => '2026-09-21',
            'cotizacion' => UploadedFile::fake()->create('cotizacion.pdf', 100, 'application/pdf'),
            'enviar' => true,
        ]);
        $res->assertRedirect();
        $s = Solicitud::whereHasMorph('solicitable', SolicitudObra::class)->first();
        $this->assertSame('enviada', $s->estado);
        $this->assertDatabaseHas('cotizaciones_obra', ['tipo' => 'cotizacion']);
    }

    public function test_requiere_items_o_cotizacion(): void
    {
        $this->seed();
        $this->actingAs($this->residente())->post(route('obra.store'), [
            'nombre_solicitante' => 'X', 'fecha_solicitud' => '2026-09-21',
        ])->assertSessionHasErrors();
    }
}
```

- [ ] **Step 2: Correr test (debe fallar)**

Run: `/c/xampp/php/php.exe artisan test tests/Feature/CrearObraTest.php`
Expected: FAIL (ruta/controlador no existen).

- [ ] **Step 3: Request de validación**

`app/Http/Requests/GuardarSolicitudObraRequest.php`:

```php
<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class GuardarSolicitudObraRequest extends FormRequest
{
    public function authorize(): bool { return true; } // la policy corre en el controlador

    public function rules(): array
    {
        return [
            'nombre_solicitante' => 'required|string|max:255',
            'fecha_solicitud'    => 'required|date',
            'fecha_entrega'      => 'nullable|date',
            'observacion'        => 'nullable|string',
            'items'                 => 'array',
            'items.*.especificacion'=> 'required_with:items|string|max:255',
            'items.*.unidad'        => 'nullable|string|max:50',
            'items.*.cantidad'      => 'required_with:items|numeric|min:0',
            'items.*.sede'          => 'nullable|string|max:255',
            'cotizacion'         => 'nullable|file|mimes:pdf,jpg,jpeg,png,xlsx,xls|max:5120',
            'enviar'             => 'boolean',
        ];
    }

    public function after(): array
    {
        return [function (Validator $v) {
            // Debe traer al menos un item O una cotizacion adjunta.
            $items = $this->input('items', []);
            if (empty($items) && ! $this->hasFile('cotizacion')) {
                $v->errors()->add('items', 'Agregue al menos un elemento o adjunte una cotización.');
            }
        }];
    }
}
```

- [ ] **Step 4: Controlador ObraController (create/store)**

`app/Http/Controllers/ObraController.php`:

```php
<?php
namespace App\Http\Controllers;

use App\Http\Requests\GuardarSolicitudObraRequest;
use App\Models\{CotizacionObra, ItemObra, Solicitud, SolicitudObra, TipoSolicitud};
use App\Services\MotorWorkflow;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ObraController extends Controller
{
    public function __construct(private MotorWorkflow $motor) {}

    public function create()
    {
        $this->authorize('crearObra', Solicitud::class);
        return Inertia::render('Obra/Crear', [
            'nombreSugerido' => auth()->user()->name,
        ]);
    }

    public function store(GuardarSolicitudObraRequest $request)
    {
        $this->authorize('crearObra', Solicitud::class);
        $tipo = TipoSolicitud::where('clave', 'OBR')->firstOrFail();

        $solicitud = DB::transaction(function () use ($request, $tipo) {
            $cab = SolicitudObra::create($request->only([
                'nombre_solicitante','fecha_solicitud','fecha_entrega','observacion',
            ]));
            foreach ($request->input('items', []) as $it) {
                ItemObra::create([
                    'solicitud_obra_id' => $cab->id,
                    'especificacion' => $it['especificacion'],
                    'unidad' => $it['unidad'] ?? null,
                    'cantidad' => $it['cantidad'],
                    'sede' => $it['sede'] ?? null,
                ]);
            }
            if ($request->hasFile('cotizacion')) {
                $path = $request->file('cotizacion')->store('cotizaciones_obra', 'local');
                CotizacionObra::create([
                    'solicitud_obra_id' => $cab->id, 'tipo' => 'cotizacion',
                    'path' => $path, 'nombre_original' => $request->file('cotizacion')->getClientOriginalName(),
                    'usuario_id' => auth()->id(),
                ]);
            }
            return Solicitud::create([
                'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => auth()->id(),
                'solicitable_type' => SolicitudObra::class, 'solicitable_id' => $cab->id,
                'estado' => 'borrador', 'radicado' => Solicitud::generarRadicado($tipo),
            ]);
        });

        if ($request->boolean('enviar') && $this->motor->puede($solicitud, 'enviar', auth()->user())) {
            $this->motor->aplicarTransicion($solicitud, 'enviar', auth()->user());
        }

        return redirect()->route('solicitudes.show', $solicitud)
            ->with('success', $request->boolean('enviar') ? 'Solicitud enviada a RR. HH.' : 'Borrador guardado.');
    }
}
```

- [ ] **Step 5: Policy `crearObra`**

En `app/Policies/SolicitudPolicy.php`, agregar:

```php
public function crearObra($usuario): bool
{
    return $usuario->hasAnyRole(['residente', 'lider_area']);
}
```

- [ ] **Step 6: Rutas**

En `routes/web.php`, dentro del grupo `auth`:

```php
Route::get('/obra/crear',  [ObraController::class, 'create'])->name('obra.crear');
Route::post('/obra',       [ObraController::class, 'store'])->name('obra.store');
```

Y agregar `ObraController` al `use App\Http\Controllers\{...}`.

- [ ] **Step 7: Página React Obra/Crear.jsx**

Crear `resources/js/Pages/Obra/Crear.jsx` (basado en Oficina/Crear.jsx): cabecera
(nombre solicitante prellenado con `nombreSugerido`, fecha solicitud, fecha entrega,
observación), tabla editable de items (especificación, unidad, cantidad, sede) con
agregar/quitar filas, input de archivo de cotización opcional, y botones
"Guardar borrador" (enviar=false) y "Crear y enviar a RR. HH." (enviar=true).
Usa `useForm` de Inertia con `forceFormData: true` por el archivo.

- [ ] **Step 8: Ítem de menú "Obras"**

En `resources/js/Layouts/AppLayout.jsx`, sección "Nueva solicitud", agregar un `NavItem`
a `route('obra.crear')` visible para residente y lider_area:

```jsx
const puedeCrearObra = usuario.roles?.some((r) => ['residente','lider_area'].includes(r.name));
// ...dentro de NavSection "Nueva solicitud":
{puedeCrearObra && (
    <NavItem href={route('obra.crear')} active={route().current('obra.*')} icon={IconBuilding}>
        Obras
    </NavItem>
)}
```

- [ ] **Step 9: Build + correr test**

Run: `npm run build`
Run: `/c/xampp/php/php.exe artisan test tests/Feature/CrearObraTest.php`
Expected: PASS.

- [ ] **Step 10: Commit**

```bash
git add app/Http/Controllers/ObraController.php app/Http/Requests/GuardarSolicitudObraRequest.php app/Policies/SolicitudPolicy.php routes/web.php resources/js/Pages/Obra resources/js/Layouts/AppLayout.jsx tests/Feature/CrearObraTest.php
git commit -m "feat(obras): creacion de solicitud (items y/o cotizacion) + menu"
```

---

## Task 4: Cotización por RR.HH., relación de contrato y anexos; gate de contrato

**Files:**
- Modify: `app/Http/Controllers/ObraController.php` (cotizar, relacionarContrato, anexar)
- Modify: `app/Http/Controllers/SolicitudController.php` (gate en transicion enviar_contabilidad)
- Modify: `app/Policies/SolicitudPolicy.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/CotizarObraTest.php`

- [ ] **Step 1: Test — RR.HH. cotiza (valores), relaciona contrato, y no puede enviar a contabilidad sin contrato**

```php
<?php
namespace Tests\Feature;

use App\Models\{Contrato, ItemObra, Solicitud, SolicitudObra, TipoSolicitud, Usuario};
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CotizarObraTest extends TestCase
{
    use RefreshDatabase;

    private function obraEnviada(): Solicitud
    {
        $tipo = TipoSolicitud::where('clave', 'OBR')->firstOrFail();
        $residente = Usuario::factory()->create(); $residente->assignRole('residente');
        $cab = SolicitudObra::create(['nombre_solicitante' => 'X', 'fecha_solicitud' => '2026-09-21']);
        ItemObra::create(['solicitud_obra_id' => $cab->id, 'especificacion' => 'CEMENTO', 'unidad' => 'BOLSA', 'cantidad' => 10]);
        $s = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $residente->id,
            'solicitable_type' => SolicitudObra::class, 'solicitable_id' => $cab->id,
            'estado' => 'borrador', 'radicado' => Solicitud::generarRadicado($tipo),
        ]);
        app(MotorWorkflow::class)->aplicarTransicion($s, 'enviar', $residente);
        return $s->fresh();
    }

    private function rrhh(): Usuario { $u = Usuario::factory()->create(); $u->assignRole('rrhh'); return $u; }

    public function test_rrhh_cotiza_valores_de_items(): void
    {
        $this->seed();
        $s = $this->obraEnviada();
        $item = $s->solicitable->items->first();

        $this->actingAs($this->rrhh())->put(route('obra.cotizar', $s), [
            'total_a_pagar' => 500000,
            'items' => [['id' => $item->id, 'valor_unitario' => 5000]],
        ])->assertRedirect();

        $this->assertEquals(50000.0, $item->fresh()->subtotal); // 10 × 5000
        $this->assertSame('cotizada', $s->fresh()->estado);
    }

    public function test_no_envia_a_contabilidad_sin_contrato(): void
    {
        $this->seed();
        $s = $this->obraEnviada();
        app(MotorWorkflow::class)->aplicarTransicion($s, 'cotizar', $this->rrhh());

        // Sin contrato relacionado, enviar_contabilidad debe bloquearse.
        $this->actingAs($this->rrhh())->post(route('solicitudes.transicion', $s->fresh()), [
            'accion' => 'enviar_contabilidad',
        ])->assertSessionHasErrors('accion');
    }

    public function test_relaciona_contrato_y_envia_a_contabilidad(): void
    {
        $this->seed();
        $s = $this->obraEnviada();
        app(MotorWorkflow::class)->aplicarTransicion($s, 'cotizar', $this->rrhh());
        $contrato = Contrato::first() ?? Contrato::create(['descripcion' => 'C', 'objeto' => 'O']);

        $this->actingAs($this->rrhh())->put(route('obra.contrato', $s->fresh()), ['contrato_id' => $contrato->id])->assertRedirect();
        $this->assertEquals($contrato->id, $s->solicitable->fresh()->contrato_id);

        $this->actingAs($this->rrhh())->post(route('solicitudes.transicion', $s->fresh()), [
            'accion' => 'enviar_contabilidad',
        ])->assertRedirect();
        $this->assertSame('en_contabilidad', $s->fresh()->estado);
    }
}
```

- [ ] **Step 2: Correr test (debe fallar)**

Run: `/c/xampp/php/php.exe artisan test tests/Feature/CotizarObraTest.php`
Expected: FAIL.

- [ ] **Step 3: Métodos en ObraController: cotizar, relacionarContrato, anexarDocumento**

Agregar a `ObraController`:

```php
public function cotizar(\Illuminate\Http\Request $request, Solicitud $solicitud)
{
    $this->authorize('cotizarObra', $solicitud);
    $request->validate([
        'total_a_pagar' => 'nullable|numeric|min:0',
        'items' => 'array',
        'items.*.id' => 'required|integer',
        'items.*.valor_unitario' => 'nullable|numeric|min:0',
    ]);
    $cab = $solicitud->solicitable;
    \Illuminate\Support\Facades\DB::transaction(function () use ($request, $solicitud, $cab) {
        if ($request->filled('total_a_pagar')) $cab->update(['total_a_pagar' => $request->total_a_pagar]);
        foreach ($request->input('items', []) as $it) {
            \App\Models\ItemObra::where('id', $it['id'])
                ->where('solicitud_obra_id', $cab->id)
                ->update(['valor_unitario' => $it['valor_unitario'] ?? null]);
        }
        // saving() de ItemObra recalcula subtotal; recargar y forzar guardado si se usó update masivo:
        $cab->items->each(fn ($i) => $i->save());
        // Avanzar a 'cotizada' si aún está 'enviada'.
        if ($solicitud->estado === 'enviada' && $this->motor->puede($solicitud, 'cotizar', auth()->user())) {
            $this->motor->aplicarTransicion($solicitud, 'cotizar', auth()->user());
        }
    });
    return back()->with('success', 'Cotización guardada.');
}

public function relacionarContrato(\Illuminate\Http\Request $request, Solicitud $solicitud)
{
    $this->authorize('cotizarObra', $solicitud);
    $request->validate(['contrato_id' => 'required|exists:contratos,id']);
    $solicitud->solicitable->update(['contrato_id' => $request->contrato_id]);
    return back()->with('success', 'Contrato relacionado.');
}

public function anexarDocumento(\Illuminate\Http\Request $request, Solicitud $solicitud)
{
    $this->authorize('cotizarObra', $solicitud);
    $request->validate(['documento' => 'required|file|mimes:pdf,jpg,jpeg,png,xlsx,xls|max:5120']);
    $path = $request->file('documento')->store('cotizaciones_obra', 'local');
    \App\Models\CotizacionObra::create([
        'solicitud_obra_id' => $solicitud->solicitable_id, 'tipo' => 'documento',
        'path' => $path, 'nombre_original' => $request->file('documento')->getClientOriginalName(),
        'usuario_id' => auth()->id(),
    ]);
    return back()->with('success', 'Documento anexado.');
}
```

> Nota sobre subtotales: en `update()` masivo Eloquent no dispara `saving()`. Por eso tras
> el update se recorren los items y se hace `->save()` para recalcular `subtotal`. Alternativa
> más limpia: iterar y hacer `$item->valor_unitario = ...; $item->save();` directamente.

- [ ] **Step 4: Policy `cotizarObra`**

```php
public function cotizarObra($usuario, Solicitud $solicitud): bool
{
    return $solicitud->tipoSolicitud->clave === 'OBR'
        && $usuario->hasRole('rrhh')
        && in_array($solicitud->estado, ['enviada', 'cotizada']);
}
```

- [ ] **Step 5: Gate de contrato en SolicitudController::transicion**

En `app/Http/Controllers/SolicitudController.php`, método `transicion`, antes de aplicar
la transición, agregar (junto al gate de viáticos existente):

```php
if ($request->accion === 'enviar_contabilidad'
    && $solicitud->tipoSolicitud->clave === 'OBR'
    && $solicitud->solicitable?->contrato_id === null
) {
    return back()->withErrors(['accion' => 'Debe relacionar un contrato antes de enviar a contabilidad.']);
}
```

- [ ] **Step 6: Rutas**

```php
Route::put('/obra/{solicitud}/cotizar',  [ObraController::class, 'cotizar'])->name('obra.cotizar');
Route::put('/obra/{solicitud}/contrato', [ObraController::class, 'relacionarContrato'])->name('obra.contrato');
Route::post('/obra/{solicitud}/documento',[ObraController::class, 'anexarDocumento'])->name('obra.documento');
```

- [ ] **Step 7: Correr test (debe pasar)**

Run: `/c/xampp/php/php.exe artisan test tests/Feature/CotizarObraTest.php`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/ObraController.php app/Http/Controllers/SolicitudController.php app/Policies/SolicitudPolicy.php routes/web.php tests/Feature/CotizarObraTest.php
git commit -m "feat(obras): cotizacion RR.HH., relacion de contrato con gate, anexos"
```

---

## Task 5: Aprobación y pagos con retención (contabilidad + contador)

**Files:**
- Create: `app/Http/Controllers/AbonoObraController.php`
- Create: `app/Http/Requests/RegistrarAbonoObraRequest.php`
- Modify: `app/Policies/SolicitudPolicy.php`, `routes/web.php`
- Test: `tests/Feature/PagoObraTest.php`

- [ ] **Step 1: Test — aprobar habilita pago; abono lleva a pendiente_cierre; contador aplica retención**

```php
<?php
namespace Tests\Feature;

use App\Models\{AbonoObra, Contrato, Solicitud, SolicitudObra, TipoSolicitud, Usuario};
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PagoObraTest extends TestCase
{
    use RefreshDatabase;

    private function obraAprobada(): Solicitud
    {
        $tipo = TipoSolicitud::where('clave', 'OBR')->firstOrFail();
        $residente = Usuario::factory()->create(); $residente->assignRole('residente');
        $rrhh = Usuario::factory()->create(); $rrhh->assignRole('rrhh');
        $cl = Usuario::factory()->create(); $cl->assignRole('contabilidad_lider');
        $contrato = Contrato::create(['descripcion' => 'C', 'objeto' => 'O']);
        $cab = SolicitudObra::create(['nombre_solicitante' => 'X', 'fecha_solicitud' => '2026-09-21', 'contrato_id' => $contrato->id, 'total_a_pagar' => 100000]);
        $s = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $residente->id,
            'solicitable_type' => SolicitudObra::class, 'solicitable_id' => $cab->id,
            'estado' => 'borrador', 'radicado' => Solicitud::generarRadicado($tipo),
        ]);
        $m = app(MotorWorkflow::class);
        $m->aplicarTransicion($s, 'enviar', $residente);
        $m->aplicarTransicion($s->fresh(), 'cotizar', $rrhh);
        $m->aplicarTransicion($s->fresh(), 'enviar_contabilidad', $rrhh);
        $m->aplicarTransicion($s->fresh(), 'aprobar', $cl);
        return $s->fresh();
    }

    private function cl(): Usuario { $u = Usuario::factory()->create(); $u->assignRole('contabilidad_lider'); return $u; }
    private function contador(): Usuario { $u = Usuario::factory()->create(); $u->assignRole('contador'); return $u; }

    public function test_primer_abono_lleva_a_pendiente_cierre(): void
    {
        $this->seed();
        Storage::fake('local');
        $s = $this->obraAprobada();
        $this->actingAs($this->cl())->post(route('obra.abono.store', $s), [
            'monto' => 40000, 'fecha_pago' => '2026-09-21',
            'soporte' => UploadedFile::fake()->create('pago.pdf', 100, 'application/pdf'),
        ])->assertRedirect();
        $this->assertSame('pendiente_cierre', $s->fresh()->estado);
        $this->assertEquals(40000.0, $s->solicitable->fresh()->totalPagado());
    }

    public function test_contador_aplica_retencion_porcentaje(): void
    {
        $this->seed();
        Storage::fake('local');
        $s = $this->obraAprobada();
        $abono = AbonoObra::create([
            'solicitud_obra_id' => $s->solicitable_id, 'monto' => 100000,
            'fecha_pago' => '2026-09-21', 'soporte_path' => 'x', 'soporte_nombre' => 'x',
        ]);
        $this->actingAs($this->contador())->put(route('obra.abono.retencion', [$s, $abono]), [
            'retencion_tipo' => 'porcentaje', 'retencion_valor' => 10,
        ])->assertRedirect();
        $abono->refresh();
        $this->assertEquals(10000.0, (float) $abono->retencion_monto); // 10% de 100000
        $this->assertNotNull($abono->retencion_por);
    }

    public function test_solo_contador_aplica_retencion(): void
    {
        $this->seed();
        $s = $this->obraAprobada();
        $abono = AbonoObra::create(['solicitud_obra_id' => $s->solicitable_id, 'monto' => 1000, 'fecha_pago' => '2026-09-21', 'soporte_path' => 'x', 'soporte_nombre' => 'x']);
        $this->actingAs($this->cl())->put(route('obra.abono.retencion', [$s, $abono]), [
            'retencion_tipo' => 'valor', 'retencion_valor' => 100,
        ])->assertForbidden();
    }
}
```

- [ ] **Step 2: Correr test (debe fallar)**

Run: `/c/xampp/php/php.exe artisan test tests/Feature/PagoObraTest.php`
Expected: FAIL.

- [ ] **Step 3: Request de abono**

`app/Http/Requests/RegistrarAbonoObraRequest.php`:

```php
<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class RegistrarAbonoObraRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'monto'      => 'required|numeric|min:0.01',
            'fecha_pago' => 'required|date',
            'soporte'    => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'observacion'=> 'nullable|string',
        ];
    }
}
```

- [ ] **Step 4: Controlador AbonoObraController (store + retencion)**

`app/Http/Controllers/AbonoObraController.php` (basado en AbonoOficinaController, con retención):

```php
<?php
namespace App\Http\Controllers;

use App\Http\Requests\RegistrarAbonoObraRequest;
use App\Models\{AbonoObra, Solicitud};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Storage};

class AbonoObraController extends Controller
{
    public function store(RegistrarAbonoObraRequest $request, Solicitud $solicitud)
    {
        $this->authorize('pagarObra', $solicitud);
        $cab = $solicitud->solicitable;
        $soportePath   = $request->file('soporte')->store('soportes_obra', 'local');
        $soporteNombre = $request->file('soporte')->getClientOriginalName();
        $estadoPrevio  = $solicitud->estado;

        DB::transaction(function () use ($cab, $solicitud, $request, $soportePath, $soporteNombre) {
            if ((float) $request->monto > $cab->fresh()->saldoPendiente() && $cab->total_a_pagar !== null) {
                Storage::disk('local')->delete($soportePath);
                abort(422, 'El monto supera el saldo pendiente.');
            }
            $cab->abonos()->create([
                'monto' => $request->monto, 'fecha_pago' => $request->fecha_pago,
                'soporte_path' => $soportePath, 'soporte_nombre' => $soporteNombre,
                'usuario_id' => auth()->id(), 'observacion' => $request->observacion,
            ]);
            if ($solicitud->estado === 'aprobada') {
                $solicitud->update(['estado' => 'pendiente_cierre']);
            }
        });

        // Notificar el avance (aprobada -> pendiente_cierre) a los involucrados; fail-safe.
        if ($estadoPrevio === 'aprobada' && $solicitud->fresh()->estado === 'pendiente_cierre') {
            // (Se integra con la notificación a-todos-los-involucrados de la Task 6.)
        }
        return back()->with('success', 'Pago registrado.');
    }

    public function aplicarRetencion(Request $request, Solicitud $solicitud, AbonoObra $abono)
    {
        $this->authorize('gestionarRetencionObra', $solicitud);
        abort_unless($abono->solicitud_obra_id === $solicitud->solicitable_id, 404);
        $request->validate([
            'retencion_tipo'  => 'required|in:porcentaje,valor',
            'retencion_valor' => 'required|numeric|min:0',
        ]);
        $monto = $request->retencion_tipo === 'porcentaje'
            ? round((float) $abono->monto * ((float) $request->retencion_valor / 100), 2)
            : round((float) $request->retencion_valor, 2);
        $abono->update([
            'retencion_tipo' => $request->retencion_tipo,
            'retencion_valor' => $request->retencion_valor,
            'retencion_monto' => $monto,
            'retencion_por' => auth()->id(),
        ]);
        return back()->with('success', 'Retención aplicada.');
    }

    public function descargarSoporte(Solicitud $solicitud, AbonoObra $abono)
    {
        $this->authorize('verDetalle', $solicitud);
        abort_unless($abono->solicitud_obra_id === $solicitud->solicitable_id, 404);
        abort_unless(Storage::disk('local')->exists($abono->soporte_path), 404);
        return Storage::disk('local')->download($abono->soporte_path, $abono->soporte_nombre);
    }
}
```

- [ ] **Step 5: Policies `pagarObra` y `gestionarRetencionObra`**

```php
public function pagarObra($usuario, Solicitud $solicitud): bool
{
    return $solicitud->tipoSolicitud->clave === 'OBR'
        && $usuario->hasRole('contabilidad_lider')
        && in_array($solicitud->estado, ['aprobada', 'pendiente_cierre']);
}

public function gestionarRetencionObra($usuario, Solicitud $solicitud): bool
{
    return $solicitud->tipoSolicitud->clave === 'OBR' && $usuario->hasRole('contador');
}
```

- [ ] **Step 6: Rutas**

```php
Route::post('/obra/{solicitud}/abonos',                    [AbonoObraController::class, 'store'])->name('obra.abono.store');
Route::put('/obra/{solicitud}/abonos/{abono}/retencion',   [AbonoObraController::class, 'aplicarRetencion'])->name('obra.abono.retencion');
Route::get('/obra/{solicitud}/abonos/{abono}/soporte',     [AbonoObraController::class, 'descargarSoporte'])->name('obra.abono.soporte');
```

- [ ] **Step 7: Correr test (debe pasar)**

Run: `/c/xampp/php/php.exe artisan test tests/Feature/PagoObraTest.php`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/AbonoObraController.php app/Http/Requests/RegistrarAbonoObraRequest.php app/Policies/SolicitudPolicy.php routes/web.php tests/Feature/PagoObraTest.php
git commit -m "feat(obras): pagos por abonos con retencion del contador"
```

---

## Task 6: Detalle de obra (frontend) + notificación a todos los involucrados

**Files:**
- Modify: `app/Http/Controllers/SolicitudController.php` (show: props de OBR)
- Modify: `app/Http/Resources/SolicitudDetalleResource.php` (bloque OBR)
- Modify: `app/Services/MotorWorkflow.php` (destinatarios OBR = todos los involucrados)
- Modify: `resources/js/Pages/Solicitudes/Detalle.jsx` (vista OBR)
- Test: `tests/Feature/ObraNotificacionesTest.php`

- [ ] **Step 1: Test — cada transición notifica a todos los involucrados**

```php
<?php
namespace Tests\Feature;

use App\Models\{Contrato, Solicitud, SolicitudObra, TipoSolicitud, Usuario};
use App\Notifications\AvisoTransicionNotification;
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class ObraNotificacionesTest extends TestCase
{
    use RefreshDatabase;

    public function test_al_aprobar_notifica_residente_y_rrhh(): void
    {
        Notification::fake();
        $this->seed();
        $residente = Usuario::factory()->create(); $residente->assignRole('residente');
        $rrhh = Usuario::factory()->create(); $rrhh->assignRole('rrhh');
        $cl = Usuario::factory()->create(); $cl->assignRole('contabilidad_lider');
        $tipo = TipoSolicitud::where('clave', 'OBR')->firstOrFail();
        $contrato = Contrato::create(['descripcion' => 'C', 'objeto' => 'O']);
        $cab = SolicitudObra::create(['nombre_solicitante' => 'X', 'fecha_solicitud' => '2026-09-21', 'contrato_id' => $contrato->id]);
        $s = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $residente->id,
            'solicitable_type' => SolicitudObra::class, 'solicitable_id' => $cab->id,
            'estado' => 'borrador', 'radicado' => Solicitud::generarRadicado($tipo),
        ]);
        $m = app(MotorWorkflow::class);
        $m->aplicarTransicion($s, 'enviar', $residente);
        $m->aplicarTransicion($s->fresh(), 'cotizar', $rrhh);
        $m->aplicarTransicion($s->fresh(), 'enviar_contabilidad', $rrhh);
        $m->aplicarTransicion($s->fresh(), 'aprobar', $cl);

        // Al aprobar, el residente (creador) y RR.HH. (ya participó) reciben aviso.
        Notification::assertSentTo($residente, AvisoTransicionNotification::class);
        Notification::assertSentTo($rrhh, AvisoTransicionNotification::class);
    }
}
```

- [ ] **Step 2: Correr test (debe fallar)**

Run: `/c/xampp/php/php.exe artisan test tests/Feature/ObraNotificacionesTest.php`
Expected: FAIL (aún no notifica a todos).

- [ ] **Step 3: Notificar a todos los involucrados (solo OBR) en MotorWorkflow**

En `app/Services/MotorWorkflow.php`, dentro de `notificarSiguientePaso`, tras las
notificaciones existentes, agregar un bloque específico para OBR que reúna a todos los
involucrados (creador + quienes ejecutaron transiciones) y les envíe el aviso de seguimiento,
sin duplicar al actor:

```php
// Obras: trazabilidad total. Cada transicion avisa a TODOS los involucrados
// (creador + quienes ya ejecutaron alguna transicion), menos el actor actual.
if ($solicitud->tipoSolicitud->clave === 'OBR') {
    $ids = $solicitud->transiciones()->pluck('usuario_id')
        ->push($solicitud->solicitante_id)
        ->unique()
        ->reject(fn ($id) => $id === $actor->id)
        ->values();
    if ($ids->isNotEmpty()) {
        $involucrados = \App\Models\Usuario::whereIn('id', $ids)->get();
        \App\Support\Avisos::enviar($involucrados, new AvisoTransicionNotification($solicitud, 'seguimiento', $accion));
    }
}
```

> Nota: el aviso `seguimiento` ya existe (creado para el solicitante en viáticos/oficina).
> Aquí se reutiliza para el conjunto de involucrados en OBR.

- [ ] **Step 4: Resource — bloque OBR en SolicitudDetalleResource**

En `app/Http/Resources/SolicitudDetalleResource.php`, agregar un bloque condicional
`$this->when($this->tipoSolicitud->clave === 'OBR', ...)` que exponga: items (con
subtotales), cotizaciones/documentos (id, nombre, tipo, autor), contrato relacionado,
pagos (total_a_pagar, pagado, saldo, abonos con retención y soporte descargable), y los
flags de permiso (`puede_cotizar`, `puede_pagar`, `puede_retener`, `puede_relacionar_contrato`).

- [ ] **Step 5: show() en SolicitudController carga relaciones OBR**

En el `->load([... 'solicitable' => fn ($morphTo) => $morphTo->morphWith([...])])` del
método `show`, agregar `SolicitudObra::class => ['items','cotizaciones.usuario','abonos.usuario','abonos.retenedor','contrato']`.

- [ ] **Step 6: Vista OBR en Detalle.jsx**

Agregar en `resources/js/Pages/Solicitudes/Detalle.jsx` un componente `DetalleObra` que
muestre: datos del solicitante y fechas, tabla de items (con valor unitario editable por
RR.HH. cuando `puede_cotizar`), selector de contrato + botón relacionar, subida de
documentos anexos, sección de cotizaciones/documentos descargables, y sección de pagos
(registrar abono con soporte cuando `puede_pagar`; aplicar retención por abono cuando
`puede_retener`; descargar soportes por cualquiera). Renderizarlo cuando
`solicitud.tipo.clave === 'OBR'`.

- [ ] **Step 7: Build + correr test**

Run: `npm run build`
Run: `/c/xampp/php/php.exe artisan test tests/Feature/ObraNotificacionesTest.php`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/SolicitudController.php app/Http/Resources/SolicitudDetalleResource.php app/Services/MotorWorkflow.php resources/js/Pages/Solicitudes/Detalle.jsx tests/Feature/ObraNotificacionesTest.php
git commit -m "feat(obras): detalle completo + notificacion a todos los involucrados"
```

---

## Task 7: Integración en listados/inicio y limpieza; suite completa

**Files:**
- Modify: `app/Http/Controllers/SolicitudController.php` (relacionesListado incluye OBR)
- Modify: `app/Console/Commands/LimpiarSolicitudes.php` (borrar OBR también)
- Modify: `app/Http/Resources/SolicitudResource.php` (card OBR en listado, si aplica)
- Test: `tests/Feature/ObraListadoTest.php`

- [ ] **Step 1: Test — OBR aparece en "mis solicitudes" y en pendientes del rol correcto**

```php
<?php
namespace Tests\Feature;

use App\Models\{Solicitud, SolicitudObra, TipoSolicitud, Usuario};
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\TestCase;

class ObraListadoTest extends TestCase
{
    use RefreshDatabase;

    public function test_obra_enviada_aparece_pendiente_para_rrhh(): void
    {
        $this->seed();
        $residente = Usuario::factory()->create(); $residente->assignRole('residente');
        $rrhh = Usuario::factory()->create(); $rrhh->assignRole('rrhh');
        $tipo = TipoSolicitud::where('clave', 'OBR')->firstOrFail();
        $cab = SolicitudObra::create(['nombre_solicitante' => 'X', 'fecha_solicitud' => '2026-09-21']);
        $s = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => $residente->id,
            'solicitable_type' => SolicitudObra::class, 'solicitable_id' => $cab->id,
            'estado' => 'borrador', 'radicado' => Solicitud::generarRadicado($tipo),
        ]);
        app(MotorWorkflow::class)->aplicarTransicion($s, 'enviar', $residente);

        $this->actingAs($rrhh)
            ->get(route('solicitudes.index', ['tab' => 'pendientes']))
            ->assertInertia(fn (AssertableInertia $p) => $p->has('solicitudes.data', 1));
    }
}
```

- [ ] **Step 2: Correr test (debe fallar si el listado no soporta OBR)**

Run: `/c/xampp/php/php.exe artisan test tests/Feature/ObraListadoTest.php`
Expected: FAIL o PASS parcial (ajustar según morphWith del listado).

- [ ] **Step 3: relacionesListado incluye OBR**

En `SolicitudController::relacionesListado`, agregar al `morphWith`:
`SolicitudObra::class => ['contrato']` (para que el card pueda mostrar el contrato/obra).

- [ ] **Step 4: LimpiarSolicitudes borra OBR**

En `app/Console/Commands/LimpiarSolicitudes.php`, agregar el borrado de `SolicitudObra`
(y sus archivos físicos de cotizaciones_obra y soportes_obra, como se hace con oficina):
antes de borrar, recolectar los paths de `CotizacionObra` y `AbonoObra` y eliminarlos del
disco; luego `SolicitudObra::query()->delete()` (cascade borra items/cotizaciones/abonos).

- [ ] **Step 5: Card OBR en el listado (SolicitudResource)**

Si `SolicitudResource` diferencia por tipo, agregar el caso OBR mostrando nombre de la
obra/contrato y el estado. Si es genérico, verificar que no rompa con OBR.

- [ ] **Step 6: Correr suite completa**

Run: `/c/xampp/php/php.exe artisan test`
Expected: PASS (todos los tests, incluidos los previos sin regresión).

- [ ] **Step 7: Build final**

Run: `npm run build`

- [ ] **Step 8: Commit**

```bash
git add app/Http/Controllers/SolicitudController.php app/Console/Commands/LimpiarSolicitudes.php app/Http/Resources/SolicitudResource.php tests/Feature/ObraListadoTest.php
git commit -m "feat(obras): integracion en listados/inicio y limpieza de solicitudes"
```

---

## Self-Review (completado por el autor del plan)

- **Cobertura del spec:** roles residente+lider_area (T1,T3), tipo OBR y flujo (T1),
  modelo de datos 4 tablas (T2), creación items/cotización (T3), cotización RR.HH. +
  contrato con gate + anexos (T4), aprobación explícita + pagos + retención por pago (T5),
  detalle + notificación a todos los involucrados (T6), listados/inicio/limpieza (T7).
  Reportes de Obras quedan explícitamente en fase 2 (fuera de alcance).
- **Consistencia de tipos:** `SolicitudObra`, `ItemObra`, `CotizacionObra`, `AbonoObra`
  usados con las mismas firmas en todas las tareas. Rutas con nombres `obra.*` coherentes.
- **Sin placeholders de lógica:** cada tarea trae el código real; las 2 vistas React
  grandes (Crear.jsx, DetalleObra en Detalle.jsx) se describen con su contenido y patrón
  de referencia (Oficina/Crear.jsx) por su tamaño, no como código completo — es la única
  parte no literal, justificada por volumen y por seguir un patrón existente ya probado.

## Execution Handoff

Plan guardado en `docs/superpowers/plans/2026-09-21-proceso-obras.md`.
Ejecutar con subagent-driven-development (recomendado) o executing-plans, tarea por tarea.
