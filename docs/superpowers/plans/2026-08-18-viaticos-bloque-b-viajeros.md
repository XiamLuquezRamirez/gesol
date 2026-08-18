# Viáticos — Bloque B (Viajeros) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Habilitar contrato opcional por viajero y viajeros externos (fuera de la BD de empleados) en la comisión de viáticos, y bloquear el borrado de contratos con viajeros asociados.

**Architecture:** Se extiende la tabla `viajeros_comision` con `contrato_id` (nullable, FK nullOnDelete) y con soporte de viajero externo (`empleado_id` nullable + `nombre_externo` + `identificacion_externo`). El modelo gana relación `contrato()` y accessors `nombreMostrado`/`identificacionMostrada` que centralizan la composición del nombre. La validación se vuelve condicional (empleado vs externo). El frontend añade un toggle "Viajero externo" y un select de contrato. Se implementa la regla diferida del Bloque A en `destroyContrato`.

**Tech Stack:** Laravel 10.50, PHP 8.2, Inertia/React 18, spatie/laravel-permission, PHPUnit + SQLite `:memory:`. **`doctrine/dbal` NO instalado** ⇒ la migración de nullability es driver-aware.

**Spec:** `docs/superpowers/specs/2026-08-18-viaticos-bloque-b-viajeros-design.md`

---

## File Structure

- **Create** `database/migrations/2026_08_18_110000_add_contrato_id_to_viajeros_comision_table.php` — columna `contrato_id` nullable + FK nullOnDelete.
- **Create** `database/migrations/2026_08_18_110100_make_empleado_id_nullable_and_add_externo_to_viajeros_comision_table.php` — `empleado_id` nullable (driver-aware) + `nombre_externo` + `identificacion_externo`.
- **Modify** `app/Models/ViajeroComision.php` — fillable, relación `contrato()`, accessors.
- **Modify** `app/Models/Contrato.php` — relación inversa `viajeros()`.
- **Modify** `app/Http/Requests/GuardarSolicitudViaticosRequest.php` — reglas condicionales + mensajes.
- **Modify** `app/Http/Controllers/ViaticosController.php` — props `contratos`, eager-load, persistir campos.
- **Modify** `app/Http/Controllers/ParametrosController.php` — `destroyContrato` con guard.
- **Modify** `resources/js/Pages/Viaticos/Crear.jsx` — toggle externo, campos, select contrato.
- **Modify** consumidores de lectura para usar `nombreMostrado` (Mail, PDF, Notification, ComisionesRrhhController, Detalle.jsx, Liquidacion.jsx).
- **Create** `tests/Feature/ViajerosBloqueBTest.php` — cobertura del bloque.

---

## Task 1: Migración — contrato_id en viajeros_comision

**Files:**
- Create: `database/migrations/2026_08_18_110000_add_contrato_id_to_viajeros_comision_table.php`

