# Viáticos Bloque A: municipios y contratos — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Crear el catálogo de municipios (multiselect en la comisión de viáticos) y la gestión de contratos (CRUD en Parámetros con municipios), como primer bloque de la mejora al subsistema de viáticos.

**Architecture:** Tabla `municipios` (solo seeder) y pivote `comision_municipio` reemplazan el texto libre `municipio_destino` en el formulario (la columna se conserva por compatibilidad). Tabla `contratos` + pivote `contrato_municipio`, gestionada desde un tab nuevo en Parámetros. Se sigue el patrón de beneficiarios múltiples de oficina (belongsToMany + multiselect de checkboxes) y el CRUD de empleados en Parámetros.

**Tech Stack:** PHP 8.2, Laravel 10.50, Inertia 0.6, React 18, PHPUnit + SQLite `:memory:`.

**Spec:** [docs/superpowers/specs/2026-08-18-viaticos-bloque-a-municipios-contratos-design.md](../specs/2026-08-18-viaticos-bloque-a-municipios-contratos-design.md)

---

## Estructura de archivos

**Crear:**
- `database/migrations/2026_08_18_100000_create_municipios_table.php`
- `database/migrations/2026_08_18_100100_create_contratos_table.php`
- `database/migrations/2026_08_18_100200_create_contrato_municipio_table.php`
- `database/migrations/2026_08_18_100300_create_comision_municipio_table.php`
- `app/Models/Municipio.php`
- `app/Models/Contrato.php`
- `database/seeders/MunicipiosSeeder.php`
- `tests/Feature/MunicipiosComisionTest.php`
- `tests/Feature/ContratosParametrosTest.php`

**Modificar:**
- `database/seeders/DatabaseSeeder.php` — registrar `MunicipiosSeeder`.
- `app/Models/SolicitudViaticos.php` — relación `municipios()`.
- `app/Http/Requests/GuardarSolicitudViaticosRequest.php` — validar `municipios` (reemplaza `municipio_destino`).
- `app/Http/Controllers/ViaticosController.php` — props `municipios` en create/edit; sync en store/update.
- `app/Http/Controllers/SolicitudController.php` — eager load `municipios` en show.
- `resources/js/Pages/Viaticos/Crear.jsx` — multiselect de municipios.
- `resources/js/Pages/Solicitudes/Detalle.jsx` — mostrar lista de municipios en `DetalleViaticos`.
- `app/Http/Controllers/ParametrosController.php` — CRUD de contratos + props.
- `routes/web.php` — rutas `parametros.contratos.*`.
- `resources/js/Pages/Parametros/Index.jsx` — tab "Contratos".

---

# FASE 1 — Municipios (datos)

## Task 1: Tabla, modelo y seeder de municipios

**Files:**
- Create: `database/migrations/2026_08_18_100000_create_municipios_table.php`
- Create: `app/Models/Municipio.php`
- Create: `database/seeders/MunicipiosSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/MunicipiosComisionTest.php`

- [ ] **Step 1: Migración de municipios**

Crear `database/migrations/2026_08_18_100000_create_municipios_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('municipios', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('municipios');
    }
};
```

- [ ] **Step 2: Modelo `Municipio`**

Crear `app/Models/Municipio.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Municipio extends Model
{
    protected $table = 'municipios';
    protected $fillable = ['nombre'];

    public function comisiones()
    {
        return $this->belongsToMany(SolicitudViaticos::class, 'comision_municipio', 'municipio_id', 'solicitud_viaticos_id')
            ->withTimestamps();
    }

    public function contratos()
    {
        return $this->belongsToMany(Contrato::class, 'contrato_municipio', 'municipio_id', 'contrato_id')
            ->withTimestamps();
    }
}
```

- [ ] **Step 3: Seeder de municipios**

Crear `database/seeders/MunicipiosSeeder.php` (lista inicial de municipios del Cesar; ajustable):

```php
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MunicipiosSeeder extends Seeder
{
    public function run(): void
    {
        $municipios = [
            'Valledupar', 'Aguachica', 'Agustín Codazzi', 'Bosconia', 'Chimichagua',
            'Chiriguaná', 'Curumaní', 'El Copey', 'El Paso', 'La Jagua de Ibirico',
            'Becerril', 'La Paz', 'Manaure', 'Pailitas', 'Pelaya', 'Pueblo Bello',
            'San Alberto', 'San Diego', 'San Martín', 'Tamalameque',
        ];
        foreach ($municipios as $nombre) {
            DB::table('municipios')->insertOrIgnore([
                'nombre' => $nombre, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
```

