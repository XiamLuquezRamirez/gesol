# Otros pagos en solicitudes de oficina — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: subagent-driven-development. Steps use checkbox (`- [ ]`) syntax.

**Goal:** Permitir registrar "otros pagos" (gasolina, lavado de carro, peaje, etc.) mezclados con los elementos normales dentro de una misma solicitud de oficina (OFI), usando un catálogo configurable de conceptos de pago.

**Architecture:** Un ítem de oficina (`items_oficina`) gana una FK nullable `concepto_pago_id` hacia una nueva tabla `conceptos_pago` (administrada en Parámetros, patrón `tarifas_viaticos`). `concepto_pago_id === null` ⇒ elemento de oficina normal (comportamiento actual, intacto). Con valor ⇒ es un pago/gasto de ese concepto. El total, abonos, aprobación, notificaciones y motor de workflow no cambian: siguen sumando `subtotal`.

**Tech Stack:** Laravel 10.50, Inertia/React 18, MariaDB, spatie/permission. PHP CLI: `/c/xampp/php/php.exe`. Tests: PHPUnit + SQLite :memory:.

**Convenciones:** Identificadores ASCII sin tildes; UI/comentarios con ortografía correcta. Modelo `Usuario`/tabla `usuarios`.

---

## File Structure

- `database/migrations/2026_09_22_120000_create_conceptos_pago_table.php` — nueva tabla
- `database/migrations/2026_09_22_120100_add_concepto_pago_id_to_items_oficina_table.php` — FK nullable
- `app/Models/ConceptoPago.php` — modelo (patrón `TarifaViatico`)
- `app/Models/ItemOficina.php` — relación `conceptoPago()`, fillable
- `database/seeders/ConceptoPagoSeeder.php` + registro en `DatabaseSeeder`
- `app/Http/Controllers/ParametrosController.php` — CRUD conceptos + prop `conceptosPago`
- `routes/web.php` — 3 rutas `parametros.conceptos.*`
- `resources/js/Pages/Parametros/Index.jsx` — nueva pestaña "Conceptos de pago"
- `app/Http/Requests/GuardarSolicitudOficinaRequest.php` — validación `concepto_pago_id`
- `app/Http/Controllers/OficinaController.php` — `normalizarItem` propaga `concepto_pago_id`; `create`/`edit` pasan `conceptosPago`
- `resources/js/Pages/Oficina/Crear.jsx` — selector "tipo de línea" por ítem
- `resources/js/Pages/Solicitudes/Detalle.jsx` — columna Concepto en tabla de items OFI

---

### Task 1: Tabla, modelo y seeder de ConceptoPago

**Files:**
- Create: `database/migrations/2026_09_22_120000_create_conceptos_pago_table.php`
- Create: `app/Models/ConceptoPago.php`
- Create: `database/seeders/ConceptoPagoSeeder.php`
- Modify: `database/seeders/DatabaseSeeder.php`
- Test: `tests/Feature/ConceptoPagoModeloTest.php`

- [ ] **Step 1: Escribir test que falla**

```php
<?php
namespace Tests\Feature;

use App\Models\ConceptoPago;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConceptoPagoModeloTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_crea_conceptos_base(): void
    {
        $this->seed(\Database\Seeders\ConceptoPagoSeeder::class);
        $this->assertDatabaseHas('conceptos_pago', ['nombre' => 'Gasolina']);
        $this->assertDatabaseHas('conceptos_pago', ['nombre' => 'Lavado de carro']);
        $this->assertTrue(ConceptoPago::where('nombre', 'Gasolina')->first()->activo);
    }

    public function test_nombre_es_unico(): void
    {
        ConceptoPago::create(['nombre' => 'Peaje']);
        $this->expectException(\Illuminate\Database\QueryException::class);
        ConceptoPago::create(['nombre' => 'Peaje']);
    }
}
```

- [ ] **Step 2: Correr test y verlo fallar**

Run: `/c/xampp/php/php.exe artisan test --filter=ConceptoPagoModeloTest`
Expected: FAIL (tabla/modelo no existen).

- [ ] **Step 3: Migración**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conceptos_pago', function (Blueprint $table) {
            $table->id();
            $table->string('nombre')->unique();
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conceptos_pago');
    }
};
```

- [ ] **Step 4: Modelo**

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ConceptoPago extends Model
{
    protected $table = 'conceptos_pago';
    protected $fillable = ['nombre', 'activo'];
    protected $casts = ['activo' => 'boolean'];
}
```

