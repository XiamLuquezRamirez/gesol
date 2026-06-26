# Motor de Solicitudes — Plan de Implementación

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Construir el motor de solicitudes dual (elementos de oficina + viáticos) sobre la base Laravel 10 ya configurada, con flujo de aprobación multi-etapa basado en matrices de transición almacenadas como datos.

**Architecture:** Un único servicio `MotorWorkflow` interpreta matrices JSON en `tipos_solicitud.transiciones`. La tabla `solicitudes` usa relación polimórfica `solicitable` hacia las cabeceras específicas. Totales recalculados vía eventos de modelo. Páginas React reciben datos exclusivamente como props Inertia.

**Tech Stack:** Laravel 10 · PHP 8.2 · Eloquent · Inertia.js 0.6 · React 18 · Tailwind CSS · spatie/laravel-permission 6 · MariaDB.

**Nomenclatura:** `ESTANDARES_NOMENCLATURA.md` es obligatorio. Tablas: `solicitudes`, `tipos_solicitud`, `transiciones_solicitud`, `solicitudes_oficina`, `items_oficina`, `solicitudes_viaticos`, `viajeros_comision`, `asignaciones_viaticos`, `tarifas_viaticos`, `areas`. Claves de la matriz JSON en español: `origen`, `destino`, `accion`, `roles`, `notificar`.

**Estado actual:** Fases 0-2 del prompt de estructura completadas (Laravel 10, auth, modelo `Usuario`/tabla `usuarios`, 5 roles seedeados, `AppLayout` + `Inicio/Index`).

---

## Mapa de archivos

```
Crear:
  app/Enums/UrgenciaOficina.php
  app/Enums/CategoriaItem.php
  app/Enums/Rubro.php
  app/Models/Area.php
  app/Models/TipoSolicitud.php
  app/Models/Solicitud.php
  app/Models/TransicionSolicitud.php
  app/Models/SolicitudOficina.php
  app/Models/ItemOficina.php
  app/Models/SolicitudViaticos.php
  app/Models/ViajeroComision.php
  app/Models/AsignacionViatico.php
  app/Models/TarifaViatico.php
  app/Services/MotorWorkflow.php
  app/Policies/SolicitudPolicy.php
  app/Exceptions/TransicionNoPermitidaException.php
  app/Notifications/AvisoTransicionNotification.php
  app/Http/Controllers/SolicitudController.php
  app/Http/Controllers/OficinaController.php
  app/Http/Controllers/ViaticosController.php
  app/Http/Requests/GuardarSolicitudOficinaRequest.php
  app/Http/Requests/ActualizarSolicitudOficinaRequest.php
  app/Http/Requests/EjecutarTransicionRequest.php
  app/Http/Requests/GuardarSolicitudViaticosRequest.php
  app/Http/Requests/ActualizarAsignacionesRequest.php
  app/Http/Resources/SolicitudResource.php
  app/Http/Resources/SolicitudDetalleResource.php
  app/Http/Resources/TransicionResource.php
  database/seeders/AreaSeeder.php
  database/seeders/TipoSolicitudSeeder.php
  database/seeders/TarifaViaticosSeeder.php
  database/seeders/UsuariosDemoSeeder.php
  resources/js/lib/format.js
  resources/js/Components/BadgeEstado.jsx
  resources/js/Components/LineaTiempo.jsx
  resources/js/Components/ModalAccion.jsx
  resources/js/Components/CampoMoneda.jsx
  resources/js/Pages/Solicitudes/Index.jsx
  resources/js/Pages/Solicitudes/Detalle.jsx
  resources/js/Pages/Oficina/Crear.jsx
  resources/js/Pages/Viaticos/Crear.jsx
  resources/js/Pages/Viaticos/Liquidacion.jsx
  tests/Feature/MotorWorkflowOficinaTest.php
  tests/Feature/MotorWorkflowViaticosTest.php

Modificar:
  routes/web.php
  database/seeders/DatabaseSeeder.php
  app/Providers/AuthServiceProvider.php
  app/Http/Middleware/HandleInertiaRequests.php
  resources/js/Layouts/AppLayout.jsx
```

---

## FASE 1 — Datos (migraciones y seeders)

### Task 1: Enums de dominio

**Archivos:** Crear `app/Enums/UrgenciaOficina.php`, `app/Enums/CategoriaItem.php`, `app/Enums/Rubro.php`

- [ ] **Crear `app/Enums/UrgenciaOficina.php`**

```php
<?php
namespace App\Enums;

enum UrgenciaOficina: string {
    case Baja  = 'baja';
    case Media = 'media';
    case Alta  = 'alta';
}
```

- [ ] **Crear `app/Enums/CategoriaItem.php`**

```php
<?php
namespace App\Enums;

enum CategoriaItem: string {
    case Producto = 'producto';
    case Servicio = 'servicio';
}
```

- [ ] **Crear `app/Enums/Rubro.php`**

```php
<?php
namespace App\Enums;

enum Rubro: string {
    case Desayuno = 'desayuno';
    case Almuerzo = 'almuerzo';
    case Cena     = 'cena';
    case Merienda = 'merienda';
    case Gasolina = 'gasolina';
}
```

---

### Task 2: Migración `areas` + seeder

**Archivos:** Crear migración + `database/seeders/AreaSeeder.php`

- [ ] **Generar migración**

```bash
php artisan make:migration create_areas_table
```

- [ ] **Editar la migración generada**

```php
public function up(): void
{
    Schema::create('areas', function (Blueprint $table) {
        $table->id();
        $table->string('nombre');
        $table->string('descripcion')->nullable();
        $table->timestamps();
    });
}
public function down(): void { Schema::dropIfExists('areas'); }
```

