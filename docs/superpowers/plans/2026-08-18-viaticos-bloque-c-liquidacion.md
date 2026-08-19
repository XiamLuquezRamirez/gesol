# Viáticos — Bloque C (Liquidación) Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Adjuntar archivos por viajero (comprobantes de transferencia y soportes) en la liquidación, arreglar el bug que impide agregar rubros, y cambiar la regla de meriendas a 1 por día.

**Architecture:** Una tabla `archivos_viajero` (con campo `tipo` = comprobante|soporte) guarda archivos por viajero, gestionada por un `ArchivoViajeroController` con endpoints store/descargar/destroy que replican el patrón de `AbonoOficinaController` (disco `local`). El front (`Liquidacion.jsx`) sube con `router.post` + `forceFormData`. Se corrige el bug `diasEntre`→`diasComision` y la regla de meriendas en `rubros.js`.

**Tech Stack:** Laravel 10.50, PHP 8.2, Inertia/React 18, PHPUnit + SQLite `:memory:`, `Storage::fake('local')` para tests de archivos.

**Spec:** `docs/superpowers/specs/2026-08-18-viaticos-bloque-c-liquidacion-design.md`

---

## File Structure

- **Create** `database/migrations/2026_08_18_120000_create_archivos_viajero_table.php`
- **Create** `app/Models/ArchivoViajero.php`
- **Modify** `app/Models/ViajeroComision.php` — relación `archivos()`.
- **Create** `app/Http/Requests/GuardarArchivoViajeroRequest.php`
- **Create** `app/Http/Controllers/ArchivoViajeroController.php` — store/descargar/destroy.
- **Modify** `routes/web.php` — 3 rutas.
- **Modify** `app/Http/Controllers/ViaticosController.php` — eager-load `archivos` en `liquidacion()`.
- **Modify** `resources/js/lib/rubros.js` — regla de meriendas.
- **Modify** `resources/js/Pages/Viaticos/Liquidacion.jsx` — fix `diasEntre`, UI de comprobantes y soportes.
- **Create** `tests/Feature/ArchivosViajeroTest.php`

---

## Task 1: Migración y modelo ArchivoViajero

**Files:**
- Create: `database/migrations/2026_08_18_120000_create_archivos_viajero_table.php`
- Create: `app/Models/ArchivoViajero.php`
- Modify: `app/Models/ViajeroComision.php`

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
        if (! Schema::hasTable('archivos_viajero')) {
            Schema::create('archivos_viajero', function (Blueprint $table) {
                $table->id();
                $table->foreignId('viajero_comision_id')->constrained('viajeros_comision')->cascadeOnDelete();
                $table->enum('tipo', ['comprobante', 'soporte']);
                $table->string('path');
                $table->string('nombre');
                $table->foreignId('usuario_id')->nullable()->constrained('usuarios')->nullOnDelete();
                $table->timestamps();
                $table->index(['viajero_comision_id', 'tipo']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('archivos_viajero');
    }
};
```

- [ ] **Step 2: Escribir el test del modelo/relación**

Test: `tests/Feature/ArchivosViajeroTest.php`

```php
<?php
namespace Tests\Feature;

use App\Models\ArchivoViajero;
use App\Models\Empleados;
use App\Models\SolicitudViaticos;
use App\Models\ViajeroComision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArchivosViajeroTest extends TestCase
{
    use RefreshDatabase;

    private function viajero(): ViajeroComision
    {
        $cab = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => '', 'observacion' => 'x']);
        return ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => Empleados::first()->id,
            'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
        ]);
    }

    public function test_viajero_tiene_archivos(): void
    {
        $this->seed();
        $v = $this->viajero();
        ArchivoViajero::create(['viajero_comision_id' => $v->id, 'tipo' => 'comprobante', 'path' => 'x/a.pdf', 'nombre' => 'a.pdf']);
        ArchivoViajero::create(['viajero_comision_id' => $v->id, 'tipo' => 'soporte', 'path' => 'x/b.pdf', 'nombre' => 'b.pdf']);

        $this->assertEquals(2, $v->fresh()->archivos()->count());
        $this->assertEquals(1, $v->fresh()->archivos()->where('tipo', 'comprobante')->count());
    }
}
```

- [ ] **Step 3: Ejecutar el test (falla)**

Run: `php artisan test --filter=test_viajero_tiene_archivos`
Expected: FAIL (modelo/relación no existen).

- [ ] **Step 4: Crear el modelo `app/Models/ArchivoViajero.php`**

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ArchivoViajero extends Model
{
    protected $table = 'archivos_viajero';
    protected $fillable = ['viajero_comision_id', 'tipo', 'path', 'nombre', 'usuario_id'];

    public function viajero()
    {
        return $this->belongsTo(ViajeroComision::class, 'viajero_comision_id');
    }
}
```