- [ ] **Step 5: Seeder + registro**

```php
<?php
namespace Database\Seeders;
use App\Models\ConceptoPago;
use Illuminate\Database\Seeder;

class ConceptoPagoSeeder extends Seeder
{
    public function run(): void
    {
        foreach (['Gasolina', 'Lavado de carro', 'Peaje', 'Parqueadero'] as $nombre) {
            ConceptoPago::firstOrCreate(['nombre' => $nombre], ['activo' => true]);
        }
    }
}
```

En `DatabaseSeeder::run()` añadir `$this->call(ConceptoPagoSeeder::class);` junto a los demás `call`.

- [ ] **Step 6: Correr test y verlo pasar**

Run: `/c/xampp/php/php.exe artisan test --filter=ConceptoPagoModeloTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add database/migrations app/Models/ConceptoPago.php database/seeders tests/Feature/ConceptoPagoModeloTest.php
git commit -m "feat(oficina): tabla, modelo y seeder de ConceptoPago"
```

---

### Task 2: CRUD de conceptos en Parámetros

**Files:**
- Modify: `app/Http/Controllers/ParametrosController.php`
- Modify: `routes/web.php`
- Modify: `resources/js/Pages/Parametros/Index.jsx`
- Test: `tests/Feature/ConceptoPagoParametrosTest.php`

- [ ] **Step 1: Test que falla**

```php
<?php
namespace Tests\Feature;

use App\Models\{ConceptoPago, Usuario};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ConceptoPagoParametrosTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): Usuario
    {
        $this->seed();
        return Usuario::where('email', 'admin@demo.test')->firstOrFail();
    }

    public function test_crear_concepto(): void
    {
        $this->actingAs($this->admin())
            ->post(route('parametros.conceptos.store'), ['nombre' => 'Grúa'])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('conceptos_pago', ['nombre' => 'Grúa']);
    }

    public function test_actualizar_concepto(): void
    {
        $c = ConceptoPago::create(['nombre' => 'Taxi']);
        $this->actingAs($this->admin())
            ->put(route('parametros.conceptos.update', $c), ['nombre' => 'Taxi urbano', 'activo' => false])
            ->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseHas('conceptos_pago', ['id' => $c->id, 'nombre' => 'Taxi urbano', 'activo' => false]);
    }

    public function test_eliminar_concepto(): void
    {
        $c = ConceptoPago::create(['nombre' => 'Otro']);
        $this->actingAs($this->admin())
            ->delete(route('parametros.conceptos.destroy', $c))->assertRedirect();
        $this->assertDatabaseMissing('conceptos_pago', ['id' => $c->id]);
    }

    public function test_nombre_unico_en_crear(): void
    {
        ConceptoPago::create(['nombre' => 'Gasolina']);
        $this->actingAs($this->admin())
            ->post(route('parametros.conceptos.store'), ['nombre' => 'Gasolina'])
            ->assertSessionHasErrors('nombre');
    }
}
```

- [ ] **Step 2: Correr y ver fallar**

Run: `/c/xampp/php/php.exe artisan test --filter=ConceptoPagoParametrosTest`
Expected: FAIL (rutas no existen).

- [ ] **Step 3: Rutas** (en `routes/web.php`, junto a las de `parametros.contratos.*`)

```php
Route::post('/parametros/conceptos',              [ParametrosController::class, 'storeConcepto'])->name('parametros.conceptos.store');
Route::put('/parametros/conceptos/{concepto}',    [ParametrosController::class, 'updateConcepto'])->name('parametros.conceptos.update');
Route::delete('/parametros/conceptos/{concepto}', [ParametrosController::class, 'destroyConcepto'])->name('parametros.conceptos.destroy');
```

- [ ] **Step 4: Controller** — añadir `ConceptoPago` al `use`, agregar `'conceptosPago' => ConceptoPago::orderBy('nombre')->get(['id','nombre','activo']),` en `index()`, y los 3 métodos:

```php
public function storeConcepto(Request $request)
{
    $data = $request->validate([
        'nombre' => 'required|string|max:100|unique:conceptos_pago,nombre',
        'activo' => 'boolean',
    ]);
    \App\Models\ConceptoPago::create($data);
    return back()->with('success', 'Concepto de pago creado.');
}

public function updateConcepto(Request $request, \App\Models\ConceptoPago $concepto)
{
    $data = $request->validate([
        'nombre' => 'required|string|max:100|unique:conceptos_pago,nombre,'.$concepto->id,
        'activo' => 'boolean',
    ]);
    $concepto->update($data);
    return back()->with('success', 'Concepto de pago actualizado.');
}

public function destroyConcepto(\App\Models\ConceptoPago $concepto)
{
    $concepto->delete();
    return back()->with('success', 'Concepto de pago eliminado.');
}
```