- [ ] **Step 1: Escribir la migración (idempotente)**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('viajeros_comision', 'contrato_id')) {
            Schema::table('viajeros_comision', function (Blueprint $table) {
                $table->foreignId('contrato_id')->nullable()->after('empleado_id')
                    ->constrained('contratos')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('viajeros_comision', 'contrato_id')) {
            Schema::table('viajeros_comision', function (Blueprint $table) {
                $table->dropConstrainedForeignId('contrato_id');
            });
        }
    }
};
```

- [ ] **Step 2: Ejecutar migración en la suite (SQLite crea desde cero)**

Run: `php artisan test --filter=MunicipiosComisionTest`
Expected: PASS (verifica que la migración corre sin romper el esquema existente).

- [ ] **Step 3: Commit**

```bash
git add database/migrations/2026_08_18_110000_add_contrato_id_to_viajeros_comision_table.php
git commit -m "feat(viaticos): columna contrato_id en viajeros_comision"
```

---

## Task 2: Migración — empleado_id nullable + campos de viajero externo (driver-aware)

**Files:**
- Create: `database/migrations/2026_08_18_110100_make_empleado_id_nullable_and_add_externo_to_viajeros_comision_table.php`

**Contexto crítico:** `doctrine/dbal` NO está instalado, así que `->change()` no sirve. En MariaDB se usa `ALTER ... MODIFY`. En SQLite (tests) no existe `MODIFY`; pero como en `:memory:` la tabla se crea desde cero en cada corrida, para SQLite basta con recrear la tabla con el esquema final. Las columnas `nombre_externo`/`identificacion_externo` se agregan con `Schema::table` (soportado por ambos drivers).

- [ ] **Step 1: Escribir la migración driver-aware**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Columnas de viajero externo (ambos drivers las soportan vía Schema::table).
        Schema::table('viajeros_comision', function (Blueprint $table) {
            if (! Schema::hasColumn('viajeros_comision', 'nombre_externo')) {
                $table->string('nombre_externo')->nullable()->after('empleado_id');
            }
            if (! Schema::hasColumn('viajeros_comision', 'identificacion_externo')) {
                $table->string('identificacion_externo', 50)->nullable()->after('nombre_externo');
            }
        });

        // empleado_id -> nullable. doctrine/dbal no instalado: driver-aware.
        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            // MariaDB/MySQL: ALTER ... MODIFY. La FK a empleados permanece.
            DB::statement('ALTER TABLE viajeros_comision MODIFY empleado_id BIGINT UNSIGNED NULL');
        } elseif ($driver === 'sqlite') {
            // SQLite no permite alterar nullability in-place. Recreamos la tabla.
            // En :memory: (tests) esto corre sobre una tabla recién creada.
            $this->recrearTablaSqlite();
        }
        // Otros drivers: no soportados en este proyecto.
    }

    public function down(): void
    {
        // No revertimos la nullability (requeriría dbal / recrear). Solo columnas externas.
        Schema::table('viajeros_comision', function (Blueprint $table) {
            if (Schema::hasColumn('viajeros_comision', 'identificacion_externo')) {
                $table->dropColumn('identificacion_externo');
            }
            if (Schema::hasColumn('viajeros_comision', 'nombre_externo')) {
                $table->dropColumn('nombre_externo');
            }
        });
    }

    private function recrearTablaSqlite(): void
    {
        // Desactivar FKs para la recreación.
        DB::statement('PRAGMA foreign_keys = OFF');

        Schema::rename('viajeros_comision', 'viajeros_comision_old');

        Schema::create('viajeros_comision', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_viaticos_id')->constrained('solicitudes_viaticos')->cascadeOnDelete();
            $table->foreignId('empleado_id')->nullable()->constrained('empleados');
            $table->foreignId('contrato_id')->nullable()->constrained('contratos')->nullOnDelete();
            $table->string('nombre_externo')->nullable();
            $table->string('identificacion_externo', 50)->nullable();
            $table->string('rol_en_comision')->nullable();
            $table->text('motivo');
            $table->date('fecha_salida');
            $table->string('hora_salida', 5);
            $table->date('fecha_regreso');
            $table->string('hora_regreso', 5);
            $table->enum('tipo_pago', ['efectivo', 'transferencia'])->default('efectivo');
            $table->timestamps();
        });

        // Copiar datos existentes (en :memory: normalmente vacía).
        $columnas = 'id, solicitud_viaticos_id, empleado_id, contrato_id, nombre_externo, identificacion_externo, rol_en_comision, motivo, fecha_salida, hora_salida, fecha_regreso, hora_regreso, tipo_pago, created_at, updated_at';
        DB::statement("INSERT INTO viajeros_comision ($columnas) SELECT $columnas FROM viajeros_comision_old");

        Schema::drop('viajeros_comision_old');

        DB::statement('PRAGMA foreign_keys = ON');
    }
};
```

- [ ] **Step 2: Ejecutar la suite completa (valida el camino SQLite)**

Run: `php artisan test`
Expected: 135 passed (el esquema se recrea correctamente en SQLite; sin regresiones).

- [ ] **Step 3: Verificar en MariaDB (dev real)**

Run: `php artisan migrate --pretend` (inspección) y, si el usuario lo autoriza en su entorno, `php artisan migrate`.
Expected: la sentencia `ALTER TABLE viajeros_comision MODIFY empleado_id BIGINT UNSIGNED NULL` aparece; migración aplica sin error.

> Nota: si el orden de migraciones hace que `contrato_id` (Task 1) no exista aún al recrear en SQLite, el `INSERT ... SELECT` fallaría. Como Task 1 corre antes (timestamp 110000 < 110100), `contrato_id` ya existe. Verificar el orden con `php artisan migrate:status`.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_08_18_110100_make_empleado_id_nullable_and_add_externo_to_viajeros_comision_table.php
git commit -m "feat(viaticos): empleado_id nullable + campos de viajero externo (driver-aware)"
```

---

## Task 3: Modelo ViajeroComision — fillable, relación contrato, accessors

**Files:**
- Modify: `app/Models/ViajeroComision.php`

- [ ] **Step 1: Escribir el test de los accessors**

Test: `tests/Feature/ViajerosBloqueBTest.php`

```php
<?php
namespace Tests\Feature;