- [ ] **Crear `database/seeders/AreaSeeder.php`**

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
            DB::table('areas')->insertOrIgnore(['nombre' => $nombre, 'created_at' => now(), 'updated_at' => now()]);
        }
    }
}
```

---

### Task 3: Migración `tipos_solicitud` + seeder con matrices de workflow

**Archivos:** Crear migración + `database/seeders/TipoSolicitudSeeder.php`

- [ ] **Generar migración**

```bash
php artisan make:migration create_tipos_solicitud_table
```

- [ ] **Editar la migración**

```php
public function up(): void
{
    Schema::create('tipos_solicitud', function (Blueprint $table) {
        $table->id();
        $table->string('clave')->unique();
        $table->string('nombre');
        $table->string('estado_inicial');
        $table->json('estados');
        $table->json('transiciones');
        $table->timestamps();
    });
}
public function down(): void { Schema::dropIfExists('tipos_solicitud'); }
```

- [ ] **Crear `database/seeders/TipoSolicitudSeeder.php`**

```php
<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TipoSolicitudSeeder extends Seeder
{
    public function run(): void
    {
        DB::table('tipos_solicitud')->insertOrIgnore([
            [
                'clave'         => 'OFI',
                'nombre'        => 'Elementos de oficina',
                'estado_inicial'=> 'borrador',
                'estados'       => json_encode(['borrador','enviada','verificada','aprobada','pagada','cerrada','rechazada']),
                'transiciones'  => json_encode([
                    ['origen'=>'borrador',   'accion'=>'enviar',    'destino'=>'enviada',    'roles'=>['lider_area']],
                    ['origen'=>'enviada',    'accion'=>'verificar', 'destino'=>'verificada', 'roles'=>['rrhh']],
                    ['origen'=>'enviada',    'accion'=>'devolver',  'destino'=>'borrador',   'roles'=>['rrhh']],
                    ['origen'=>'verificada', 'accion'=>'aprobar',   'destino'=>'aprobada',   'roles'=>['contabilidad_lider']],
                    ['origen'=>'verificada', 'accion'=>'rechazar',  'destino'=>'rechazada',  'roles'=>['contabilidad_lider']],
                    ['origen'=>'aprobada',   'accion'=>'pagar',     'destino'=>'pagada',     'roles'=>['contabilidad_lider']],
                    ['origen'=>'pagada',     'accion'=>'cerrar',    'destino'=>'cerrada',    'roles'=>['contabilidad_lider','lider_area']],
                ]),
                'created_at' => now(), 'updated_at' => now(),
            ],
            [
                'clave'         => 'VIA',
                'nombre'        => 'Viáticos',
                'estado_inicial'=> 'borrador',
                'estados'       => json_encode(['borrador','enviada','aprobada_monto','liquidada','cerrada','rechazada']),
                'transiciones'  => json_encode([
                    ['origen'=>'borrador',       'accion'=>'enviar',   'destino'=>'enviada',        'roles'=>['lider_comite']],
                    ['origen'=>'enviada',         'accion'=>'aprobar',  'destino'=>'aprobada_monto', 'roles'=>['contabilidad_lider'], 'notificar'=>['rrhh']],
                    ['origen'=>'enviada',         'accion'=>'rechazar', 'destino'=>'rechazada',      'roles'=>['contabilidad_lider']],
                    ['origen'=>'enviada',         'accion'=>'devolver', 'destino'=>'borrador',       'roles'=>['contabilidad_lider']],
                    ['origen'=>'aprobada_monto',  'accion'=>'liquidar', 'destino'=>'liquidada',      'roles'=>['contador']],
                    ['origen'=>'liquidada',       'accion'=>'cerrar',   'destino'=>'cerrada',        'roles'=>['contador','lider_comite']],
                ]),
                'created_at' => now(), 'updated_at' => now(),
            ],
        ]);
    }
}
```

---

### Task 4: Migración `solicitudes`

- [ ] **Generar migración**

```bash
php artisan make:migration create_solicitudes_table
```

- [ ] **Editar la migración**

```php
public function up(): void
{
    Schema::create('solicitudes', function (Blueprint $table) {
        $table->id();
        $table->foreignId('tipo_solicitud_id')->constrained('tipos_solicitud');
        $table->foreignId('solicitante_id')->constrained('usuarios');
        $table->foreignId('area_id')->nullable()->constrained('areas');
        $table->morphs('solicitable');          // solicitable_type, solicitable_id
        $table->string('estado');
        $table->string('radicado')->unique();
        $table->decimal('total', 14, 2)->default(0);
        $table->index(['tipo_solicitud_id', 'estado'], 'idx_solicitudes_tipo_estado');
        $table->timestamps();
    });
}
public function down(): void { Schema::dropIfExists('solicitudes'); }
```

---

### Task 5: Migración `transiciones_solicitud`

- [ ] **Generar migración**

```bash
php artisan make:migration create_transiciones_solicitud_table
```

- [ ] **Editar la migración**

```php
public function up(): void
{
    Schema::create('transiciones_solicitud', function (Blueprint $table) {
        $table->id();
        $table->foreignId('solicitud_id')->constrained('solicitudes')->cascadeOnDelete();
        $table->string('estado_origen')->nullable();
        $table->string('estado_destino');
        $table->string('accion');
        $table->foreignId('usuario_id')->constrained('usuarios');
        $table->text('comentario')->nullable();
        $table->json('metadatos')->nullable();
        $table->timestamp('created_at')->useCurrent();
    });
}
public function down(): void { Schema::dropIfExists('transiciones_solicitud'); }
```

---

### Task 6: Migraciones `solicitudes_oficina` + `items_oficina`

- [ ] **Generar migraciones**

```bash
php artisan make:migration create_solicitudes_oficina_table
php artisan make:migration create_items_oficina_table
```

- [ ] **Editar `create_solicitudes_oficina_table`**

```php
public function up(): void
{
    Schema::create('solicitudes_oficina', function (Blueprint $table) {
        $table->id();
        $table->foreignId('beneficiario_id')->constrained('usuarios');
        $table->enum('urgencia', ['baja','media','alta'])->default('media');
        $table->text('justificacion');
        $table->decimal('total', 14, 2)->default(0);
        $table->decimal('valor_pagado', 14, 2)->nullable();
        $table->date('fecha_pago')->nullable();
        $table->string('comprobante')->nullable();
        $table->timestamps();
    });
}
public function down(): void { Schema::dropIfExists('solicitudes_oficina'); }
```

- [ ] **Editar `create_items_oficina_table`**

```php
public function up(): void
{
    Schema::create('items_oficina', function (Blueprint $table) {
        $table->id();
        $table->foreignId('solicitud_oficina_id')->constrained('solicitudes_oficina')->cascadeOnDelete();
        $table->string('nombre');
        $table->enum('categoria', ['producto','servicio']);
        $table->unsignedInteger('cantidad')->default(1);
        $table->decimal('costo_estimado', 14, 2);
        $table->decimal('subtotal', 14, 2);
        $table->string('notas')->nullable();
        $table->timestamps();
    });
}
public function down(): void { Schema::dropIfExists('items_oficina'); }
```

---

### Task 7: Migraciones viáticos

- [ ] **Generar migraciones**

```bash
php artisan make:migration create_solicitudes_viaticos_table
php artisan make:migration create_viajeros_comision_table
php artisan make:migration create_asignaciones_viaticos_table
php artisan make:migration create_tarifas_viaticos_table
```

- [ ] **Editar `create_solicitudes_viaticos_table`**

```php
public function up(): void
{
    Schema::create('solicitudes_viaticos', function (Blueprint $table) {
        $table->id();
        $table->string('nombre_comision');
        $table->string('municipio_destino');
        $table->text('motivo');
        $table->date('fecha_salida');
        $table->date('fecha_regreso');
        $table->decimal('total', 14, 2)->default(0);
        $table->timestamps();
    });
}
public function down(): void { Schema::dropIfExists('solicitudes_viaticos'); }
```

- [ ] **Editar `create_viajeros_comision_table`**

```php
public function up(): void
{
    Schema::create('viajeros_comision', function (Blueprint $table) {
        $table->id();
        $table->foreignId('solicitud_viaticos_id')->constrained('solicitudes_viaticos')->cascadeOnDelete();
        $table->foreignId('usuario_id')->constrained('usuarios');
        $table->string('rol_en_comision')->nullable();
        $table->timestamps();
    });
}
public function down(): void { Schema::dropIfExists('viajeros_comision'); }
```

- [ ] **Editar `create_asignaciones_viaticos_table`**

```php
public function up(): void
{
    Schema::create('asignaciones_viaticos', function (Blueprint $table) {
        $table->id();
        $table->foreignId('viajero_comision_id')->constrained('viajeros_comision')->cascadeOnDelete();
        $table->enum('rubro', ['desayuno','almuerzo','cena','merienda','gasolina']);
        $table->decimal('valor_unitario', 14, 2);
        $table->unsignedInteger('dias')->default(1);
        $table->decimal('subtotal', 14, 2);
        $table->timestamps();
    });
}
public function down(): void { Schema::dropIfExists('asignaciones_viaticos'); }
```

- [ ] **Editar `create_tarifas_viaticos_table`**

```php
public function up(): void
{
    Schema::create('tarifas_viaticos', function (Blueprint $table) {
        $table->id();
        $table->string('rubro')->unique();
        $table->decimal('valor_sugerido', 14, 2);
        $table->timestamps();
    });
}
public function down(): void { Schema::dropIfExists('tarifas_viaticos'); }
```

- [ ] **Crear `database/seeders/TarifaViaticosSeeder.php`**

```php
<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TarifaViaticosSeeder extends Seeder
{
    public function run(): void
    {
        $tarifas = [
            ['rubro'=>'desayuno', 'valor_sugerido'=>15000],
            ['rubro'=>'almuerzo', 'valor_sugerido'=>25000],
            ['rubro'=>'cena',     'valor_sugerido'=>20000],
            ['rubro'=>'merienda', 'valor_sugerido'=>10000],
            ['rubro'=>'gasolina', 'valor_sugerido'=>50000],
        ];
        foreach ($tarifas as $t) {
            DB::table('tarifas_viaticos')->insertOrIgnore(array_merge($t, ['created_at'=>now(),'updated_at'=>now()]));
        }
    }
}
```

---

### Task 8: Seeders de usuarios demo + DatabaseSeeder + migración fresca

**Archivos:** Crear `database/seeders/UsuariosDemoSeeder.php`, modificar `DatabaseSeeder.php`

- [ ] **Crear `database/seeders/UsuariosDemoSeeder.php`**

```php
<?php
namespace Database\Seeders;
use App\Models\Usuario;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsuariosDemoSeeder extends Seeder
{
    public function run(): void
    {
        $usuarios = [
            ['name'=>'Líder Área Demo',         'email'=>'lider.area@demo.test',         'roles'=>['lider_area']],
            ['name'=>'Líder Comité Demo',        'email'=>'lider.comite@demo.test',       'roles'=>['lider_comite']],
            ['name'=>'RRHH Demo',               'email'=>'rrhh@demo.test',               'roles'=>['rrhh']],
            ['name'=>'Contabilidad Líder Demo', 'email'=>'contabilidad.lider@demo.test', 'roles'=>['contabilidad_lider']],
            ['name'=>'Contador Demo',           'email'=>'contador@demo.test',           'roles'=>['contador']],
        ];

        foreach ($usuarios as $data) {
            $roles = $data['roles'];
            unset($data['roles']);
            $usuario = Usuario::firstOrCreate(
                ['email' => $data['email']],
                array_merge($data, ['password' => Hash::make('password')])
            );
            $usuario->syncRoles($roles);
        }
    }
}
```

- [ ] **Modificar `database/seeders/DatabaseSeeder.php`**

```php
<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesSeeder::class,
            AreaSeeder::class,
            TipoSolicitudSeeder::class,
            TarifaViaticosSeeder::class,
            AdminSeeder::class,
            UsuariosDemoSeeder::class,
        ]);
    }
}
```

- [ ] **Ejecutar migración fresca**

```bash
php artisan migrate:fresh --seed
```

Resultado esperado: `Seeding: RolesSeeder ... Seeding: UsuariosDemoSeeder` sin errores. Verificar en MariaDB que existen todas las tablas y los 6 usuarios demo.

- [ ] **Commit**

```bash
git add database/
git commit -m "feat: fase 1 - migraciones y seeders del motor de solicitudes"
```

---

## FASE 2 — Dominio (modelos, servicio, policy, tests)

### Task 9: Modelos `Area` y `TipoSolicitud`

- [ ] **Crear `app/Models/Area.php`**

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Area extends Model
{
    protected $table = 'areas';
    protected $fillable = ['nombre', 'descripcion'];

    public function solicitudes()
    {
        return $this->hasMany(Solicitud::class, 'area_id');
    }
}
```

- [ ] **Crear `app/Models/TipoSolicitud.php`**

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TipoSolicitud extends Model
{
    protected $table = 'tipos_solicitud';
    protected $fillable = ['clave','nombre','estado_inicial','estados','transiciones'];
    protected $casts = [
        'estados'     => 'array',
        'transiciones'=> 'array',
    ];

    public function solicitudes()
    {
        return $this->hasMany(Solicitud::class, 'tipo_solicitud_id');
    }
}
```

---

### Task 10: Modelo `Solicitud` con generación de radicado

- [ ] **Crear `app/Models/Solicitud.php`**

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Solicitud extends Model
{
    protected $table = 'solicitudes';
    protected $fillable = ['tipo_solicitud_id','solicitante_id','area_id','solicitable_type','solicitable_id','estado','radicado','total'];

    protected static function booted(): void
    {
        static::creating(function (Solicitud $solicitud) {
            if (empty($solicitud->radicado)) {
                $solicitud->radicado = static::generarRadicado($solicitud->tipoSolicitud);
            }
            if (empty($solicitud->estado)) {
                $solicitud->estado = $solicitud->tipoSolicitud->estado_inicial;
            }
        });
    }

    public static function generarRadicado(TipoSolicitud $tipo): string
    {
        $clave    = strtoupper($tipo->clave);
        $anio     = now()->year;
        $secuencia = static::whereYear('created_at', $anio)
            ->where('tipo_solicitud_id', $tipo->id)
            ->count() + 1;
        return sprintf('%s-%d-%05d', $clave, $anio, $secuencia);
    }

    public function tipoSolicitud()   { return $this->belongsTo(TipoSolicitud::class, 'tipo_solicitud_id'); }
    public function solicitante()     { return $this->belongsTo(Usuario::class, 'solicitante_id'); }
    public function area()            { return $this->belongsTo(Area::class, 'area_id'); }
    public function solicitable()     { return $this->morphTo(); }
    public function transiciones()    { return $this->hasMany(TransicionSolicitud::class, 'solicitud_id')->orderBy('created_at'); }
}
```

---

### Task 11: Modelo `TransicionSolicitud`

- [ ] **Crear `app/Models/TransicionSolicitud.php`**

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TransicionSolicitud extends Model
{
    protected $table = 'transiciones_solicitud';
    public $timestamps = false;
    const CREATED_AT = 'created_at';

    protected $fillable = ['solicitud_id','estado_origen','estado_destino','accion','usuario_id','comentario','metadatos'];
    protected $casts = ['metadatos' => 'array', 'created_at' => 'datetime'];

    public function solicitud() { return $this->belongsTo(Solicitud::class, 'solicitud_id'); }
    public function usuario()   { return $this->belongsTo(Usuario::class, 'usuario_id'); }
}
```

---

### Task 12: Modelos `SolicitudOficina` e `ItemOficina` (con recalcularTotal)

- [ ] **Crear `app/Models/SolicitudOficina.php`**

```php
<?php
namespace App\Models;
use App\Enums\UrgenciaOficina;
use Illuminate\Database\Eloquent\Model;

class SolicitudOficina extends Model
{
    protected $table = 'solicitudes_oficina';
    protected $fillable = ['beneficiario_id','urgencia','justificacion','total','valor_pagado','fecha_pago','comprobante'];
    protected $casts = ['urgencia'=>UrgenciaOficina::class, 'fecha_pago'=>'date'];

    public function beneficiario() { return $this->belongsTo(Usuario::class, 'beneficiario_id'); }
    public function items()        { return $this->hasMany(ItemOficina::class, 'solicitud_oficina_id'); }
    public function solicitud()    { return $this->morphOne(Solicitud::class, 'solicitable'); }

    public function recalcularTotal(): void
    {
        $total = $this->items()->sum('subtotal');
        $this->updateQuietly(['total' => $total]);
        $this->solicitud()->update(['total' => $total]);
    }
}
```

- [ ] **Crear `app/Models/ItemOficina.php`**

```php
<?php
namespace App\Models;
use App\Enums\CategoriaItem;
use Illuminate\Database\Eloquent\Model;

class ItemOficina extends Model
{
    protected $table = 'items_oficina';
    protected $fillable = ['solicitud_oficina_id','nombre','categoria','cantidad','costo_estimado','subtotal','notas'];
    protected $casts = ['categoria' => CategoriaItem::class];

    protected static function booted(): void
    {
        static::saving(function (ItemOficina $item) {
            $item->subtotal = $item->cantidad * $item->costo_estimado;
        });
        static::saved(fn(ItemOficina $item)   => $item->solicitudOficina->recalcularTotal());
        static::deleted(fn(ItemOficina $item) => $item->solicitudOficina->recalcularTotal());
    }