- [ ] **Step 5: Añadir la relación en `ViajeroComision`**

Dentro de la clase, junto a las otras relaciones:

```php
    public function archivos()
    {
        return $this->hasMany(ArchivoViajero::class, 'viajero_comision_id');
    }
```

- [ ] **Step 6: Ejecutar el test (pasa)**

Run: `php artisan test --filter=test_viajero_tiene_archivos`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add database/migrations/2026_08_18_120000_create_archivos_viajero_table.php app/Models/ArchivoViajero.php app/Models/ViajeroComision.php tests/Feature/ArchivosViajeroTest.php
git commit -m "feat(viaticos): tabla y modelo archivos_viajero (comprobante/soporte)"
```

---

## Task 2: Request de validación GuardarArchivoViajeroRequest

**Files:**
- Create: `app/Http/Requests/GuardarArchivoViajeroRequest.php`

- [ ] **Step 1: Crear el request**

```php
<?php
namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GuardarArchivoViajeroRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // La autorizacion real la hace la policy editarLiquidacion en el controlador.
    }

    public function rules(): array
    {
        return [
            'tipo'       => 'required|in:comprobante,soporte',
            'archivos'   => 'required|array|min:1',
            'archivos.*' => 'file|mimes:pdf,jpg,jpeg,png|max:5120',
        ];
    }

    public function messages(): array
    {
        return [
            'tipo.required'       => 'Falta el tipo de archivo.',
            'tipo.in'             => 'Tipo de archivo no válido.',
            'archivos.required'   => 'Adjunte al menos un archivo.',
            'archivos.*.mimes'    => 'Solo se permiten PDF o imágenes (jpg, png).',
            'archivos.*.max'      => 'Cada archivo no puede superar 5 MB.',
        ];
    }
}
```

- [ ] **Step 2: Verificar que la app no rompe**

Run: `php artisan test --filter=ArchivosViajeroTest`
Expected: el test previo sigue PASS (el request aún no se usa; solo confirma que la clase carga sin error de sintaxis).

- [ ] **Step 3: Commit**

```bash
git add app/Http/Requests/GuardarArchivoViajeroRequest.php
git commit -m "feat(viaticos): request de validacion de archivos de viajero"
```

---

## Task 3: Controlador ArchivoViajeroController y rutas

**Files:**
- Create: `app/Http/Controllers/ArchivoViajeroController.php`
- Modify: `routes/web.php`

**Contexto:** Replica el patrón de `AbonoOficinaController` (disco `local`, `Storage::disk('local')->store/download/delete`). Autoriza subir/borrar con la policy `editarLiquidacion` y descargar con `verDetalle`. Valida pertenencia del viajero a la comisión y del archivo al viajero con `abort_unless`.

- [ ] **Step 1: Escribir los tests HTTP**

Añadir a `tests/Feature/ArchivosViajeroTest.php` (dentro de la clase). Usar `Storage::fake('local')`. El usuario contador de los seeders: verificar su email en los seeders/otros tests de viáticos (p.ej. buscar en `tests/Feature` un test que use rol `contador`); si no se halla, crear uno con `Usuario::factory()` y asignar rol `contador` vía spatie. El implementer DEBE confirmar el usuario correcto.

```php
    public function test_contador_sube_comprobante(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->seed();
        [$solicitud, $viajero, $contador] = $this->comisionEnviada();

        $this->actingAs($contador)->post(
            route('viaticos.archivos.store', [$solicitud, $viajero]),
            ['tipo' => 'comprobante', 'archivos' => [\Illuminate\Http\UploadedFile::fake()->create('t.pdf', 100, 'application/pdf')]]
        )->assertRedirect();

        $this->assertEquals(1, $viajero->archivos()->where('tipo', 'comprobante')->count());
        $archivo = $viajero->archivos()->first();
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($archivo->path);
    }

    public function test_sube_varios_soportes(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->seed();
        [$solicitud, $viajero, $contador] = $this->comisionEnviada();

        $this->actingAs($contador)->post(
            route('viaticos.archivos.store', [$solicitud, $viajero]),
            ['tipo' => 'soporte', 'archivos' => [
                \Illuminate\Http\UploadedFile::fake()->image('a.jpg'),
                \Illuminate\Http\UploadedFile::fake()->image('b.jpg'),
            ]]
        )->assertRedirect();

        $this->assertEquals(2, $viajero->archivos()->where('tipo', 'soporte')->count());
    }

    public function test_usuario_no_contador_no_sube(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->seed();
        [$solicitud, $viajero] = $this->comisionEnviada();
        $otro = \App\Models\Usuario::where('email', 'lider.comite@demo.test')->firstOrFail();

        $this->actingAs($otro)->post(
            route('viaticos.archivos.store', [$solicitud, $viajero]),
            ['tipo' => 'comprobante', 'archivos' => [\Illuminate\Http\UploadedFile::fake()->create('t.pdf', 10, 'application/pdf')]]
        )->assertForbidden();
    }

    public function test_contador_elimina_archivo(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->seed();
        [$solicitud, $viajero, $contador] = $this->comisionEnviada();
        $path = \Illuminate\Support\Facades\Storage::disk('local')->putFile('archivos_viajero', \Illuminate\Http\UploadedFile::fake()->create('t.pdf', 10, 'application/pdf'));
        $archivo = ArchivoViajero::create(['viajero_comision_id' => $viajero->id, 'tipo' => 'soporte', 'path' => $path, 'nombre' => 't.pdf']);

        $this->actingAs($contador)->delete(route('viaticos.archivos.destroy', [$solicitud, $viajero, $archivo]))
            ->assertRedirect();

        $this->assertDatabaseMissing('archivos_viajero', ['id' => $archivo->id]);
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($path);
    }

    public function test_archivo_de_otra_comision_da_404(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->seed();
        [$solicitud, $viajero, $contador] = $this->comisionEnviada();
        // Un viajero de OTRA comision.
        $otroViajero = $this->viajero();
        $archivoAjeno = ArchivoViajero::create(['viajero_comision_id' => $otroViajero->id, 'tipo' => 'soporte', 'path' => 'x/z.pdf', 'nombre' => 'z.pdf']);

        $this->actingAs($contador)->delete(route('viaticos.archivos.destroy', [$solicitud, $viajero, $archivoAjeno]))
            ->assertNotFound();
    }