use App\Models\Contrato;
use App\Models\Empleados;
use App\Models\Municipio;
use App\Models\SolicitudViaticos;
use App\Models\ViajeroComision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ViajerosBloqueBTest extends TestCase
{
    use RefreshDatabase;

    public function test_nombre_mostrado_usa_empleado_o_externo(): void
    {
        $this->seed();
        $cab = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => '', 'observacion' => 'x']);
        $emp = Empleados::first();

        $conEmpleado = ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => $emp->id,
            'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
        ]);
        $externo = ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => null,
            'nombre_externo' => 'Juan Externo', 'identificacion_externo' => '999',
            'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
        ]);

        $this->assertEquals(
            trim($emp->nombres.' '.$emp->apellidos),
            $conEmpleado->fresh()->nombreMostrado
        );
        $this->assertEquals('Juan Externo', $externo->fresh()->nombreMostrado);
        $this->assertEquals('999', $externo->fresh()->identificacionMostrada);
    }
}
```

- [ ] **Step 2: Ejecutar el test (falla: accessors no existen)**

Run: `php artisan test --filter=test_nombre_mostrado_usa_empleado_o_externo`
Expected: FAIL (propiedad `nombreMostrado` indefinida / campos no fillable).

- [ ] **Step 3: Modificar el modelo**

Reemplazar el contenido de `app/Models/ViajeroComision.php` por:

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ViajeroComision extends Model
{
    protected $table    = 'viajeros_comision';
    protected $fillable = [
        'solicitud_viaticos_id','empleado_id','contrato_id',
        'nombre_externo','identificacion_externo',
        'rol_en_comision','motivo','fecha_salida','hora_salida','fecha_regreso','hora_regreso','tipo_pago',
    ];
    protected $casts = ['fecha_salida'=>'date','fecha_regreso'=>'date'];

    public function empleado()          { return $this->belongsTo(Empleados::class, 'empleado_id'); }
    public function contrato()          { return $this->belongsTo(Contrato::class, 'contrato_id'); }
    public function solicitudViaticos() { return $this->belongsTo(SolicitudViaticos::class, 'solicitud_viaticos_id'); }
    public function asignaciones()      { return $this->hasMany(AsignacionViatico::class, 'viajero_comision_id'); }

    /** Nombre a mostrar: empleado de la BD o nombre libre del viajero externo. */
    public function getNombreMostradoAttribute(): string
    {
        if ($this->empleado) {
            return trim(($this->empleado->nombres ?? '').' '.($this->empleado->apellidos ?? ''));
        }
        return $this->nombre_externo ?? '';
    }

    /** Identificación a mostrar: la del empleado o la del externo. */
    public function getIdentificacionMostradaAttribute(): ?string
    {
        return $this->empleado?->identificacion ?? $this->identificacion_externo;
    }
}
```

- [ ] **Step 4: Ejecutar el test (pasa)**

Run: `php artisan test --filter=test_nombre_mostrado_usa_empleado_o_externo`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/ViajeroComision.php tests/Feature/ViajerosBloqueBTest.php
git commit -m "feat(viaticos): ViajeroComision con contrato, campos externos y accessors de nombre"
```

---

## Task 4: Modelo Contrato — relación inversa viajeros()

**Files:**
- Modify: `app/Models/Contrato.php`

- [ ] **Step 1: Escribir el test**

Añadir a `tests/Feature/ViajerosBloqueBTest.php`:

```php
    public function test_contrato_tiene_viajeros(): void
    {
        $this->seed();
        $contrato = Contrato::create(['descripcion' => 'D', 'objeto' => 'O']);
        $contrato->municipios()->sync(Municipio::take(1)->pluck('id')->all());
        $cab = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => '', 'observacion' => 'x']);
        ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => Empleados::first()->id,
            'contrato_id' => $contrato->id,
            'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
        ]);

        $this->assertEquals(1, $contrato->fresh()->viajeros()->count());
    }
```

- [ ] **Step 2: Ejecutar (falla: relación no existe)**

Run: `php artisan test --filter=test_contrato_tiene_viajeros`
Expected: FAIL (método `viajeros` indefinido en Contrato).

- [ ] **Step 3: Añadir la relación en `app/Models/Contrato.php`**

Dentro de la clase, después de `municipios()`:

```php
    public function viajeros()
    {
        return $this->hasMany(ViajeroComision::class, 'contrato_id');
    }
```

- [ ] **Step 4: Ejecutar (pasa)**

Run: `php artisan test --filter=test_contrato_tiene_viajeros`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Models/Contrato.php tests/Feature/ViajerosBloqueBTest.php
git commit -m "feat(viaticos): relacion inversa Contrato->viajeros"
```

---

## Task 5: Validación condicional en GuardarSolicitudViaticosRequest

**Files:**
- Modify: `app/Http/Requests/GuardarSolicitudViaticosRequest.php`