    public function solicitudOficina()
    {
        return $this->belongsTo(SolicitudOficina::class, 'solicitud_oficina_id');
    }
}
```

---

### Task 13: Modelos viáticos (con recalcularTotal)

- [ ] **Crear `app/Models/SolicitudViaticos.php`**

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class SolicitudViaticos extends Model
{
    protected $table = 'solicitudes_viaticos';
    protected $fillable = ['nombre_comision','municipio_destino','motivo','fecha_salida','fecha_regreso','total'];
    protected $casts = ['fecha_salida'=>'date','fecha_regreso'=>'date'];

    public function viajeros()  { return $this->hasMany(ViajeroComision::class, 'solicitud_viaticos_id'); }
    public function solicitud() { return $this->morphOne(Solicitud::class, 'solicitable'); }

    public function recalcularTotal(): void
    {
        $total = $this->viajeros()
            ->join('asignaciones_viaticos','viajeros_comision.id','=','asignaciones_viaticos.viajero_comision_id')
            ->sum('asignaciones_viaticos.subtotal');
        $this->updateQuietly(['total' => $total]);
        $this->solicitud()->update(['total' => $total]);
    }
}
```

- [ ] **Crear `app/Models/ViajeroComision.php`**

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class ViajeroComision extends Model
{
    protected $table = 'viajeros_comision';
    protected $fillable = ['solicitud_viaticos_id','usuario_id','rol_en_comision'];

    public function usuario()           { return $this->belongsTo(Usuario::class, 'usuario_id'); }
    public function solicitudViaticos() { return $this->belongsTo(SolicitudViaticos::class, 'solicitud_viaticos_id'); }
    public function asignaciones()      { return $this->hasMany(AsignacionViatico::class, 'viajero_comision_id'); }
}
```

- [ ] **Crear `app/Models/AsignacionViatico.php`**

```php
<?php
namespace App\Models;
use App\Enums\Rubro;
use Illuminate\Database\Eloquent\Model;

class AsignacionViatico extends Model
{
    protected $table = 'asignaciones_viaticos';
    protected $fillable = ['viajero_comision_id','rubro','valor_unitario','dias','subtotal'];
    protected $casts = ['rubro' => Rubro::class];

    protected static function booted(): void
    {
        static::saving(fn(AsignacionViatico $a)  => $a->subtotal = $a->valor_unitario * $a->dias);
        static::saved(fn(AsignacionViatico $a)   => $a->viajeroComision->solicitudViaticos->recalcularTotal());
        static::deleted(fn(AsignacionViatico $a) => $a->viajeroComision->solicitudViaticos->recalcularTotal());
    }

    public function viajeroComision() { return $this->belongsTo(ViajeroComision::class, 'viajero_comision_id'); }
}
```

- [ ] **Crear `app/Models/TarifaViatico.php`**

```php
<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class TarifaViatico extends Model
{
    protected $table = 'tarifas_viaticos';
    protected $fillable = ['rubro','valor_sugerido'];
}
```

---

### Task 14: Excepción de dominio

- [ ] **Crear `app/Exceptions/TransicionNoPermitidaException.php`**

```php
<?php
namespace App\Exceptions;
use Exception;

class TransicionNoPermitidaException extends Exception
{
    public function __construct(string $mensaje = 'Transición no permitida.')
    {
        parent::__construct($mensaje);
    }
}
```

---

### Task 15: Servicio `MotorWorkflow`

- [ ] **Crear `app/Services/MotorWorkflow.php`**

```php
<?php
namespace App\Services;

use App\Exceptions\TransicionNoPermitidaException;
use App\Models\{Solicitud, TransicionSolicitud, Usuario};
use App\Notifications\AvisoTransicionNotification;
use Illuminate\Support\Facades\DB;

class MotorWorkflow
{
    public function accionesDisponibles(Solicitud $solicitud, Usuario $usuario): array
    {
        $rolesUsuario = $usuario->getRoleNames()->toArray();

        return collect($solicitud->tipoSolicitud->transiciones)
            ->filter(fn($t) =>
                $t['origen'] === $solicitud->estado &&
                !empty(array_intersect($t['roles'], $rolesUsuario))
            )
            ->values()
            ->toArray();
    }

    public function puede(Solicitud $solicitud, string $accion, Usuario $usuario): bool
    {
        return collect($this->accionesDisponibles($solicitud, $usuario))
            ->contains('accion', $accion);
    }

    public function aplicarTransicion(
        Solicitud $solicitud,
        string $accion,
        Usuario $usuario,
        ?string $comentario = null,
        array $metadatos = []
    ): Solicitud {
        if (!$this->puede($solicitud, $accion, $usuario)) {
            throw new TransicionNoPermitidaException(
                "La acción «{$accion}» no está permitida en el estado «{$solicitud->estado}» para tu rol."
            );
        }

        $transicion = collect($solicitud->tipoSolicitud->transiciones)
            ->firstWhere('accion', $accion);

        DB::transaction(function () use ($solicitud, $transicion, $accion, $usuario, $comentario, $metadatos) {
            $estadoAnterior = $solicitud->estado;
            $solicitud->update(['estado' => $transicion['destino']]);

            TransicionSolicitud::create([
                'solicitud_id'   => $solicitud->id,
                'estado_origen'  => $estadoAnterior,
                'estado_destino' => $transicion['destino'],
                'accion'         => $accion,
                'usuario_id'     => $usuario->id,
                'comentario'     => $comentario,
                'metadatos'      => $metadatos ?: null,
            ]);

            $this->notificarSiguientePaso($solicitud->fresh(), $transicion);
        });

        return $solicitud->fresh();
    }

    private function notificarSiguientePaso(Solicitud $solicitud, array $transicion): void
    {
        // Notificar a actores del siguiente estado
        $rolesActores = collect($solicitud->tipoSolicitud->transiciones)
            ->filter(fn($t) => $t['origen'] === $transicion['destino'])
            ->pluck('roles')
            ->flatten()
            ->unique()
            ->toArray();

        if (!empty($rolesActores)) {
            $usuarios = Usuario::role($rolesActores)->get();
            foreach ($usuarios as $u) {
                $u->notify(new AvisoTransicionNotification($solicitud, 'accion_requerida'));
            }
        }

        // Notificar observadores (llave 'notificar')
        if (!empty($transicion['notificar'])) {
            $observadores = Usuario::role($transicion['notificar'])->get();
            foreach ($observadores as $u) {
                $u->notify(new AvisoTransicionNotification($solicitud, 'informativo'));
            }
        }
    }
}
```

---

### Task 16: `SolicitudPolicy` + registro en `AuthServiceProvider`

- [ ] **Crear `app/Policies/SolicitudPolicy.php`**

```php
<?php
namespace App\Policies;

use App\Models\{Solicitud, Usuario};
use App\Services\MotorWorkflow;

class SolicitudPolicy
{
    public function __construct(private MotorWorkflow $motor) {}

    public function verDetalle(Usuario $usuario, Solicitud $solicitud): bool
    {
        if ($usuario->id === $solicitud->solicitante_id) return true;
        $rolesUsuario = $usuario->getRoleNames()->toArray();
        return collect($solicitud->tipoSolicitud->transiciones)
            ->pluck('roles')->flatten()->unique()
            ->intersect($rolesUsuario)->isNotEmpty();
    }

    public function ejecutarTransicion(Usuario $usuario, Solicitud $solicitud, string $accion): bool
    {
        return $this->motor->puede($solicitud, $accion, $usuario);
    }

    public function editar(Usuario $usuario, Solicitud $solicitud): bool
    {
        return $usuario->id === $solicitud->solicitante_id &&
            in_array($solicitud->estado, ['borrador']);
    }
}
```

- [ ] **Modificar `app/Providers/AuthServiceProvider.php`**

```php
<?php
namespace App\Providers;
use App\Models\Solicitud;
use App\Policies\SolicitudPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    protected $policies = [
        Solicitud::class => SolicitudPolicy::class,
    ];

    public function boot(): void
    {
        $this->registerPolicies();
    }
}
```

---

### Task 17: Notificación `AvisoTransicionNotification`

- [ ] **Crear `app/Notifications/AvisoTransicionNotification.php`**

```php
<?php
namespace App\Notifications;

use App\Models\Solicitud;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class AvisoTransicionNotification extends Notification
{
    use Queueable;

    public function __construct(
        public readonly Solicitud $solicitud,
        public readonly string $tipo  // 'accion_requerida' | 'informativo'
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'solicitud_id' => $this->solicitud->id,
            'radicado'     => $this->solicitud->radicado,
            'estado'       => $this->solicitud->estado,
            'tipo'         => $this->tipo,
            'tipo_nombre'  => $this->solicitud->tipoSolicitud->nombre,
        ];
    }
}
```

- [ ] **Ejecutar migración de notificaciones**

```bash
php artisan notifications:table
php artisan migrate
```

---

### Task 18: Tests `MotorWorkflow` — flujo oficina

- [ ] **Crear `tests/Feature/MotorWorkflowOficinaTest.php`**

```php
<?php
namespace Tests\Feature;

use App\Enums\{CategoriaItem, UrgenciaOficina};
use App\Exceptions\TransicionNoPermitidaException;
use App\Models\{Area, Solicitud, SolicitudOficina, ItemOficina, TipoSolicitud, Usuario};
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MotorWorkflowOficinaTest extends TestCase
{
    use RefreshDatabase;

    private MotorWorkflow $motor;
    private TipoSolicitud $tipo;
    private Usuario $liderArea;
    private Usuario $rrhh;
    private Usuario $contabilidadLider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->motor             = app(MotorWorkflow::class);
        $this->tipo              = TipoSolicitud::where('clave','OFI')->firstOrFail();
        $this->liderArea         = Usuario::where('email','lider.area@demo.test')->firstOrFail();
        $this->rrhh              = Usuario::where('email','rrhh@demo.test')->firstOrFail();
        $this->contabilidadLider = Usuario::where('email','contabilidad.lider@demo.test')->firstOrFail();
    }

    private function crearSolicitudOficina(): Solicitud
    {
        $cabecera = SolicitudOficina::create([
            'beneficiario_id' => $this->liderArea->id,
            'urgencia'        => UrgenciaOficina::Media,
            'justificacion'   => 'Necesitamos material de oficina.',
        ]);
        ItemOficina::create([
            'solicitud_oficina_id' => $cabecera->id,
            'nombre'               => 'Mouse USB',
            'categoria'            => CategoriaItem::Producto,
            'cantidad'             => 2,
            'costo_estimado'       => 35000,
            'subtotal'             => 70000,
        ]);
        $area = Area::first();
        return Solicitud::create([
            'tipo_solicitud_id'  => $this->tipo->id,
            'solicitante_id'     => $this->liderArea->id,
            'area_id'            => $area->id,
            'solicitable_type'   => SolicitudOficina::class,
            'solicitable_id'     => $cabecera->id,
            'estado'             => 'borrador',
            'radicado'           => Solicitud::generarRadicado($this->tipo),
        ]);
    }

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

        $this->motor->aplicarTransicion($solicitud->fresh(), 'pagar', $this->contabilidadLider, null, [
            'valor_pagado'  => 70000,
            'fecha_pago'    => now()->toDateString(),
            'comprobante'   => 'COMP-001',
        ]);
        $this->assertEquals('pagada', $solicitud->fresh()->estado);

        $this->motor->aplicarTransicion($solicitud->fresh(), 'cerrar', $this->contabilidadLider);
        $this->assertEquals('cerrada', $solicitud->fresh()->estado);

        $this->assertDatabaseCount('transiciones_solicitud', 5);
    }

    public function test_rol_incorrecto_lanza_excepcion(): void
    {
        $solicitud = $this->crearSolicitudOficina();
        $this->motor->aplicarTransicion($solicitud, 'enviar', $this->liderArea);

        $this->expectException(TransicionNoPermitidaException::class);
        $this->motor->aplicarTransicion($solicitud->fresh(), 'verificar', $this->liderArea);
    }

    public function test_total_se_recalcula_al_agregar_item(): void
    {
        $solicitud = $this->crearSolicitudOficina();
        $cabecera  = $solicitud->solicitable;
        ItemOficina::create([
            'solicitud_oficina_id' => $cabecera->id,
            'nombre'               => 'Teclado',
            'categoria'            => CategoriaItem::Producto,
            'cantidad'             => 1,
            'costo_estimado'       => 50000,
            'subtotal'             => 50000,
        ]);
        $this->assertEquals(120000, $solicitud->fresh()->total);
    }
}
```

- [ ] **Ejecutar tests**

```bash
php artisan test tests/Feature/MotorWorkflowOficinaTest.php
```

Resultado esperado: 3 tests, 3 passed.

---

### Task 19: Tests `MotorWorkflow` — flujo viáticos

- [ ] **Crear `tests/Feature/MotorWorkflowViaticosTest.php`**

```php
<?php
namespace Tests\Feature;