```

Y añadir el helper `comisionEnviada()` que crea una comisión VIA en estado `enviada` con un viajero y devuelve `[Solicitud, ViajeroComision, Usuario contador]`. El implementer DEBE construirlo correctamente: crear la cabecera + `Solicitud` polimórfica con `tipo_solicitud` VIA en estado `enviada` (mirar cómo `ViaticosController::store` arma la Solicitud y `TipoSolicitud::where('clave','VIA')`), y obtener el usuario con rol `contador` (verificar el email real en seeders; si no existe usuario contador demo, crearlo y asignarle el rol).

```php
    private function comisionEnviada(): array
    {
        $tipo = \App\Models\TipoSolicitud::where('clave', 'VIA')->firstOrFail();
        $cab = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => '', 'observacion' => 'x']);
        $viajero = ViajeroComision::create([
            'solicitud_viaticos_id' => $cab->id, 'empleado_id' => Empleados::first()->id,
            'motivo' => 'm', 'fecha_salida' => '2026-08-20', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-08-21', 'hora_regreso' => '17:00',
        ]);
        $solicitud = \App\Models\Solicitud::create([
            'tipo_solicitud_id' => $tipo->id,
            'solicitante_id'    => \App\Models\Usuario::first()->id,
            'solicitable_type'  => SolicitudViaticos::class,
            'solicitable_id'    => $cab->id,
            'estado'            => 'enviada',
            'radicado'          => \App\Models\Solicitud::generarRadicado($tipo),
        ]);
        $contador = \App\Models\Usuario::role('contador')->first()
            ?? \App\Models\Usuario::where('email', 'admin@demo.test')->firstOrFail();
        return [$solicitud, $viajero, $contador];
    }