- [ ] **Step 4: Registrar el seeder en `DatabaseSeeder`**

En `database/seeders/DatabaseSeeder.php`, añadir `MunicipiosSeeder::class` al array `$this->call([...])`, tras
`TarifaViaticosSeeder::class`:

```php
        $this->call([
            RolesSeeder::class,
            AreaSeeder::class,
            TipoSolicitudSeeder::class,
            TarifaViaticosSeeder::class,
            MunicipiosSeeder::class,
            AdminSeeder::class,
            UsuariosDemoSeeder::class,
            EmpleadosSeeder::class,
        ]);
```

- [ ] **Step 5: Escribir el test del seeder/modelo**

Crear `tests/Feature/MunicipiosComisionTest.php`:

```php
<?php
namespace Tests\Feature;

use App\Models\Municipio;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MunicipiosComisionTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_seeder_crea_el_catalogo_de_municipios(): void
    {
        $this->seed();
        $this->assertGreaterThan(0, Municipio::count());
        $this->assertDatabaseHas('municipios', ['nombre' => 'Valledupar']);
    }
}
```

- [ ] **Step 6: Ejecutar**

Run: `php artisan test --filter=MunicipiosComisionTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_18_100000_create_municipios_table.php app/Models/Municipio.php database/seeders/MunicipiosSeeder.php database/seeders/DatabaseSeeder.php tests/Feature/MunicipiosComisionTest.php
git commit -m "feat(viaticos): catalogo de municipios (tabla, modelo, seeder)"
```

---

# FASE 2 — Contratos (datos)

## Task 2: Tablas y modelo de contratos

**Files:**
- Create: `database/migrations/2026_08_18_100100_create_contratos_table.php`
- Create: `database/migrations/2026_08_18_100200_create_contrato_municipio_table.php`
- Create: `app/Models/Contrato.php`
- Test: `tests/Feature/ContratosParametrosTest.php`

- [ ] **Step 1: Migración de contratos**

Crear `database/migrations/2026_08_18_100100_create_contratos_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contratos', function (Blueprint $table) {
            $table->id();
            $table->string('descripcion');
            $table->text('objeto');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contratos');
    }
};
```

- [ ] **Step 2: Migración de la pivote contrato–municipio**

Crear `database/migrations/2026_08_18_100200_create_contrato_municipio_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contrato_municipio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('contrato_id')->constrained('contratos')->cascadeOnDelete();
            $table->foreignId('municipio_id')->constrained('municipios');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contrato_municipio');
    }
};
```

- [ ] **Step 3: Modelo `Contrato`**

Crear `app/Models/Contrato.php`:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Contrato extends Model
{
    protected $table = 'contratos';
    protected $fillable = ['descripcion', 'objeto'];

    public function municipios()
    {
        return $this->belongsToMany(Municipio::class, 'contrato_municipio', 'contrato_id', 'municipio_id')
            ->withTimestamps();
    }
}
```

- [ ] **Step 4: Escribir el test del modelo**

Crear `tests/Feature/ContratosParametrosTest.php`:

```php
<?php
namespace Tests\Feature;

use App\Models\{Contrato, Municipio};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContratosParametrosTest extends TestCase
{
    use RefreshDatabase;

    public function test_un_contrato_tiene_varios_municipios(): void
    {
        $this->seed();
        $ids = Municipio::take(2)->pluck('id')->all();
        $c = Contrato::create(['descripcion' => 'C-001', 'objeto' => 'Mantenimiento vial']);
        $c->municipios()->sync($ids);

        $this->assertEquals(2, $c->fresh()->municipios()->count());
        $this->assertEqualsCanonicalizing($ids, $c->fresh()->municipios->pluck('id')->all());
    }
}
```

- [ ] **Step 5: Ejecutar**

Run: `php artisan test --filter=ContratosParametrosTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_18_100100_create_contratos_table.php database/migrations/2026_08_18_100200_create_contrato_municipio_table.php app/Models/Contrato.php tests/Feature/ContratosParametrosTest.php
git commit -m "feat(contratos): tabla de contratos con municipios (pivote)"
```

---

# FASE 3 — Comisión ↔ municipios (backend)

## Task 3: Pivote comisión–municipio, relación y validación

**Files:**
- Create: `database/migrations/2026_08_18_100300_create_comision_municipio_table.php`
- Modify: `app/Models/SolicitudViaticos.php`
- Modify: `app/Http/Requests/GuardarSolicitudViaticosRequest.php`
- Test: `tests/Feature/MunicipiosComisionTest.php`

- [ ] **Step 1: Migración de la pivote comisión–municipio**

Crear `database/migrations/2026_08_18_100300_create_comision_municipio_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('comision_municipio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_viaticos_id')->constrained('solicitudes_viaticos')->cascadeOnDelete();
            $table->foreignId('municipio_id')->constrained('municipios');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comision_municipio');
    }
};
```

- [ ] **Step 2: Relación en `SolicitudViaticos`**

En `app/Models/SolicitudViaticos.php`, añadir tras la relación `viajeros()`:

```php
    public function municipios()
    {
        return $this->belongsToMany(Municipio::class, 'comision_municipio', 'solicitud_viaticos_id', 'municipio_id')
            ->withTimestamps();
    }
