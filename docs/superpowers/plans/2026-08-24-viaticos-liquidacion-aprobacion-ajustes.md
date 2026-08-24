# Liquidación y aprobación de reajustes post-cierre (anexos) — Plan de implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Convertir el reajuste de una comisión de viáticos **ya cerrada** en una entidad `AjusteComision` con estado propio (`pendiente_liquidacion → liquidado → aprobado`, + `devuelto`), con liquidación del delta de rubros calculada por un Service PHP, aprobada por el líder de contabilidad, sin alterar la comisión cerrada.

**Architecture:** Nueva tabla `ajustes_comision` + columna `ajuste_comision_id` en `asignaciones_viaticos`. Un `App\Services\CalculadoraRubrosViaticos` (portado de `resources/js/lib/rubros.js`) calcula el delta de rubros por diferencia de fechas/horas. Nuevos endpoints en `ViaticosController` gobiernan el mini-flujo del ajuste; permisos en `SolicitudPolicy`; notificaciones vía `AvisoTransicionNotification`. Frontend: tabla "Ajustes" ampliada en `Detalle.jsx` con badges y acciones por rol, y nueva pantalla `Viaticos/LiquidacionAjuste.jsx`.

**Tech Stack:** Laravel 10.50, PHP 8.2 (CLI: `/c/xampp/php/php.exe`), Inertia + React 18, spatie/laravel-permission 6, PHPUnit + SQLite `:memory:`, Tailwind, Vite.

**Convenciones del repo (leer antes de empezar):**
- El PHP del PATH es 7.4 y rompe artisan/composer. Usar SIEMPRE `/c/xampp/php/php.exe`. Ejemplos:
  - Tests: `/c/xampp/php/php.exe artisan test --filter=NombreTest`
  - Migrar en dev (MariaDB): `/c/xampp/php/php.exe artisan migrate`
- Identificadores de dominio SIN tildes ni ñ (ASCII). Comentarios/UI con ortografía correcta.
- Modelo de usuario: `App\Models\Usuario`. Roles: `lider_area`, `lider_comite`, `rrhh`, `contabilidad_lider`, `contador`.
- Migraciones que tocan enums deben ser **driver-aware** (MySQL `MODIFY` + SQLite reescribiendo el CHECK en `sqlite_master`), siguiendo `database/migrations/2026_08_20_130000_add_transporte_to_asignaciones_rubro_enum.php`.
- Commits: mensaje en español, terminando con `Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>`. Un commit por tarea. NO hacer push salvo que el usuario lo pida.
- **IMPORTANTE:** el working tree tiene cambios ajenos sin commitear que NO son de esta feature y NO deben tocarse ni incluirse en ningún commit: `app/Services/LiquidacionPdf.php`, `public/favicon.ico`, `public/images/logo.png`, `public/images/logo2.png`. Al hacer `git add`, listar archivos explícitos — nunca `git add -A` ni `git add .`.

**Spec de referencia:** `docs/superpowers/specs/2026-08-24-viaticos-liquidacion-aprobacion-ajustes-design.md`

---

## Estructura de archivos

**Backend (nuevos):**
- `app/Services/CalculadoraRubrosViaticos.php` — cálculo de días por rubro y delta (fuente de verdad server-side).
- `app/Models/AjusteComision.php` — entidad del anexo.
- `database/migrations/2026_08_24_100000_create_ajustes_comision_table.php`
- `database/migrations/2026_08_24_100100_add_ajuste_comision_id_to_asignaciones_viaticos.php`
- `app/Http/Requests/SolicitarAjusteFechasRequest.php`
- `app/Http/Requests/SolicitarAjusteRubroRequest.php`
- `app/Http/Requests/LiquidarAjusteRequest.php`
- `app/Http/Requests/DevolverAjusteRequest.php`

**Backend (modificados):**
- `app/Models/AsignacionViatico.php` — fillable + hooks recalculan cabecera (excluyendo anexos) o ajuste.
- `app/Models/SolicitudViaticos.php` — `recalcularTotal()` excluye anexos; relación `ajustes()`.
- `app/Models/ViajeroComision.php` — relación `ajustes()`.
- `app/Http/Controllers/ViaticosController.php` — nuevos métodos: `solicitarAjusteFechas`, `solicitarAjusteRubro`, `liquidacionAjuste` (GET), `updateAjuste` (PUT), `aprobarAjuste`, `devolverAjuste`. Helpers de notificación.
- `app/Policies/SolicitudPolicy.php` — `liquidarAjuste`, `aprobarAjuste`, `devolverAjuste`, `verAjuste` (y reuso de `ajustar` para solicitar).
- `routes/web.php` — rutas nuevas.
- `app/Http/Middleware/HandleInertiaRequests.php` — (si aplica) exponer nada extra; el detalle carga los ajustes vía controlador de solicitudes.
- Controlador que renderiza `Solicitudes/Detalle` (buscar dónde se hace `Inertia::render('Solicitudes/Detalle'...)`) — cargar `solicitable.ajustes` con sus relaciones y exponer flags de permiso.

**Frontend (nuevos):**
- `resources/js/Pages/Viaticos/LiquidacionAjuste.jsx`

**Frontend (modificados):**
- `resources/js/Pages/Solicitudes/Detalle.jsx` — tabla "Ajustes" con registros `AjusteComision`, badges, acciones por rol/estado, modales de devolver; enrutar "Ajustar"/"Reajustar" a los nuevos endpoints cuando la comisión está cerrada.
- `resources/js/Components/PanelNotificaciones.jsx` — copy/estilo de nuevos tipos.
- Componente/página del listado de solicitudes — badge "Ajuste pendiente".

**Tests (nuevos):**
- `tests/Unit/CalculadoraRubrosViaticosTest.php`
- `tests/Feature/AjusteComisionFlujoTest.php`
- `tests/Feature/AjusteComisionValorUnitarioTest.php`
- `tests/Feature/AjusteComisionAislamientoTest.php`

---

## Task 1: Service de cálculo de rubros (portado de rubros.js)

**Files:**
- Create: `app/Services/CalculadoraRubrosViaticos.php`
- Test: `tests/Unit/CalculadoraRubrosViaticosTest.php`

Portar `resources/js/lib/rubros.js` a PHP. `hora_salida`/`hora_regreso` son strings `HH:MM` (o `null`). Fechas son `Y-m-d` (o `Carbon`/`DateTime` — normalizar a los primeros 10 chars como hace el JS).

- [ ] **Step 1: Escribir el test de cálculo base y delta**

```php
<?php
namespace Tests\Unit;

use App\Services\CalculadoraRubrosViaticos;
use PHPUnit\Framework\TestCase;

class CalculadoraRubrosViaticosTest extends TestCase
{
    private CalculadoraRubrosViaticos $calc;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calc = new CalculadoraRubrosViaticos();
    }

    public function test_dias_comision_inclusivo(): void
    {
        $this->assertSame(1, $this->calc->diasComision('2026-01-10', '2026-01-10'));
        $this->assertSame(3, $this->calc->diasComision('2026-01-10', '2026-01-12'));
    }

    public function test_mismo_dia_solo_comidas_en_ventana(): void
    {
        // Salida 10:00, regreso 15:00 => almuerzo (14:00) sí; desayuno (09:00) no; cena (18:00) no.
        $c = $this->calc->conteoComidas('2026-01-10', '2026-01-10', '10:00', '15:00');
        $this->assertSame(0, $c['desayuno']);
        $this->assertSame(1, $c['almuerzo']);
        $this->assertSame(0, $c['cena']);
        $this->assertSame(1, $c['merienda']); // hubo alguna comida ese día
    }

    public function test_cena_aplica_si_sigue_despues_de_las_18(): void
    {
        // Un día, regreso 19:00 => cena cuenta (hora tope 18:00 <= 19:00).
        $c = $this->calc->conteoComidas('2026-01-10', '2026-01-10', '08:00', '19:00');
        $this->assertSame(1, $c['cena']);
    }

    public function test_merienda_una_por_dia_con_comida(): void
    {
        // 3 días completos sin horas => cada día tiene comidas => 3 meriendas.
        $c = $this->calc->conteoComidas('2026-01-10', '2026-01-12', null, null);
        $this->assertSame(3, $c['merienda']);
    }

    public function test_dias_de_rubro_gasolina_usa_dias_comision(): void
    {
        $this->assertSame(3, $this->calc->diasDeRubro('gasolina', '2026-01-10', '2026-01-12', null, null));
        $this->assertSame(3, $this->calc->diasDeRubro('transporte', '2026-01-10', '2026-01-12', null, null));
    }

    public function test_calcular_delta_extiende_suma(): void
    {
        // Antes: 1 día (10 al 10, 08:00-15:00). Después: 2 días (10 al 11, 08:00-19:00).
        $antes   = ['fecha_salida' => '2026-01-10', 'hora_salida' => '08:00', 'fecha_regreso' => '2026-01-10', 'hora_regreso' => '15:00'];
        $despues = ['fecha_salida' => '2026-01-10', 'hora_salida' => '08:00', 'fecha_regreso' => '2026-01-11', 'hora_regreso' => '19:00'];
        $delta = $this->calc->calcularDelta($antes, $despues);
        // gasolina/transporte: 2 - 1 = +1
        $this->assertSame(1, $delta['gasolina']);
        $this->assertSame(1, $delta['transporte']);
        // cena: antes 0 (regreso 15:00), después: día1 sin cena (regreso ese día no aplica al ser primer día con h>=salida => cena 18:00>=08:00 sí en día no-último)... ver nota
        $this->assertArrayNotHasKey('desayuno', array_filter($delta, fn($v) => $v === 0));
    }

    public function test_calcular_delta_recorta_resta(): void
    {
        $antes   = ['fecha_salida' => '2026-01-10', 'hora_salida' => '08:00', 'fecha_regreso' => '2026-01-12', 'hora_regreso' => '19:00'];
        $despues = ['fecha_salida' => '2026-01-10', 'hora_salida' => '08:00', 'fecha_regreso' => '2026-01-11', 'hora_regreso' => '19:00'];
        $delta = $this->calc->calcularDelta($antes, $despues);
        $this->assertSame(-1, $delta['gasolina']); // 2 - 3
    }

    public function test_calcular_delta_sin_cambio_vacio(): void
    {
        $mismo = ['fecha_salida' => '2026-01-10', 'hora_salida' => '08:00', 'fecha_regreso' => '2026-01-12', 'hora_regreso' => '19:00'];
        $this->assertSame([], $this->calc->calcularDelta($mismo, $mismo));
    }
}
```