- [ ] **Step 1: Escribir los tests de validación**

Añadir a `tests/Feature/ViajerosBloqueBTest.php`:

```php
    private function payloadBase(array $viajero): array
    {
        return [
            'nombre_comision' => 'C',
            'municipios'      => Municipio::take(1)->pluck('id')->all(),
            'observacion'     => 'x',
            'viajeros'        => [$viajero],
        ];
    }

    public function test_externo_sin_nombre_es_invalido(): void
    {
        $this->seed();
        $lider = \App\Models\Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();
        $this->actingAs($lider)->from(route('viaticos.crear'))
            ->post(route('viaticos.store'), $this->payloadBase([
                'es_externo' => true, 'identificacion_externo' => '123',
                'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
                'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
            ]))
            ->assertSessionHasErrors('viajeros.0.nombre_externo');
    }

    public function test_no_externo_sin_empleado_es_invalido(): void
    {
        $this->seed();
        $lider = \App\Models\Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();
        $this->actingAs($lider)->from(route('viaticos.crear'))
            ->post(route('viaticos.store'), $this->payloadBase([
                'es_externo' => false,
                'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
                'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
            ]))
            ->assertSessionHasErrors('viajeros.0.empleado_id');
    }
```

- [ ] **Step 2: Ejecutar (falla: reglas actuales exigen empleado_id siempre)**

Run: `php artisan test --filter="test_externo_sin_nombre_es_invalido|test_no_externo_sin_empleado_es_invalido"`
Expected: el segundo puede pasar por casualidad (empleado_id required), pero el primero FALLA (externo rechazado por empleado_id required). Objetivo: ambos deben reflejar la lógica condicional.

- [ ] **Step 3: Reescribir `rules()` y `messages()`**

En `app/Http/Requests/GuardarSolicitudViaticosRequest.php`, método `rules()`:

```php
    public function rules(): array
    {
        return [
            'nombre_comision'                    => 'required|string|max:255',
            'municipios'                         => 'required|array|min:1',
            'municipios.*'                       => 'exists:municipios,id',
            'observacion'                        => 'nullable|string|max:2000',
            'viajeros'                           => 'required|array|min:1',
            'viajeros.*.es_externo'              => 'nullable|boolean',
            'viajeros.*.contrato_id'             => 'nullable|exists:contratos,id',
            'viajeros.*.empleado_id'             => 'required_unless:viajeros.*.es_externo,true|nullable|exists:empleados,id',
            'viajeros.*.nombre_externo'          => 'required_if:viajeros.*.es_externo,true|nullable|string|max:255',
            'viajeros.*.identificacion_externo'  => 'required_if:viajeros.*.es_externo,true|nullable|string|max:50',
            'viajeros.*.motivo'                  => 'required|string|max:2000',
            'viajeros.*.fecha_salida'            => 'required|date',
            'viajeros.*.hora_salida'             => 'required|string|max:5',
            'viajeros.*.fecha_regreso'           => 'required|date',
            'viajeros.*.hora_regreso'            => 'required|string|max:5',
        ];
    }
```

En `messages()`, añadir (conservando los existentes):

```php
            'viajeros.*.empleado_id.required_unless'      => 'Seleccione el empleado o marque viajero externo.',
            'viajeros.*.empleado_id.exists'               => 'El empleado seleccionado no es válido.',
            'viajeros.*.nombre_externo.required_if'       => 'Ingrese el nombre del viajero externo.',
            'viajeros.*.identificacion_externo.required_if' => 'Ingrese la identificación del viajero externo.',
            'viajeros.*.contrato_id.exists'               => 'El contrato seleccionado no es válido.',
```

Si el `required_unless`/`required_if` sobre wildcard no dispara correctamente contra el payload (booleano), implementar la validación cruzada en `withValidator()`:

```php
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            foreach ((array) $this->input('viajeros', []) as $i => $v) {
                $externo = filter_var($v['es_externo'] ?? false, FILTER_VALIDATE_BOOLEAN);
                if ($externo) {
                    if (empty($v['nombre_externo'])) {
                        $validator->errors()->add("viajeros.$i.nombre_externo", 'Ingrese el nombre del viajero externo.');
                    }
                    if (empty($v['identificacion_externo'])) {
                        $validator->errors()->add("viajeros.$i.identificacion_externo", 'Ingrese la identificación del viajero externo.');
                    }
                } elseif (empty($v['empleado_id'])) {
                    $validator->errors()->add("viajeros.$i.empleado_id", 'Seleccione el empleado o marque viajero externo.');
                }
            }
        });
    }
```