Nota: el binding `{concepto}` mapea a `ConceptoPago $concepto` por nombre de variable.

- [ ] **Step 5: Frontend** — en `Parametros/Index.jsx`: añadir componente `TabConceptos` siguiendo el patrón EXACTO de `TabTarifas` (panel crear/editar + tabla + confirmación de borrado). Campos: `nombre` (Input) y `activo` (checkbox, default true). Usar rutas `parametros.conceptos.*`. Agregar `{ id: 'conceptos', label: 'Conceptos de pago' }` a `TABS`, aceptar `conceptosPago = []` en las props de `Index`, y renderizar `{tab === 'conceptos' && <TabConceptos conceptos={conceptosPago} />}`.

`const CONCEPTO_VACIO = { nombre: '', activo: true };`

- [ ] **Step 6: Build + test**

Run: `npm run build` (o `npx vite build`), luego
`/c/xampp/php/php.exe artisan test --filter=ConceptoPagoParametrosTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/ParametrosController.php routes/web.php resources/js/Pages/Parametros/Index.jsx tests/Feature/ConceptoPagoParametrosTest.php public/build
git commit -m "feat(oficina): CRUD de conceptos de pago en Parametros"
```

---

### Task 3: Ítem de oficina soporta concepto_pago_id (backend)

**Files:**
- Create: `database/migrations/2026_09_22_120100_add_concepto_pago_id_to_items_oficina_table.php`
- Modify: `app/Models/ItemOficina.php`
- Modify: `app/Http/Requests/GuardarSolicitudOficinaRequest.php`
- Modify: `app/Http/Controllers/OficinaController.php`
- Test: `tests/Feature/OficinaConceptoPagoTest.php`

- [ ] **Step 1: Test que falla**

```php
<?php
namespace Tests\Feature;

use App\Models\{Area, ConceptoPago, ItemOficina, Solicitud, Usuario};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OficinaConceptoPagoTest extends TestCase
{
    use RefreshDatabase;

    public function test_solicitud_mezcla_elemento_y_pago(): void
    {
        $this->seed();
        $lider = Usuario::where('email', 'lider@demo.test')->firstOrFail();
        $areaGeneral = Area::where('es_general', true)->firstOrFail();
        $gasolina = ConceptoPago::create(['nombre' => 'Gasolina']);

        $this->actingAs($lider)->post(route('oficina.store'), [
            'area_id'       => $areaGeneral->id,
            'urgencia'      => 'media',
            'justificacion' => 'Mixta',
            'items' => [
                ['nombre' => 'Resma', 'categoria' => 'producto', 'cantidad' => 2, 'costo_estimado' => 10000, 'notas' => '', 'concepto_pago_id' => null],
                ['nombre' => 'Tanqueo', 'categoria' => 'servicio', 'cantidad' => 1, 'costo_estimado' => 50000, 'notas' => 'Placa ABC123', 'concepto_pago_id' => $gasolina->id],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertDatabaseHas('items_oficina', ['nombre' => 'Resma', 'concepto_pago_id' => null]);
        $this->assertDatabaseHas('items_oficina', ['nombre' => 'Tanqueo', 'concepto_pago_id' => $gasolina->id]);
    }

    public function test_concepto_inexistente_es_rechazado(): void
    {
        $this->seed();
        $lider = Usuario::where('email', 'lider@demo.test')->firstOrFail();
        $areaGeneral = Area::where('es_general', true)->firstOrFail();

        $this->actingAs($lider)->post(route('oficina.store'), [
            'area_id'       => $areaGeneral->id,
            'urgencia'      => 'media',
            'justificacion' => 'x',
            'items' => [
                ['nombre' => 'X', 'categoria' => 'producto', 'cantidad' => 1, 'costo_estimado' => null, 'notas' => '', 'concepto_pago_id' => 999999],
            ],
        ])->assertSessionHasErrors('items.0.concepto_pago_id');
    }

    public function test_relacion_concepto_pago(): void
    {
        $c = ConceptoPago::create(['nombre' => 'Peaje']);
        $item = new ItemOficina(['nombre' => 'x', 'categoria' => 'servicio', 'cantidad' => 1, 'concepto_pago_id' => $c->id]);
        $this->assertSame('Peaje', $item->conceptoPago()->getRelated()->newQuery()->find($c->id)->nombre);
    }
}
```