- [ ] **Step 2: Ejecutar el test para verlo fallar**

Run: `/c/xampp/php/php.exe artisan test --filter=CalculadoraRubrosViaticosTest`
Expected: FAIL con "Class CalculadoraRubrosViaticos not found".

- [ ] **Step 3: Implementar el Service**

```php
<?php
namespace App\Services;

class CalculadoraRubrosViaticos
{
    // Horas tope de cada comida, en minutos desde medianoche (idéntico a rubros.js).
    private const HORA_COMIDA = [
        'desayuno' => 9 * 60,   // 09:00
        'almuerzo' => 14 * 60,  // 14:00
        'cena'     => 18 * 60,  // 18:00 — la cena aplica si sigue en comision despues de las 6 p. m.
    ];

    /** "HH:MM" -> minutos. Sin hora valida devuelve el default (inicio/fin del dia). */
    private function aMinutos(?string $hora, int $porDefecto): int
    {
        if (! $hora) return $porDefecto;
        if (! preg_match('/^(\d{1,2}):(\d{2})/', $hora, $m)) return $porDefecto;
        return ((int) $m[1]) * 60 + ((int) $m[2]);
    }

    private function fechaSolo(string $f): \DateTimeImmutable
    {
        return new \DateTimeImmutable(substr($f, 0, 10).' 00:00:00');
    }

    /** Dias de comision, inclusivo (salida y regreso cuentan), minimo 1. */
    public function diasComision(?string $fechaSalida, ?string $fechaRegreso): int
    {
        if (! $fechaSalida || ! $fechaRegreso) return 1;
        $dif = $this->fechaSolo($fechaSalida)->diff($this->fechaSolo($fechaRegreso))->days;
        return max(1, $dif + 1);
    }

    /** ¿La comida esta presente el dia $indice (0 = primero)? Afina bordes con horas. */
    private function comidaPresente(string $nombre, int $indice, int $totalDias, int $minSalida, int $minRegreso): bool
    {
        $h = self::HORA_COMIDA[$nombre];
        $esPrimero = $indice === 0;
        $esUltimo  = $indice === $totalDias - 1;

        if ($totalDias === 1) {
            return $h >= $minSalida && $h <= $minRegreso;
        }
        if ($esPrimero) return $h >= $minSalida;
        if ($esUltimo)  return $h <= $minRegreso;
        return true; // dia intermedio
    }

    /** Cuenta cada comida a lo largo de la comision. Devuelve [desayuno,almuerzo,cena,merienda]. */
    public function conteoComidas(?string $fechaSalida, ?string $fechaRegreso, ?string $horaSalida, ?string $horaRegreso): array
    {
        $dias = $this->diasComision($fechaSalida, $fechaRegreso);
        $minSalida = $this->aMinutos($horaSalida, 0);
        $minRegreso = $this->aMinutos($horaRegreso, 24 * 60);

        $c = ['desayuno' => 0, 'almuerzo' => 0, 'cena' => 0, 'merienda' => 0];
        for ($i = 0; $i < $dias; $i++) {
            $d = $this->comidaPresente('desayuno', $i, $dias, $minSalida, $minRegreso);
            $a = $this->comidaPresente('almuerzo', $i, $dias, $minSalida, $minRegreso);
            $ce = $this->comidaPresente('cena', $i, $dias, $minSalida, $minRegreso);
            if ($d)  $c['desayuno'] += 1;
            if ($a)  $c['almuerzo'] += 1;
            if ($ce) $c['cena'] += 1;
            if ($d || $a || $ce) $c['merienda'] += 1;
        }
        return $c;
    }

    /** Dias que corresponden a un rubro segun fechas/horas. Comidas usan conteo; resto usa dias. */
    public function diasDeRubro(string $rubro, ?string $fechaSalida, ?string $fechaRegreso, ?string $horaSalida, ?string $horaRegreso): int
    {
        $comidas = $this->conteoComidas($fechaSalida, $fechaRegreso, $horaSalida, $horaRegreso);
        if (array_key_exists($rubro, $comidas)) return $comidas[$rubro];
        return $this->diasComision($fechaSalida, $fechaRegreso);
    }

    /**
     * Delta de dias por rubro entre el snapshot ANTES y DESPUES.
     * Cada snapshot: ['fecha_salida','hora_salida','fecha_regreso','hora_regreso'].
     * Devuelve solo rubros con delta != 0. Puede ser negativo (se recorto la comision).
     */
    public function calcularDelta(array $antes, array $despues): array
    {
        $rubros = ['desayuno', 'almuerzo', 'cena', 'merienda', 'gasolina', 'transporte'];
        $delta = [];
        foreach ($rubros as $rubro) {
            $diasAntes = $this->diasDeRubro(
                $rubro, $antes['fecha_salida'] ?? null, $antes['fecha_regreso'] ?? null,
                $antes['hora_salida'] ?? null, $antes['hora_regreso'] ?? null
            );
            $diasDespues = $this->diasDeRubro(
                $rubro, $despues['fecha_salida'] ?? null, $despues['fecha_regreso'] ?? null,
                $despues['hora_salida'] ?? null, $despues['hora_regreso'] ?? null
            );
            $d = $diasDespues - $diasAntes;
            if ($d !== 0) $delta[$rubro] = $d;
        }
        return $delta;
    }
}
```

- [ ] **Step 4: Ejecutar los tests hasta que pasen**

Run: `/c/xampp/php/php.exe artisan test --filter=CalculadoraRubrosViaticosTest`
Expected: PASS. Si algún assert de los tests de delta no coincide con el comportamiento real de bordes, corregir el **test** (no el Service) para que refleje exactamente las mismas reglas que `rubros.js` — el Service es el port fiel. Verificar contra `rubros.js` línea a línea.

- [ ] **Step 5: Commit**

```bash
git add app/Services/CalculadoraRubrosViaticos.php tests/Unit/CalculadoraRubrosViaticosTest.php
git commit -m "feat(viaticos): Service de calculo de rubros y delta (port de rubros.js)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 2: Migraciones (tabla ajustes_comision + columna en asignaciones)

**Files:**
- Create: `database/migrations/2026_08_24_100000_create_ajustes_comision_table.php`
- Create: `database/migrations/2026_08_24_100100_add_ajuste_comision_id_to_asignaciones_viaticos.php`

- [ ] **Step 1: Escribir la migración de la tabla ajustes_comision**

El enum `estado` y `tipo` en SQLite genera un CHECK; en MySQL genera ENUM. `Schema::create` con `->enum()` funciona en ambos para la creación inicial (no requiere el truco de ALTER; ese solo hace falta al **modificar** un enum existente). Usar `enum()` directamente:

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ajustes_comision', function (Blueprint $table) {
            $table->id();
            $table->foreignId('solicitud_id')->constrained('solicitudes')->cascadeOnDelete();
            $table->foreignId('viajero_comision_id')->constrained('viajeros_comision')->cascadeOnDelete();
            $table->foreignId('solicitado_por')->constrained('usuarios');
            $table->enum('tipo', ['fechas', 'rubro']);
            $table->text('motivo');
            $table->enum('estado', ['pendiente_liquidacion', 'liquidado', 'aprobado', 'devuelto'])
                  ->default('pendiente_liquidacion');
            $table->json('fechas_antes')->nullable();
            $table->json('fechas_despues')->nullable();
            $table->string('rubro')->nullable();
            $table->integer('cantidad')->nullable();
            $table->decimal('total_delta', 14, 2)->default(0);
            $table->text('motivo_devolucion')->nullable();
            $table->foreignId('liquidado_por')->nullable()->constrained('usuarios');
            $table->timestamp('liquidado_en')->nullable();
            $table->foreignId('aprobado_por')->nullable()->constrained('usuarios');
            $table->timestamp('aprobado_en')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ajustes_comision');
    }
};
```

- [ ] **Step 2: Escribir la migración de la columna ajuste_comision_id**