> Preferir `withValidator()` como fuente de verdad de la lógica condicional (evita la fragilidad de `required_unless` sobre `*`), y dejar en `rules()` solo `nullable` + tipo/exists para los tres campos condicionales. El implementador decide, pero DEBE garantizar que ambos tests pasen.

- [ ] **Step 4: Ejecutar (pasan)**

Run: `php artisan test --filter="test_externo_sin_nombre_es_invalido|test_no_externo_sin_empleado_es_invalido"`
Expected: PASS ambos.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Requests/GuardarSolicitudViaticosRequest.php tests/Feature/ViajerosBloqueBTest.php
git commit -m "feat(viaticos): validacion condicional empleado vs viajero externo"
```

---

## Task 6: Controlador ViaticosController — props, eager-load y persistencia

**Files:**
- Modify: `app/Http/Controllers/ViaticosController.php`

- [ ] **Step 1: Escribir tests de persistencia**

Añadir a `tests/Feature/ViajerosBloqueBTest.php`:

```php
    public function test_persiste_contrato_y_viajero_externo(): void
    {
        $this->seed();
        $lider = \App\Models\Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();
        $contrato = Contrato::create(['descripcion' => 'D', 'objeto' => 'O']);
        $emp = Empleados::first();

        $this->actingAs($lider)->post(route('viaticos.store'), [
            'nombre_comision' => 'C',
            'municipios'      => Municipio::take(1)->pluck('id')->all(),
            'observacion'     => 'x',
            'viajeros'        => [
                [ // empleado con contrato
                    'es_externo' => false, 'empleado_id' => $emp->id, 'contrato_id' => $contrato->id,
                    'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
                    'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
                ],
                [ // externo sin contrato
                    'es_externo' => true, 'nombre_externo' => 'Ana Externa', 'identificacion_externo' => '555',
                    'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
                    'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
                ],
            ],
        ])->assertRedirect();

        $cab = SolicitudViaticos::latest('id')->first();
        $viajeros = $cab->viajeros()->orderBy('id')->get();

        $this->assertEquals($emp->id, $viajeros[0]->empleado_id);
        $this->assertEquals($contrato->id, $viajeros[0]->contrato_id);
        $this->assertNull($viajeros[1]->empleado_id);
        $this->assertNull($viajeros[1]->contrato_id);
        $this->assertEquals('Ana Externa', $viajeros[1]->nombre_externo);
        $this->assertEquals('555', $viajeros[1]->identificacion_externo);
    }
```

- [ ] **Step 2: Ejecutar (falla: controller no persiste los campos nuevos)**

Run: `php artisan test --filter=test_persiste_contrato_y_viajero_externo`
Expected: FAIL (contrato_id/nombre_externo nulos porque el controller no los mapea).

- [ ] **Step 3: Modificar el controlador**

En `create()` y `edit()`, añadir la prop `contratos`:

```php
'contratos'  => \App\Models\Contrato::orderBy('descripcion')->get(['id','descripcion','objeto']),
```

En `edit()`, ampliar el eager-load:

```php
$solicitud->load(['solicitable.viajeros.empleado', 'solicitable.viajeros.contrato', 'solicitable.municipios']);
```

Extraer un helper privado para mapear un viajero del request (DRY entre store/update):

```php
    /** Mapea un viajero del request a los atributos persistibles, resolviendo empleado vs externo. */
    private function atributosViajero(int $cabeceraId, array $v): array
    {
        $externo = filter_var($v['es_externo'] ?? false, FILTER_VALIDATE_BOOLEAN);
        return [
            'solicitud_viaticos_id'  => $cabeceraId,
            'empleado_id'            => $externo ? null : ($v['empleado_id'] ?? null),
            'contrato_id'            => $v['contrato_id'] ?? null,
            'nombre_externo'         => $externo ? ($v['nombre_externo'] ?? null) : null,
            'identificacion_externo' => $externo ? ($v['identificacion_externo'] ?? null) : null,
            'motivo'                 => $v['motivo'],
            'fecha_salida'           => $v['fecha_salida'],
            'hora_salida'            => $v['hora_salida'],
            'fecha_regreso'          => $v['fecha_regreso'],
            'hora_regreso'           => $v['hora_regreso'],
        ];
    }
```

En `store()` y `update()`, reemplazar el `ViajeroComision::create([...])` del bucle por:

```php
foreach ($request->viajeros as $v) {
    ViajeroComision::create($this->atributosViajero($cabecera->id, $v));
}
```

- [ ] **Step 4: Ejecutar (pasa)**

Run: `php artisan test --filter=test_persiste_contrato_y_viajero_externo`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/ViaticosController.php tests/Feature/ViajerosBloqueBTest.php
git commit -m "feat(viaticos): persistir contrato y viajero externo en store/update"
```

