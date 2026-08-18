# Viáticos — Bloque B (Viajeros): contrato por viajero y viajero externo

**Fecha:** 2026-08-18
**Rama:** `feature/viaticos-viajeros`
**Depende de:** Bloque A (catálogos `municipios` y `contratos`, ya en `main`).

## Objetivo

Sobre la tabla `viajeros_comision`, habilitar dos capacidades en el formulario de comisión de viáticos:

1. **Cambio 3 — Contrato por viajero:** asociar (opcionalmente) un contrato a cada viajero.
2. **Cambio 4 — Viajero externo:** permitir viajeros que no están en la base de empleados, capturando nombre e identificación como texto libre.

Además, implementar la **regla diferida del Bloque A**: bloquear el borrado de un contrato que tenga viajeros asociados.

## Decisiones de diseño (acordadas con el usuario)

- **Contrato:** el select lista **todos** los contratos (sin filtrar por municipio). El contrato es **opcional** (`contrato_id` nullable).
- **Viajero externo:** se capturan **nombre + identificación** (texto libre). Sin email ⇒ el botón "Correo" queda deshabilitado para externos (comportamiento ya existente, basado en `empleado?.email`).
- **UX de selección empleado/externo:** un **interruptor "Viajero externo"** en el mini-formulario del viajero. Apagado ⇒ select de empleado. Encendido ⇒ campos de texto Nombre e Identificación.

## Modelo de datos

### Migración: contrato por viajero
Agregar a `viajeros_comision`:
- `contrato_id` → `foreignId` **nullable**, FK a `contratos`, `nullOnDelete()` (si se borra el contrato, el viajero queda sin contrato; no rompe la fila).

### Migración: viajero externo
Sobre `viajeros_comision`:
- `empleado_id` → cambiar a **nullable** (hoy es FK NOT NULL).
  - **`doctrine/dbal` NO está instalado**, así que `$table->foreignId('empleado_id')->nullable()->change()` NO funciona en Laravel 10.
  - **MariaDB (prod/dev):** `DB::statement('ALTER TABLE viajeros_comision MODIFY empleado_id BIGINT UNSIGNED NULL')`.
  - **SQLite (tests, `:memory:`):** SQLite **no soporta `MODIFY COLUMN`** ni alterar nullability de una columna existente. Estrategia recomendada: en la migración, detectar el driver (`DB::getDriverName()`). Para `sqlite`, **recrear la tabla** dentro de esta misma migración: renombrar `viajeros_comision` → tmp, `Schema::create` con el esquema final (empleado_id nullable + columnas externas + contrato_id), copiar datos, drop tmp. Para `mysql`/`mariadb`, usar el `ALTER ... MODIFY`. Alternativa más simple y preferible si el orden de migraciones lo permite: como en `:memory:` la tabla se crea desde cero en cada corrida de tests, **modificar la migración original `create_viajeros_comision_table` para que `empleado_id` sea nullable de entrada** NO es opción (rompe idempotencia en prod donde ya existe NOT NULL). Por tanto, la ruta driver-aware es la correcta.
  - Verificar en implementación con `php artisan migrate:fresh` (MariaDB) y con la suite (SQLite) que ambas pasen.
- `nombre_externo` → `string` nullable.
- `identificacion_externo` → `string` nullable.

Ambas migraciones deben ser **idempotentes** (`Schema::hasColumn`) porque algunas bases de desarrollo pueden ya tener columnas creadas fuera del flujo.

### Regla de integridad (nivel dominio, no solo BD)
El borrado de un contrato con viajeros asociados se bloquea **en el controlador** (mensaje claro al usuario), no solo por la FK. La FK usa `nullOnDelete`, así que la BD no lo impediría por sí sola — el bloqueo es responsabilidad de `destroyContrato`.

## Modelo `ViajeroComision`

- `$fillable`: añadir `contrato_id`, `nombre_externo`, `identificacion_externo`.
- Relación: `contrato()` → `belongsTo(Contrato::class, 'contrato_id')`.
- **Accessor `nombreMostrado`** (centraliza la lógica hoy duplicada en 6+ consumidores):
  - Si hay empleado: `trim("{$empleado->nombres} {$empleado->apellidos}")`.
  - Si no: `nombre_externo ?? ''`.
- **Accessor `identificacionMostrada`** (análogo): `empleado?->identificacion ?? identificacion_externo`.

Estos accessors NO cambian el contrato de datos existente; los consumidores migran a usarlos progresivamente (ver más abajo), pero el fallback `?? ''` que ya usan sigue siendo válido durante la transición.

## Validación — `GuardarSolicitudViaticosRequest`

Reglas nuevas/ajustadas para `viajeros.*`:

- `viajeros.*.es_externo` → `nullable|boolean` (bandera de UI; no se persiste como columna).
- `viajeros.*.contrato_id` → `nullable|exists:contratos,id`.
- `viajeros.*.empleado_id`:
  - **required_if** `es_externo` es falso/ausente → `required_unless:viajeros.*.es_externo,true` + `nullable|exists:empleados,id`.
- `viajeros.*.nombre_externo`:
  - `required_if:viajeros.*.es_externo,true` + `nullable|string|max:255`.
- `viajeros.*.identificacion_externo`:
  - `required_if:viajeros.*.es_externo,true` + `nullable|string|max:50`.