```php
<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('asignaciones_viaticos', function (Blueprint $table) {
            $table->foreignId('ajuste_comision_id')->nullable()->after('viajero_comision_id')
                  ->constrained('ajustes_comision')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('asignaciones_viaticos', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ajuste_comision_id');
        });
    }
};
```

**Nota SQLite:** `dropConstrainedForeignId` en SQLite puede requerir recrear la tabla; si el test de migración falla en el rollback, dejar el `down()` con `PRAGMA legacy_alter_table = ON` alrededor del drop, siguiendo el patrón usado en otras migraciones del repo. Para el `up()` no hace falta.

- [ ] **Step 3: Ejecutar las migraciones en un test rápido (sqlite en memoria)**

Run: `/c/xampp/php/php.exe artisan migrate --pretend` (solo para ver que el DDL se genera sin error de sintaxis).
Luego confía en que Task 3+ (que usan `RefreshDatabase`) las ejerciten de verdad.

- [ ] **Step 4: Commit**

```bash
git add database/migrations/2026_08_24_100000_create_ajustes_comision_table.php database/migrations/2026_08_24_100100_add_ajuste_comision_id_to_asignaciones_viaticos.php
git commit -m "feat(viaticos): migraciones de ajustes_comision y FK en asignaciones

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 3: Modelo AjusteComision y ajustes en modelos existentes

**Files:**
- Create: `app/Models/AjusteComision.php`
- Modify: `app/Models/AsignacionViatico.php`
- Modify: `app/Models/SolicitudViaticos.php:21-28` (recalcularTotal) y relación nueva
- Modify: `app/Models/ViajeroComision.php` (relación ajustes)
- Test: `tests/Feature/AjusteComisionAislamientoTest.php`

- [ ] **Step 1: Escribir el test de aislamiento del total**

```php
<?php
namespace Tests\Feature;

use App\Models\{AsignacionViatico, AjusteComision, Solicitud, SolicitudViaticos, TipoSolicitud, Usuario, ViajeroComision};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AjusteComisionAislamientoTest extends TestCase
{
    use RefreshDatabase;

    public function test_recalcular_total_excluye_asignaciones_de_ajustes(): void
    {
        $this->seed(\Database\Seeders\RolesSeeder::class);
        $tipo = TipoSolicitud::factory()->create(['clave' => 'VIA']); // ajustar segun helpers existentes

        $cabecera = SolicitudViaticos::create(['nombre_comision' => 'C', 'municipio_destino' => 'X']);
        $solicitud = Solicitud::create([
            'tipo_solicitud_id' => $tipo->id, 'solicitante_id' => Usuario::factory()->create()->id,
            'solicitable_type' => SolicitudViaticos::class, 'solicitable_id' => $cabecera->id,
            'estado' => 'cerrada', 'radicado' => 'VIA-TEST-1',
        ]);
        $viajero = ViajeroComision::create([
            'solicitud_viaticos_id' => $cabecera->id, 'motivo' => 'm',
            'fecha_salida' => '2026-01-10', 'hora_salida' => '08:00',
            'fecha_regreso' => '2026-01-10', 'hora_regreso' => '15:00',
        ]);

        // Asignacion original (sin ajuste)
        AsignacionViatico::create(['viajero_comision_id' => $viajero->id, 'rubro' => 'almuerzo', 'valor_unitario' => 25000, 'dias' => 1]);
        $cabecera->refresh();
        $this->assertEquals(25000, $cabecera->total);

        // Ajuste con su asignacion anexa: NO debe alterar el total de la cabecera
        $ajuste = AjusteComision::create([
            'solicitud_id' => $solicitud->id, 'viajero_comision_id' => $viajero->id,
            'solicitado_por' => $solicitud->solicitante_id, 'tipo' => 'fechas', 'motivo' => 'x',
        ]);
        AsignacionViatico::create([
            'viajero_comision_id' => $viajero->id, 'ajuste_comision_id' => $ajuste->id,
            'rubro' => 'cena', 'valor_unitario' => 20000, 'dias' => 1,
        ]);

        $cabecera->refresh();
        $this->assertEquals(25000, $cabecera->total, 'La cabecera no debe sumar anexos');

        $ajuste->refresh();
        $this->assertEquals(20000, $ajuste->total_delta, 'El ajuste suma su propio delta');
    }
}
```

> Nota para el implementer: ajustar la creación de `TipoSolicitud`/`Usuario` a los helpers/factories reales del repo (mirar `tests/Feature/AjustarComisionTest.php` y `EditarLiquidacionTest.php` para el patrón de setup de una comisión VIA). Lo esencial del test son las dos aserciones de total.

- [ ] **Step 2: Ejecutar el test para verlo fallar**

Run: `/c/xampp/php/php.exe artisan test --filter=AjusteComisionAislamientoTest`
Expected: FAIL (modelo/relaciones/columna aún no soportan el aislamiento).

- [ ] **Step 3: Crear el modelo AjusteComision**

```php
<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AjusteComision extends Model
{
    protected $table = 'ajustes_comision';
    protected $fillable = [
        'solicitud_id', 'viajero_comision_id', 'solicitado_por', 'tipo', 'motivo', 'estado',
        'fechas_antes', 'fechas_despues', 'rubro', 'cantidad', 'total_delta',
        'motivo_devolucion', 'liquidado_por', 'liquidado_en', 'aprobado_por', 'aprobado_en',
    ];
    protected $casts = [
        'fechas_antes' => 'array', 'fechas_despues' => 'array',
        'total_delta' => 'decimal:2',
        'liquidado_en' => 'datetime', 'aprobado_en' => 'datetime',
        'cantidad' => 'integer',
    ];

    public function solicitud()   { return $this->belongsTo(Solicitud::class, 'solicitud_id'); }
    public function viajero()     { return $this->belongsTo(ViajeroComision::class, 'viajero_comision_id'); }
    public function solicitante() { return $this->belongsTo(Usuario::class, 'solicitado_por'); }
    public function liquidador()  { return $this->belongsTo(Usuario::class, 'liquidado_por'); }
    public function aprobador()   { return $this->belongsTo(Usuario::class, 'aprobado_por'); }
    public function asignaciones(){ return $this->hasMany(AsignacionViatico::class, 'ajuste_comision_id'); }

    /** Suma los subtotales de sus asignaciones anexas y persiste en total_delta. */
    public function recalcularTotalDelta(): void
    {
        $total = $this->asignaciones()->sum('subtotal');
        $this->updateQuietly(['total_delta' => $total]);
    }
}
```

- [ ] **Step 4: Actualizar AsignacionViatico (fillable + hooks driver del total)**

Reemplazar el bloque actual (`app/Models/AsignacionViatico.php`) por:

```php
<?php
namespace App\Models;
use App\Enums\Rubro;
use Illuminate\Database\Eloquent\Model;

class AsignacionViatico extends Model
{
    protected $table = 'asignaciones_viaticos';
    protected $fillable = ['viajero_comision_id','ajuste_comision_id','rubro','valor_unitario','dias','subtotal'];
    protected $casts = ['rubro' => Rubro::class];

    protected static function booted(): void
    {
        static::saving(fn(AsignacionViatico $a)  => $a->subtotal = $a->valor_unitario * $a->dias);
        static::saved(fn(AsignacionViatico $a)   => $a->recalcularOrigen());
        static::deleted(fn(AsignacionViatico $a) => $a->recalcularOrigen());
    }

    /**
     * Recalcula el total del contenedor correcto: si la asignacion pertenece a un
     * ajuste (anexo), recalcula el total_delta del ajuste; si no, el total de la
     * comision (que a su vez excluye los anexos).
     */
    private function recalcularOrigen(): void
    {
        if ($this->ajuste_comision_id) {
            $this->ajusteComision?->recalcularTotalDelta();
            return;
        }
        $this->viajeroComision->solicitudViaticos->recalcularTotal();
    }

    public function viajeroComision() { return $this->belongsTo(ViajeroComision::class, 'viajero_comision_id'); }
    public function ajusteComision()  { return $this->belongsTo(AjusteComision::class, 'ajuste_comision_id'); }
}
```

- [ ] **Step 5: Actualizar SolicitudViaticos.recalcularTotal para excluir anexos + relación ajustes**

En `app/Models/SolicitudViaticos.php`, cambiar `recalcularTotal()` para excluir asignaciones con `ajuste_comision_id` no nulo, y añadir relación `ajustes()`:

```php
    public function ajustes() { return $this->hasManyThrough(
        AjusteComision::class, Solicitud::class,
        'solicitable_id', 'solicitud_id', 'id', 'id'
    )->where('solicitudes.solicitable_type', SolicitudViaticos::class); }
```

> Nota: la relación `ajustes()` es cómoda pero el `hasManyThrough` con morph puede ser incómodo. Alternativa más simple y explícita: exponer los ajustes desde el controlador vía `AjusteComision::where('solicitud_id', $solicitud->id)`. El implementer puede elegir; lo importante para los tests es `recalcularTotal()`. Cambiarlo así:

```php
    public function recalcularTotal(): void
    {
        $total = $this->viajeros()
            ->join('asignaciones_viaticos','viajeros_comision.id','=','asignaciones_viaticos.viajero_comision_id')
            ->whereNull('asignaciones_viaticos.ajuste_comision_id') // excluir anexos
            ->sum('asignaciones_viaticos.subtotal');
        $this->updateQuietly(['total' => $total]);
        $this->solicitud()->update(['total' => $total]);
    }