```

> IMPORTANTE: el implementer verifica cuál es el usuario con rol `contador` (en los seeders el admin demo tiene todos los roles; `Usuario::role('contador')->first()` debería funcionar con spatie). Ajustar `comisionEnviada()` si la construcción de `Solicitud` difiere del patrón real de `store()`.

- [ ] **Step 2: Ejecutar (falla: no hay rutas ni controlador)**

Run: `php artisan test --filter=ArchivosViajeroTest`
Expected: FAIL (ruta `viaticos.archivos.store` no existe).

- [ ] **Step 3: Crear el controlador**

`app/Http/Controllers/ArchivoViajeroController.php`:

```php
<?php
namespace App\Http\Controllers;

use App\Http\Requests\GuardarArchivoViajeroRequest;
use App\Models\ArchivoViajero;
use App\Models\Solicitud;
use App\Models\ViajeroComision;
use Illuminate\Support\Facades\Storage;

class ArchivoViajeroController extends Controller
{
    public function store(GuardarArchivoViajeroRequest $request, Solicitud $solicitud, ViajeroComision $viajero)
    {
        $this->authorize('editarLiquidacion', $solicitud);
        abort_unless($viajero->solicitud_viaticos_id === $solicitud->solicitable_id, 404);

        foreach ($request->file('archivos') as $archivo) {
            $path = $archivo->store('archivos_viajero', 'local');
            $viajero->archivos()->create([
                'tipo'       => $request->tipo,
                'path'       => $path,
                'nombre'     => $archivo->getClientOriginalName(),
                'usuario_id' => auth()->id(),
            ]);
        }

        return back()->with('success', 'Archivo(s) adjuntado(s).');
    }

    public function descargar(Solicitud $solicitud, ViajeroComision $viajero, ArchivoViajero $archivo)
    {
        $this->authorize('verDetalle', $solicitud);
        abort_unless($viajero->solicitud_viaticos_id === $solicitud->solicitable_id, 404);
        abort_unless($archivo->viajero_comision_id === $viajero->id, 404);
        abort_unless(Storage::disk('local')->exists($archivo->path), 404);

        return Storage::disk('local')->download($archivo->path, $archivo->nombre);
    }

    public function destroy(Solicitud $solicitud, ViajeroComision $viajero, ArchivoViajero $archivo)
    {
        $this->authorize('editarLiquidacion', $solicitud);
        abort_unless($viajero->solicitud_viaticos_id === $solicitud->solicitable_id, 404);
        abort_unless($archivo->viajero_comision_id === $viajero->id, 404);

        Storage::disk('local')->delete($archivo->path);
        $archivo->delete();

        return back()->with('success', 'Archivo eliminado.');
    }
}
```

> Verificar que `$solicitud->solicitable_id` es la columna correcta de la Solicitud polimórfica (mirar la tabla `solicitudes`). Si el nombre difiere, ajustar el `abort_unless`.

- [ ] **Step 4: Registrar las rutas en `routes/web.php`**

Junto a las rutas de viáticos (grupo autenticado), añadir:

```php
Route::post('/viaticos/{solicitud}/viajeros/{viajero}/archivos', [\App\Http\Controllers\ArchivoViajeroController::class, 'store'])->name('viaticos.archivos.store');
Route::get('/viaticos/{solicitud}/viajeros/{viajero}/archivos/{archivo}', [\App\Http\Controllers\ArchivoViajeroController::class, 'descargar'])->name('viaticos.archivos.descargar');
Route::delete('/viaticos/{solicitud}/viajeros/{viajero}/archivos/{archivo}', [\App\Http\Controllers\ArchivoViajeroController::class, 'destroy'])->name('viaticos.archivos.destroy');
```

> Verificar el import/uso: seguir el estilo del archivo (si usa `use App\Http\Controllers\...;` arriba, añadir el import y usar la clase corta).

- [ ] **Step 5: Ejecutar (pasan)**

Run: `php artisan test --filter=ArchivosViajeroTest`
Expected: PASS todos.

- [ ] **Step 6: Suite completa**

Run: `php artisan test`
Expected: 142 + nuevos, verde.

- [ ] **Step 7: Commit**

```bash
git add app/Http/Controllers/ArchivoViajeroController.php routes/web.php tests/Feature/ArchivosViajeroTest.php
git commit -m "feat(viaticos): endpoints para adjuntar, descargar y eliminar archivos de viajero"
```

---

## Task 4: eager-load de archivos en liquidacion()

**Files:**
- Modify: `app/Http/Controllers/ViaticosController.php`

- [ ] **Step 1: Ampliar el load de `liquidacion()`**

Cambiar:
```php
$solicitud->load(['solicitable.viajeros.empleado','solicitable.viajeros.asignaciones']);
```
por:
```php
$solicitud->load(['solicitable.viajeros.empleado','solicitable.viajeros.asignaciones','solicitable.viajeros.archivos']);
```

- [ ] **Step 2: Verificar**

Run: `php artisan test --filter=ViaticosLiquidacion` (o la suite de viáticos; si no hay test específico, `php artisan test` completo)
Expected: verde (no rompe nada; solo añade datos a las props).

- [ ] **Step 3: Commit**

```bash
git add app/Http/Controllers/ViaticosController.php
git commit -m "feat(viaticos): cargar archivos de viajero en la pantalla de liquidacion"
```

---

## Task 5: Regla de meriendas (1/día) y fix del bug diasEntre

**Files:**
- Modify: `resources/js/lib/rubros.js`
- Modify: `resources/js/Pages/Viaticos/Liquidacion.jsx`

**Contexto:** Dos correcciones de front independientes de los archivos. (a) `conteoComidas` en `rubros.js` cuenta hasta 2 meriendas/día; debe contar 1/día. (b) `Liquidacion.jsx:72` llama `diasEntre(...)` inexistente; la función real es `diasComision` (exportada por `rubros.js`).

- [ ] **Step 1: Cambiar la regla de meriendas en `rubros.js`**

Localizar en `conteoComidas` (líneas ~74-77):
```js
        // Merienda: 1 por franja presente (mañana ~ desayuno, tarde ~ cena).
        if (desayuno) conteo.merienda += 1;
        if (cena) conteo.merienda += 1;