```

- [ ] **Step 3: Validación en el request**

En `app/Http/Requests/GuardarSolicitudViaticosRequest.php`, en `rules()` reemplazar
`'municipio_destino' => 'required|string|max:255',` por:

```php
            'municipios'                  => 'required|array|min:1',
            'municipios.*'                => 'exists:municipios,id',
```

Y en `messages()` añadir:

```php
            'municipios.required'             => 'Seleccione al menos un municipio.',
            'municipios.min'                  => 'Seleccione al menos un municipio.',
            'municipios.*.exists'             => 'Uno de los municipios seleccionados no es válido.',
```

- [ ] **Step 4: Escribir el test de sincronización**

Añadir al final de la clase `MunicipiosComisionTest` (antes del `}` de cierre):

```php
    public function test_una_comision_sincroniza_varios_municipios(): void
    {
        $this->seed();
        $cab = \App\Models\SolicitudViaticos::create([
            'nombre_comision' => 'C', 'municipio_destino' => '', 'observacion' => 'x',
        ]);
        $ids = \App\Models\Municipio::take(3)->pluck('id')->all();
        $cab->municipios()->sync($ids);

        $this->assertEquals(3, $cab->fresh()->municipios()->count());
        $this->assertEqualsCanonicalizing($ids, $cab->fresh()->municipios->pluck('id')->all());
    }
```

- [ ] **Step 5: Ejecutar**

Run: `php artisan test --filter=MunicipiosComisionTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add database/migrations/2026_08_18_100300_create_comision_municipio_table.php app/Models/SolicitudViaticos.php app/Http/Requests/GuardarSolicitudViaticosRequest.php tests/Feature/MunicipiosComisionTest.php
git commit -m "feat(viaticos): relacion comision-municipios y validacion"
```

---

## Task 4: Controlador de viáticos — props y sync de municipios

**Files:**
- Modify: `app/Http/Controllers/ViaticosController.php`
- Modify: `app/Http/Controllers/SolicitudController.php`
- Test: `tests/Feature/MunicipiosComisionTest.php`

- [ ] **Step 1: Pasar `municipios` en create/edit y cargar la relación en edit**

En `app/Http/Controllers/ViaticosController.php`:

Añadir `Municipio` al `use` de modelos (buscar la línea `use App\Models\{...};` e incluir `Municipio`).

En `create()`, añadir el prop `municipios` al `Inertia::render('Viaticos/Crear', [...])`:

```php
            'municipios' => \App\Models\Municipio::orderBy('nombre')->get(['id','nombre']),
```

En `edit()`, cargar `solicitable.municipios` y pasar `municipios`. La línea de `load` actual es
`$solicitud->load(['solicitable.viajeros.empleado']);` → cambiar a
`$solicitud->load(['solicitable.viajeros.empleado', 'solicitable.municipios']);`, y añadir al render:

```php
            'municipios' => \App\Models\Municipio::orderBy('nombre')->get(['id','nombre']),
```

- [ ] **Step 2: Sync en store()**

En `store()`, la cabecera se crea con `'municipio_destino' => $request->municipio_destino`. Cambiarlo a `''`
(ya no viene del formulario) y sincronizar municipios tras crear la cabecera:

```php
            $cabecera = SolicitudViaticos::create([
                'nombre_comision'   => $request->nombre_comision,
                'municipio_destino' => '',
                'observacion'       => $request->observacion,
            ]);
            $cabecera->municipios()->sync($request->municipios);