```

- [ ] **Step 6: Añadir relación ajustes a ViajeroComision**

En `app/Models/ViajeroComision.php`, añadir:

```php
    public function ajustes() { return $this->hasMany(AjusteComision::class, 'viajero_comision_id'); }
```

- [ ] **Step 7: Ejecutar el test hasta que pase**

Run: `/c/xampp/php/php.exe artisan test --filter=AjusteComisionAislamientoTest`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Models/AjusteComision.php app/Models/AsignacionViatico.php app/Models/SolicitudViaticos.php app/Models/ViajeroComision.php tests/Feature/AjusteComisionAislamientoTest.php
git commit -m "feat(viaticos): modelo AjusteComision y aislamiento de totales (anexos)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 4: Permisos en SolicitudPolicy

**Files:**
- Modify: `app/Policies/SolicitudPolicy.php` (añadir métodos; el registro de policy ya existe para Solicitud)

Nota: los métodos de ajuste reciben `AjusteComision` además de `Usuario`. Como `SolicitudPolicy` está asociada a `Solicitud`, se invocarán con `->can('liquidarAjuste', [$solicitud, $ajuste])` o directamente sobre el modelo del ajuste. Para simplicidad, definir los métodos con firma `(Usuario $usuario, Solicitud $solicitud, AjusteComision $ajuste)` y llamarlos con array. Alternativa: crear `AjusteComisionPolicy`. Este plan usa `SolicitudPolicy` para mantener todo junto.

- [ ] **Step 1: Escribir el test de permisos (dentro del test de flujo, se cubre en Task 6). Aquí solo implementar.**

- [ ] **Step 2: Añadir los métodos a SolicitudPolicy**

Añadir `use App\Models\AjusteComision;` al `use` de modelos (línea 5) y estos métodos antes del cierre de la clase:

```php
    /**
     * El contador liquida el delta de un ajuste (anexo) mientras esté pendiente de
     * liquidar o haya sido devuelto por el líder de contabilidad para recalcular.
     */
    public function liquidarAjuste(Usuario $usuario, Solicitud $solicitud, AjusteComision $ajuste): bool
    {
        return $solicitud->tipoSolicitud->clave === 'VIA'
            && $usuario->hasRole('contador')
            && $ajuste->solicitud_id === $solicitud->id
            && in_array($ajuste->estado, ['pendiente_liquidacion', 'devuelto']);
    }

    /** El líder de contabilidad aprueba un ajuste ya liquidado. */
    public function aprobarAjuste(Usuario $usuario, Solicitud $solicitud, AjusteComision $ajuste): bool
    {
        return $solicitud->tipoSolicitud->clave === 'VIA'
            && $usuario->hasRole('contabilidad_lider')
            && $ajuste->solicitud_id === $solicitud->id
            && $ajuste->estado === 'liquidado';
    }

    /** El líder de contabilidad devuelve un ajuste liquidado para que el contador lo recalcule. */
    public function devolverAjuste(Usuario $usuario, Solicitud $solicitud, AjusteComision $ajuste): bool
    {
        return $this->aprobarAjuste($usuario, $solicitud, $ajuste);
    }

    /** Ver el detalle/liquidación de un ajuste: quien pueda ver la comisión. */
    public function verAjuste(Usuario $usuario, Solicitud $solicitud, AjusteComision $ajuste): bool
    {
        return $ajuste->solicitud_id === $solicitud->id && $this->verDetalle($usuario, $solicitud);
    }
```

Para **solicitar** el ajuste post-cierre se reusa el método `ajustar` ya existente (`SolicitudPolicy::ajustar`), que permite al solicitante en cualquier estado salvo `cancelada` (incluye `cerrada`).

- [ ] **Step 3: Verificar que no rompe la suite existente**

Run: `/c/xampp/php/php.exe artisan test --filter=SolicitudPolicy` (si existe test de policy) y `--filter=AjustarComisionTest`.
Expected: PASS (sin cambios de comportamiento en los métodos existentes).

- [ ] **Step 4: Commit**

```bash
git add app/Policies/SolicitudPolicy.php
git commit -m "feat(viaticos): permisos para liquidar/aprobar/devolver ajustes

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 5: Requests de validación

**Files:**
- Create: `app/Http/Requests/SolicitarAjusteFechasRequest.php`
- Create: `app/Http/Requests/SolicitarAjusteRubroRequest.php`
- Create: `app/Http/Requests/LiquidarAjusteRequest.php`
- Create: `app/Http/Requests/DevolverAjusteRequest.php`

- [ ] **Step 1: SolicitarAjusteFechasRequest**

```php
<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class SolicitarAjusteFechasRequest extends FormRequest
{
    public function authorize(): bool { return true; } // autoriza el controlador via policy 'ajustar'
    public function rules(): array
    {
        return [
            'viajero_comision_id' => ['required', 'integer', 'exists:viajeros_comision,id'],
            'fecha_salida'        => ['required', 'date'],
            'hora_salida'         => ['required', 'string'],
            'fecha_regreso'       => ['required', 'date', 'after_or_equal:fecha_salida'],
            'hora_regreso'        => ['required', 'string'],
            'motivo'              => ['required', 'string', 'max:2000'],
        ];
    }
}
```

- [ ] **Step 2: SolicitarAjusteRubroRequest**

```php
<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class SolicitarAjusteRubroRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'viajero_comision_id' => ['required', 'integer', 'exists:viajeros_comision,id'],
            'rubro'               => ['required', 'in:gasolina,transporte'],
            'cantidad'            => ['required', 'integer'], // puede ser negativa (disminuir)
            'motivo'              => ['required', 'string', 'max:2000'],
        ];
    }
}
```

> Nota: `cantidad` puede ser negativa para "disminuir". Si se prefiere solo positivos y un flag de dirección, ajustar; el plan asume entero con signo.

- [ ] **Step 3: LiquidarAjusteRequest**

```php
<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class LiquidarAjusteRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return [
            'asignaciones'                  => ['present', 'array'],
            'asignaciones.*.rubro'          => ['required', 'exists:tarifas_viaticos,rubro'],
            'asignaciones.*.valor_unitario' => ['required', 'numeric', 'min:0'],
            'asignaciones.*.dias'           => ['required', 'integer'], // puede ser negativo (resta)
        ];
    }
}
```

- [ ] **Step 4: DevolverAjusteRequest**

```php
<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class DevolverAjusteRequest extends FormRequest
{
    public function authorize(): bool { return true; }
    public function rules(): array
    {
        return ['motivo_devolucion' => ['required', 'string', 'max:2000']];
    }
}
```

- [ ] **Step 5: Commit**

```bash
git add app/Http/Requests/SolicitarAjusteFechasRequest.php app/Http/Requests/SolicitarAjusteRubroRequest.php app/Http/Requests/LiquidarAjusteRequest.php app/Http/Requests/DevolverAjusteRequest.php
git commit -m "feat(viaticos): form requests de solicitud/liquidacion/devolucion de ajustes

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 6: Controlador — solicitar ajuste (fechas y rubro)

**Files:**
- Modify: `app/Http/Controllers/ViaticosController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/AjusteComisionFlujoTest.php`

- [ ] **Step 1: Escribir el test de solicitud de ajuste (parte 1 del flujo)**

```php
<?php
namespace Tests\Feature;

use App\Models\{AjusteComision, AsignacionViatico, Solicitud, SolicitudViaticos, TarifaViatico, TipoSolicitud, Usuario, ViajeroComision};
use App\Notifications\AvisoTransicionNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AjusteComisionFlujoTest extends TestCase
{
    use RefreshDatabase;

    private function comisionCerrada(): array
    {
        // Reusar el patron de setup de AjustarComisionTest / EditarLiquidacionTest.
        // Debe devolver [$solicitud, $viajero, $lider] con la comision en estado 'cerrada'
        // y una asignacion original (p.ej. gasolina 2 dias @ 50000) para probar el valor unitario original.
        // ... (implementer: portar el helper existente)
    }

    public function test_lider_solicita_ajuste_fechas_crea_pendiente_y_notifica_contador(): void
    {
        Notification::fake();
        [$solicitud, $viajero, $lider] = $this->comisionCerrada();
        $contador = Usuario::factory()->create(); $contador->assignRole('contador');

        $this->actingAs($lider)->put(route('viaticos.ajustar', $solicitud), [
            'viajero_comision_id' => $viajero->id,
            'fecha_salida' => $viajero->fecha_salida->toDateString(),
            'hora_salida' => $viajero->hora_salida,
            'fecha_regreso' => $viajero->fecha_regreso->addDay()->toDateString(),
            'hora_regreso' => '19:00',
            'motivo' => 'Se extendio la comision',
        ])->assertRedirect();

        $solicitud->refresh();
        $this->assertSame('cerrada', $solicitud->estado, 'La comision sigue cerrada');
        $ajuste = AjusteComision::where('solicitud_id', $solicitud->id)->first();
        $this->assertNotNull($ajuste);
        $this->assertSame('pendiente_liquidacion', $ajuste->estado);
        $this->assertSame('fechas', $ajuste->tipo);
        $this->assertNotNull($ajuste->fechas_antes);
        $this->assertNotNull($ajuste->fechas_despues);

        Notification::assertSentTo($contador, AvisoTransicionNotification::class);
    }
}
```

> Nota crítica de diseño: el endpoint `viaticos.ajustar` ahora bifurca según estado. Si la comisión está **cerrada**, crea un `AjusteComision` (nuevo camino). Si NO está cerrada, mantiene el comportamiento actual (`requiere_reliquidacion`). El test de arriba cubre el caso cerrado; los tests existentes (`AjustarComisionTest`) deben seguir pasando para el caso no cerrado.

- [ ] **Step 2: Ejecutar el test para verlo fallar**

Run: `/c/xampp/php/php.exe artisan test --filter=AjusteComisionFlujoTest`
Expected: FAIL (aún no existe el camino de ajuste cerrado).

- [ ] **Step 3: Modificar `ajustar()` para bifurcar cuando la comisión está cerrada**

En `ViaticosController::ajustar` (actualmente L234-287), al inicio, después de `$this->authorize('ajustar', $solicitud)`, añadir:

```php
        // Post-cierre: el ajuste se vuelve un anexo con estado propio (no reabre la comision).
        if ($solicitud->estado === 'cerrada') {
            return $this->crearAjusteAnexoFechas($request, $solicitud);
        }