```
Reemplazar por:
```js
        // Merienda: 1 por día si hay alguna comida presente.
        if (desayuno || almuerzo || cena) conteo.merienda += 1;
```
Actualizar el comentario de cabecera de la función (líneas ~10-12) que describa la regla anterior de "2 por día" para reflejar "1 merienda por día".

- [ ] **Step 2: Arreglar el bug `diasEntre` en `Liquidacion.jsx`**

En el import (línea ~8):
```js
import { rubrosPorDefecto } from '@/lib/rubros';
```
cambiar a:
```js
import { rubrosPorDefecto, diasComision } from '@/lib/rubros';
```
En `agregarRubro` (línea ~72), reemplazar:
```js
                dias: diasEntre(viajero?.fecha_salida, viajero?.fecha_regreso),
```
por:
```js
                dias: diasComision(viajero?.fecha_salida, viajero?.fecha_regreso),
```

- [ ] **Step 3: Compilar**

Run: `npm run build`
Expected: `✓ built` sin errores.

- [ ] **Step 4: Verificación manual documentada**

Confirmar en el código que:
- `diasComision` está importada y usada en `agregarRubro` (ya no hay referencia a `diasEntre` — buscar en el archivo que no queden ocurrencias de `diasEntre`).
- La regla de meriendas cuenta 1/día (revisar el bucle de `conteoComidas`).

- [ ] **Step 5: Commit**

```bash
git add resources/js/lib/rubros.js resources/js/Pages/Viaticos/Liquidacion.jsx
git commit -m "fix(viaticos): 1 merienda por dia y corregir ReferenceError al agregar rubro"
```

---

## Task 6: UI de comprobantes y soportes en Liquidacion.jsx

**Files:**
- Modify: `resources/js/Pages/Viaticos/Liquidacion.jsx`

**Contexto:** Añadir la UI para adjuntar/listar/eliminar archivos por viajero. Los uploads usan `router.post(route('viaticos.archivos.store', [solicitud.id, viajero.id]), { tipo, archivos }, { forceFormData: true, preserveScroll: true })`. La descarga es un `<a href={route('viaticos.archivos.descargar', [solicitud.id, viajero.id, archivo.id])}>`. La eliminación usa `router.delete(route('viaticos.archivos.destroy', [...]), { preserveScroll: true })`. Leer el archivo completo antes de editar. `router` viene de `@inertiajs/react` (verificar el import; si no está, añadirlo).

- [ ] **Step 1: Asegurar el import de `router`**

En los imports de `@inertiajs/react`, incluir `router` (junto a `useForm`, `Head`, etc.). Verificar cómo está importado hoy y añadir `router` si falta.

- [ ] **Step 2: Helper de subida por viajero**

Cerca de los otros handlers, añadir:
```jsx
    const subirArchivos = (viajeroId, tipo, fileList) => {
        if (!fileList || fileList.length === 0) return;
        router.post(
            route('viaticos.archivos.store', [solicitud.id, viajeroId]),
            { tipo, archivos: Array.from(fileList) },
            { forceFormData: true, preserveScroll: true }
        );
    };

    const eliminarArchivo = (viajeroId, archivoId) => {
        router.delete(route('viaticos.archivos.destroy', [solicitud.id, viajeroId, archivoId]), { preserveScroll: true });
    };