```

- [ ] **Step 3: Sync en update()**

En `update()`, en el `$cabecera->update([...])` cambiar `'municipio_destino' => $request->municipio_destino` a
`'municipio_destino' => ''` y añadir el sync tras el update:

```php
            $cabecera->update([
                'nombre_comision'   => $request->nombre_comision,
                'municipio_destino' => '',
                'observacion'       => $request->observacion,
            ]);
            $cabecera->municipios()->sync($request->municipios);
```

- [ ] **Step 4: Eager load en `SolicitudController::show`**

En `app/Http/Controllers/SolicitudController.php`, en el `morphWith`, la línea de viáticos es
`SolicitudViaticos::class => ['viajeros.empleado', 'viajeros.asignaciones'],` → añadir `'municipios'`:

```php
                SolicitudViaticos::class => ['viajeros.empleado', 'viajeros.asignaciones', 'municipios'],
```

- [ ] **Step 5: Escribir el test HTTP de creación con municipios**

Añadir al final de `MunicipiosComisionTest`:

```php
    public function test_crear_comision_via_http_guarda_municipios(): void
    {
        $this->seed();
        $lider = \App\Models\Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();
        $emp   = \App\Models\Empleados::first();
        $muni  = \App\Models\Municipio::take(2)->pluck('id')->all();

        $this->actingAs($lider)->post(route('viaticos.store'), [
            'nombre_comision' => 'Comisión técnica',
            'municipios'      => $muni,
            'observacion'     => 'x',
            'viajeros'        => [[
                'empleado_id' => $emp->id, 'motivo' => 'Visita',
                'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
                'fecha_regreso' => '2026-08-22', 'hora_regreso' => '17:00',
            ]],
        ])->assertRedirect();

        $cab = \App\Models\SolicitudViaticos::latest('id')->first();
        $this->assertEqualsCanonicalizing($muni, $cab->municipios->pluck('id')->all());
    }

    public function test_comision_sin_municipios_es_invalida(): void
    {
        $this->seed();
        $lider = \App\Models\Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();
        $emp   = \App\Models\Empleados::first();

        $this->actingAs($lider)
            ->from(route('viaticos.crear'))
            ->post(route('viaticos.store'), [
                'nombre_comision' => 'Comisión técnica',
                'observacion'     => 'x',
                'viajeros'        => [[
                    'empleado_id' => $emp->id, 'motivo' => 'Visita',
                    'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
                    'fecha_regreso' => '2026-08-22', 'hora_regreso' => '17:00',
                ]],
            ])->assertSessionHasErrors('municipios');
    }
```

- [ ] **Step 6: Ejecutar**

Run: `php artisan test --filter=MunicipiosComisionTest`
Expected: PASS (todos).

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/ViaticosController.php app/Http/Controllers/SolicitudController.php tests/Feature/MunicipiosComisionTest.php
git commit -m "feat(viaticos): sincronizar municipios en crear/editar comision"
```

---

# FASE 4 — UI comisión

## Task 5: Multiselect de municipios en `Viaticos/Crear.jsx`

**Files:**
- Modify: `resources/js/Pages/Viaticos/Crear.jsx`

- [ ] **Step 1: Recibir `municipios` y ajustar el estado del formulario**

En `resources/js/Pages/Viaticos/Crear.jsx`, en la firma del componente (donde recibe `empleados`, `solicitud`,
`editar`), añadir `municipios = []`. En el `useForm`, reemplazar `municipio_destino: solicitable?.municipio_destino ?? ''`
por:

```jsx
        municipios: solicitable?.municipios?.map((m) => m.id) ?? [],
```

- [ ] **Step 2: Reemplazar el input por un multiselect**

Reemplazar el `<Field label="Municipio destino" ...>` con su `<Input>` (el bloque de municipio_destino) por un
multiselect de checkboxes:

```jsx
                            <Field label="Municipios destino" error={errors.municipios}>
                                <div className="border border-slate-300 rounded-lg p-3 max-h-40 overflow-y-auto space-y-1">
                                    {municipios.length === 0 && (
                                        <p className="text-xs text-slate-400">No hay municipios registrados.</p>
                                    )}
                                    {municipios.map((m) => (
                                        <label key={m.id} className="flex items-center gap-2 text-sm text-slate-700">
                                            <input
                                                type="checkbox"
                                                className="rounded border-slate-300 text-indigo-600"
                                                checked={data.municipios.includes(m.id)}
                                                onChange={(ev) => {
                                                    const next = ev.target.checked
                                                        ? [...data.municipios, m.id]
                                                        : data.municipios.filter((id) => id !== m.id);
                                                    setData('municipios', next);
                                                }}
                                            />
                                            {m.nombre}
                                        </label>
                                    ))}
                                </div>
                                {errors.municipios && <p className="text-red-500 text-xs mt-1">{errors.municipios}</p>}
                            </Field>
```

