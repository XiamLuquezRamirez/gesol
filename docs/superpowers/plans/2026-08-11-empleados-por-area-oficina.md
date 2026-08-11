# Empleados por departamento y solicitudes institucionales — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Filtrar los beneficiarios por departamento al crear una solicitud de oficina, y soportar solicitudes institucionales (papelería, aseo) mediante un área especial "General" que oculta los beneficiarios.

**Architecture:** Se versiona la relación `empleados.area_id` (ya presente en dev pero sin migración), se añade `areas.es_general` para marcar el área institucional, y el catálogo de áreas gana un registro "General". El formulario de oficina filtra beneficiarios por área en el cliente y el backend valida estrictamente la pertenencia. La gestión de empleados (Parámetros) gana un selector de departamento.

**Tech Stack:** PHP 8.2, Laravel 10.50, Inertia 0.6, React 18, spatie/laravel-permission 6, PHPUnit + SQLite `:memory:` (MariaDB en dev).

**Spec:** [docs/superpowers/specs/2026-08-11-empleados-por-area-oficina-design.md](../specs/2026-08-11-empleados-por-area-oficina-design.md)

---

## Estructura de archivos

**Crear:**
- `database/migrations/2026_08_11_100000_add_area_id_to_empleados_table.php`
- `database/migrations/2026_08_11_100100_add_es_general_to_areas_table.php`
- `tests/Feature/EmpleadoAreaTest.php`
- `tests/Feature/OficinaInstitucionalTest.php`

**Modificar:**
- `app/Models/Empleados.php` — `area_id` en fillable, relación `area()`.
- `app/Models/Area.php` — `es_general` en fillable + cast, relación `empleados()`.
- `database/seeders/AreaSeeder.php` — área "General" (`es_general = true`).
- `database/seeders/EmpleadosSeeder.php` — asignar `area_id` a empleados demo.
- `app/Http/Controllers/OficinaController.php` — empleados con `area_id`, áreas con `es_general`, sync condicional.
- `app/Http/Requests/GuardarSolicitudOficinaRequest.php` — validación condicional (general vs normal + pertenencia).
- `app/Http/Resources/SolicitudDetalleResource.php` — `institucional` para OFI.
- `app/Http/Controllers/ParametrosController.php` — `area_id` en store/update de empleado, áreas (sin general) en index.
- `resources/js/Pages/Oficina/Crear.jsx` — filtrado de beneficiarios por área + ocultar en general.
- `resources/js/Pages/Parametros/Index.jsx` — select de departamento en empleado + columna área.
- `resources/js/Pages/Solicitudes/Detalle.jsx` — mostrar "Institucional (todos)".
- `tests/Feature/CrearSolicitudOficinaTest.php` — ajustar payloads (beneficiarios del área).

---

# FASE 1 — Modelo de datos

## Task 1: Migración y relación `empleados.area_id`

**Files:**
- Create: `database/migrations/2026_08_11_100000_add_area_id_to_empleados_table.php`
- Modify: `app/Models/Empleados.php`
- Modify: `app/Models/Area.php`
- Test: `tests/Feature/EmpleadoAreaTest.php`

- [ ] **Step 1: Escribir la migración (idempotente)**

Crear `database/migrations/2026_08_11_100000_add_area_id_to_empleados_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // La columna ya existe en la base de desarrollo; la guardia evita el error
        // en dev y crea la columna en entornos limpios (CI, SQLite de tests).
        if (! Schema::hasColumn('empleados', 'area_id')) {
            Schema::table('empleados', function (Blueprint $table) {
                $table->foreignId('area_id')->nullable()->after('id')
                    ->constrained('areas')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('empleados', 'area_id')) {
            Schema::table('empleados', function (Blueprint $table) {
                $table->dropConstrainedForeignId('area_id');
            });
        }
    }
};
```

- [ ] **Step 2: Actualizar el modelo `Empleados`**

En `app/Models/Empleados.php`, añadir `area_id` a `$fillable` (queda como primer elemento) y la relación `area()`.
El `$fillable` actual es `['identificacion','nombres','apellidos','email','telefono']`. Debe quedar:

```php
    protected $fillable = [
        'area_id',
        'identificacion',
        'nombres',
        'apellidos',
        'email',
        'telefono',
    ];

    public function area()
    {
        return $this->belongsTo(Area::class, 'area_id');
    }
```

- [ ] **Step 3: Actualizar el modelo `Area`**

En `app/Models/Area.php`, añadir la relación `empleados()` tras `solicitudes()`:

```php
    public function empleados()
    {
        return $this->hasMany(Empleados::class, 'area_id');
    }
```

- [ ] **Step 4: Escribir el test**

Crear `tests/Feature/EmpleadoAreaTest.php`:

```php
<?php
namespace Tests\Feature;

use App\Models\{Area, Empleados};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmpleadoAreaTest extends TestCase
{
    use RefreshDatabase;

    public function test_empleado_pertenece_a_un_area_y_el_area_lista_sus_empleados(): void
    {
        $area = Area::create(['nombre' => 'Sistemas']);
        $empleado = Empleados::create([
            'area_id' => $area->id, 'identificacion' => '99001',
            'nombres' => 'Pedro', 'apellidos' => 'Pérez',
        ]);

        $this->assertEquals($area->id, $empleado->fresh()->area->id);
        $this->assertTrue($area->empleados->contains($empleado->id));
    }

    public function test_empleado_puede_no_tener_area(): void
    {
        $empleado = Empleados::create([
            'identificacion' => '99002', 'nombres' => 'Sin', 'apellidos' => 'Área',
        ]);

        $this->assertNull($empleado->fresh()->area_id);
    }
}
```