use App\Exceptions\TransicionNoPermitidaException;
use App\Models\{Solicitud, SolicitudViaticos, ViajeroComision, TipoSolicitud, Usuario};
use App\Services\MotorWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MotorWorkflowViaticosTest extends TestCase
{
    use RefreshDatabase;

    private MotorWorkflow $motor;
    private TipoSolicitud $tipo;
    private Usuario $liderComite;
    private Usuario $contabilidadLider;
    private Usuario $contador;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->motor             = app(MotorWorkflow::class);
        $this->tipo              = TipoSolicitud::where('clave','VIA')->firstOrFail();
        $this->liderComite       = Usuario::where('email','lider.comite@demo.test')->firstOrFail();
        $this->contabilidadLider = Usuario::where('email','contabilidad.lider@demo.test')->firstOrFail();
        $this->contador          = Usuario::where('email','contador@demo.test')->firstOrFail();
    }

    private function crearSolicitudViaticos(): Solicitud
    {
        $cabecera = SolicitudViaticos::create([
            'nombre_comision'    => 'Comité técnico',
            'municipio_destino'  => 'Medellín',
            'motivo'             => 'Capacitación',
            'fecha_salida'       => now()->addDays(5)->toDateString(),
            'fecha_regreso'      => now()->addDays(7)->toDateString(),
        ]);
        ViajeroComision::create([
            'solicitud_viaticos_id' => $cabecera->id,
            'usuario_id'            => $this->liderComite->id,
        ]);
        return Solicitud::create([
            'tipo_solicitud_id' => $this->tipo->id,
            'solicitante_id'    => $this->liderComite->id,
            'solicitable_type'  => SolicitudViaticos::class,
            'solicitable_id'    => $cabecera->id,
            'estado'            => 'borrador',
            'radicado'          => Solicitud::generarRadicado($this->tipo),
        ]);
    }

    public function test_flujo_completo_viaticos(): void
    {
        $solicitud = $this->crearSolicitudViaticos();

        $this->motor->aplicarTransicion($solicitud, 'enviar', $this->liderComite);
        $this->assertEquals('enviada', $solicitud->fresh()->estado);

        $this->motor->aplicarTransicion($solicitud->fresh(), 'aprobar', $this->contabilidadLider);
        $this->assertEquals('aprobada_monto', $solicitud->fresh()->estado);

        $this->motor->aplicarTransicion($solicitud->fresh(), 'liquidar', $this->contador);
        $this->assertEquals('liquidada', $solicitud->fresh()->estado);

        $this->motor->aplicarTransicion($solicitud->fresh(), 'cerrar', $this->contador);
        $this->assertEquals('cerrada', $solicitud->fresh()->estado);
    }

    public function test_rol_incorrecto_rechazado(): void
    {
        $solicitud = $this->crearSolicitudViaticos();
        $this->motor->aplicarTransicion($solicitud, 'enviar', $this->liderComite);

        $this->expectException(TransicionNoPermitidaException::class);
        $this->motor->aplicarTransicion($solicitud->fresh(), 'aprobar', $this->liderComite);
    }
}
```

- [ ] **Ejecutar tests**

```bash
php artisan test tests/Feature/
```

Resultado esperado: 5 tests, 5 passed.

- [ ] **Commit**

```bash
git add app/ tests/
git commit -m "feat: fase 2 - modelos, MotorWorkflow, policy, notificaciones y tests"
```

---

## FASE 3 — Web (rutas, controladores, resources, form requests)

### Task 20: API Resources

- [ ] **Crear `app/Http/Resources/TransicionResource.php`**

```php
<?php
namespace App\Http\Resources;
use Illuminate\Http\Resources\Json\JsonResource;

class TransicionResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'             => $this->id,
            'estado_origen'  => $this->estado_origen,
            'estado_destino' => $this->estado_destino,
            'accion'         => $this->accion,
            'comentario'     => $this->comentario,
            'metadatos'      => $this->metadatos,
            'created_at'     => $this->created_at?->format('d/m/Y H:i'),
            'usuario'        => ['id'=>$this->usuario->id, 'name'=>$this->usuario->name],
        ];
    }
}
```

- [ ] **Crear `app/Http/Resources/SolicitudResource.php`**

```php
<?php
namespace App\Http\Resources;
use Illuminate\Http\Resources\Json\JsonResource;

class SolicitudResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'         => $this->id,
            'radicado'   => $this->radicado,
            'estado'     => $this->estado,
            'total'      => $this->total,
            'tipo'       => ['clave'=>$this->tipoSolicitud->clave, 'nombre'=>$this->tipoSolicitud->nombre],
            'solicitante'=> ['id'=>$this->solicitante->id, 'name'=>$this->solicitante->name],
            'created_at' => $this->created_at->format('d/m/Y'),
        ];
    }
}
```

- [ ] **Crear `app/Http/Resources/SolicitudDetalleResource.php`**

```php
<?php
namespace App\Http\Resources;
use Illuminate\Http\Resources\Json\JsonResource;

class SolicitudDetalleResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id'          => $this->id,
            'radicado'    => $this->radicado,
            'estado'      => $this->estado,
            'total'       => $this->total,
            'tipo'        => ['clave'=>$this->tipoSolicitud->clave, 'nombre'=>$this->tipoSolicitud->nombre],
            'solicitante' => ['id'=>$this->solicitante->id, 'name'=>$this->solicitante->name],
            'area'        => $this->area ? ['id'=>$this->area->id, 'nombre'=>$this->area->nombre] : null,
            'solicitable' => $this->solicitable,
            'transiciones'=> TransicionResource::collection($this->transiciones),
            'created_at'  => $this->created_at->format('d/m/Y H:i'),
        ];
    }
}
```

---

### Task 21: FormRequests

- [ ] **Crear `app/Http/Requests/EjecutarTransicionRequest.php`**

```php
<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class EjecutarTransicionRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'accion'      => 'required|string',
            'comentario'  => 'nullable|string|max:1000',
            'metadatos'   => 'nullable|array',
            'metadatos.valor_pagado'  => 'nullable|numeric|min:0',
            'metadatos.fecha_pago'    => 'nullable|date',
            'metadatos.comprobante'   => 'nullable|string|max:255',
        ];
    }
}
```

- [ ] **Crear `app/Http/Requests/GuardarSolicitudOficinaRequest.php`**

```php
<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class GuardarSolicitudOficinaRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'beneficiario_id'     => 'required|exists:usuarios,id',
            'area_id'             => 'required|exists:areas,id',
            'urgencia'            => 'required|in:baja,media,alta',
            'justificacion'       => 'required|string|max:2000',
            'items'               => 'required|array|min:1',
            'items.*.nombre'      => 'required|string|max:255',
            'items.*.categoria'   => 'required|in:producto,servicio',
            'items.*.cantidad'    => 'required|integer|min:1',
            'items.*.costo_estimado' => 'required|numeric|min:0',
            'items.*.notas'       => 'nullable|string|max:500',
        ];
    }
}
```

- [ ] **Crear `app/Http/Requests/GuardarSolicitudViaticosRequest.php`**

```php
<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class GuardarSolicitudViaticosRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'nombre_comision'   => 'required|string|max:255',
            'municipio_destino' => 'required|string|max:255',
            'motivo'            => 'required|string|max:2000',
            'fecha_salida'      => 'required|date|after_or_equal:today',
            'fecha_regreso'     => 'required|date|after_or_equal:fecha_salida',
            'viajeros'          => 'required|array|min:1',
            'viajeros.*'        => 'required|exists:usuarios,id',
        ];
    }
}
```

- [ ] **Crear `app/Http/Requests/ActualizarAsignacionesRequest.php`**

```php
<?php
namespace App\Http\Requests;
use Illuminate\Foundation\Http\FormRequest;

class ActualizarAsignacionesRequest extends FormRequest
{
    public function authorize(): bool { return true; }

    public function rules(): array
    {
        return [
            'asignaciones'                       => 'required|array',
            'asignaciones.*.viajero_comision_id' => 'required|exists:viajeros_comision,id',
            'asignaciones.*.rubro'               => 'required|in:desayuno,almuerzo,cena,merienda,gasolina',
            'asignaciones.*.valor_unitario'      => 'required|numeric|min:0',
            'asignaciones.*.dias'                => 'required|integer|min:1',
        ];
    }
}
```

---

### Task 22: `SolicitudController`

- [ ] **Crear `app/Http/Controllers/SolicitudController.php`**

```php
<?php
namespace App\Http\Controllers;

use App\Exceptions\TransicionNoPermitidaException;
use App\Http\Requests\EjecutarTransicionRequest;
use App\Http\Resources\{SolicitudDetalleResource, SolicitudResource};
use App\Models\Solicitud;
use App\Services\MotorWorkflow;
use Inertia\Inertia;

class SolicitudController extends Controller
{
    public function __construct(private MotorWorkflow $motor) {}

    public function index()
    {
        $usuario = auth()->user();
        $tab     = request('tab', 'mias');

        if ($tab === 'pendientes') {
            $roles = $usuario->getRoleNames()->toArray();
            $radicadosPendientes = collect();
            // Obtener solicitudes donde el usuario puede actuar
            $solicitudes = Solicitud::with(['tipoSolicitud','solicitante'])
                ->get()
                ->filter(fn($s) => !empty($this->motor->accionesDisponibles($s, $usuario)))
                ->values();
        } else {
            $solicitudes = Solicitud::with(['tipoSolicitud','solicitante'])
                ->where('solicitante_id', $usuario->id)
                ->latest()
                ->get();
        }

        return Inertia::render('Solicitudes/Index', [
            'solicitudes' => SolicitudResource::collection($solicitudes),
            'filtros'     => ['tab' => $tab],
        ]);
    }