---

## Task 7: Regla diferida — destroyContrato bloquea si hay viajeros

**Files:**
- Modify: `app/Http/Controllers/ParametrosController.php`

- [ ] **Step 1: Escribir los tests**

Añadir a `tests/Feature/ViajerosBloqueBTest.php` (usuario con permiso para parámetros — el mismo patrón que `ContratosParametrosTest`; revisar ese archivo para el rol/usuario correcto):

```php
    public function test_no_se_borra_contrato_con_viajeros(): void
    {
        $this->seed();
        // Usuario autorizado para parametros: reutilizar el patron de ContratosParametrosTest.
        $admin = \App\Models\Usuario::where('email', 'admin@demo.test')->firstOrFail();
        $contrato = Contrato::create(['descripcion' => 'D', 'objeto' => 'O']);
        $cab = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => '', 'observacion' => 'x']);
        ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => Empleados::first()->id,
            'contrato_id' => $contrato->id,
            'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
        ]);

        $this->actingAs($admin)->delete(route('parametros.contratos.destroy', $contrato))
            ->assertSessionHas('error');
        $this->assertDatabaseHas('contratos', ['id' => $contrato->id]);
    }

    public function test_se_borra_contrato_sin_viajeros(): void
    {
        $this->seed();
        $admin = \App\Models\Usuario::where('email', 'admin@demo.test')->firstOrFail();
        $contrato = Contrato::create(['descripcion' => 'D', 'objeto' => 'O']);

        $this->actingAs($admin)->delete(route('parametros.contratos.destroy', $contrato))
            ->assertSessionHas('success');
        $this->assertDatabaseMissing('contratos', ['id' => $contrato->id]);
    }
```

> El implementador DEBE verificar el nombre exacto de la ruta (`route('parametros.contratos.destroy', ...)`) y el usuario/rol autorizado consultando `routes/web.php` y `ContratosParametrosTest.php`. Ajustar si difiere.

- [ ] **Step 2: Ejecutar (falla: hoy borra siempre)**

Run: `php artisan test --filter="test_no_se_borra_contrato_con_viajeros|test_se_borra_contrato_sin_viajeros"`
Expected: el primero FALLA (borra y no deja error).

- [ ] **Step 3: Modificar `destroyContrato`**

Reemplazar el cuerpo (línea ~109-114):

```php
    public function destroyContrato(Contrato $contrato)
    {
        if ($contrato->viajeros()->exists()) {
            return back()->with('error', 'No se puede eliminar: el contrato tiene viajeros asociados.');
        }
        $contrato->delete();
        return back()->with('success', 'Contrato eliminado.');
    }
```

- [ ] **Step 4: Ejecutar (pasan)**