> Nota: `Field` ya muestra el `error`; el `<p>` extra es defensivo por si `Field` no lo renderiza dentro del
> multiselect. Si genera doble mensaje, quitar el `<p>` interno.

- [ ] **Step 3: Compilar**

Run: `npm run build`
Expected: build sin errores.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Viaticos/Crear.jsx
git commit -m "feat(viaticos): multiselect de municipios en el formulario de comision"
```

---

## Task 6: Mostrar municipios en el detalle (`DetalleViaticos`)

**Files:**
- Modify: `resources/js/Pages/Solicitudes/Detalle.jsx`

- [ ] **Step 1: Reemplazar "Municipio destino" por la lista de municipios**

En `resources/js/Pages/Solicitudes/Detalle.jsx`, en `DetalleViaticos`, la línea
`<Campo label="Municipio destino" valor={solicitable.municipio_destino} />` se reemplaza por una que muestra la
lista de municipios de la relación (con fallback al texto histórico):

```jsx
                <Campo
                    label="Municipios destino"
                    valor={
                        solicitable.municipios?.length > 0
                            ? solicitable.municipios.map((m) => m.nombre).join(', ')
                            : (solicitable.municipio_destino || '—')
                    }
                />
```

- [ ] **Step 2: Compilar**

Run: `npm run build`
Expected: build sin errores.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Solicitudes/Detalle.jsx
git commit -m "feat(viaticos): mostrar la lista de municipios en el detalle"
```

---

# FASE 5 — Contratos (Parámetros)

## Task 7: CRUD de contratos en `ParametrosController` + rutas

**Files:**
- Modify: `app/Http/Controllers/ParametrosController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/ContratosParametrosTest.php`

- [ ] **Step 1: Props de contratos y municipios en `index()`**

En `app/Http/Controllers/ParametrosController.php`, añadir al `use` de modelos `Contrato` y `Municipio`
(la línea `use App\Models\{...};`). En `index()`, añadir al array de `Inertia::render`:

```php
            'contratos'  => \App\Models\Contrato::with('municipios:id,nombre')->orderBy('descripcion')->get(),
            'municipios' => \App\Models\Municipio::orderBy('nombre')->get(['id','nombre']),
```

- [ ] **Step 2: Métodos store/update/destroy de contrato**

Añadir a `ParametrosController` (tras los métodos de empleado):

```php
    public function storeContrato(Request $request)
    {
        $data = $request->validate([
            'descripcion' => 'required|string|max:255',
            'objeto'      => 'required|string|max:2000',
            'municipios'  => 'required|array|min:1',
            'municipios.*'=> 'exists:municipios,id',
        ]);
        $contrato = \App\Models\Contrato::create(['descripcion' => $data['descripcion'], 'objeto' => $data['objeto']]);
        $contrato->municipios()->sync($data['municipios']);
        return back()->with('success', 'Contrato creado.');
    }

    public function updateContrato(Request $request, \App\Models\Contrato $contrato)
    {
        $data = $request->validate([
            'descripcion' => 'required|string|max:255',
            'objeto'      => 'required|string|max:2000',
            'municipios'  => 'required|array|min:1',
            'municipios.*'=> 'exists:municipios,id',
        ]);
        $contrato->update(['descripcion' => $data['descripcion'], 'objeto' => $data['objeto']]);
        $contrato->municipios()->sync($data['municipios']);
        return back()->with('success', 'Contrato actualizado.');
    }

    public function destroyContrato(\App\Models\Contrato $contrato)
    {
        // Nota (Bloque B): cuando exista la relacion viajero-contrato, abortar si tiene viajeros.
        $contrato->delete();
        return back()->with('success', 'Contrato eliminado.');
    }
```

- [ ] **Step 3: Registrar las rutas**

En `routes/web.php`, tras las rutas `parametros.empleados.*`, añadir:

```php
    Route::post('/parametros/contratos',                [ParametrosController::class, 'storeContrato'])->name('parametros.contratos.store');
    Route::put('/parametros/contratos/{contrato}',      [ParametrosController::class, 'updateContrato'])->name('parametros.contratos.update');
    Route::delete('/parametros/contratos/{contrato}',   [ParametrosController::class, 'destroyContrato'])->name('parametros.contratos.destroy');
```

- [ ] **Step 4: Escribir los tests HTTP del CRUD**

Añadir al final de `ContratosParametrosTest`:

```php
    public function test_crear_contrato_con_municipios_desde_parametros(): void
    {
        $this->seed();
        $admin = \App\Models\Usuario::where('email', 'admin@demo.test')->firstOrFail();
        $muni  = \App\Models\Municipio::take(2)->pluck('id')->all();

        $this->actingAs($admin)->post(route('parametros.contratos.store'), [
            'descripcion' => 'Contrato 2026-01', 'objeto' => 'Suministro de insumos',
            'municipios'  => $muni,
        ])->assertRedirect();

        $c = \App\Models\Contrato::latest('id')->first();
        $this->assertEquals('Contrato 2026-01', $c->descripcion);
        $this->assertEqualsCanonicalizing($muni, $c->municipios->pluck('id')->all());
    }

    public function test_editar_contrato_resincroniza_municipios(): void
    {
        $this->seed();
        $admin = \App\Models\Usuario::where('email', 'admin@demo.test')->firstOrFail();
        $todos = \App\Models\Municipio::take(3)->pluck('id')->all();
        $c = Contrato::create(['descripcion' => 'X', 'objeto' => 'Y']);
        $c->municipios()->sync([$todos[0]]);

        $this->actingAs($admin)->put(route('parametros.contratos.update', $c), [
            'descripcion' => 'X editado', 'objeto' => 'Y',
            'municipios'  => [$todos[1], $todos[2]],
        ])->assertRedirect();

        $this->assertEquals('X editado', $c->fresh()->descripcion);
        $this->assertEqualsCanonicalizing([$todos[1], $todos[2]], $c->fresh()->municipios->pluck('id')->all());
    }

    public function test_contrato_sin_municipios_es_rechazado(): void
    {
        $this->seed();
        $admin = \App\Models\Usuario::where('email', 'admin@demo.test')->firstOrFail();

        $this->actingAs($admin)
            ->from(route('parametros.index'))
            ->post(route('parametros.contratos.store'), [
                'descripcion' => 'Sin municipios', 'objeto' => 'Z',
            ])->assertSessionHasErrors('municipios');
    }

    public function test_eliminar_contrato(): void
    {
        $this->seed();
        $admin = \App\Models\Usuario::where('email', 'admin@demo.test')->firstOrFail();
        $c = Contrato::create(['descripcion' => 'Borrar', 'objeto' => 'Z']);

        $this->actingAs($admin)->delete(route('parametros.contratos.destroy', $c))->assertRedirect();
        $this->assertDatabaseMissing('contratos', ['id' => $c->id]);
    }
```

> Nota: las rutas `parametros.*` están bajo `['auth','verified']` sin rol adicional; `admin@demo.test` accede.
> Añadir `use App\Models\{Contrato, Municipio, Usuario, Empleados};` al `use` del test si falta alguno.

- [ ] **Step 5: Ejecutar**

Run: `php artisan test --filter=ContratosParametrosTest`
Expected: PASS (todos).

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ParametrosController.php routes/web.php tests/Feature/ContratosParametrosTest.php
git commit -m "feat(contratos): CRUD de contratos en parametros"
```

---

## Task 8: Tab "Contratos" en `Parametros/Index.jsx`

**Files:**
- Modify: `resources/js/Pages/Parametros/Index.jsx`

- [ ] **Step 1: Recibir props y añadir el tab**

En `resources/js/Pages/Parametros/Index.jsx`, en la firma del componente por defecto añadir `contratos = []` y
`municipios = []`. En el arreglo `TABS`, añadir la entrada:

```jsx
const TABS = [
    { id: 'tarifas',   label: 'Tarifas de viáticos' },
    { id: 'empleados', label: 'Empleados' },
    { id: 'contratos', label: 'Contratos' },
];
```

Y en el render de tabs, tras `{tab === 'empleados' && ...}`, añadir:

```jsx
                {tab === 'contratos' && <TabContratos contratos={contratos} municipios={municipios} />}