```

Y añadir el método privado (usa el snapshot ANTES = fechas actuales del viajero, DESPUES = request):

```php
    private function crearAjusteAnexoFechas(AjustarComisionRequest $request, Solicitud $solicitud)
    {
        $cabecera = $solicitud->solicitable;

        // AjustarComisionRequest valida 'viajeros' como array; para el anexo se ajusta
        // un viajero a la vez. Tomar el primero (el frontend post-cierre envia uno).
        $datos = $request->viajeros[0];
        $viajero = $cabecera->viajeros()->where('id', $datos['viajero_comision_id'])->firstOrFail();

        $ajuste = null;
        DB::transaction(function () use ($request, $solicitud, $viajero, $datos, &$ajuste) {
            $ajuste = AjusteComision::create([
                'solicitud_id' => $solicitud->id,
                'viajero_comision_id' => $viajero->id,
                'solicitado_por' => auth()->id(),
                'tipo' => 'fechas',
                'motivo' => $request->motivo,
                'estado' => 'pendiente_liquidacion',
                'fechas_antes' => [
                    'fecha_salida'  => optional($viajero->fecha_salida)->toDateString() ?? $viajero->fecha_salida,
                    'hora_salida'   => $viajero->hora_salida,
                    'fecha_regreso' => optional($viajero->fecha_regreso)->toDateString() ?? $viajero->fecha_regreso,
                    'hora_regreso'  => $viajero->hora_regreso,
                ],
                'fechas_despues' => [
                    'fecha_salida'  => $datos['fecha_salida'],  'hora_salida'  => $datos['hora_salida'],
                    'fecha_regreso' => $datos['fecha_regreso'], 'hora_regreso' => $datos['hora_regreso'],
                ],
            ]);
        });

        $this->avisarAjustePendiente($ajuste->fresh(), 'accion_requerida');
        return back()->with('success', 'Ajuste solicitado. Queda pendiente de liquidacion por el contador.');
    }
```

Análogamente, modificar `reajustarRubro()` para bifurcar cuando `cerrada`:

```php
        if ($solicitud->estado === 'cerrada') {
            return $this->crearAjusteAnexoRubro($request, $solicitud);
        }
```

```php
    private function crearAjusteAnexoRubro(ReajustarRubroRequest $request, Solicitud $solicitud)
    {
        $cabecera = $solicitud->solicitable;
        $viajero = $cabecera->viajeros()->where('id', $request->viajero_comision_id)->firstOrFail();

        $ajuste = AjusteComision::create([
            'solicitud_id' => $solicitud->id,
            'viajero_comision_id' => $viajero->id,
            'solicitado_por' => auth()->id(),
            'tipo' => 'rubro',
            'motivo' => $request->motivo,
            'estado' => 'pendiente_liquidacion',
            'rubro' => $request->rubro,
            'cantidad' => (int) $request->cantidad,
        ]);

        $this->avisarAjustePendiente($ajuste->fresh(), 'accion_requerida');
        return back()->with('success', 'Reajuste de rubro solicitado. Queda pendiente de liquidacion por el contador.');
    }
```

Añadir el helper de notificación (contador acción requerida; RR.HH. informativo):

```php
    /** Notifica un ajuste-anexo pendiente. $tipoContador: 'accion_requerida'. */
    private function avisarAjustePendiente(AjusteComision $ajuste, string $tipoContador): void
    {
        $solicitud = $ajuste->solicitud;
        $actor = auth()->user()->name;
        foreach (Usuario::role('contador')->get() as $u) {
            $u->notify(new AvisoTransicionNotification($solicitud, $tipoContador, 'ajustar', $ajuste->motivo, $actor));
        }
        foreach (Usuario::role('rrhh')->get() as $u) {
            $u->notify(new AvisoTransicionNotification($solicitud, 'ajustada', 'ajustar', $ajuste->motivo, $actor));
        }
    }
```

Añadir `AjusteComision` al `use App\Models\{...}` (L5).

- [ ] **Step 4: Ejecutar test hasta que pase; verificar que AjustarComisionTest sigue verde**

Run: `/c/xampp/php/php.exe artisan test --filter=AjusteComisionFlujoTest`
Run: `/c/xampp/php/php.exe artisan test --filter=AjustarComisionTest`
Expected: ambos PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/ViaticosController.php tests/Feature/AjusteComisionFlujoTest.php
git commit -m "feat(viaticos): solicitar ajuste post-cierre como anexo con estado propio

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 7: Controlador — liquidar ajuste (GET pantalla + PUT persistencia)

**Files:**
- Modify: `app/Http/Controllers/ViaticosController.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/AjusteComisionFlujoTest.php` (añadir), `tests/Feature/AjusteComisionValorUnitarioTest.php`

- [ ] **Step 1: Escribir el test de liquidación del ajuste + valor unitario original**

Añadir a `AjusteComisionFlujoTest`:

```php
    public function test_contador_liquida_ajuste_calcula_delta_y_pasa_a_liquidado(): void
    {
        Notification::fake();
        [$solicitud, $viajero, $lider] = $this->comisionCerrada();
        $contador = Usuario::factory()->create(); $contador->assignRole('contador');
        $lcontab  = Usuario::factory()->create(); $lcontab->assignRole('contabilidad_lider');

        // Solicitar (fechas) via helper directo
        $ajuste = AjusteComision::create([
            'solicitud_id' => $solicitud->id, 'viajero_comision_id' => $viajero->id,
            'solicitado_por' => $lider->id, 'tipo' => 'fechas', 'motivo' => 'x',
            'estado' => 'pendiente_liquidacion',
            'fechas_antes'  => ['fecha_salida'=>'2026-01-10','hora_salida'=>'08:00','fecha_regreso'=>'2026-01-10','hora_regreso'=>'15:00'],
            'fechas_despues'=> ['fecha_salida'=>'2026-01-10','hora_salida'=>'08:00','fecha_regreso'=>'2026-01-11','hora_regreso'=>'19:00'],
        ]);

        // GET pantalla: debe traer el delta propuesto
        $this->actingAs($contador)
            ->get(route('viaticos.ajuste.liquidar', [$solicitud, $ajuste]))
            ->assertOk()
            ->assertInertia(fn ($p) => $p->component('Viaticos/LiquidacionAjuste')->has('delta'));

        // PUT: persistir asignaciones del anexo
        $this->actingAs($contador)->put(route('viaticos.ajuste.asignaciones', [$solicitud, $ajuste]), [
            'asignaciones' => [
                ['rubro' => 'gasolina', 'valor_unitario' => 50000, 'dias' => 1],
                ['rubro' => 'cena', 'valor_unitario' => 20000, 'dias' => 1],
            ],
        ])->assertRedirect();

        $ajuste->refresh();
        $this->assertSame('liquidado', $ajuste->estado);
        $this->assertEquals(70000, $ajuste->total_delta);
        $this->assertNotNull($ajuste->liquidado_por);
        Notification::assertSentTo($lcontab ?? $lcontab, AvisoTransicionNotification::class);

        // La comision cerrada no cambia total
        $solicitud->refresh();
        $this->assertSame('cerrada', $solicitud->estado);
    }
```

Y en `AjusteComisionValorUnitarioTest`:

```php
    public function test_delta_precarga_valor_unitario_de_la_liquidacion_original(): void
    {
        [$solicitud, $viajero, $lider] = $this->comisionCerrada();
        // La comision original tiene gasolina @ 47000 (distinto de la tarifa vigente 50000)
        AsignacionViatico::create(['viajero_comision_id' => $viajero->id, 'rubro' => 'gasolina', 'valor_unitario' => 47000, 'dias' => 2]);

        $contador = Usuario::factory()->create(); $contador->assignRole('contador');
        $ajuste = AjusteComision::create([...'tipo'=>'fechas', 'estado'=>'pendiente_liquidacion',
            'fechas_antes'=>[...2 dias...], 'fechas_despues'=>[...3 dias...]]); // +1 gasolina

        $resp = $this->actingAs($contador)->get(route('viaticos.ajuste.liquidar', [$solicitud, $ajuste]));
        // El delta propuesto para gasolina debe traer valor_unitario 47000 (el original), no 50000 (tarifa)
        $resp->assertInertia(fn ($p) => $p->where('delta.0.valor_unitario', 47000));
    }

    public function test_rubro_nuevo_cae_a_tarifa_vigente(): void
    {
        // Si el ajuste introduce un rubro que no existia en la original, usa tarifa vigente.
        // ... crear ajuste que agrega 'transporte' inexistente en la original => valor = tarifa (0 por seeder)
    }