Mensajes personalizados en español para los `required_if`/`required_unless`.

> Nota de implementación: las reglas por-índice con `required_unless`/`required_if` sobre `viajeros.*` usan el patrón de Laravel `viajeros.*.campo`. Verificar que `required_unless` con un valor booleano `true` funcione contra el payload (el front debe enviar `es_externo` como booleano real). Alternativa robusta: validar con un `Closure`/`after` hook si `required_unless` sobre wildcard da problemas.

## Controlador `ViaticosController`

### `create()` / `edit()`
- Pasar props `contratos` → `Contrato::orderBy('descripcion')->get(['id','descripcion','objeto'])`.
- `edit()`: eager-load `solicitable.viajeros.contrato` (además del `.empleado` actual).

### `store()` / `update()`
Al crear cada `ViajeroComision`, mapear:
- `empleado_id` → `$v['empleado_id'] ?? null` (será `null` cuando es externo).
- `contrato_id` → `$v['contrato_id'] ?? null`.
- `nombre_externo` → `$v['es_externo'] ?? false ? ($v['nombre_externo'] ?? null) : null`.
- `identificacion_externo` → análogo.

`update` mantiene la estrategia delete-and-recreate ya existente.

## Regla diferida del Bloque A — `destroyContrato`

En `ParametrosController::destroyContrato` (línea 111 tiene el comentario-marcador):

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

Requiere agregar en `Contrato` la relación inversa:
- `viajeros()` → `hasMany(ViajeroComision::class, 'contrato_id')`.

## Frontend — `resources/js/Pages/Viaticos/Crear.jsx`

### Estado
- `VIAJERO_VACIO`: añadir `es_externo: false`, `contrato_id: ''`, `nombre_externo: ''`, `identificacion_externo: ''`.
- `viajerosIniciales` (modo editar): mapear los nuevos campos desde `v` (derivar `es_externo` de `!v.empleado_id`).

### Mini-formulario del viajero
- **Toggle "Viajero externo (no está en la lista)"** (checkbox).
- **Apagado:** select de empleado (como hoy).
- **Encendido:** dos inputs de texto — "Nombre del viajero" e "Identificación" — y se oculta el select de empleado.
- **Select "Contrato (opcional)"** siempre visible: `— Sin contrato —` + lista de `contratos` (mostrando `descripcion`).
- `validarForm()`: si `es_externo` ⇒ exigir `nombre_externo` e `identificacion_externo`; si no ⇒ exigir `empleado_id` (como hoy). Contrato nunca obligatorio.
- `agregarViajero()`: componer `nombre` mostrado desde empleado o desde `nombre_externo`; incluir `contrato_id` numérico o `null`.

### Tabla de viajeros agregados
- Mostrar el `nombre` (ya se hace) y, opcionalmente, una columna/línea con el contrato asociado (`descripcion`) cuando exista.

## Consumidores de lectura (migración a `nombreMostrado`)

Reemplazar el acceso directo `empleado->nombres/apellidos` por el accessor `nombreMostrado` para que el externo aparezca con su nombre (hoy saldría vacío por el `?? ''`). Archivos:

- `app/Mail/LiquidacionViajeroMail.php:34`
- `app/Services/LiquidacionPdf.php:33` y `:57` (nombre del archivo)
- `app/Notifications/ComisionCerradaNotification.php:34-35`
- `app/Http/Controllers/ComisionesRrhhController.php:36-37`
- Frontend `Detalle.jsx` (líneas ~179, ~246) y `Liquidacion.jsx` (~116): ya usan guards `v.empleado ? ... : '—'`; añadir fallback a `v.nombre_externo`.

El botón "Correo" (`Detalle.jsx:212`, `LiquidacionPdfController.php:34`) sigue dependiendo de `empleado?->email`; para externos permanece deshabilitado/abortado con el mensaje actual. Correcto por diseño.

## Testing

Tests nuevos (feature, SQLite en memoria):

1. **Contrato por viajero se persiste:** crear comisión con `viajeros[0].contrato_id` ⇒ el viajero queda con ese contrato; con `contrato_id` ausente ⇒ `null`.
2. **Viajero externo se persiste:** `es_externo=true`, `nombre_externo`, `identificacion_externo`, sin `empleado_id` ⇒ se guarda con `empleado_id` null y los campos externos poblados.
3. **Validación externo:** `es_externo=true` sin `nombre_externo` ⇒ error de validación.
4. **Validación empleado:** `es_externo=false` sin `empleado_id` ⇒ error de validación.
5. **`nombreMostrado`:** empleado ⇒ nombre del empleado; externo ⇒ `nombre_externo`.
6. **Bloqueo de borrado de contrato:** contrato con viajero asociado ⇒ `destroyContrato` responde con error y el contrato sigue existiendo; contrato sin viajeros ⇒ se borra.
7. **`nullOnDelete`:** borrar un contrato **sin viajeros** no afecta nada; (caso con viajeros nunca llega a borrar por el guard del controlador).
8. **Editar comisión** preserva/actualiza `contrato_id` y campos externos (delete-and-recreate).

Toda la suite existente (135) debe seguir verde.

## Fuera de alcance (Bloque C)

Soporte de transferencia por viajero (capturas), editar planilla (días ±, soportes), regla de meriendas (1/día). No se tocan aquí.