Run: `php artisan test --filter="test_no_se_borra_contrato_con_viajeros|test_se_borra_contrato_sin_viajeros"`
Expected: PASS ambos.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/ParametrosController.php tests/Feature/ViajerosBloqueBTest.php
git commit -m "feat(viaticos): bloquear borrado de contrato con viajeros asociados"
```

---

## Task 8: Frontend — toggle externo, campos y select de contrato en Crear.jsx

**Files:**
- Modify: `resources/js/Pages/Viaticos/Crear.jsx`

**Contexto:** Cambios de UI (sin test automatizado; validar con `npm run build`). Leer el archivo completo antes de editar para respetar el patrón del componente `Field`, el mini-formulario `form`/`setF`, `validarForm()`, `agregarViajero()` y la tabla de viajeros agregados.

- [ ] **Step 1: Ampliar el estado**

`VIAJERO_VACIO` (líneas ~7-14): añadir campos.

```jsx
const VIAJERO_VACIO = {
    empleado_id:   '',
    es_externo:    false,
    nombre_externo: '',
    identificacion_externo: '',
    contrato_id:   '',
    motivo:       '',
    fecha_salida: '',
    hora_salida:  '',
    fecha_regreso:'',
    hora_regreso: '',
};
```

- [ ] **Step 2: Aceptar la prop `contratos` y mapear viajeros iniciales**

Firma del componente: añadir `contratos = []`.

```jsx
export default function Crear({ empleados, contratos = [], solicitud = null, editar = false, municipios = [] }) {
```

`viajerosIniciales`: derivar los nuevos campos desde `v`:

```jsx
const viajerosIniciales = (solicitable?.viajeros ?? []).map((v) => ({
    empleado_id:   v.empleado_id ?? '',
    es_externo:    !v.empleado_id,
    nombre_externo: v.nombre_externo ?? '',
    identificacion_externo: v.identificacion_externo ?? '',
    contrato_id:   v.contrato_id ?? '',
    motivo:        v.motivo ?? '',
    fecha_salida:  String(v.fecha_salida ?? '').substring(0, 10),
    hora_salida:   v.hora_salida ?? '',
    fecha_regreso: String(v.fecha_regreso ?? '').substring(0, 10),
    hora_regreso:  v.hora_regreso ?? '',
    nombre: v.empleado ? `${v.empleado.nombres} ${v.empleado.apellidos}` : (v.nombre_externo ?? ''),
}));
```

- [ ] **Step 3: Toggle y campos condicionales en el mini-formulario**

Reemplazar el `Field` del select de empleado (líneas ~186-200) por un bloque con toggle + condicional + select de contrato. El select de empleado se conserva para el caso no-externo:

```jsx
<label className="flex items-center gap-2 text-sm text-slate-600 mb-2">
    <input
        type="checkbox"
        checked={form.es_externo}
        onChange={(e) => setForm((f) => ({ ...f, es_externo: e.target.checked, empleado_id: '' }))}
        className="rounded border-slate-300"
    />
    Viajero externo (no está en la lista)
</label>

{form.es_externo ? (
    <div className="grid grid-cols-1 sm:grid-cols-2 gap-3">
        <Field label="Nombre del viajero" error={formError.nombre_externo}>
            <input
                type="text"
                value={form.nombre_externo}
                onChange={(e) => setF('nombre_externo', e.target.value)}
                className={['w-full rounded-lg border text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none',
                    formError.nombre_externo ? 'border-red-400' : 'border-slate-300'].join(' ')}
            />
        </Field>
        <Field label="Identificación" error={formError.identificacion_externo}>
            <input
                type="text"
                value={form.identificacion_externo}
                onChange={(e) => setF('identificacion_externo', e.target.value)}
                className={['w-full rounded-lg border text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none',
                    formError.identificacion_externo ? 'border-red-400' : 'border-slate-300'].join(' ')}
            />
        </Field>
    </div>
) : (
    <Field label="Nombre del viajero" error={formError.empleado_id}>
        <select
            value={form.empleado_id}
            onChange={(e) => setF('empleado_id', e.target.value)}
            className={['w-full rounded-lg border text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none',
                formError.empleado_id ? 'border-red-400' : 'border-slate-300'].join(' ')}
        >
            <option value="">— Seleccionar viajero —</option>
            {empleados.map((e) => (
                <option key={e.id} value={e.id}>{e.nombres} {e.apellidos}</option>
            ))}
        </select>
    </Field>
)}

<Field label="Contrato (opcional)">
    <select
        value={form.contrato_id}
        onChange={(e) => setF('contrato_id', e.target.value)}
        className="w-full rounded-lg border border-slate-300 text-sm px-3 py-2 focus:ring-2 focus:ring-indigo-500 outline-none"
    >
        <option value="">— Sin contrato —</option>
        {contratos.map((c) => (
            <option key={c.id} value={c.id}>{c.descripcion}</option>
        ))}
    </select>
</Field>
```

- [ ] **Step 4: Actualizar `validarForm()` y `agregarViajero()`**

`validarForm()` (líneas ~92-102): validar según `es_externo`.

```jsx
const validarForm = () => {
    const e = {};
    if (form.es_externo) {
        if (!form.nombre_externo.trim()) e.nombre_externo = 'Ingrese el nombre.';
        if (!form.identificacion_externo.trim()) e.identificacion_externo = 'Ingrese la identificación.';
    } else if (!form.empleado_id) {
        e.empleado_id = 'Seleccione el empleado.';
    }
    if (!form.motivo.trim()) e.motivo = 'Ingrese el motivo.';
    if (!form.fecha_salida) e.fecha_salida = 'Ingrese la fecha de salida.';
    if (!form.hora_salida) e.hora_salida = 'Ingrese la hora de salida.';
    if (!form.fecha_regreso) e.fecha_regreso = 'Ingrese la fecha de regreso.';
    if (!form.hora_regreso) e.hora_regreso = 'Ingrese la hora de regreso.';
    setFormError(e);
    return Object.keys(e).length === 0;
};
```

> Verificar los nombres exactos de los campos que ya valida `validarForm()` al leer el archivo; conservar los existentes y añadir solo la rama `es_externo`.

`agregarViajero()` (líneas ~104-115): componer `nombre` y normalizar tipos.

```jsx
const agregarViajero = () => {
    if (!validarForm()) return;
    const empleado = empleados.find((e) => e.id === Number(form.empleado_id));
    const nombre = form.es_externo
        ? form.nombre_externo
        : (empleado ? `${empleado.nombres} ${empleado.apellidos}` : '');
    setData('viajeros', [
        ...data.viajeros,
        {
            ...form,
            empleado_id: form.es_externo ? null : Number(form.empleado_id),
            contrato_id: form.contrato_id ? Number(form.contrato_id) : null,
            nombre,
        },
    ]);
    setForm(VIAJERO_VACIO);
    setFormError({});
};
```

- [ ] **Step 5: Mostrar el contrato en la tabla de viajeros agregados (opcional pero recomendado)**

En la tabla de viajeros (líneas ~262-304), añadir la descripción del contrato cuando exista. Resolver el nombre del contrato desde la prop `contratos`:

```jsx
// helper cerca del render:
const nombreContrato = (id) => contratos.find((c) => c.id === Number(id))?.descripcion ?? '—';
```

Y en la fila, mostrar `nombreContrato(v.contrato_id)` en una celda/línea adicional. Ajustar encabezados de la tabla en consecuencia.

- [ ] **Step 6: Compilar**

Run: `npm run build`
Expected: `✓ built` sin errores.

- [ ] **Step 7: Commit**

```bash
git add resources/js/Pages/Viaticos/Crear.jsx
git commit -m "feat(viaticos): toggle de viajero externo y select de contrato en el formulario"
```

---

## Task 9: Consumidores de lectura usan nombreMostrado

**Files:**
- Modify: `app/Mail/LiquidacionViajeroMail.php`
- Modify: `app/Services/LiquidacionPdf.php`
- Modify: `app/Notifications/ComisionCerradaNotification.php`
- Modify: `app/Http/Controllers/ComisionesRrhhController.php`
- Modify: `resources/js/Pages/Solicitudes/Detalle.jsx`
- Modify: `resources/js/Pages/Viaticos/Liquidacion.jsx`

**Contexto:** Reemplazar la composición manual del nombre por el accessor `nombreMostrado` (y `identificacionMostrada` donde aplique) para que el viajero externo aparezca con su nombre. Leer cada archivo antes de editar. NO cambiar la lógica del botón "Correo" (sigue dependiendo de `empleado?->email`).

- [ ] **Step 1: Backend — reemplazar composición manual**

- `LiquidacionViajeroMail.php:34`: usar `$viajero->nombreMostrado` en lugar de `trim(($viajero->empleado->nombres ?? '').' '.(...))`.
- `LiquidacionPdf.php:33`: `'empleado' => $viajero->nombreMostrado`.
- `LiquidacionPdf.php:57` (nombre de archivo): `str_replace(' ', '_', $viajero->nombreMostrado ?: 'viajero')`.
- `ComisionCerradaNotification.php:34-35`: `'empleado' => $v->nombreMostrado`, `'identificacion' => $v->identificacionMostrada`.
- `ComisionesRrhhController.php:36-37`: `'empleado' => $v->nombreMostrado`, `'identificacion' => $v->identificacionMostrada`.

> Verificar el número de línea exacto al abrir cada archivo; el patrón a reemplazar es `($viajero->empleado->nombres ?? '').' '.($viajero->empleado->apellidos ?? '')` o equivalente.

- [ ] **Step 2: Frontend — fallback a nombre_externo**

- `Detalle.jsx` (~179, ~246): donde hace `v.empleado ? \`${v.empleado.nombres} ${v.empleado.apellidos}\` : '—'`, cambiar el fallback de `'—'` a `(v.nombre_externo || '—')`.
- `Liquidacion.jsx` (~116): mismo ajuste de fallback.

> Verificar líneas exactas al abrir. Mantener los guards existentes; solo cambiar el valor de fallback.

- [ ] **Step 3: Verificar que no hay regresión**

Run: `php artisan test`
Expected: 135 + nuevos tests, todos verdes.

Run: `npm run build`
Expected: `✓ built`.

- [ ] **Step 4: Commit**

```bash
git add app/Mail/LiquidacionViajeroMail.php app/Services/LiquidacionPdf.php app/Notifications/ComisionCerradaNotification.php app/Http/Controllers/ComisionesRrhhController.php resources/js/Pages/Solicitudes/Detalle.jsx resources/js/Pages/Viaticos/Liquidacion.jsx
git commit -m "feat(viaticos): mostrar nombre del viajero externo en correo, PDF, RR.HH. y detalle"
```

---

## Task 10: Verificación final del bloque

- [ ] **Step 1: Suite completa**

Run: `php artisan test`
Expected: todos verdes (135 previos + ~9 nuevos).

- [ ] **Step 2: Build de producción**

Run: `npm run build`
Expected: `✓ built` sin errores.

- [ ] **Step 3: Estado git limpio**

Run: `git status`
Expected: working tree limpio, sin archivos sin commitear.