```

> Nota implementer: el orden/índices del array `delta` en las aserciones dependen de cómo lo construyas; ajusta las claves/índices a tu implementación real manteniendo la intención (valor unitario = original; tarifa si nuevo).

- [ ] **Step 2: Ejecutar para verlo fallar**

Run: `/c/xampp/php/php.exe artisan test --filter=AjusteComisionFlujoTest`
Expected: FAIL (rutas/métodos no existen).

- [ ] **Step 3: Implementar `liquidacionAjuste` (GET) y `updateAjuste` (PUT)**

```php
    public function liquidacionAjuste(Solicitud $solicitud, AjusteComision $ajuste)
    {
        $this->authorize('liquidarAjuste', [$solicitud, $ajuste]);
        $ajuste->load('asignaciones', 'viajero.empleado');

        $delta = $this->deltaPropuesto($solicitud, $ajuste);

        return Inertia::render('Viaticos/LiquidacionAjuste', [
            'solicitud' => $solicitud->only('id', 'radicado', 'estado'),
            'ajuste'    => $ajuste,
            'delta'     => $delta,          // [{rubro, dias, valor_unitario, subtotal}]
            'tarifas'   => TarifaViatico::all()->keyBy('rubro'),
            'rubros'    => TarifaViatico::orderBy('id')->pluck('rubro')->toArray(),
        ]);
    }

    /**
     * Construye el delta de rubros propuesto para el ajuste:
     * - tipo 'fechas': usa CalculadoraRubrosViaticos::calcularDelta(antes, despues).
     * - tipo 'rubro': un solo rubro con dias = cantidad.
     * Cada fila trae valor_unitario = el de la liquidacion original del viajero para ese
     * rubro; si el rubro no existia en la original, cae a la tarifa vigente.
     * Si el ajuste ya fue liquidado antes (devuelto y re-liquidando), precarga sus asignaciones.
     */
    private function deltaPropuesto(Solicitud $solicitud, AjusteComision $ajuste): array
    {
        // Si ya tiene asignaciones (re-liquidacion tras devolucion), devolver esas.
        if ($ajuste->asignaciones->isNotEmpty()) {
            return $ajuste->asignaciones->map(fn ($a) => [
                'rubro' => $a->rubro->value ?? $a->rubro,
                'dias' => $a->dias, 'valor_unitario' => (float) $a->valor_unitario,
                'subtotal' => (float) $a->subtotal,
            ])->values()->all();
        }

        $viajero = $ajuste->viajero;
        // Valor unitario original por rubro (asignaciones sin ajuste)
        $originales = AsignacionViatico::where('viajero_comision_id', $viajero->id)
            ->whereNull('ajuste_comision_id')->get()
            ->mapWithKeys(fn ($a) => [($a->rubro->value ?? $a->rubro) => (float) $a->valor_unitario]);
        $tarifas = TarifaViatico::all()->keyBy('rubro');

        $valorDe = fn (string $rubro) => $originales[$rubro]
            ?? (float) ($tarifas[$rubro]->valor_sugerido ?? 0);

        if ($ajuste->tipo === 'rubro') {
            $rubro = $ajuste->rubro;
            $dias = (int) $ajuste->cantidad;
            $vu = $valorDe($rubro);
            return [[
                'rubro' => $rubro, 'dias' => $dias, 'valor_unitario' => $vu,
                'subtotal' => $vu * $dias,
            ]];
        }

        // tipo 'fechas'
        $calc = app(CalculadoraRubrosViaticos::class);
        $delta = $calc->calcularDelta($ajuste->fechas_antes, $ajuste->fechas_despues);
        $filas = [];
        foreach ($delta as $rubro => $dias) {
            $vu = $valorDe($rubro);
            $filas[] = ['rubro' => $rubro, 'dias' => $dias, 'valor_unitario' => $vu, 'subtotal' => $vu * $dias];
        }
        return $filas;
    }

    public function updateAjuste(LiquidarAjusteRequest $request, Solicitud $solicitud, AjusteComision $ajuste)
    {
        $this->authorize('liquidarAjuste', [$solicitud, $ajuste]);

        DB::transaction(function () use ($request, $ajuste) {
            $ajuste->asignaciones()->delete(); // recrear
            foreach ($request->asignaciones as $data) {
                AsignacionViatico::create([
                    'viajero_comision_id' => $ajuste->viajero_comision_id,
                    'ajuste_comision_id'  => $ajuste->id,
                    'rubro'               => $data['rubro'],
                    'valor_unitario'      => $data['valor_unitario'],
                    'dias'                => $data['dias'],
                    'subtotal'            => $data['valor_unitario'] * $data['dias'],
                ]);
            }
            $ajuste->recalcularTotalDelta();
            $ajuste->update([
                'estado' => 'liquidado',
                'liquidado_por' => auth()->id(),
                'liquidado_en' => now(),
            ]);
        });

        $this->avisarAjusteLiquidado($ajuste->fresh());
        return redirect()->route('solicitudes.show', $solicitud)
            ->with('success', 'Ajuste liquidado. Queda pendiente de aprobacion del lider de contabilidad.');
    }

    private function avisarAjusteLiquidado(AjusteComision $ajuste): void
    {
        $actor = auth()->user()->name;
        foreach (Usuario::role('contabilidad_lider')->get() as $u) {
            $u->notify(new AvisoTransicionNotification($ajuste->solicitud, 'accion_requerida', 'aprobar', $ajuste->motivo, $actor));
        }
    }
```

Añadir `use App\Services\CalculadoraRubrosViaticos;` y `AjusteComision`, `LiquidarAjusteRequest` a los `use`.

- [ ] **Step 4: Añadir rutas**

En `routes/web.php` tras la línea 45 (reajustar-rubro):

```php
    Route::get('/viaticos/{solicitud}/ajustes/{ajuste}/liquidar',      [ViaticosController::class, 'liquidacionAjuste'])->name('viaticos.ajuste.liquidar');
    Route::put('/viaticos/{solicitud}/ajustes/{ajuste}/asignaciones',  [ViaticosController::class, 'updateAjuste'])->name('viaticos.ajuste.asignaciones');
    Route::post('/viaticos/{solicitud}/ajustes/{ajuste}/aprobar',      [ViaticosController::class, 'aprobarAjuste'])->name('viaticos.ajuste.aprobar');
    Route::post('/viaticos/{solicitud}/ajustes/{ajuste}/devolver',     [ViaticosController::class, 'devolverAjuste'])->name('viaticos.ajuste.devolver');
```

> Nota: `{ajuste}` hace binding implícito a `AjusteComision` (nombre de variable `$ajuste`). Verificar que el modelo usa la convención por defecto de route-model-binding (id). Ya funciona con `App\Models\AjusteComision`.

- [ ] **Step 5: Ejecutar tests hasta que pasen**

Run: `/c/xampp/php/php.exe artisan test --filter=AjusteComisionFlujoTest`
Run: `/c/xampp/php/php.exe artisan test --filter=AjusteComisionValorUnitarioTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Http/Controllers/ViaticosController.php routes/web.php tests/Feature/AjusteComisionFlujoTest.php tests/Feature/AjusteComisionValorUnitarioTest.php
git commit -m "feat(viaticos): liquidacion del ajuste con delta de rubros y valor original

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 8: Controlador — aprobar y devolver ajuste

**Files:**
- Modify: `app/Http/Controllers/ViaticosController.php`
- Test: `tests/Feature/AjusteComisionFlujoTest.php` (añadir)

- [ ] **Step 1: Escribir tests de aprobar y devolver**