```

- [ ] **Step 2: Componente `TabContratos`**

Añadir el componente `TabContratos` (sigue el patrón de `TabEmpleados`: panel crear/editar + tabla). Colocarlo
antes del `export default function Index`:

```jsx
const CONTRATO_VACIO = { descripcion: '', objeto: '', municipios: [] };

function TabContratos({ contratos, municipios }) {
    const [panel, setPanel] = useState(null); // null | { tipo, id }
    const [confirmarId, setConfirmarId] = useState(null);
    const { data, setData, post, put, reset, processing, errors, clearErrors } = useForm(CONTRATO_VACIO);

    const abrirCrear = () => { reset(); clearErrors(); setPanel({ tipo: 'crear', id: null }); };
    const abrirEditar = (c) => {
        setData({ descripcion: c.descripcion, objeto: c.objeto, municipios: c.municipios.map((m) => m.id) });
        clearErrors();
        setPanel({ tipo: 'editar', id: c.id });
    };
    const cancelar = () => { setPanel(null); clearErrors(); };

    const submit = (e) => {
        e.preventDefault();
        if (panel.tipo === 'crear') post(route('parametros.contratos.store'), { onSuccess: () => setPanel(null) });
        else put(route('parametros.contratos.update', panel.id), { onSuccess: () => setPanel(null) });
    };
    const eliminar = (c) => {
        if (confirmarId !== c.id) { setConfirmarId(c.id); return; }
        router.delete(route('parametros.contratos.destroy', c.id), { onSuccess: () => setConfirmarId(null) });
    };
    const toggleMunicipio = (id, checked) => {
        setData('municipios', checked ? [...data.municipios, id] : data.municipios.filter((x) => x !== id));
    };

    return (
        <div className="space-y-4">
            <div className="flex justify-end">
                <button type="button" onClick={abrirCrear}
                    className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors">
                    Nuevo contrato
                </button>
            </div>

            {panel && (
                <div className="bg-white rounded-xl border border-indigo-200 p-5">
                    <p className="text-xs font-semibold uppercase tracking-wide text-indigo-500 mb-4">
                        {panel.tipo === 'crear' ? 'Nuevo contrato' : 'Editar contrato'}
                    </p>
                    <form onSubmit={submit} className="space-y-4">
                        <Field label="Descripción" error={errors.descripcion}>
                            <Input value={data.descripcion} onChange={(e) => setData('descripcion', e.target.value)} error={errors.descripcion} />
                        </Field>
                        <Field label="Objeto del contrato" error={errors.objeto}>
                            <textarea value={data.objeto} onChange={(e) => setData('objeto', e.target.value)} rows={3}
                                className="w-full rounded-lg border border-slate-300 text-sm py-2 px-3 focus:ring-2 focus:ring-indigo-500 outline-none" />
                            {errors.objeto && <p className="text-red-500 text-xs mt-1">{errors.objeto}</p>}
                        </Field>
                        <div>
                            <label className="block text-sm font-medium text-slate-700 mb-1">Municipios</label>
                            <div className="border border-slate-300 rounded-lg p-3 max-h-40 overflow-y-auto space-y-1">
                                {municipios.map((m) => (
                                    <label key={m.id} className="flex items-center gap-2 text-sm text-slate-700">
                                        <input type="checkbox" className="rounded border-slate-300 text-indigo-600"
                                            checked={data.municipios.includes(m.id)}
                                            onChange={(e) => toggleMunicipio(m.id, e.target.checked)} />
                                        {m.nombre}
                                    </label>
                                ))}
                            </div>
                            {errors.municipios && <p className="text-red-500 text-xs mt-1">{errors.municipios}</p>}
                        </div>
                        <div className="flex justify-end gap-3 pt-1">
                            <button type="button" onClick={cancelar}
                                className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-slate-600 bg-white border border-slate-300 rounded-lg hover:bg-slate-50">
                                Cancelar
                            </button>
                            <button type="submit" disabled={processing}
                                className="inline-flex items-center gap-2 px-4 py-2 text-sm font-medium text-white bg-green-600 hover:bg-green-700 rounded-lg disabled:opacity-50">
                                {processing ? 'Guardando…' : panel.tipo === 'crear' ? 'Crear contrato' : 'Guardar cambios'}
                            </button>
                        </div>
                    </form>
                </div>
            )}

            <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
                {contratos.length === 0 ? (
                    <p className="text-sm text-slate-400 text-center py-10">No hay contratos registrados.</p>
                ) : (
                    <table className="w-full text-sm">
                        <thead className="bg-slate-50 border-b border-slate-100">
                            <tr>
                                <th className="text-left text-xs font-semibold text-slate-500 px-4 py-3">Descripción</th>
                                <th className="text-left text-xs font-semibold text-slate-500 px-4 py-3">Objeto</th>
                                <th className="text-left text-xs font-semibold text-slate-500 px-4 py-3">Municipios</th>
                                <th className="px-4 py-3 w-24"></th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-50">
                            {contratos.map((c) => (
                                <tr key={c.id} className="hover:bg-slate-50/50">
                                    <td className="px-4 py-3 text-slate-700">{c.descripcion}</td>
                                    <td className="px-4 py-3 text-slate-500">{c.objeto}</td>
                                    <td className="px-4 py-3 text-slate-500">{c.municipios.map((m) => m.nombre).join(', ') || '—'}</td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center justify-end gap-1">
                                            <button type="button" onClick={() => abrirEditar(c)}
                                                className="p-1.5 rounded-lg text-slate-400 hover:text-indigo-600 hover:bg-indigo-50" title="Editar">✎</button>
                                            <button type="button" onClick={() => eliminar(c)}
                                                className="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50" title="Eliminar">
                                                {confirmarId === c.id ? '¿Confirmar?' : '🗑'}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </div>
        </div>
    );
}
```

> Verificar que `useForm`, `router`, `Field`, `Input` estén importados/definidos en el archivo (ya se usan en
> `TabEmpleados`/`TabTarifas`). Si `Field`/`Input` son componentes locales del archivo, reutilizarlos.

- [ ] **Step 3: Compilar**

Run: `npm run build`
Expected: build sin errores.

- [ ] **Step 4: Commit**

```bash
git add resources/js/Pages/Parametros/Index.jsx
git commit -m "feat(contratos): tab de gestion de contratos en parametros"
```

---

# FASE 6 — Verificación final

## Task 9: Suite completa, build y migrate

- [ ] **Step 1: Ejecutar toda la suite**

Run: `php artisan test`
Expected: todos verdes (MunicipiosComisionTest, ContratosParametrosTest, sin regresiones en viáticos).

> Verificado al escribir el plan: TODOS los tests de viáticos existentes (`ComisionesRrhhTest`,
> `ConteosPestanasSolicitudesTest`, `MotorWorkflowViaticosTest`, `NotificacionRrhhViaticosTest`,
> `PendientesLiderContadorTest`, `LiquidacionPdfTest`, `VerRubrosDetalleTest`, `ValorEnListaSolicitudesTest`,
> `EditarLiquidacionTest`) crean la comisión vía `SolicitudViaticos::create` (modelo directo), **no** vía POST a
> `viaticos.store`. Por eso NO pasan por `GuardarSolicitudViaticosRequest` y el cambio de validación
> (`municipio_destino` → `municipios`) NO los rompe; siguen pasando `'municipio_destino' => '...'` al crear la
> cabecera, que es válido (la columna se conserva). Ningún test existente requiere cambios.

- [ ] **Step 2: Build de producción**

Run: `npm run build`
Expected: build sin errores.

- [ ] **Step 3: Aplicar migraciones y sembrar municipios en desarrollo**

Run: `php artisan migrate`
Expected: corren las 4 migraciones nuevas.

Run: `php artisan db:seed --class=MunicipiosSeeder`
Expected: siembra el catálogo de municipios.

- [ ] **Step 4: Verificar árbol limpio**

Run: `git status --short`
Expected: sin cambios pendientes (los assets de `public/build` no se versionan).

---

## Cobertura del spec (checklist de auto-revisión)

- Catálogo de municipios (tabla+seeder+modelo): Task 1. ✔
- Tabla contratos + pivote contrato-municipio + modelo: Task 2. ✔
- Pivote comisión-municipio + relación + validación: Task 3. ✔
- Sync de municipios en crear/editar comisión + eager load: Task 4. ✔
- Multiselect de municipios en el formulario: Task 5. ✔
- Detalle muestra la lista de municipios: Task 6. ✔
- CRUD de contratos en Parámetros (backend + rutas): Task 7. ✔
- Tab "Contratos" en la UI de Parámetros: Task 8. ✔
- Nota "bloquear borrado si tiene viajeros" diferida al Bloque B: Task 7 Step 2 (comentario). ✔