- [ ] **Step 5: Ejecutar el test**

Run: `php artisan test --filter=EmpleadoAreaTest`
Expected: PASS (2 passed).

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_11_100000_add_area_id_to_empleados_table.php app/Models/Empleados.php app/Models/Area.php tests/Feature/EmpleadoAreaTest.php
git commit -m "feat(empleados): relacion empleado-departamento (area_id)"
```

---

## Task 2: Migración y modelo de `areas.es_general`

**Files:**
- Create: `database/migrations/2026_08_11_100100_add_es_general_to_areas_table.php`
- Modify: `app/Models/Area.php`

- [ ] **Step 1: Escribir la migración**

Crear `database/migrations/2026_08_11_100100_add_es_general_to_areas_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->boolean('es_general')->default(false)->after('descripcion');
        });
    }

    public function down(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->dropColumn('es_general');
        });
    }
};
```

- [ ] **Step 2: Actualizar el modelo `Area`**

En `app/Models/Area.php`, añadir `es_general` a `$fillable` y castearlo a boolean. El `$fillable` actual es
`['nombre', 'descripcion']`. Debe quedar:

```php
    protected $fillable = ['nombre', 'descripcion', 'es_general'];
    protected $casts = ['es_general' => 'boolean'];
```

- [ ] **Step 3: Ejecutar los tests existentes de área para verificar que la columna no rompe nada**

Run: `php artisan test --filter=EmpleadoAreaTest`
Expected: PASS (el cast y la columna nueva no afectan; `Area::create(['nombre'=>...])` sigue funcionando con `es_general` default false).

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_08_11_100100_add_es_general_to_areas_table.php app/Models/Area.php
git commit -m "feat(areas): marca es_general para el area institucional"
```

---

## Task 3: Seeders — área General y áreas de empleados demo

**Files:**
- Modify: `database/seeders/AreaSeeder.php`
- Modify: `database/seeders/EmpleadosSeeder.php`
- Test: `tests/Feature/EmpleadoAreaTest.php`

- [ ] **Step 1: Actualizar `AreaSeeder` para incluir el área General**