> Nota: el usuario `lider@demo.test` con rol `lider_area` y un `Area` con `es_general=true` provienen del seeder demo. Si el email difiere, ajústalo al que exista en `AdminSeeder`/`RolesSeeder`.

- [ ] **Step 2: Correr y ver fallar**

Run: `/c/xampp/php/php.exe artisan test --filter=OficinaConceptoPagoTest`
Expected: FAIL (columna no existe / validación rechaza campo desconocido no, pero FK sí).

- [ ] **Step 3: Migración**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items_oficina', function (Blueprint $table) {
            $table->foreignId('concepto_pago_id')->nullable()->after('categoria')
                ->constrained('conceptos_pago')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('items_oficina', function (Blueprint $table) {
            $table->dropConstrainedForeignId('concepto_pago_id');
        });
    }
};
```

- [ ] **Step 4: Modelo** — en `ItemOficina.php` añadir `'concepto_pago_id'` al `$fillable` y la relación:

```php
public function conceptoPago()
{
    return $this->belongsTo(ConceptoPago::class, 'concepto_pago_id');
}
```

(añadir `use App\Models\ConceptoPago;` no hace falta si está en el mismo namespace `App\Models`).

- [ ] **Step 5: Validación** — en `GuardarSolicitudOficinaRequest::rules()` añadir:

```php
'items.*.concepto_pago_id' => 'nullable|exists:conceptos_pago,id',
```

y en `attributes()`: `'items.*.concepto_pago_id' => 'concepto de pago',`

- [ ] **Step 6: Controller** — en `OficinaController::normalizarItem()`, asegurar que `concepto_pago_id` se propague (normalizar `''` → null):

```php
private function normalizarItem(array $item): array
{
    $costo = $item['costo_estimado'] ?? null;
    $item['costo_estimado'] = ($costo === '' || $costo === null) ? null : $costo;
    $concepto = $item['concepto_pago_id'] ?? null;
    $item['concepto_pago_id'] = ($concepto === '' || $concepto === null) ? null : $concepto;
    return $item;
}
```

Verificar que `ItemOficina::create(array_merge($this->normalizarItem($item), [...]))` en `store()` y `update()` no filtre el campo (está en fillable, ok).

- [ ] **Step 7: Correr y ver pasar**

Run: `/c/xampp/php/php.exe artisan test --filter=OficinaConceptoPagoTest`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add database/migrations app/Models/ItemOficina.php app/Http/Requests/GuardarSolicitudOficinaRequest.php app/Http/Controllers/OficinaController.php tests/Feature/OficinaConceptoPagoTest.php
git commit -m "feat(oficina): items soportan concepto de pago (gasolina, lavado, etc.)"
```

---

### Task 4: Formulario Crear.jsx con selector "tipo de línea"

**Files:**
- Modify: `app/Http/Controllers/OficinaController.php` (pasar `conceptosPago` a `create`/`edit`)
- Modify: `resources/js/Pages/Oficina/Crear.jsx`
- Test: manual (UI). Verificación: `npm run build` sin errores.

- [ ] **Step 1: Controller pasa conceptos** — en `create()` y `edit()` de `OficinaController`, añadir a las props de `Inertia::render('Oficina/Crear', [...])`:

```php
'conceptosPago' => \App\Models\ConceptoPago::where('activo', true)->orderBy('nombre')->get(['id','nombre']),
```

Y en `edit()`, cargar la relación: cambiar `$solicitud->load('solicitable.items', 'solicitable.beneficiarios');` para que los items ya traen `concepto_pago_id` (columna directa, no requiere eager load extra).

- [ ] **Step 2: Frontend — estado del ítem**

En `Crear.jsx`, actualizar `ITEM_VACIO` para incluir el campo y mapear en el estado inicial de edición:

```js
const ITEM_VACIO = { nombre: '', categoria: 'producto', cantidad: 1, costo_estimado: '', notas: '', concepto_pago_id: '' };
```

En el `map` de `solicitable?.items` añadir `concepto_pago_id: i.concepto_pago_id ?? ''`.

- [ ] **Step 3: Frontend — aceptar prop y selector por ítem**

Aceptar `conceptosPago = []` en las props del componente `Crear`.

Dentro del render de cada ítem, ANTES del grid de "Nombre / Categoría", agregar un selector de tipo de línea:

```jsx
<div>
    <label className="block text-sm font-medium text-slate-700 mb-1">Tipo de línea</label>
    <select
        value={item.concepto_pago_id ? 'pago' : 'elemento'}
        onChange={(e) => actualizarItem(idx, 'concepto_pago_id', e.target.value === 'pago' ? (conceptosPago[0]?.id ?? '') : '')}
        className="w-full rounded-lg border border-slate-300 text-sm py-2 px-3 focus:ring-2 focus:ring-indigo-500 outline-none">
        <option value="elemento">Elemento de oficina</option>
        <option value="pago">Otro pago / gasto</option>
    </select>
</div>
```

Cuando `item.concepto_pago_id` tiene valor (es pago), en vez de la Categoría producto/servicio mostrar el selector de concepto:

```jsx
{item.concepto_pago_id ? (
    <div>
        <label className="block text-sm font-medium text-slate-700 mb-1">Concepto</label>
        <select value={item.concepto_pago_id}
            onChange={(e) => actualizarItem(idx, 'concepto_pago_id', e.target.value)}
            className="w-full rounded-lg border border-slate-300 text-sm py-2 px-3 focus:ring-2 focus:ring-indigo-500 outline-none">
            {conceptosPago.map((c) => <option key={c.id} value={c.id}>{c.nombre}</option>)}
        </select>
    </div>
) : (
    /* selector Categoría producto/servicio actual */
)}
```

Para líneas de pago, la "Cantidad" puede quedarse en 1 (sigue enviándose). El campo "Costo estimado" pasa a ser el monto del pago (misma UI). Las notas ya existen para placa/responsable/detalle. NO ocultes cantidad para no romper la validación `required|integer|min:1`; si es pago, muéstrala pero con valor por defecto 1 (opcionalmente etiqueta "Cantidad" → deja igual para simplicidad).

- [ ] **Step 4: Build**

Run: `npm run build`
Expected: build OK sin errores.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/OficinaController.php resources/js/Pages/Oficina/Crear.jsx public/build
git commit -m "feat(oficina): selector de tipo de linea (elemento vs otro pago) en el formulario"
```

---

### Task 5: Mostrar el concepto en el detalle

**Files:**
- Modify: `resources/js/Pages/Solicitudes/Detalle.jsx`
- Test: manual + build. (El `solicitable` de OFI ya se serializa completo con `concepto_pago_id`; solo falta el nombre del concepto.)

- [ ] **Step 1: Backend — exponer nombre del concepto**

En `SolicitudDetalleResource.php`, para OFI el `solicitable` se serializa directo. Para tener el NOMBRE del concepto en el detalle, en `SolicitudController::show()` (o donde se carga el detalle OFI) hacer eager load: `$solicitud->load('solicitable.items.conceptoPago')`. Verifica en `SolicitudController` cómo se carga hoy el detalle OFI y añade `.items.conceptoPago` al load. Así cada item traerá `concepto_pago: { id, nombre }`.

> Antes de editar, LEE `app/Http/Controllers/SolicitudController.php@show` para ubicar el `load`/`morphWith` de OFI y añadir la relación sin romper los otros tipos.

- [ ] **Step 2: Frontend — columna Concepto**

En `Detalle.jsx`, la tabla de items OFI (thead con "Ítem" / "Cant."). Añadir una columna "Concepto":

```jsx
<th className="px-3 py-2 font-medium">Concepto</th>
```
y en el body:
```jsx
<td className="px-3 py-2.5 text-slate-500">{item.concepto_pago?.nombre ?? '—'}</td>
```

- [ ] **Step 3: Build**

Run: `npm run build`
Expected: OK.

- [ ] **Step 4: Correr TODA la suite (no regresiones)**

Run: `/c/xampp/php/php.exe artisan test`
Expected: verde (los ~286 tests previos + los nuevos).

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Solicitudes/Detalle.jsx app/Http/Controllers/SolicitudController.php public/build
git commit -m "feat(oficina): mostrar concepto de pago en el detalle de la solicitud"
```

---

## Self-Review

- Cobertura: catálogo (T1), CRUD (T2), backend item+validación (T3), formulario (T4), detalle (T5). ✔
- Sin placeholders: todo el código está escrito. ✔
- Consistencia de tipos: `concepto_pago_id` (snake) en BD/PHP/payload; `conceptoPago()` relación; `conceptosPago` prop React; `concepto_pago` en JSON de detalle. ✔
- No cambia motor, políticas, notificaciones ni pagos. Solicitudes existentes: `concepto_pago_id = null`. ✔