    public function show(Solicitud $solicitud)
    {
        $this->authorize('verDetalle', $solicitud);

        $solicitud->load(['tipoSolicitud','solicitante','area','solicitable','transiciones.usuario']);

        return Inertia::render('Solicitudes/Detalle', [
            'solicitud' => new SolicitudDetalleResource($solicitud),
            'acciones'  => $this->motor->accionesDisponibles($solicitud, auth()->user()),
        ]);
    }

    public function transicion(EjecutarTransicionRequest $request, Solicitud $solicitud)
    {
        $this->authorize('verDetalle', $solicitud);

        try {
            $this->motor->aplicarTransicion(
                $solicitud,
                $request->accion,
                auth()->user(),
                $request->comentario,
                $request->metadatos ?? []
            );
        } catch (TransicionNoPermitidaException $e) {
            return back()->withErrors(['accion' => $e->getMessage()]);
        }

        return redirect()->route('solicitudes.show', $solicitud)
            ->with('success', 'Transición aplicada correctamente.');
    }
}
```

---

### Task 23: `OficinaController`

- [ ] **Crear `app/Http/Controllers/OficinaController.php`**

```php
<?php
namespace App\Http\Controllers;

use App\Http\Requests\GuardarSolicitudOficinaRequest;
use App\Models\{Area, Solicitud, SolicitudOficina, ItemOficina, TipoSolicitud, Usuario};
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class OficinaController extends Controller
{
    public function create()
    {
        $this->authorize('create', Solicitud::class);
        return Inertia::render('Oficina/Crear', [
            'areas'      => Area::orderBy('nombre')->get(['id','nombre']),
            'usuarios'   => Usuario::orderBy('name')->get(['id','name']),
        ]);
    }

    public function store(GuardarSolicitudOficinaRequest $request)
    {
        $this->authorize('create', Solicitud::class);
        $tipo = TipoSolicitud::where('clave','OFI')->firstOrFail();

        $solicitud = DB::transaction(function () use ($request, $tipo) {
            $cabecera = SolicitudOficina::create([
                'beneficiario_id' => $request->beneficiario_id,
                'urgencia'        => $request->urgencia,
                'justificacion'   => $request->justificacion,
            ]);

            foreach ($request->items as $item) {
                ItemOficina::create(array_merge($item, ['solicitud_oficina_id' => $cabecera->id, 'subtotal' => 0]));
            }

            return Solicitud::create([
                'tipo_solicitud_id' => $tipo->id,
                'solicitante_id'    => auth()->id(),
                'area_id'           => $request->area_id,
                'solicitable_type'  => SolicitudOficina::class,
                'solicitable_id'    => $cabecera->id,
                'estado'            => $tipo->estado_inicial,
                'radicado'          => Solicitud::generarRadicado($tipo),
            ]);
        });

        return redirect()->route('solicitudes.show', $solicitud)
            ->with('success', 'Solicitud creada: '.$solicitud->radicado);
    }

    public function edit(Solicitud $solicitud)
    {
        $this->authorize('editar', $solicitud);
        $solicitud->load('solicitable.items','solicitable.beneficiario');
        return Inertia::render('Oficina/Crear', [
            'solicitud' => $solicitud,
            'areas'     => Area::orderBy('nombre')->get(['id','nombre']),
            'usuarios'  => Usuario::orderBy('name')->get(['id','name']),
            'editar'    => true,
        ]);
    }

    public function update(GuardarSolicitudOficinaRequest $request, Solicitud $solicitud)
    {
        $this->authorize('editar', $solicitud);
        $cabecera = $solicitud->solicitable;

        DB::transaction(function () use ($request, $cabecera) {
            $cabecera->update([
                'beneficiario_id' => $request->beneficiario_id,
                'urgencia'        => $request->urgencia,
                'justificacion'   => $request->justificacion,
            ]);
            $cabecera->items()->delete();
            foreach ($request->items as $item) {
                ItemOficina::create(array_merge($item, ['solicitud_oficina_id' => $cabecera->id, 'subtotal' => 0]));
            }
        });

        return redirect()->route('solicitudes.show', $solicitud)
            ->with('success', 'Solicitud actualizada.');
    }
}
```

---

### Task 24: `ViaticosController`

- [ ] **Crear `app/Http/Controllers/ViaticosController.php`**

```php
<?php
namespace App\Http\Controllers;

use App\Http\Requests\{GuardarSolicitudViaticosRequest, ActualizarAsignacionesRequest};
use App\Models\{Area, AsignacionViatico, Solicitud, SolicitudViaticos, TarifaViatico, TipoSolicitud, Usuario, ViajeroComision};
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ViaticosController extends Controller
{
    public function create()
    {
        return Inertia::render('Viaticos/Crear', [
            'usuarios' => Usuario::orderBy('name')->get(['id','name']),
        ]);
    }

    public function store(GuardarSolicitudViaticosRequest $request)
    {
        $tipo = TipoSolicitud::where('clave','VIA')->firstOrFail();

        $solicitud = DB::transaction(function () use ($request, $tipo) {
            $cabecera = SolicitudViaticos::create($request->only([
                'nombre_comision','municipio_destino','motivo','fecha_salida','fecha_regreso',
            ]));
            foreach ($request->viajeros as $userId) {
                ViajeroComision::create(['solicitud_viaticos_id'=>$cabecera->id,'usuario_id'=>$userId]);
            }
            return Solicitud::create([
                'tipo_solicitud_id' => $tipo->id,
                'solicitante_id'    => auth()->id(),
                'solicitable_type'  => SolicitudViaticos::class,
                'solicitable_id'    => $cabecera->id,
                'estado'            => $tipo->estado_inicial,
                'radicado'          => Solicitud::generarRadicado($tipo),
            ]);
        });

        return redirect()->route('solicitudes.show', $solicitud)
            ->with('success', 'Solicitud creada: '.$solicitud->radicado);
    }

    public function liquidacion(Solicitud $solicitud)
    {
        $solicitud->load(['solicitable.viajeros.usuario','solicitable.viajeros.asignaciones']);
        return Inertia::render('Viaticos/Liquidacion', [
            'solicitud' => $solicitud,
            'tarifas'   => TarifaViatico::all()->keyBy('rubro'),
            'rubros'    => ['desayuno','almuerzo','cena','merienda','gasolina'],
        ]);
    }

    public function updateAllocations(ActualizarAsignacionesRequest $request, Solicitud $solicitud)
    {
        DB::transaction(function () use ($request, $solicitud) {
            foreach ($request->asignaciones as $data) {
                AsignacionViatico::updateOrCreate(
                    ['viajero_comision_id'=>$data['viajero_comision_id'],'rubro'=>$data['rubro']],
                    ['valor_unitario'=>$data['valor_unitario'],'dias'=>$data['dias'],'subtotal'=>0]
                );
            }
        });

        return back()->with('success', 'Asignaciones guardadas.');
    }
}
```

---

### Task 25: Rutas (`routes/web.php`) + actualizar `HandleInertiaRequests`

- [ ] **Reemplazar `routes/web.php`**

```php
<?php
use App\Http\Controllers\{OficinaController, PerfilController, SolicitudController, ViaticosController};
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn() => redirect()->route('solicitudes.index'))->middleware('auth');