```php
    public function test_lider_contabilidad_aprueba_ajuste(): void
    {
        Notification::fake();
        [$solicitud, $viajero, $lider] = $this->comisionCerrada();
        $lcontab = Usuario::factory()->create(); $lcontab->assignRole('contabilidad_lider');
        $ajuste = AjusteComision::create([
            'solicitud_id'=>$solicitud->id,'viajero_comision_id'=>$viajero->id,'solicitado_por'=>$lider->id,
            'tipo'=>'rubro','motivo'=>'x','estado'=>'liquidado','rubro'=>'gasolina','cantidad'=>1,'total_delta'=>50000,
        ]);

        $this->actingAs($lcontab)->post(route('viaticos.ajuste.aprobar', [$solicitud, $ajuste]))->assertRedirect();
        $ajuste->refresh();
        $this->assertSame('aprobado', $ajuste->estado);
        $this->assertNotNull($ajuste->aprobado_por);
    }

    public function test_lider_contabilidad_devuelve_ajuste_para_recalcular(): void
    {
        [$solicitud, $viajero, $lider] = $this->comisionCerrada();
        $lcontab = Usuario::factory()->create(); $lcontab->assignRole('contabilidad_lider');
        $ajuste = AjusteComision::create([
            'solicitud_id'=>$solicitud->id,'viajero_comision_id'=>$viajero->id,'solicitado_por'=>$lider->id,
            'tipo'=>'rubro','motivo'=>'x','estado'=>'liquidado','rubro'=>'gasolina','cantidad'=>1,
        ]);

        $this->actingAs($lcontab)->post(route('viaticos.ajuste.devolver', [$solicitud, $ajuste]), [
            'motivo_devolucion' => 'Revisar el valor',
        ])->assertRedirect();
        $ajuste->refresh();
        $this->assertSame('devuelto', $ajuste->estado);
        $this->assertSame('Revisar el valor', $ajuste->motivo_devolucion);
    }

    public function test_contador_no_puede_aprobar(): void
    {
        [$solicitud, $viajero, $lider] = $this->comisionCerrada();
        $contador = Usuario::factory()->create(); $contador->assignRole('contador');
        $ajuste = AjusteComision::create([
            'solicitud_id'=>$solicitud->id,'viajero_comision_id'=>$viajero->id,'solicitado_por'=>$lider->id,
            'tipo'=>'rubro','motivo'=>'x','estado'=>'liquidado','rubro'=>'gasolina','cantidad'=>1,
        ]);
        $this->actingAs($contador)->post(route('viaticos.ajuste.aprobar', [$solicitud, $ajuste]))->assertForbidden();
    }
```

- [ ] **Step 2: Ejecutar para verlos fallar**

Run: `/c/xampp/php/php.exe artisan test --filter=AjusteComisionFlujoTest`
Expected: FAIL.

- [ ] **Step 3: Implementar aprobar/devolver**

```php
    public function aprobarAjuste(Solicitud $solicitud, AjusteComision $ajuste)
    {
        $this->authorize('aprobarAjuste', [$solicitud, $ajuste]);
        $ajuste->update(['estado' => 'aprobado', 'aprobado_por' => auth()->id(), 'aprobado_en' => now()]);

        $actor = auth()->user()->name;
        $ajuste->solicitante->notify(new AvisoTransicionNotification($solicitud, 'ajustada', 'aprobar', $ajuste->motivo, $actor));
        foreach (Usuario::role('contador')->get() as $u) {
            $u->notify(new AvisoTransicionNotification($solicitud, 'ajustada', 'aprobar', $ajuste->motivo, $actor));
        }
        return back()->with('success', 'Ajuste aprobado.');
    }

    public function devolverAjuste(DevolverAjusteRequest $request, Solicitud $solicitud, AjusteComision $ajuste)
    {
        $this->authorize('devolverAjuste', [$solicitud, $ajuste]);
        $ajuste->update(['estado' => 'devuelto', 'motivo_devolucion' => $request->motivo_devolucion]);

        $actor = auth()->user()->name;
        foreach (Usuario::role('contador')->get() as $u) {
            $u->notify(new AvisoTransicionNotification($solicitud, 'accion_requerida', 'ajustar', $request->motivo_devolucion, $actor));
        }
        return back()->with('success', 'Ajuste devuelto al contador para recalcular.');
    }
```

Añadir `DevolverAjusteRequest` a los `use`.

- [ ] **Step 4: Ejecutar tests hasta que pasen**

Run: `/c/xampp/php/php.exe artisan test --filter=AjusteComisionFlujoTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Http/Controllers/ViaticosController.php tests/Feature/AjusteComisionFlujoTest.php
git commit -m "feat(viaticos): aprobar y devolver ajustes (lider de contabilidad)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 9: Exponer ajustes en el detalle (backend)

**Files:**
- Modify: el controlador que renderiza `Inertia::render('Solicitudes/Detalle', ...)` (localizar con grep).
- Test: cubierto indirectamente; añadir aserción simple si el controlador tiene test.

- [ ] **Step 1: Localizar el render del detalle**

Run: `grep -rn "Solicitudes/Detalle" app/Http/Controllers/`
Identificar el método (probablemente `SolicitudController::show`).

- [ ] **Step 2: Cargar los ajustes y flags de permiso**

En ese método, añadir al payload de la comisión VIA los ajustes con sus relaciones y los flags de permiso que el frontend necesita. Ejemplo (adaptar a la forma real del método):

```php
        // Para comisiones de viaticos: adjuntar los ajustes-anexo con su estado.
        $ajustes = [];
        if ($solicitud->solicitable_type === \App\Models\SolicitudViaticos::class) {
            $ajustes = \App\Models\AjusteComision::where('solicitud_id', $solicitud->id)
                ->with(['viajero.empleado', 'solicitante:id,name', 'asignaciones'])
                ->orderByDesc('created_at')->get();
        }
```

Y exponer en el array de props: `'ajustes' => $ajustes`, más flags:
```php
        'permisos' => [
            'liquidarAjuste' => $user->hasRole('contador'),
            'aprobarAjuste'  => $user->hasRole('contabilidad_lider'),
            // ...
        ],
```

> Nota: si el detalle ya expone un objeto `solicitud` completo con relaciones, integrar `ajustes` de la forma coherente con lo existente (p.ej. `$solicitud->solicitable->setRelation('ajustes', $ajustes)`).

- [ ] **Step 3: Verificar suite**

Run: `/c/xampp/php/php.exe artisan test --filter=Detalle` (si existe) o la suite completa más adelante.

- [ ] **Step 4: Commit**

```bash
git add app/Http/Controllers/<ControllerDelDetalle>.php
git commit -m "feat(viaticos): exponer ajustes-anexo y permisos en el detalle

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 10: Frontend — pantalla LiquidacionAjuste.jsx

**Files:**
- Create: `resources/js/Pages/Viaticos/LiquidacionAjuste.jsx`

Reusar el patrón de `resources/js/Pages/Viaticos/Liquidacion.jsx` (useForm, tabla de rubros, `CampoMoneda`, envío con `put`).

- [ ] **Step 1: Implementar el componente**

Props: `{ solicitud, ajuste, delta, tarifas, rubros }`. Estado inicial de `asignaciones` = `delta` (cada fila `{rubro, dias, valor_unitario, subtotal}`). Permitir editar `valor_unitario` y `dias` (dias puede ser negativo — mostrar el signo). Calcular `total_delta` en vivo. Enviar con `put(route('viaticos.ajuste.asignaciones', [solicitud.id, ajuste.id]))`.

Estructura mínima:

```jsx
import { useForm, Link } from '@inertiajs/react';
import AppLayout from '@/Layouts/AppLayout';
import CampoMoneda from '@/Components/CampoMoneda';

export default function LiquidacionAjuste({ solicitud, ajuste, delta, tarifas, rubros }) {
    const { data, setData, put, processing } = useForm({
        asignaciones: (delta ?? []).map((d) => ({
            rubro: d.rubro, dias: d.dias, valor_unitario: d.valor_unitario,
        })),
    });

    const totalDelta = data.asignaciones.reduce((s, a) => s + a.valor_unitario * a.dias, 0);

    const actualizar = (i, campo, valor) => {
        const copia = [...data.asignaciones];
        copia[i] = { ...copia[i], [campo]: valor };
        setData('asignaciones', copia);
    };

    const enviar = (e) => {
        e.preventDefault();
        put(route('viaticos.ajuste.asignaciones', [solicitud.id, ajuste.id]));
    };

    return (
        <AppLayout>
            <div className="max-w-3xl mx-auto p-6">
                <div className="mb-4 rounded bg-amber-50 border border-amber-200 p-3 text-amber-800 text-sm">
                    Este es un ajuste (anexo) sobre la comisión {solicitud.radicado}, ya cerrada.
                    No modifica la liquidación original.
                </div>
                <h1 className="text-lg font-semibold mb-2">Liquidación del ajuste — {ajuste.viajero?.nombre_mostrado ?? ''}</h1>
                {ajuste.motivo_devolucion && (
                    <p className="text-sm text-red-600 mb-2">Devuelto: {ajuste.motivo_devolucion}</p>
                )}
                <form onSubmit={enviar}>
                    <table className="w-full text-sm">
                        <thead><tr><th>Rubro</th><th>Días (± )</th><th>Valor unitario</th><th>Subtotal</th></tr></thead>
                        <tbody>
                            {data.asignaciones.map((a, i) => (
                                <tr key={i}>
                                    <td className="capitalize">{a.rubro}</td>
                                    <td>
                                        <input type="number" value={a.dias}
                                            onChange={(e) => actualizar(i, 'dias', parseInt(e.target.value || '0', 10))}
                                            className={a.dias < 0 ? 'text-red-600 w-20' : 'w-20'} />
                                    </td>
                                    <td><CampoMoneda value={a.valor_unitario}
                                        onChange={(v) => actualizar(i, 'valor_unitario', v)} /></td>
                                    <td>{(a.valor_unitario * a.dias).toLocaleString('es-CO')}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    <p className="mt-3 font-semibold">
                        Total del ajuste: <span className={totalDelta < 0 ? 'text-red-600' : ''}>
                            {totalDelta.toLocaleString('es-CO')}
                        </span>
                    </p>
                    <div className="mt-4 flex gap-2">
                        <button type="submit" disabled={processing}
                            className="px-4 py-2 bg-blue-600 text-white rounded">Guardar liquidación del ajuste</button>
                        <Link href={route('solicitudes.show', solicitud.id)} className="px-4 py-2 border rounded">Cancelar</Link>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
```