Reemplazar el contenido de `database/seeders/AreaSeeder.php` por:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AreaSeeder extends Seeder
{
    public function run(): void
    {
        $areas = ['Tecnología', 'Contabilidad', 'Recursos Humanos', 'Gerencia'];
        foreach ($areas as $nombre) {
            DB::table('areas')->insertOrIgnore([
                'nombre' => $nombre, 'es_general' => false,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        // Area institucional para solicitudes de consumo general (papeleria, aseo).
        DB::table('areas')->insertOrIgnore([
            'nombre'      => 'General',
            'descripcion' => 'Solicitudes institucionales (papelería, aseo)',
            'es_general'  => true,
            'created_at'  => now(), 'updated_at' => now(),
        ]);
    }
}
```

- [ ] **Step 2: Actualizar `EmpleadosSeeder` para asignar `area_id`**

Los empleados demo deben quedar repartidos entre las áreas reales. Reemplazar el contenido de
`database/seeders/EmpleadosSeeder.php` por:

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmpleadosSeeder extends Seeder
{
    public function run(): void
    {
        // Mapa nombre de area -> id (solo areas reales, no la General).
        $areas = DB::table('areas')->where('es_general', false)->pluck('id', 'nombre');

        $empleados = [
            ['identificacion'=>'10001','nombres'=>'Ana','apellidos'=>'Martínez','email'=>'ana.martinez@demo.test','telefono'=>'3001000001','area'=>'Tecnología'],
            ['identificacion'=>'10002','nombres'=>'Carlos','apellidos'=>'López','email'=>'carlos.lopez@demo.test','telefono'=>'3001000002','area'=>'Tecnología'],
            ['identificacion'=>'10003','nombres'=>'Luisa','apellidos'=>'Ramírez','email'=>'luisa.ramirez@demo.test','telefono'=>'3001000003','area'=>'Contabilidad'],
            ['identificacion'=>'10004','nombres'=>'Jorge','apellidos'=>'Herrera','email'=>'jorge.herrera@demo.test','telefono'=>'3001000004','area'=>'Recursos Humanos'],
            ['identificacion'=>'10005','nombres'=>'María','apellidos'=>'Gómez','email'=>'maria.gomez@demo.test','telefono'=>'3001000005','area'=>'Gerencia'],
        ];

        foreach ($empleados as $e) {
            DB::table('empleados')->insertOrIgnore([
                'area_id'        => $areas[$e['area']] ?? null,
                'identificacion' => $e['identificacion'],
                'nombres'        => $e['nombres'],
                'apellidos'      => $e['apellidos'],
                'email'          => $e['email'],
                'telefono'       => $e['telefono'],
                'created_at'     => now(), 'updated_at' => now(),
            ]);
        }
    }
}
```

> `insertOrIgnore` protege contra duplicados por la `identificacion` única. En una base ya sembrada
> (identificaciones existentes) NO reasigna el área; para eso se re-siembra en limpio o se ajusta a mano. Los
> tests usan RefreshDatabase (base limpia), así que ahí sí toma el `area_id`.

- [ ] **Step 3: Escribir el test de seeders**

Añadir al final de la clase `EmpleadoAreaTest` (antes del `}` de cierre):

```php
    public function test_seeder_crea_area_general_y_asigna_areas_a_empleados(): void
    {
        $this->seed();

        $general = \App\Models\Area::where('es_general', true)->first();
        $this->assertNotNull($general);
        $this->assertEquals('General', $general->nombre);

        // Los empleados demo quedaron con area real (no la General).
        $conArea = \App\Models\Empleados::whereNotNull('area_id')->count();
        $this->assertGreaterThan(0, $conArea);
        $this->assertEquals(0, \App\Models\Empleados::where('area_id', $general->id)->count());
    }
```

- [ ] **Step 4: Ejecutar**

Run: `php artisan test --filter=EmpleadoAreaTest`
Expected: PASS (3 passed).

- [ ] **Step 5: Re-seedear la base de desarrollo (área General)**

Run: `php artisan db:seed --class=AreaSeeder`
Expected: seeding OK (añade "General"; las áreas existentes se ignoran por `insertOrIgnore`).

> Nota: los empleados demo ya existentes en dev NO cambian de área con el re-seed (insertOrIgnore). Sus áreas
> se asignarán desde la UI de Parámetros (Fase 3) o re-sembrando en limpio.

- [ ] **Step 6: Commit**

```bash
git add database/seeders/AreaSeeder.php database/seeders/EmpleadosSeeder.php tests/Feature/EmpleadoAreaTest.php
git commit -m "feat(seed): area General institucional y area de empleados demo"
```

---

# FASE 2 — Backend de la solicitud de oficina

## Task 4: Validación condicional en `GuardarSolicitudOficinaRequest`

**Files:**
- Modify: `app/Http/Requests/GuardarSolicitudOficinaRequest.php`
- Test: `tests/Feature/OficinaInstitucionalTest.php`

- [ ] **Step 1: Reescribir el request con reglas condicionales**

Reemplazar el contenido de `app/Http/Requests/GuardarSolicitudOficinaRequest.php` por:

```php
<?php

namespace App\Http\Requests;

use App\Models\Area;
use App\Models\Empleados;
use Illuminate\Foundation\Http\FormRequest;

class GuardarSolicitudOficinaRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** El area elegida es la institucional (General). */
    private function areaEsGeneral(): bool
    {
        $area = Area::find($this->input('area_id'));
        return (bool) ($area?->es_general);
    }

    public function rules(): array
    {
        $reglas = [
            'area_id'                => 'required|exists:areas,id',
            'urgencia'               => 'required|in:baja,media,alta',
            'justificacion'          => 'required|string|max:2000',
            'items'                  => 'required|array|min:1',
            'items.*.nombre'         => 'required|string|max:255',
            'items.*.categoria'      => 'required|in:producto,servicio',
            'items.*.cantidad'       => 'required|integer|min:1',
            'items.*.costo_estimado' => 'nullable|numeric|min:0',
            'items.*.notas'          => 'nullable|string|max:500',
        ];

        if ($this->areaEsGeneral()) {
            // Institucional: sin beneficiarios (se ignora lo que venga).
            $reglas['beneficiarios']   = 'nullable|array';
        } else {
            // Area normal: al menos un beneficiario, todos del area elegida.
            $reglas['beneficiarios']   = 'required|array|min:1';
            $reglas['beneficiarios.*'] = 'exists:empleados,id';
        }

        return $reglas;
    }

    /**
     * Regla estricta: en un area normal, cada beneficiario debe pertenecer al
     * area elegida. Un empleado sin area, o de otra area, se rechaza.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($this->areaEsGeneral()) {
                return;
            }
            $ids = (array) $this->input('beneficiarios', []);
            if (empty($ids)) {
                return; // ya lo cubre required|min:1
            }
            $ajenos = Empleados::whereIn('id', $ids)
                ->where(fn ($q) => $q->where('area_id', '!=', $this->input('area_id'))->orWhereNull('area_id'))
                ->exists();
            if ($ajenos) {
                $validator->errors()->add('beneficiarios', 'Todos los beneficiarios deben pertenecer al departamento seleccionado.');
            }
        });
    }

    public function attributes(): array
    {
        return [
            'beneficiarios'          => 'beneficiarios',
            'beneficiarios.*'        => 'beneficiario',
            'area_id'                => 'departamento',
            'urgencia'               => 'urgencia',
            'justificacion'          => 'justificación',
            'items'                  => 'ítems',
            'items.*.nombre'         => 'nombre del ítem',
            'items.*.categoria'      => 'categoría del ítem',
            'items.*.cantidad'       => 'cantidad del ítem',
            'items.*.costo_estimado' => 'costo estimado',
        ];
    }
}
```

- [ ] **Step 2: Escribir los tests de validación**

Crear `tests/Feature/OficinaInstitucionalTest.php`:

```php
<?php
namespace Tests\Feature;

use App\Models\{Area, Empleados, SolicitudOficina, Usuario};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OficinaInstitucionalTest extends TestCase
{
    use RefreshDatabase;

    private Usuario $liderArea;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->liderArea = Usuario::where('email', 'lider.area@demo.test')->firstOrFail();
    }

    private function areaConEmpleados(): array
    {
        $area = Area::where('es_general', false)->whereHas('empleados')->first();
        $emp  = $area->empleados()->pluck('id')->all();
        return [$area, $emp];
    }

    private function payload(array $override = []): array
    {
        return array_merge([
            'urgencia'      => 'media',
            'justificacion' => 'Insumos.',
            'items'         => [['nombre'=>'Mouse','categoria'=>'producto','cantidad'=>1,'costo_estimado'=>1000,'notas'=>'']],
        ], $override);
    }

    public function test_solicitud_normal_con_beneficiarios_del_area_es_valida(): void
    {
        [$area, $emp] = $this->areaConEmpleados();

        $this->actingAs($this->liderArea)
            ->post(route('oficina.store'), $this->payload(['area_id'=>$area->id,'beneficiarios'=>$emp]))
            ->assertRedirect();
    }

    public function test_solicitud_normal_rechaza_beneficiario_de_otra_area(): void
    {
        [$area, ] = $this->areaConEmpleados();
        // Empleado de un area distinta a la elegida.
        $ajeno = Empleados::where('area_id', '!=', $area->id)->whereNotNull('area_id')->first();

        $this->actingAs($this->liderArea)
            ->from(route('oficina.crear'))
            ->post(route('oficina.store'), $this->payload(['area_id'=>$area->id,'beneficiarios'=>[$ajeno->id]]))
            ->assertSessionHasErrors('beneficiarios');
    }

    public function test_solicitud_normal_sin_beneficiarios_es_invalida(): void
    {
        [$area, ] = $this->areaConEmpleados();

        $this->actingAs($this->liderArea)
            ->from(route('oficina.crear'))
            ->post(route('oficina.store'), $this->payload(['area_id'=>$area->id]))
            ->assertSessionHasErrors('beneficiarios');
    }

    public function test_solicitud_general_sin_beneficiarios_es_valida(): void
    {
        $general = Area::where('es_general', true)->firstOrFail();

        $this->actingAs($this->liderArea)
            ->post(route('oficina.store'), $this->payload(['area_id'=>$general->id]))
            ->assertRedirect();

        $cabecera = SolicitudOficina::latest('id')->first();
        $this->assertEquals(0, $cabecera->beneficiarios()->count());
    }

    public function test_solicitud_general_ignora_beneficiarios_enviados(): void
    {
        $general = Area::where('es_general', true)->firstOrFail();
        $algun   = Empleados::first();

        $this->actingAs($this->liderArea)
            ->post(route('oficina.store'), $this->payload(['area_id'=>$general->id,'beneficiarios'=>[$algun->id]]))
            ->assertRedirect();

        $cabecera = SolicitudOficina::latest('id')->first();
        $this->assertEquals(0, $cabecera->beneficiarios()->count());
    }
}
```

- [ ] **Step 3: Ejecutar (fallarán los de "general" hasta el sync en Task 5)**

Run: `php artisan test --filter=OficinaInstitucionalTest`
Expected: los tests de "normal" pasan; los de "general" que verifican `beneficiarios()->count() === 0` pasan
solo si el controlador hace `sync([])` en general. Como el controlador actual hace
`sync($request->beneficiarios)` con `beneficiarios` nulo, PHP dará error o sincronizará vacío. Se resuelve en
Task 5. Es aceptable que estos 2 fallen aquí.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Requests/GuardarSolicitudOficinaRequest.php tests/Feature/OficinaInstitucionalTest.php
git commit -m "feat(oficina): validacion condicional general vs normal con pertenencia al area"
```

---

## Task 5: Controlador de oficina — props y sync condicional

**Files:**
- Modify: `app/Http/Controllers/OficinaController.php`
- Test: `tests/Feature/OficinaInstitucionalTest.php` (de Task 4)

- [ ] **Step 1: Pasar empleados con `area_id` y áreas con `es_general` en create/edit**

En `app/Http/Controllers/OficinaController.php`:

En `create()`, el prop `empleados` actual es
`Empleados::orderBy('nombres')->get(['id','nombres','apellidos','identificacion'])`. Añadir `area_id` a las
columnas, y cambiar el prop `areas` para incluir `es_general`. `create()` debe quedar:

```php
    public function create()
    {
        $this->authorize('create', Solicitud::class);
        return Inertia::render('Oficina/Crear', [
            'areas'     => Area::orderBy('nombre')->get(['id','nombre','es_general']),
            'usuarios'  => Usuario::orderBy('name')->get(['id','name']),
            'empleados' => Empleados::orderBy('nombres')->get(['id','nombres','apellidos','identificacion','area_id']),
        ]);
    }
```

Aplicar el mismo cambio de columnas de `areas` y `empleados` en `edit()`:

```php
            'areas'     => Area::orderBy('nombre')->get(['id','nombre','es_general']),
            'usuarios'  => Usuario::orderBy('name')->get(['id','name']),
            'empleados' => Empleados::orderBy('nombres')->get(['id','nombres','apellidos','identificacion','area_id']),
```

- [ ] **Step 2: Sync condicional en `store()`**

En `store()`, la línea actual es `$cabecera->beneficiarios()->sync($request->beneficiarios);`. Reemplazarla por
una versión que respeta el área general (sin beneficiarios):

```php
            $cabecera->beneficiarios()->sync($this->beneficiariosASincronizar($request));
```

Y añadir el método privado auxiliar en el controlador (tras `normalizarItem()`):

```php
    /**
     * En un area institucional (General) la solicitud no lleva beneficiarios.
     */
    private function beneficiariosASincronizar($request): array
    {
        $area = \App\Models\Area::find($request->area_id);
        if ($area?->es_general) {
            return [];
        }
        return (array) $request->beneficiarios;
    }
```

- [ ] **Step 3: Sync condicional en `update()`**

En `update()`, reemplazar `$cabecera->beneficiarios()->sync($request->beneficiarios);` por:

```php
            $cabecera->beneficiarios()->sync($this->beneficiariosASincronizar($request));
```

- [ ] **Step 4: Ejecutar los tests**

Run: `php artisan test --filter=OficinaInstitucionalTest`
Expected: PASS (5 passed — normal y general).

- [ ] **Step 5: Ejecutar la suite de creación existente**

Run: `php artisan test --filter=CrearSolicitudOficinaTest`
Expected: puede FALLAR si sus payloads usan empleados que no pertenecen al área elegida (la validación ahora es
estricta). Se corrige en Task 6.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/OficinaController.php
git commit -m "feat(oficina): empleados con area, areas con es_general y sync institucional"
```

---

## Task 6: Ajustar `CrearSolicitudOficinaTest` a la validación por área

**Files:**
- Modify: `tests/Feature/CrearSolicitudOficinaTest.php`

- [ ] **Step 1: Usar beneficiarios que pertenezcan al área elegida**

En `tests/Feature/CrearSolicitudOficinaTest.php`, los payloads usan `Area::first()` y
`Empleados::take(1/2)` sin garantizar que el empleado pertenezca a esa área. Ahora la validación es estricta.
Reemplazar el `payloadBase()` para elegir un área real que tenga empleados y usar SUS empleados:

```php
    private function payloadBase(array $itemOverride = []): array
    {
        $area = Area::where('es_general', false)->whereHas('empleados')->first();
        return [
            'area_id'       => $area->id,
            'beneficiarios' => $area->empleados()->take(1)->pluck('id')->all(),
            'urgencia'      => 'media',
            'justificacion' => 'Se requieren insumos.',
            'items'         => [array_merge([
                'nombre'         => 'Resma de papel',
                'categoria'      => 'producto',
                'cantidad'       => 2,
                'costo_estimado' => 15000,
                'notas'          => '',
            ], $itemOverride)],
        ];
    }
```

- [ ] **Step 2: Ajustar `test_crea_solicitud_con_varios_beneficiarios` y `test_editar_sincroniza_los_beneficiarios`**

Ambos eligen `Area::first()` y `Empleados::take(2)`. Cambiarlos para usar un área con al menos 2 empleados.
Reemplazar el cuerpo de `test_crea_solicitud_con_varios_beneficiarios` por:

```php
    public function test_crea_solicitud_con_varios_beneficiarios(): void
    {
        $area = Area::where('es_general', false)->has('empleados', '>=', 2)->first();
        $empleados = $area->empleados()->take(2)->pluck('id')->all();

        $this->actingAs($this->liderArea)->post(route('oficina.store'), [
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

Y el cuerpo de `test_editar_sincroniza_los_beneficiarios` por (mismo departamento en store y update, para que la
validación estricta pase; se cambian los beneficiarios dentro de la misma área):

```php
    public function test_editar_sincroniza_los_beneficiarios(): void
    {
        $area = Area::where('es_general', false)->has('empleados', '>=', 2)->first();
        $todos = $area->empleados()->pluck('id')->all();
        $inicial = [$todos[0]];
        $nuevos  = array_slice($todos, 0, 2);

        $this->actingAs($this->liderArea)->post(route('oficina.store'), [
            'area_id'       => $area->id,
            'beneficiarios' => $inicial,
            'urgencia'      => 'media',
            'justificacion' => 'Version inicial.',
            'items'         => [['nombre'=>'Mouse','categoria'=>'producto','cantidad'=>1,'costo_estimado'=>1000,'notas'=>'']],
        ])->assertRedirect();

        $solicitud = \App\Models\Solicitud::latest('id')->first();

        $this->actingAs($this->liderArea)->put(route('oficina.update', $solicitud), [
            'area_id'       => $area->id,
            'beneficiarios' => $nuevos,
            'urgencia'      => 'alta',
            'justificacion' => 'Version editada.',
            'items'         => [['nombre'=>'Teclado','categoria'=>'producto','cantidad'=>1,'costo_estimado'=>2000,'notas'=>'']],
        ])->assertRedirect();

        $this->assertEqualsCanonicalizing(
            $nuevos,
            $solicitud->solicitable->fresh()->beneficiarios->pluck('id')->all()
        );
    }
```

> Asegurar que `Area` está importado en el `use` del test (ya lo está: `use App\Models\{Area, Empleados, Usuario};`).

- [ ] **Step 3: Ejecutar**

Run: `php artisan test --filter=CrearSolicitudOficinaTest`
Expected: PASS (todos).

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/CrearSolicitudOficinaTest.php
git commit -m "test(oficina): beneficiarios del area elegida en los tests de creacion"
```

---

## Task 7: Exponer `institucional` en el detalle (Resource)

**Files:**
- Modify: `app/Http/Resources/SolicitudDetalleResource.php`

- [ ] **Step 1: Añadir `institucional` al Resource**

En `app/Http/Resources/SolicitudDetalleResource.php`, dentro del `return`, tras la clave `beneficiarios`
(línea ~28), añadir:

```php
            'institucional' => $this->when($esOficina, fn () => (bool) ($this->area?->es_general)),
```

- [ ] **Step 2: Verificar que el detalle carga**

Run: `php artisan test --filter=OficinaInstitucionalTest`
Expected: PASS (sin cambios de comportamiento; el Resource solo añade una clave).

- [ ] **Step 3: Commit**

```bash
git add app/Http/Resources/SolicitudDetalleResource.php
git commit -m "feat(oficina): exponer si la solicitud es institucional en el detalle"
```

---

# FASE 3 — Backend de Parámetros (gestión de empleados)

## Task 8: `area_id` en el CRUD de empleado y áreas en el index

**Files:**
- Modify: `app/Http/Controllers/ParametrosController.php`
- Test: `tests/Feature/EmpleadoAreaTest.php`

- [ ] **Step 1: Pasar áreas (sin la General) en `index()`**

En `app/Http/Controllers/ParametrosController.php`, añadir el import de `Area` al `use` superior
(actual: `use App\Models\{Empleados, TarifaViatico};`):

```php
use App\Models\{Area, Empleados, TarifaViatico};
```

Y en `index()` añadir el prop `areas` (excluyendo la General):

```php
    public function index()
    {
        return Inertia::render('Parametros/Index', [
            'tarifas'   => TarifaViatico::all(),
            'empleados' => Empleados::with('area:id,nombre')->orderBy('apellidos')->orderBy('nombres')->get(),
            'areas'     => Area::where('es_general', false)->orderBy('nombre')->get(['id','nombre']),
        ]);
    }
```

- [ ] **Step 2: Validar y guardar `area_id` en store/update de empleado**

En `storeEmpleado()`, añadir a las reglas `'area_id' => 'nullable|exists:areas,id',`. Debe quedar:

```php
    public function storeEmpleado(Request $request)
    {
        $data = $request->validate([
            'area_id'        => 'nullable|exists:areas,id',
            'identificacion' => 'required|string|max:20|unique:empleados,identificacion',
            'nombres'        => 'required|string|max:100',
            'apellidos'      => 'required|string|max:100',
            'email'          => 'nullable|email|max:255|unique:empleados,email',
            'telefono'       => 'nullable|string|max:20',
        ]);
        Empleados::create($data);
        return back()->with('success', 'Empleado creado.');
    }
```

En `updateEmpleado()`, igual:

```php
    public function updateEmpleado(Request $request, Empleados $empleado)
    {
        $data = $request->validate([
            'area_id'        => 'nullable|exists:areas,id',
            'identificacion' => 'required|string|max:20|unique:empleados,identificacion,'.$empleado->id,
            'nombres'        => 'required|string|max:100',
            'apellidos'      => 'required|string|max:100',
            'email'          => 'nullable|email|max:255|unique:empleados,email,'.$empleado->id,
            'telefono'       => 'nullable|string|max:20',
        ]);
        $empleado->update($data);
        return back()->with('success', 'Empleado actualizado.');
    }
```

- [ ] **Step 3: Escribir el test**

Añadir al final de la clase `EmpleadoAreaTest` (antes del `}` de cierre):

```php
    public function test_crear_empleado_con_area_desde_parametros(): void
    {
        $this->seed();
        $admin = \App\Models\Usuario::where('email', 'admin@demo.test')->firstOrFail();
        $area  = \App\Models\Area::where('es_general', false)->first();

        $this->actingAs($admin)->post(route('parametros.empleados.store'), [
            'area_id'        => $area->id,
            'identificacion' => '77001',
            'nombres'        => 'Nuevo',
            'apellidos'      => 'Empleado',
        ])->assertRedirect();

        $this->assertDatabaseHas('empleados', [
            'identificacion' => '77001', 'area_id' => $area->id,
        ]);
    }

    public function test_area_invalida_al_crear_empleado_es_rechazada(): void
    {
        $this->seed();
        $admin = \App\Models\Usuario::where('email', 'admin@demo.test')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('parametros.index'))
            ->post(route('parametros.empleados.store'), [
                'area_id'        => 99999,
                'identificacion' => '77002',
                'nombres'        => 'X', 'apellidos' => 'Y',
            ])->assertSessionHasErrors('area_id');
    }
```

> Nota: `parametros.*` está tras el middleware de rol adecuado; el usuario `admin@demo.test` tiene todos los
> roles. Verificar en `routes/web.php` qué rol protege `parametros.empleados.store` y usar un usuario con ese
> rol si no fuera `admin` (leer la ruta antes de asumir).

- [ ] **Step 4: Ejecutar**

Run: `php artisan test --filter=EmpleadoAreaTest`
Expected: PASS (todos).

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/ParametrosController.php tests/Feature/EmpleadoAreaTest.php
git commit -m "feat(parametros): asignar departamento (area) a los empleados"
```

---

# FASE 4 — UI (React)

## Task 9: Filtrado de beneficiarios por área en `Crear.jsx`

**Files:**
- Modify: `resources/js/Pages/Oficina/Crear.jsx`

- [ ] **Step 1: Derivar el área elegida y filtrar empleados; ocultar en general**

En `resources/js/Pages/Oficina/Crear.jsx`, dentro del componente `Crear`, tras el `useForm` (línea ~64),
añadir el cálculo del área elegida y los empleados filtrados:

```jsx
    const areaElegida = areas.find((a) => String(a.id) === String(data.area_id));
    const esGeneral = !!areaElegida?.es_general;
    const empleadosDelArea = empleados.filter((e) => String(e.area_id) === String(data.area_id));
```

Y un handler para cambiar el área que limpia beneficiarios que ya no aplican:

```jsx
    const cambiarArea = (v) => {
        setData('area_id', v);
        const area = areas.find((a) => String(a.id) === String(v));
        if (area?.es_general) {
            setData('beneficiarios', []);
        } else {
            const validos = empleados
                .filter((e) => String(e.area_id) === String(v))
                .map((e) => e.id);
            setData('beneficiarios', data.beneficiarios.filter((id) => validos.includes(id)));
        }
    };
```

> Nota técnica: `setData` en @inertiajs/react es asíncrono; hacer dos `setData` seguidos (area_id y
> beneficiarios) funciona porque no dependen del mismo campo. Si el linter/comportamiento lo requiere, usar la
> forma funcional `setData(prev => ({...prev, ...}))`. Mantener la versión mostrada, que es la usada en el resto
> del archivo.

- [ ] **Step 2: Usar el handler en el select y filtrar/ocultar el bloque de beneficiarios**

Reemplazar el `SelectField` de departamento (líneas ~95-97) para usar `cambiarArea`:

```jsx
                            <SelectField label="Departamento:" name="area_id" value={data.area_id}
                                onChange={cambiarArea}
                                options={areas} error={errors.area_id} placeholder="Seleccionar departamento" />
```

Y reemplazar el bloque de beneficiarios (el `<div>` con el label "Beneficiario(s):" y su lista, líneas ~98-123)
por una versión que: (a) si es general muestra un aviso institucional; (b) si no, lista solo `empleadosDelArea`:

```jsx
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Beneficiario(s):</label>
                                {esGeneral ? (
                                    <p className="text-sm text-slate-500 border border-slate-200 rounded-lg p-3 bg-slate-50">
                                        Solicitud institucional (papelería, aseo): aplica a toda la organización.
                                    </p>
                                ) : (
                                    <>
                                        <div className="border border-slate-300 rounded-lg p-3 max-h-40 overflow-y-auto space-y-1">
                                            {!data.area_id && (
                                                <p className="text-xs text-slate-400">Seleccione primero un departamento.</p>
                                            )}
                                            {data.area_id && empleadosDelArea.length === 0 && (
                                                <p className="text-xs text-slate-400">Este departamento no tiene empleados.</p>
                                            )}
                                            {empleadosDelArea.map((e) => (
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
                                    </>
                                )}
                            </div>
```

- [ ] **Step 3: Compilar**

Run: `npm run build`
Expected: build sin errores.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Oficina/Crear.jsx
git commit -m "feat(oficina): filtrar beneficiarios por departamento y modo institucional"
```

---

## Task 10: Select de departamento en el CRUD de empleado (`Parametros/Index.jsx`)

**Files:**
- Modify: `resources/js/Pages/Parametros/Index.jsx`

- [ ] **Step 1: Recibir `areas` y pasarlo al tab de empleados**

En `resources/js/Pages/Parametros/Index.jsx`, la firma del componente por defecto es
`export default function Index({ tarifas, empleados })`. Cambiarla por:

```jsx
export default function Index({ tarifas, empleados, areas = [] }) {
```

Y donde se renderiza el tab (`{tab === 'empleados' && <TabEmpleados empleados={empleados} />}`), pasar `areas`:

```jsx
                {tab === 'empleados' && <TabEmpleados empleados={empleados} areas={areas} />}
```

- [ ] **Step 2: Añadir `area_id` al formulario del tab de empleados**

En `TabEmpleados`: cambiar la firma a `function TabEmpleados({ empleados, areas })`, añadir `area_id` al objeto
`VACIO` y al `setData` de `abrirEditar`.

`VACIO` actual es `{ identificacion: '', nombres: '', apellidos: '', email: '', telefono: '' }`. Debe quedar:

```jsx
const VACIO = { area_id: '', identificacion: '', nombres: '', apellidos: '', email: '', telefono: '' };
```

En `abrirEditar`, el `setData({...})` actual no incluye `area_id`. Reemplazarlo por:

```jsx
        setData({ area_id: emp.area_id ?? '', identificacion: emp.identificacion, nombres: emp.nombres, apellidos: emp.apellidos, email: emp.email ?? '', telefono: emp.telefono ?? '' });
```

Y añadir un `<Field>` con un `<select>` de departamento dentro del grid del formulario (tras el `<Field>` de
Teléfono, dentro del `<div className="grid grid-cols-3 gap-4">`):

```jsx
                            <Field label="Departamento" error={errors.area_id}>
                                <select
                                    value={data.area_id}
                                    onChange={(e) => setData('area_id', e.target.value)}
                                    className="w-full rounded-lg border border-slate-300 text-sm py-2 px-3 focus:ring-2 focus:ring-indigo-500 outline-none"
                                >
                                    <option value="">— Sin departamento —</option>
                                    {areas.map((a) => (
                                        <option key={a.id} value={a.id}>{a.nombre}</option>
                                    ))}
                                </select>
                            </Field>
```

- [ ] **Step 3: Mostrar el departamento en la tabla de empleados**

En la tabla, añadir una columna "Departamento". En el `<thead>`, tras la cabecera "Apellidos", añadir:

```jsx
                                <th className="text-left text-xs font-semibold text-slate-500 px-4 py-3">Departamento</th>
```

Y en el `<tbody>`, en cada fila, tras la celda de Apellidos (`<td ...>{emp.apellidos}</td>`), añadir:

```jsx
                                    <td className="px-4 py-3 text-slate-500">{emp.area?.nombre ?? '—'}</td>
```

> El controlador ya carga `with('area:id,nombre')`, así que `emp.area.nombre` viene en el prop.

- [ ] **Step 4: Compilar**

Run: `npm run build`
Expected: build sin errores.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Parametros/Index.jsx
git commit -m "feat(parametros): selector de departamento y columna area en empleados"
```

---

## Task 11: Mostrar "Institucional (todos)" en el detalle

**Files:**
- Modify: `resources/js/Pages/Solicitudes/Detalle.jsx`

- [ ] **Step 1: Usar `institucional` en `DetalleOficina`**

En `resources/js/Pages/Solicitudes/Detalle.jsx`, el componente `DetalleOficina` recibe `solicitable` y
`beneficiarios`. Cambiar su firma para recibir también `institucional`, y calcular el texto del beneficiario.

La firma actual es `function DetalleOficina({ solicitable, beneficiarios = [] }) {` y el cálculo actual es:

```jsx
    const nombresBeneficiarios = beneficiarios.length > 0
        ? beneficiarios.map((b) => b.nombre).join(', ')
        : (solicitable.beneficiario || null);
```

Reemplazar ambos por:

```jsx
function DetalleOficina({ solicitable, beneficiarios = [], institucional = false }) {
    const nombresBeneficiarios = institucional
        ? 'Institucional (todos)'
        : (beneficiarios.length > 0
            ? beneficiarios.map((b) => b.nombre).join(', ')
            : (solicitable.beneficiario || null));
```

- [ ] **Step 2: Pasar `institucional` desde el render**

Donde se renderiza `<DetalleOficina ... />` (línea ~660), añadir el prop:

```jsx
                    {esOficina && <DetalleOficina solicitable={solicitud.solicitable} beneficiarios={solicitud.beneficiarios} institucional={solicitud.institucional} />}
```

- [ ] **Step 3: Compilar**

Run: `npm run build`
Expected: build sin errores.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Solicitudes/Detalle.jsx
git commit -m "feat(oficina): mostrar beneficiario institucional en el detalle"
```

---

# FASE 5 — Verificación final

## Task 12: Suite completa, build y re-seed

- [ ] **Step 1: Ejecutar toda la suite**

Run: `php artisan test`
Expected: todos verdes (incluyendo EmpleadoAreaTest, OficinaInstitucionalTest, CrearSolicitudOficinaTest y los
existentes de oficina/cotizaciones/abonos).

- [ ] **Step 2: Build de producción**

Run: `npm run build`
Expected: build sin errores.

- [ ] **Step 3: Aplicar migraciones y re-seed del área General en desarrollo**

Run: `php artisan migrate`
Expected: corre `add_area_id_to_empleados` (idempotente, no-op si ya existe) y `add_es_general_to_areas`.

Run: `php artisan db:seed --class=AreaSeeder`
Expected: añade el área "General".

- [ ] **Step 4: Verificar árbol limpio**

Run: `git status --short`
Expected: sin cambios pendientes (los assets de `public/build` no se versionan).

---

## Cobertura del spec (checklist de auto-revisión)

- Empleado→área (`area_id`, relaciones): Tasks 1. ✔
- Área especial General (`es_general`, seeder): Tasks 2, 3. ✔
- Filtrado de beneficiarios por área (UI): Task 9. ✔
- Ocultar beneficiarios / institucional en General (UI + backend): Tasks 4, 5, 9. ✔
- Validación estricta (beneficiario del área elegida): Tasks 4, 6. ✔
- Institucional derivado + detalle: Tasks 7, 11. ✔
- Gestión de empleados con área (Parámetros): Tasks 8, 10. ✔