Route::middleware(['auth','verified'])->group(function () {
    Route::get('/inicio', fn() => Inertia::render('Inicio/Index'))->name('inicio');

    // Solicitudes
    Route::get('/solicitudes',                           [SolicitudController::class, 'index'])->name('solicitudes.index');
    Route::get('/solicitudes/{solicitud}',               [SolicitudController::class, 'show'])->name('solicitudes.show');
    Route::post('/solicitudes/{solicitud}/transicion',   [SolicitudController::class, 'transicion'])->name('solicitudes.transicion');

    // Oficina
    Route::get('/oficina/crear',               [OficinaController::class, 'create'])->name('oficina.crear');
    Route::post('/oficina',                    [OficinaController::class, 'store'])->name('oficina.store');
    Route::get('/oficina/{solicitud}/editar',  [OficinaController::class, 'edit'])->name('oficina.editar');
    Route::put('/oficina/{solicitud}',         [OficinaController::class, 'update'])->name('oficina.update');

    // Viáticos
    Route::get('/viaticos/crear',                       [ViaticosController::class, 'create'])->name('viaticos.crear');
    Route::post('/viaticos',                            [ViaticosController::class, 'store'])->name('viaticos.store');
    Route::get('/viaticos/{solicitud}/liquidar',        [ViaticosController::class, 'liquidacion'])->name('viaticos.liquidacion');
    Route::put('/viaticos/{solicitud}/asignaciones',    [ViaticosController::class, 'updateAllocations'])->name('viaticos.asignaciones');

    // Perfil
    Route::get('/perfil',    [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/perfil',  [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/perfil', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
```

- [ ] **Actualizar `app/Http/Middleware/HandleInertiaRequests.php`**

```php
public function share(Request $request): array
{
    return [
        ...parent::share($request),
        'auth' => [
            'user' => $request->user()?->load('roles'),
        ],
        'notificaciones_no_leidas' => $request->user()
            ?->unreadNotifications()->count() ?? 0,
        'flash' => [
            'success' => $request->session()->get('success'),
            'error'   => $request->session()->get('error'),
        ],
    ];
}
```

- [ ] **Verificar rutas**

```bash
php artisan route:list --path=solicitudes
php artisan route:list --path=oficina
php artisan route:list --path=viaticos
```

- [ ] **Commit**

```bash
git add app/Http/ routes/ 
git commit -m "feat: fase 3 - rutas, controladores, resources y form requests"
```

---

## FASE 4 — Páginas base (React)

### Task 26: `resources/js/lib/format.js` + `BadgeEstado`

- [ ] **Crear `resources/js/lib/format.js`**

```js
export function formatearMoneda(valor) {
    return new Intl.NumberFormat('es-CO', {
        style: 'currency', currency: 'COP', minimumFractionDigits: 0,
    }).format(valor ?? 0);
}

export function formatearFecha(fechaStr) {
    if (!fechaStr) return '—';
    return new Date(fechaStr).toLocaleDateString('es-CO', {
        day: '2-digit', month: 'short', year: 'numeric',
    });
}
```

- [ ] **Crear `resources/js/Components/BadgeEstado.jsx`**

```jsx
const COLORES = {
    borrador:       'bg-slate-100 text-slate-600 border-slate-200',
    enviada:        'bg-blue-50 text-blue-700 border-blue-200',
    verificada:     'bg-indigo-50 text-indigo-700 border-indigo-200',
    aprobada:       'bg-emerald-50 text-emerald-700 border-emerald-200',
    aprobada_monto: 'bg-emerald-50 text-emerald-700 border-emerald-200',
    pagada:         'bg-teal-50 text-teal-700 border-teal-200',
    liquidada:      'bg-teal-50 text-teal-700 border-teal-200',
    cerrada:        'bg-slate-100 text-slate-500 border-slate-200',
    rechazada:      'bg-red-50 text-red-700 border-red-200',
};

const ETIQUETAS = {
    borrador:'Borrador', enviada:'Enviada', verificada:'Verificada',
    aprobada:'Aprobada', aprobada_monto:'Monto aprobado', pagada:'Pagada',
    liquidada:'Liquidada', cerrada:'Cerrada', rechazada:'Rechazada',
};

export default function BadgeEstado({ estado }) {
    const clase = COLORES[estado] ?? 'bg-slate-100 text-slate-600 border-slate-200';
    return (
        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium border ${clase}`}>
            {ETIQUETAS[estado] ?? estado}
        </span>
    );
}
```

---

### Task 27: `LineaTiempo` component

- [ ] **Crear `resources/js/Components/LineaTiempo.jsx`**

```jsx
import { formatearFecha } from '@/lib/format';

const ICONOS_ACCION = {
    enviar:'→', verificar:'✓', aprobar:'✓', devolver:'↩', rechazar:'✗',
    pagar:'$', liquidar:'$', cerrar:'■',
};

export default function LineaTiempo({ transiciones }) {
    if (!transiciones?.length) {
        return <p className="text-sm text-slate-400 py-4 text-center">Sin movimientos aún.</p>;
    }

    return (
        <ol className="relative border-l border-slate-200 ml-3 space-y-6">
            {transiciones.map((t) => (
                <li key={t.id} className="ml-6">
                    <span className="absolute -left-3 flex h-6 w-6 items-center justify-center rounded-full bg-indigo-100 text-indigo-600 text-xs font-bold ring-4 ring-white">
                        {ICONOS_ACCION[t.accion] ?? '·'}
                    </span>
                    <div className="flex items-center gap-2 mb-1">
                        <span className="text-sm font-semibold text-slate-800 capitalize">{t.accion}</span>
                        <span className="text-xs text-slate-400">por {t.usuario.name}</span>
                        <span className="text-xs text-slate-400 ml-auto">{t.created_at}</span>
                    </div>
                    <p className="text-xs text-slate-500">
                        {t.estado_origen ?? '—'} → {t.estado_destino}
                    </p>
                    {t.comentario && (
                        <p className="mt-1 text-xs text-slate-600 bg-slate-50 rounded px-2 py-1 border border-slate-100">
                            {t.comentario}
                        </p>
                    )}
                </li>
            ))}
        </ol>
    );
}
```

---

### Task 28: `ModalAccion` component

- [ ] **Crear `resources/js/Components/ModalAccion.jsx`**

```jsx
import { useForm } from '@inertiajs/react';
import { useEffect } from 'react';

export default function ModalAccion({ solicitudId, accion, onClose }) {
    const esPago = accion?.accion === 'pagar';

    const { data, setData, post, processing, errors, reset } = useForm({
        accion:               accion?.accion ?? '',
        comentario:           '',
        'metadatos[valor_pagado]': '',
        'metadatos[fecha_pago]':   '',
        'metadatos[comprobante]':  '',
    });

    useEffect(() => {
        setData('accion', accion?.accion ?? '');
    }, [accion]);

    const submit = (e) => {
        e.preventDefault();
        post(route('solicitudes.transicion', solicitudId), {
            onSuccess: () => { reset(); onClose(); },
        });
    };

    if (!accion) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/40">
            <div className="bg-white rounded-xl shadow-xl w-full max-w-md p-6">
                <h3 className="text-base font-semibold text-slate-900 mb-4 capitalize">
                    Confirmar: {accion.accion}
                </h3>
                <form onSubmit={submit} className="space-y-4">
                    <div>
                        <label className="block text-sm font-medium text-slate-700 mb-1">
                            Comentario (opcional)
                        </label>
                        <textarea
                            className="w-full rounded-lg border-slate-300 text-sm"
                            rows={3}
                            value={data.comentario}
                            onChange={e => setData('comentario', e.target.value)}
                        />
                        {errors.comentario && <p className="text-red-500 text-xs mt-1">{errors.comentario}</p>}
                    </div>

                    {esPago && (
                        <>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Valor pagado (COP)</label>
                                <input type="number" className="w-full rounded-lg border-slate-300 text-sm"
                                    value={data['metadatos[valor_pagado]']}
                                    onChange={e => setData('metadatos[valor_pagado]', e.target.value)} />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Fecha de pago</label>
                                <input type="date" className="w-full rounded-lg border-slate-300 text-sm"
                                    value={data['metadatos[fecha_pago]']}
                                    onChange={e => setData('metadatos[fecha_pago]', e.target.value)} />
                            </div>
                            <div>
                                <label className="block text-sm font-medium text-slate-700 mb-1">Comprobante</label>
                                <input type="text" className="w-full rounded-lg border-slate-300 text-sm"
                                    value={data['metadatos[comprobante]']}
                                    onChange={e => setData('metadatos[comprobante]', e.target.value)} />
                            </div>
                        </>
                    )}

                    {errors.accion && <p className="text-red-500 text-sm">{errors.accion}</p>}

                    <div className="flex justify-end gap-3 pt-2">
                        <button type="button" onClick={onClose}
                            className="px-4 py-2 text-sm text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors">
                            Cancelar
                        </button>
                        <button type="submit" disabled={processing}
                            className="px-4 py-2 text-sm text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors disabled:opacity-50 capitalize">
                            {accion.accion}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
```

---

### Task 29: Actualizar `AppLayout` con links de navegación

- [ ] **Modificar `resources/js/Layouts/AppLayout.jsx`** — agregar items de nav en la sección `Principal`:

```jsx
// Reemplazar el NavSection "Principal" existente con:
<NavSection label="Principal">
    <NavItem href={route('inicio')} active={route().current('inicio')} icon={IconHome}>
        Inicio
    </NavItem>
    <NavItem href={route('solicitudes.index')} active={route().current('solicitudes.*')} icon={IconBandeja}>
        Solicitudes
    </NavItem>
</NavSection>

<NavSection label="Nueva solicitud">
    <NavItem href={route('oficina.crear')} active={route().current('oficina.*')} icon={IconOficina}>
        Oficina
    </NavItem>
    <NavItem href={route('viaticos.crear')} active={route().current('viaticos.*')} icon={IconViaticos}>
        Viáticos
    </NavItem>
</NavSection>
```

Agregar también los iconos `IconBandeja`, `IconOficina`, `IconViaticos` al archivo (SVGs de Heroicons fill):

```jsx
const IconBandeja = ({ className }) => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className={className}>
        <path fillRule="evenodd" d="M2.625 6.75a1.125 1.125 0 1 1 2.25 0 1.125 1.125 0 0 1-2.25 0Zm4.875 0A.75.75 0 0 1 8.25 6h12a.75.75 0 0 1 0 1.5h-12a.75.75 0 0 1-.75-.75ZM2.625 12a1.125 1.125 0 1 1 2.25 0 1.125 1.125 0 0 1-2.25 0ZM7.5 12a.75.75 0 0 1 .75-.75h12a.75.75 0 0 1 0 1.5h-12A.75.75 0 0 1 7.5 12Zm-4.875 5.25a1.125 1.125 0 1 1 2.25 0 1.125 1.125 0 0 1-2.25 0Zm4.875 0a.75.75 0 0 1 .75-.75h12a.75.75 0 0 1 0 1.5h-12a.75.75 0 0 1-.75-.75Z" clipRule="evenodd" />
    </svg>
);

const IconOficina = ({ className }) => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className={className}>
        <path d="M3.375 3C2.339 3 1.5 3.84 1.5 4.875v.75c0 1.036.84 1.875 1.875 1.875h17.25c1.035 0 1.875-.84 1.875-1.875v-.75C22.5 3.839 21.66 3 20.625 3H3.375Z" />
        <path fillRule="evenodd" d="m3.087 9 .54 9.176A3 3 0 0 0 6.62 21h10.757a3 3 0 0 0 2.995-2.824L20.913 9H3.087Zm6.163 3.75A.75.75 0 0 1 10 12h4a.75.75 0 0 1 0 1.5h-4a.75.75 0 0 1-.75-.75Z" clipRule="evenodd" />
    </svg>
);

const IconViaticos = ({ className }) => (
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" className={className}>
        <path fillRule="evenodd" d="m11.54 22.351.07.04.028.016a.76.76 0 0 0 .723 0l.028-.015.071-.041a16.975 16.975 0 0 0 1.144-.742 19.58 19.58 0 0 0 2.683-2.282c1.944-2.003 3.5-4.697 3.5-8.327a8 8 0 1 0-16 0c0 3.63 1.556 6.326 3.5 8.327a19.58 19.58 0 0 0 2.682 2.282 16.975 16.975 0 0 0 1.144.742ZM12 13.5a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" clipRule="evenodd" />
    </svg>
);
```

También actualizar el badge de notificaciones en el top bar para mostrar el conteo real:

```jsx
// En el top bar, reemplazar el botón de campana:
const { notificaciones_no_leidas } = usePage().props;
// ...
<button type="button" title="Notificaciones"
    className="relative w-8 h-8 flex items-center justify-center rounded-lg text-slate-400 hover:text-slate-700 hover:bg-slate-100 transition-colors duration-150">
    <IconBell className="w-5 h-5" />
    {notificaciones_no_leidas > 0 && (
        <span className="absolute top-1 right-1 w-2 h-2 bg-red-500 rounded-full" />
    )}
</button>
```

---

### Task 30: `Solicitudes/Index.jsx` — bandejas

- [ ] **Crear `resources/js/Pages/Solicitudes/Index.jsx`**

```jsx
import AppLayout from '@/Layouts/AppLayout';
import BadgeEstado from '@/Components/BadgeEstado';
import { Head, Link, router } from '@inertiajs/react';
import { formatearMoneda } from '@/lib/format';

export default function Index({ solicitudes, filtros }) {
    const tab = filtros?.tab ?? 'mias';

    const cambiarTab = (nuevoTab) => {
        router.get(route('solicitudes.index'), { tab: nuevoTab }, { preserveState: true });
    };

    return (
        <AppLayout title="Solicitudes">
            <Head title="Solicitudes" />

            <div className="flex-1 flex flex-col px-6 py-6 gap-4">
                {/* Tabs */}
                <div className="flex gap-1 bg-slate-100 p-1 rounded-lg w-fit">
                    {[['mias','Mis solicitudes'],['pendientes','Pendientes']].map(([key, label]) => (
                        <button key={key} onClick={() => cambiarTab(key)}
                            className={`px-4 py-1.5 text-sm font-medium rounded-md transition-all ${
                                tab === key ? 'bg-white text-slate-900 shadow-sm' : 'text-slate-500 hover:text-slate-700'
                            }`}>
                            {label}
                        </button>
                    ))}
                </div>

                {/* Tabla */}
                {solicitudes.data.length === 0 ? (
                    <div className="flex-1 flex items-center justify-center">
                        <p className="text-slate-400 text-sm">No hay solicitudes en esta bandeja.</p>
                    </div>
                ) : (
                    <div className="bg-white rounded-xl border border-slate-200 overflow-hidden">
                        <table className="w-full text-sm">
                            <thead className="bg-slate-50 border-b border-slate-200">
                                <tr>
                                    {['Radicado','Tipo','Estado','Total','Solicitante','Fecha'].map(h => (
                                        <th key={h} className="text-left px-4 py-3 text-xs font-semibold text-slate-500 uppercase tracking-wide">
                                            {h}
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-100">
                                {solicitudes.data.map((s) => (
                                    <tr key={s.id} className="hover:bg-slate-50 transition-colors">
                                        <td className="px-4 py-3">
                                            <Link href={route('solicitudes.show', s.id)}
                                                className="font-mono text-indigo-600 hover:underline text-xs font-semibold">
                                                {s.radicado}
                                            </Link>
                                        </td>
                                        <td className="px-4 py-3 text-slate-600">{s.tipo.nombre}</td>
                                        <td className="px-4 py-3"><BadgeEstado estado={s.estado} /></td>
                                        <td className="px-4 py-3 text-slate-700 font-medium">{formatearMoneda(s.total)}</td>
                                        <td className="px-4 py-3 text-slate-500">{s.solicitante.name}</td>
                                        <td className="px-4 py-3 text-slate-400 text-xs">{s.created_at}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
```

---

### Task 31: `Solicitudes/Detalle.jsx`

- [ ] **Crear `resources/js/Pages/Solicitudes/Detalle.jsx`**

```jsx
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import BadgeEstado from '@/Components/BadgeEstado';
import LineaTiempo from '@/Components/LineaTiempo';
import ModalAccion from '@/Components/ModalAccion';
import { Head, usePage } from '@inertiajs/react';
import { formatearMoneda } from '@/lib/format';

export default function Detalle({ solicitud, acciones }) {
    const [accionActiva, setAccionActiva] = useState(null);

    return (
        <AppLayout title={`Solicitud ${solicitud.radicado}`}>
            <Head title={solicitud.radicado} />

            <div className="flex-1 flex flex-col px-6 py-6 gap-6">
                {/* Header */}
                <div className="flex items-start justify-between">
                    <div>
                        <p className="text-xs text-slate-400 font-mono mb-1">{solicitud.radicado}</p>
                        <h2 className="text-xl font-bold text-slate-900">{solicitud.tipo.nombre}</h2>
                        <div className="flex items-center gap-3 mt-2">
                            <BadgeEstado estado={solicitud.estado} />
                            <span className="text-sm text-slate-500">por {solicitud.solicitante.name}</span>
                            <span className="text-sm text-slate-400">{solicitud.created_at}</span>
                        </div>
                    </div>
                    <div className="text-right">
                        <p className="text-2xl font-bold text-slate-900">{formatearMoneda(solicitud.total)}</p>
                        <p className="text-xs text-slate-400 mt-1">Total</p>
                    </div>
                </div>

                {/* Botonera de acciones */}
                {acciones.length > 0 && (
                    <div className="flex flex-wrap gap-2 p-4 bg-amber-50 border border-amber-100 rounded-xl">
                        <p className="w-full text-xs font-semibold text-amber-700 mb-1">Acciones disponibles para ti:</p>
                        {acciones.map((a) => (
                            <button key={a.accion} onClick={() => setAccionActiva(a)}
                                className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg transition-colors capitalize">
                                {a.accion}
                            </button>
                        ))}
                    </div>
                )}

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 flex-1">
                    {/* Detalle solicitable */}
                    <div className="lg:col-span-2 bg-white rounded-xl border border-slate-200 p-6">
                        <h3 className="text-sm font-semibold text-slate-700 mb-4">Detalle</h3>
                        <pre className="text-xs text-slate-600 whitespace-pre-wrap">
                            {JSON.stringify(solicitud.solicitable, null, 2)}
                        </pre>
                    </div>

                    {/* Timeline */}
                    <div className="bg-white rounded-xl border border-slate-200 p-6">
                        <h3 className="text-sm font-semibold text-slate-700 mb-4">Historial</h3>
                        <LineaTiempo transiciones={solicitud.transiciones} />
                    </div>
                </div>
            </div>

            <ModalAccion
                solicitudId={solicitud.id}
                accion={accionActiva}
                onClose={() => setAccionActiva(null)}
            />
        </AppLayout>
    );
}
```

- [ ] **Commit**

```bash
git add resources/js/
git commit -m "feat: fase 4 - componentes base y páginas de bandejas/detalle"
```

---

## FASE 5 — Páginas de procesos

### Task 32: `CampoMoneda` component

- [ ] **Crear `resources/js/Components/CampoMoneda.jsx`**

```jsx
export default function CampoMoneda({ label, value, onChange, error, className = '' }) {
    return (
        <div className={className}>
            {label && <label className="block text-xs font-medium text-slate-600 mb-1">{label}</label>}
            <div className="relative">
                <span className="absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 text-sm">$</span>
                <input
                    type="number"
                    min="0"
                    step="100"
                    value={value}
                    onChange={e => onChange(Number(e.target.value))}
                    className="w-full pl-7 pr-3 py-2 text-sm rounded-lg border-slate-300 focus:border-indigo-500 focus:ring-indigo-500"
                />
            </div>
            {error && <p className="text-red-500 text-xs mt-1">{error}</p>}
        </div>
    );
}
```

---

### Task 33: `Oficina/Crear.jsx`

- [ ] **Crear `resources/js/Pages/Oficina/Crear.jsx`**

```jsx
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import CampoMoneda from '@/Components/CampoMoneda';
import { Head, useForm } from '@inertiajs/react';
import { formatearMoneda } from '@/lib/format';

const ITEM_VACIO = { nombre: '', categoria: 'producto', cantidad: 1, costo_estimado: 0, notas: '' };

export default function Crear({ areas, usuarios, solicitud, editar }) {
    const cab = solicitud?.solicitable ?? {};

    const { data, setData, post, put, processing, errors } = useForm({
        beneficiario_id: cab.beneficiario_id ?? '',
        area_id:         solicitud?.area_id ?? '',
        urgencia:        cab.urgencia ?? 'media',
        justificacion:   cab.justificacion ?? '',
        items:           cab.items?.length ? cab.items : [{ ...ITEM_VACIO }],
    });

    const agregarItem = () => setData('items', [...data.items, { ...ITEM_VACIO }]);

    const actualizarItem = (idx, campo, valor) => {
        const items = [...data.items];
        items[idx] = { ...items[idx], [campo]: valor };
        setData('items', items);
    };

    const eliminarItem = (idx) => setData('items', data.items.filter((_, i) => i !== idx));

    const totalLocal = data.items.reduce((acc, i) => acc + (i.cantidad * i.costo_estimado), 0);

    const submit = (e) => {
        e.preventDefault();
        editar
            ? put(route('oficina.update', solicitud.id))
            : post(route('oficina.store'));
    };

    return (
        <AppLayout title={editar ? 'Editar solicitud' : 'Nueva solicitud de oficina'}>
            <Head title={editar ? 'Editar' : 'Oficina'} />

            <div className="flex-1 flex flex-col px-6 py-6">
                <form onSubmit={submit} className="flex-1 flex flex-col gap-6">
                    {/* Cabecera */}
                    <div className="bg-white rounded-xl border border-slate-200 p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <h3 className="col-span-full text-sm font-semibold text-slate-700">Información general</h3>

                        <div>
                            <label className="block text-xs font-medium text-slate-600 mb-1">Beneficiario</label>
                            <select value={data.beneficiario_id} onChange={e => setData('beneficiario_id', e.target.value)}
                                className="w-full text-sm rounded-lg border-slate-300">
                                <option value="">Seleccionar...</option>
                                {usuarios.map(u => <option key={u.id} value={u.id}>{u.name}</option>)}
                            </select>
                            {errors.beneficiario_id && <p className="text-red-500 text-xs mt-1">{errors.beneficiario_id}</p>}
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-600 mb-1">Área</label>
                            <select value={data.area_id} onChange={e => setData('area_id', e.target.value)}
                                className="w-full text-sm rounded-lg border-slate-300">
                                <option value="">Seleccionar...</option>
                                {areas.map(a => <option key={a.id} value={a.id}>{a.nombre}</option>)}
                            </select>
                        </div>

                        <div>
                            <label className="block text-xs font-medium text-slate-600 mb-1">Urgencia</label>
                            <select value={data.urgencia} onChange={e => setData('urgencia', e.target.value)}
                                className="w-full text-sm rounded-lg border-slate-300">
                                <option value="baja">Baja</option>
                                <option value="media">Media</option>
                                <option value="alta">Alta</option>
                            </select>
                        </div>

                        <div className="col-span-full">
                            <label className="block text-xs font-medium text-slate-600 mb-1">Justificación</label>
                            <textarea rows={3} value={data.justificacion}
                                onChange={e => setData('justificacion', e.target.value)}
                                className="w-full text-sm rounded-lg border-slate-300" />
                            {errors.justificacion && <p className="text-red-500 text-xs mt-1">{errors.justificacion}</p>}
                        </div>
                    </div>

                    {/* Ítems */}
                    <div className="bg-white rounded-xl border border-slate-200 p-6 flex-1 flex flex-col">
                        <div className="flex items-center justify-between mb-4">
                            <h3 className="text-sm font-semibold text-slate-700">Ítems</h3>
                            <button type="button" onClick={agregarItem}
                                className="text-xs text-indigo-600 hover:text-indigo-800 font-medium">
                                + Agregar ítem
                            </button>
                        </div>

                        <div className="flex-1 overflow-auto">
                            <table className="w-full text-sm">
                                <thead className="text-xs text-slate-500 uppercase border-b border-slate-100">
                                    <tr>
                                        {['Nombre','Categoría','Cantidad','Costo unit.','Subtotal',''].map(h => (
                                            <th key={h} className="text-left pb-2 pr-3 font-medium">{h}</th>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-50">
                                    {data.items.map((item, idx) => (
                                        <tr key={idx}>
                                            <td className="py-2 pr-3">
                                                <input value={item.nombre} onChange={e => actualizarItem(idx,'nombre',e.target.value)}
                                                    className="w-full text-sm rounded border-slate-300" placeholder="Nombre" />
                                            </td>
                                            <td className="py-2 pr-3">
                                                <select value={item.categoria} onChange={e => actualizarItem(idx,'categoria',e.target.value)}
                                                    className="text-sm rounded border-slate-300">
                                                    <option value="producto">Producto</option>
                                                    <option value="servicio">Servicio</option>
                                                </select>
                                            </td>
                                            <td className="py-2 pr-3 w-20">
                                                <input type="number" min="1" value={item.cantidad}
                                                    onChange={e => actualizarItem(idx,'cantidad',Number(e.target.value))}
                                                    className="w-full text-sm rounded border-slate-300" />
                                            </td>
                                            <td className="py-2 pr-3 w-32">
                                                <CampoMoneda value={item.costo_estimado}
                                                    onChange={v => actualizarItem(idx,'costo_estimado',v)} />
                                            </td>
                                            <td className="py-2 pr-3 text-slate-600 font-medium whitespace-nowrap">
                                                {formatearMoneda(item.cantidad * item.costo_estimado)}
                                            </td>
                                            <td className="py-2">
                                                <button type="button" onClick={() => eliminarItem(idx)}
                                                    className="text-red-400 hover:text-red-600 text-xs">✕</button>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <div className="flex items-center justify-between pt-4 border-t border-slate-100 mt-4">
                            <span className="text-sm text-slate-500">Total estimado</span>
                            <span className="text-lg font-bold text-slate-900">{formatearMoneda(totalLocal)}</span>
                        </div>
                    </div>

                    {/* Acciones */}
                    <div className="flex justify-end gap-3">
                        <button type="submit" disabled={processing}
                            className="px-6 py-2.5 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg disabled:opacity-50 transition-colors">
                            {processing ? 'Guardando...' : (editar ? 'Actualizar solicitud' : 'Crear solicitud')}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
```

---

### Task 34: `Viaticos/Crear.jsx`

- [ ] **Crear `resources/js/Pages/Viaticos/Crear.jsx`**

```jsx
import AppLayout from '@/Layouts/AppLayout';
import { Head, useForm } from '@inertiajs/react';

export default function Crear({ usuarios }) {
    const { data, setData, post, processing, errors } = useForm({
        nombre_comision:   '',
        municipio_destino: '',
        motivo:            '',
        fecha_salida:      '',
        fecha_regreso:     '',
        viajeros:          [],
    });

    const toggleViajero = (id) => {
        const ids = data.viajeros.includes(id)
            ? data.viajeros.filter(v => v !== id)
            : [...data.viajeros, id];
        setData('viajeros', ids);
    };

    const submit = (e) => {
        e.preventDefault();
        post(route('viaticos.store'));
    };

    return (
        <AppLayout title="Nueva solicitud de viáticos">
            <Head title="Viáticos" />

            <div className="flex-1 flex flex-col px-6 py-6">
                <form onSubmit={submit} className="flex-1 flex flex-col gap-6">
                    <div className="bg-white rounded-xl border border-slate-200 p-6 grid grid-cols-1 md:grid-cols-2 gap-4">
                        <h3 className="col-span-full text-sm font-semibold text-slate-700">Información de la comisión</h3>

                        {[
                            ['nombre_comision','Nombre de la comisión','text'],
                            ['municipio_destino','Municipio destino','text'],
                            ['fecha_salida','Fecha de salida','date'],
                            ['fecha_regreso','Fecha de regreso','date'],
                        ].map(([field, label, type]) => (
                            <div key={field}>
                                <label className="block text-xs font-medium text-slate-600 mb-1">{label}</label>
                                <input type={type} value={data[field]}
                                    onChange={e => setData(field, e.target.value)}
                                    className="w-full text-sm rounded-lg border-slate-300" />
                                {errors[field] && <p className="text-red-500 text-xs mt-1">{errors[field]}</p>}
                            </div>
                        ))}

                        <div className="col-span-full">
                            <label className="block text-xs font-medium text-slate-600 mb-1">Motivo</label>
                            <textarea rows={3} value={data.motivo}
                                onChange={e => setData('motivo', e.target.value)}
                                className="w-full text-sm rounded-lg border-slate-300" />
                        </div>
                    </div>

                    {/* Viajeros */}
                    <div className="bg-white rounded-xl border border-slate-200 p-6">
                        <h3 className="text-sm font-semibold text-slate-700 mb-4">
                            Viajeros <span className="text-slate-400 font-normal">({data.viajeros.length} seleccionados)</span>
                        </h3>
                        {errors.viajeros && <p className="text-red-500 text-xs mb-2">{errors.viajeros}</p>}
                        <div className="grid grid-cols-2 md:grid-cols-3 gap-2">
                            {usuarios.map(u => (
                                <label key={u.id}
                                    className={`flex items-center gap-2 p-3 rounded-lg border cursor-pointer transition-colors ${
                                        data.viajeros.includes(u.id)
                                            ? 'border-indigo-300 bg-indigo-50'
                                            : 'border-slate-200 hover:border-slate-300'
                                    }`}>
                                    <input type="checkbox" className="rounded"
                                        checked={data.viajeros.includes(u.id)}
                                        onChange={() => toggleViajero(u.id)} />
                                    <span className="text-sm text-slate-700">{u.name}</span>
                                </label>
                            ))}
                        </div>
                    </div>

                    <div className="flex justify-end">
                        <button type="submit" disabled={processing}
                            className="px-6 py-2.5 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg disabled:opacity-50">
                            {processing ? 'Guardando...' : 'Crear solicitud'}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
```

---

### Task 35: `Viaticos/Liquidacion.jsx`

- [ ] **Crear `resources/js/Pages/Viaticos/Liquidacion.jsx`**

```jsx
import { useState } from 'react';
import AppLayout from '@/Layouts/AppLayout';
import CampoMoneda from '@/Components/CampoMoneda';
import { Head, useForm } from '@inertiajs/react';
import { formatearMoneda } from '@/lib/format';

export default function Liquidacion({ solicitud, tarifas, rubros }) {
    const cabecera = solicitud.solicitable;
    const viajeros = cabecera.viajeros ?? [];

    // Construir estado inicial: { [viajero_id]: { [rubro]: { valor_unitario, dias } } }
    const estadoInicial = {};
    viajeros.forEach(v => {
        estadoInicial[v.id] = {};
        rubros.forEach(r => {
            const asig = v.asignaciones?.find(a => a.rubro === r);
            estadoInicial[v.id][r] = {
                viajero_comision_id: v.id,
                rubro: r,
                valor_unitario: asig?.valor_unitario ?? tarifas[r]?.valor_sugerido ?? 0,
                dias: asig?.dias ?? 1,
            };
        });
    });

    const [celdas, setCeldas] = useState(estadoInicial);

    const actualizarCelda = (viajeroId, rubro, campo, valor) => {
        setCeldas(prev => ({
            ...prev,
            [viajeroId]: {
                ...prev[viajeroId],
                [rubro]: { ...prev[viajeroId][rubro], [campo]: valor },
            },
        }));
    };

    const totalViajero = (viajeroId) =>
        rubros.reduce((acc, r) => {
            const c = celdas[viajeroId][r];
            return acc + c.valor_unitario * c.dias;
        }, 0);

    const totalGeneral = viajeros.reduce((acc, v) => acc + totalViajero(v.id), 0);

    const { post, processing } = useForm();

    const guardar = () => {
        const asignaciones = viajeros.flatMap(v =>
            rubros.map(r => celdas[v.id][r])
        );
        post(route('viaticos.asignaciones', solicitud.id), { data: { asignaciones } });
    };

    return (
        <AppLayout title={`Liquidar — ${solicitud.radicado}`}>
            <Head title="Liquidación" />

            <div className="flex-1 flex flex-col px-6 py-6 gap-4 overflow-hidden">
                <div className="flex items-center justify-between">
                    <div>
                        <h2 className="text-lg font-bold text-slate-900">{cabecera.nombre_comision}</h2>
                        <p className="text-sm text-slate-500">{cabecera.municipio_destino} · {cabecera.fecha_salida} → {cabecera.fecha_regreso}</p>
                    </div>
                    <div className="text-right">
                        <p className="text-2xl font-bold text-slate-900">{formatearMoneda(totalGeneral)}</p>
                        <p className="text-xs text-slate-400">Total comisión</p>
                    </div>
                </div>

                <div className="flex-1 overflow-auto">
                    <table className="w-full text-xs border-collapse">
                        <thead>
                            <tr className="bg-slate-50">
                                <th className="text-left px-3 py-2 font-medium text-slate-600 border border-slate-200 sticky left-0 bg-slate-50 z-10">
                                    Viajero
                                </th>
                                {rubros.map(r => (
                                    <th key={r} colSpan={2} className="px-3 py-2 font-medium text-slate-600 border border-slate-200 capitalize text-center">
                                        {r}
                                    </th>
                                ))}
                                <th className="px-3 py-2 font-medium text-slate-600 border border-slate-200">Total</th>
                            </tr>
                            <tr className="bg-slate-50/50">
                                <th className="border border-slate-200 sticky left-0 bg-slate-50/50 z-10" />
                                {rubros.map(r => (
                                    <>
                                        <th key={`${r}-v`} className="px-2 py-1 text-slate-400 font-normal border border-slate-200">Valor</th>
                                        <th key={`${r}-d`} className="px-2 py-1 text-slate-400 font-normal border border-slate-200">Días</th>
                                    </>
                                ))}
                                <th className="border border-slate-200" />
                            </tr>
                        </thead>
                        <tbody>
                            {viajeros.map(v => (
                                <tr key={v.id} className="hover:bg-slate-50">
                                    <td className="px-3 py-2 font-medium text-slate-700 border border-slate-200 sticky left-0 bg-white z-10 whitespace-nowrap">
                                        {v.usuario.name}
                                    </td>
                                    {rubros.map(r => (
                                        <>
                                            <td key={`${v.id}-${r}-v`} className="border border-slate-200 p-1 w-28">
                                                <CampoMoneda
                                                    value={celdas[v.id][r].valor_unitario}
                                                    onChange={val => actualizarCelda(v.id, r, 'valor_unitario', val)}
                                                />
                                            </td>
                                            <td key={`${v.id}-${r}-d`} className="border border-slate-200 p-1 w-16">
                                                <input type="number" min="1"
                                                    value={celdas[v.id][r].dias}
                                                    onChange={e => actualizarCelda(v.id, r, 'dias', Number(e.target.value))}
                                                    className="w-full text-xs rounded border-slate-300 text-center" />
                                            </td>
                                        </>
                                    ))}
                                    <td className="px-3 py-2 font-semibold text-slate-800 border border-slate-200 whitespace-nowrap">
                                        {formatearMoneda(totalViajero(v.id))}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                        <tfoot>
                            <tr className="bg-slate-50 font-semibold">
                                <td colSpan={rubros.length * 2 + 1}
                                    className="px-3 py-2 text-right text-sm text-slate-700 border border-slate-200">
                                    Total general:
                                </td>
                                <td className="px-3 py-2 text-sm text-slate-900 border border-slate-200 whitespace-nowrap">
                                    {formatearMoneda(totalGeneral)}
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div className="flex justify-end pt-2">
                    <button onClick={guardar} disabled={processing}
                        className="px-6 py-2.5 text-sm font-semibold text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg disabled:opacity-50">
                        {processing ? 'Guardando...' : 'Guardar asignaciones'}
                    </button>
                </div>
            </div>
        </AppLayout>
    );
}
```

- [ ] **Compilar y verificar**

```bash
npm run build
php artisan test
```

Resultado esperado: assets compilados sin errores, 5 tests passing.

- [ ] **Commit final**

```bash
git add resources/js/ routes/
git commit -m "feat: fase 5 - páginas de procesos (oficina, viáticos, liquidación)"
```

---

## Auto-revisión del plan

### Cobertura del spec (PROMPT_CLAUDE_CODE_1.md)

| Sección spec | Task(s) que lo cubre |
|---|---|
| §3 Modelo de datos | Tasks 2–7 |
| §4 Matrices workflow | Task 3 (TipoSolicitudSeeder) |
| §5 Roles | Ya seedeado en fase anterior |
| §6 WorkflowService (`MotorWorkflow`) | Task 15 |
| §7.1 Rutas | Task 25 |
| §7.2 Controladores Inertia | Tasks 22–24 |
| §7.3 Props compartidas | Task 25 (HandleInertiaRequests) |
| §8 Frontend — componentes | Tasks 26–29 |
| §8 Frontend — páginas bandejas | Task 30 |
| §8 Frontend — páginas Show | Task 31 |
| §8 Frontend — crear oficina | Task 33 |
| §8 Frontend — crear viáticos | Task 34 |
| §8 Frontend — liquidación | Task 35 |
| §9 Seeders demo | Task 8 |
| §10 CLAUDE.md/README actualizado | Pendiente: actualizar manualmente tras completar |
| Tests flujo completo | Tasks 18–19 |
| Regla de oro de totales | Tasks 12–13 (eventos modelo) |
| Generación radicado | Task 10 |
| Notificaciones canal database | Tasks 17, 25 |
| Política SolicitudPolicy | Task 16 |
| TransicionNoPermitidaException | Task 14 |

### Notas de implementación críticas

1. **FK explícitas en Eloquent**: todos los `belongsTo`/`hasMany` pasan el nombre de la FK como segundo argumento (ESTANDARES §4.4).
2. **`updateQuietly`** en `recalcularTotal()` evita disparar eventos recursivos.
3. **`MotorWorkflow` recibe `Solicitud` con `tipoSolicitud` ya cargada**; cargar la relación antes de llamar al servicio si no está eager-loaded.
4. **Rutas de viáticos** en el `ViaticosController::updateAllocations` reciben `{ data: { asignaciones } }` desde React; ajustar si se usa `useForm` de Inertia directamente.
5. **`SolicitudController::index` con tab `pendientes`**: la implementación actual carga todas las solicitudes y filtra en PHP. Para producción con volumen alto, convertir a query con join sobre `tipos_solicitud.transiciones` JSON.