```

- [ ] **Step 3: Bloque de comprobante (tipo=comprobante) bajo el toggle de pago**

En el bloque del toggle Efectivo/Transferencia de cada viajero (líneas ~138-160), después de los botones, cuando el pago activo sea `transferencia`, renderizar el listado + input. Ejemplo:

```jsx
{(data.pagos.find((p) => p.viajero_comision_id === viajero.id)?.tipo_pago) === 'transferencia' && (
    <div className="mt-2 space-y-1">
        <ul className="space-y-1">
            {(viajero.archivos ?? []).filter((a) => a.tipo === 'comprobante').map((a) => (
                <li key={a.id} className="flex items-center gap-2 text-xs">
                    <a href={route('viaticos.archivos.descargar', [solicitud.id, viajero.id, a.id])}
                       className="text-indigo-600 hover:underline">{a.nombre}</a>
                    <button type="button" onClick={() => eliminarArchivo(viajero.id, a.id)}
                            className="text-red-500 hover:text-red-700">Eliminar</button>
                </li>
            ))}
        </ul>
        <input type="file" multiple accept=".pdf,.jpg,.jpeg,.png"
               onChange={(e) => { subirArchivos(viajero.id, 'comprobante', e.target.files); e.target.value = ''; }}
               className="block w-full text-xs text-slate-600" />
    </div>
)}
```

- [ ] **Step 4: Bloque de soportes (tipo=soporte) por viajero**

En la zona de la planilla de cada viajero (cerca de su tabla de rubros), añadir un bloque análogo con `tipo='soporte'` y etiqueta "Soportes adicionales":

```jsx
<div className="mt-3">
    <p className="text-xs font-medium text-slate-600 mb-1">Soportes adicionales</p>
    <ul className="space-y-1">
        {(viajero.archivos ?? []).filter((a) => a.tipo === 'soporte').map((a) => (
            <li key={a.id} className="flex items-center gap-2 text-xs">
                <a href={route('viaticos.archivos.descargar', [solicitud.id, viajero.id, a.id])}
                   className="text-indigo-600 hover:underline">{a.nombre}</a>
                <button type="button" onClick={() => eliminarArchivo(viajero.id, a.id)}
                        className="text-red-500 hover:text-red-700">Eliminar</button>
            </li>
        ))}
    </ul>
    <input type="file" multiple accept=".pdf,.jpg,.jpeg,.png"
           onChange={(e) => { subirArchivos(viajero.id, 'soporte', e.target.files); e.target.value = ''; }}
           className="block w-full text-xs text-slate-600" />
</div>
```

> Ajustar la ubicación exacta y clases al layout real del componente (leerlo primero). El objeto `viajero` en el render debe ser el mismo que ya se itera para mostrar la planilla; usar su `.archivos` (viene del eager-load de Task 4). Verificar el nombre de la variable de iteración (puede ser `viajero` o `v`).

- [ ] **Step 5: Compilar**

Run: `npm run build`
Expected: `✓ built` sin errores.

- [ ] **Step 6: Commit**

```bash
git add resources/js/Pages/Viaticos/Liquidacion.jsx
git commit -m "feat(viaticos): adjuntar comprobantes de transferencia y soportes por viajero en la liquidacion"
```

---

## Task 7: Verificación final del bloque

- [ ] **Step 1: Suite completa**

Run: `php artisan test`
Expected: 142 previos + ~7 nuevos, todos verdes.

- [ ] **Step 2: Build de producción**

Run: `npm run build`
Expected: `✓ built` sin errores.

- [ ] **Step 3: Estado git limpio**

Run: `git status`
Expected: working tree limpio.

- [ ] **Step 4: Confirmar que no quedan referencias al bug**

Run: `grep -rn "diasEntre" resources/js` (o Grep)
Expected: sin resultados (el bug fue eliminado).