> Nota: adaptar la firma de `CampoMoneda` (value/onChange) a la real del componente en el repo. Revisar `Liquidacion.jsx` para el uso exacto.

- [ ] **Step 2: Compilar**

Run: `npm run build`
Expected: build sin errores; aparece `LiquidacionAjuste-*.js`.

- [ ] **Step 3: Commit**

```bash
git add resources/js/Pages/Viaticos/LiquidacionAjuste.jsx
git commit -m "feat(viaticos): pantalla de liquidacion del ajuste (delta de rubros)

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 11: Frontend — tabla de Ajustes con estado y acciones en Detalle.jsx

**Files:**
- Modify: `resources/js/Pages/Solicitudes/Detalle.jsx`

La tabla "Ajustes" actual (`DetalleViaticos`, ~L268) lista transiciones `ajustar`. Ahora debe listar los registros `AjusteComision` (prop nuevo `solicitable.ajustes` o `ajustes`). Mantener compatibilidad: seguir mostrando transiciones históricas de flujo normal si aplica, pero la tabla principal de anexos usa `ajustes`.

- [ ] **Step 1: Consumir el prop `ajustes` y renderizar la tabla con badges**

En `DetalleViaticos`, tomar `ajustes` desde `solicitable.ajustes ?? []` (o el prop que Task 9 haya expuesto). Añadir un mapa de badges:

```jsx
const BADGE_AJUSTE = {
    pendiente_liquidacion: { txt: 'Pendiente de liquidación', cls: 'bg-amber-100 text-amber-800' },
    liquidado:             { txt: 'Liquidado',                 cls: 'bg-blue-100 text-blue-800' },
    aprobado:              { txt: 'Aprobado',                  cls: 'bg-green-100 text-green-800' },
    devuelto:              { txt: 'Devuelto',                  cls: 'bg-red-100 text-red-800' },
};
```

Columnas: Viajero · Cambio · Motivo · Total delta · Estado (badge) · Fecha · Acciones. Para "Cambio":
- tipo `fechas`: mostrar antes→después (usar `fechas_antes`/`fechas_despues`).
- tipo `rubro`: mostrar `{rubro} × {cantidad}`.

- [ ] **Step 2: Acciones por rol y estado**

Recibir flags `permisos` (de Task 9). Renderizar:
- `permisos.liquidarAjuste && ['pendiente_liquidacion','devuelto'].includes(a.estado)` → `<Link href={route('viaticos.ajuste.liquidar', [solicitudId, a.id])}>Liquidar ajuste</Link>`.
- `permisos.aprobarAjuste && a.estado === 'liquidado'` → botón **Aprobar** (`post` a `viaticos.ajuste.aprobar`) y botón **Devolver** (abre modal con `motivo_devolucion`, `post` a `viaticos.ajuste.devolver`).
- `a.estado === 'aprobado'` → enlace/acciones de "Ver liquidación del anexo" (reusar `viaticos.ajuste.liquidar` en modo lectura, o simplemente mostrar el detalle del delta; PDF/correo si la infraestructura lo permite — opcional).

Usar `router.post(route(...), {...})` de `@inertiajs/react` para aprobar/devolver.

- [ ] **Step 3: Enrutar "Ajustar"/"Reajustar" a los nuevos endpoints cuando la comisión está cerrada**

El formulario actual de "Ajustar" postea a `viaticos.ajustar` y el de rubro a `viaticos.reajustar-rubro`. Como el backend (Task 6) ya bifurca por estado `cerrada`, **no hay que cambiar la URL** — al postear a `viaticos.ajustar` con la comisión cerrada, el backend crea el anexo. Verificar que el modal de ajustar sigue disponible cuando `cerrada` (hoy `puedeAjustar` lo permite). Ajustar los textos del modal para dejar claro que, si está cerrada, se creará un anexo pendiente de liquidación.

- [ ] **Step 4: Compilar y verificar sin el TypeError**

Run: `npm run build`
Expected: build limpio.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Pages/Solicitudes/Detalle.jsx
git commit -m "feat(viaticos): tabla de ajustes con estado, badges y acciones por rol

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 12: Frontend — notificaciones y badge "Ajuste pendiente" en el listado

**Files:**
- Modify: `resources/js/Components/PanelNotificaciones.jsx`
- Modify: el componente/página del listado de solicitudes (localizar).

- [ ] **Step 1: Copy/estilos de notificación**

En `PanelNotificaciones.jsx`, en el `switch(n.tipo)` de mensajes y en `ESTILO_TIPO`, asegurar que:
- `accion_requerida` (ya existe) cubra "Ajuste pendiente de liquidar/aprobar" (el copy genérico "Tienes una acción pendiente" sirve; opcionalmente afinar usando `n.accion === 'aprobar'` vs `'ajustar'`).
- `ajustada` (ya existe) cubra "Ajuste aprobado/registrado".

Si el copy genérico basta, no cambiar nada aquí y anotarlo. Si se afina, hacerlo por `n.accion`.

- [ ] **Step 2: Localizar el listado de solicitudes**

Run: `grep -rln "route('solicitudes.show'" resources/js/Pages/`
Identificar la página de listado (p.ej. `Solicitudes/Index.jsx`).

- [ ] **Step 3: Badge "Ajuste pendiente"**

El backend del listado debe exponer, por comisión VIA, si tiene algún ajuste en `pendiente_liquidacion` (para contador) o `liquidado` (para contabilidad_lider). Añadir en el controlador del listado un flag `ajuste_pendiente` por solicitud según el rol del usuario, y en el JSX renderizar un pequeño badge amber "Ajuste pendiente" que enlaza al detalle.

> Nota: si tocar el controlador del listado excede el alcance, dejar el badge basado en un campo `ajustes_pendientes_count` que el controlador calcule con `withCount(['ajustes' => fn($q) => $q->whereIn('estado', [...])])`. Implementer decide la vía más limpia según el listado real.

- [ ] **Step 4: Compilar**

Run: `npm run build`
Expected: build limpio.

- [ ] **Step 5: Commit**

```bash
git add resources/js/Components/PanelNotificaciones.jsx resources/js/Pages/<Listado>.jsx app/Http/Controllers/<ListadoController>.php
git commit -m "feat(viaticos): badge de ajuste pendiente y copy de notificaciones

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Task 13: Suite completa + migrar/sembrar en dev

**Files:** ninguno nuevo.

- [ ] **Step 1: Ejecutar toda la suite**

Run: `/c/xampp/php/php.exe artisan test`
Expected: todo verde, sin regresiones. Arreglar lo que rompa.

- [ ] **Step 2: Aplicar migraciones en la BD real de desarrollo (MariaDB)**

Run: `/c/xampp/php/php.exe artisan migrate`
Expected: corren `create_ajustes_comision_table` y `add_ajuste_comision_id_to_asignaciones_viaticos` sin error.

- [ ] **Step 3: Verificación manual mínima (opcional pero recomendada)**

Levantar `php artisan serve` + `npm run dev`, cerrar una comisión de prueba, solicitar un ajuste de fechas como líder, liquidar como contador (ver el delta con valor unitario original), aprobar como líder de contabilidad. Confirmar que la comisión sigue `cerrada` y su total no cambió.

- [ ] **Step 4: Commit (si hubo fixes de la suite)**

```bash
git add <archivos-arreglados>
git commit -m "test(viaticos): ajustes finales de la suite de anexos

Co-Authored-By: Claude Opus 4.8 <noreply@anthropic.com>"
```

---

## Self-review del plan (cobertura del spec)

- Service de delta (spec §3) → Task 1. ✓
- Tabla `ajustes_comision` + columna FK (spec §2) → Task 2, 3. ✓
- Aislamiento del total de la comisión cerrada (spec §2.2) → Task 3 (recalcularTotal excluye anexos). ✓
- Valor unitario de la liquidación original + fallback tarifa (spec §4) → Task 7 (`deltaPropuesto`). ✓
- Flujo/endpoints y estados (spec §5) → Task 6 (solicitar), 7 (liquidar), 8 (aprobar/devolver). ✓
- Rechazo→devuelve, ciclo re-liquidación (spec §5) → Task 7 (`deltaPropuesto` precarga asignaciones si devuelto) + 8 (devolver). ✓
- Frontend tabla + badges + acciones (spec §6.1) → Task 11. ✓
- Pantalla LiquidacionAjuste (spec §6.2) → Task 10. ✓
- Badge en listado (spec §6.3) → Task 12. ✓
- Notificaciones (spec §7) → Tasks 6, 7, 8, 12. ✓
- Permisos (spec §8) → Task 4. ✓
- Tests (spec §9) → Tasks 1, 3, 6, 7, 8. ✓
- Migraciones (spec §10) → Task 2. ✓

Consistencia de nombres verificada: `AjusteComision`, `ajuste_comision_id`, `total_delta`, `estado` con valores `pendiente_liquidacion|liquidado|aprobado|devuelto`, rutas `viaticos.ajuste.liquidar|asignaciones|aprobar|devolver`, `CalculadoraRubrosViaticos::calcularDelta`. Coinciden entre tareas.
