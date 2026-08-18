# Viáticos — Bloque C (Liquidación): archivos por viajero, editar planilla y regla de meriendas

**Fecha:** 2026-08-18
**Rama:** `feature/viaticos-liquidacion`
**Depende de:** Bloques A y B (ya en `main`).

## Objetivo

Cerrar los 3 cambios restantes de los 7 pedidos para viáticos, más un bug bloqueante:

5. **Comprobante de transferencia por viajero:** adjuntar capturas cuando el pago es por transferencia.
6. **Editar planilla de liquidación:** aumentar/reducir días y adjuntar soportes adicionales por viajero.
7. **Regla de meriendas:** contar solo 1 merienda por día (hoy cuenta hasta 2).
- **Bug bloqueante:** `Liquidacion.jsx:72` llama `diasEntre(...)` (inexistente) al agregar un rubro → `ReferenceError`. Debe arreglarse para que "editar planilla / agregar rubro" funcione.

## Decisiones de diseño (acordadas con el usuario)

- **Archivos por viajero en UNA sola tabla** `archivos_viajero` con campo `tipo` (`comprobante` | `soporte`). Varios archivos por viajero. Un solo controlador y endpoints, filtrando por `tipo`.
- **Comprobante (tipo=comprobante):** se adjunta en la sección de pago del viajero, condicionado a `tipo_pago === 'transferencia'`.
- **Soportes adicionales (tipo=soporte):** se adjuntan por viajero, en su propio bloque.
- **Bug `diasEntre` y meriendas:** dentro de este bloque, con tests.

## Modelo de datos

### Migración: tabla `archivos_viajero`
```
id
viajero_comision_id  -> FK a viajeros_comision, cascadeOnDelete
tipo                 -> enum('comprobante','soporte')
path                 -> string (ruta en disco 'local')
nombre               -> string (nombre original del archivo)
usuario_id           -> FK a usuarios (quién lo subió), nullable
timestamps
```
Idempotente (`Schema::hasTable`). Índice por `(viajero_comision_id, tipo)` para listar rápido.

### Modelo `ArchivoViajero`
- `$fillable`: `viajero_comision_id`, `tipo`, `path`, `nombre`, `usuario_id`.
- Relación `viajero()` → `belongsTo(ViajeroComision::class, 'viajero_comision_id')`.
- Constantes o scope para `tipo` (`comprobante`/`soporte`).

### Modelo `ViajeroComision`
- Relación `archivos()` → `hasMany(ArchivoViajero::class, 'viajero_comision_id')`.
- Accessors o scopes de conveniencia: `comprobantes()` / `soportes()` (filtran por tipo) — opcional; el front puede filtrar por `tipo`.

## Almacenamiento de archivos (reutiliza el patrón de `AbonoOficinaController`)

- Disco `local`, carpeta `archivos_viajero`.
- Validación: `file|mimes:pdf,jpg,jpeg,png|max:5120` (5 MB), igual que abonos/cotizaciones.
- Al subir: `store('archivos_viajero','local')` + guardar `nombre` original.
- Al borrar: `Storage::disk('local')->delete($path)` y luego `delete()` del registro.
- Descarga controlada: `Storage::disk('local')->download($path, $nombre)` con `abort_unless` de pertenencia.

## Controlador y rutas

Nuevo controlador `ArchivoViajeroController` (o métodos en `ViaticosController`; se prefiere controlador propio por cohesión), con:

- `store(Solicitud $solicitud, ViajeroComision $viajero)` — sube uno o varios archivos de un `tipo`. Autoriza con la policy `editarLiquidacion` (mismo contador que liquida). Valida que el viajero pertenezca a la comisión.
- `destroy(Solicitud $solicitud, ViajeroComision $viajero, ArchivoViajero $archivo)` — borra archivo + registro. Misma policy.
- `descargar(Solicitud $solicitud, ViajeroComision $viajero, ArchivoViajero $archivo)` — descarga. Autoriza con `verDetalle` (quien ve el detalle puede descargar).

Rutas nuevas en `routes/web.php` (patrón de nombres coherente con `oficina.abono.*`):
```
POST   viaticos/{solicitud}/viajeros/{viajero}/archivos           -> viaticos.archivos.store
GET    viaticos/{solicitud}/viajeros/{viajero}/archivos/{archivo} -> viaticos.archivos.descargar
DELETE viaticos/{solicitud}/viajeros/{viajero}/archivos/{archivo} -> viaticos.archivos.destroy
```
El `tipo` (`comprobante`/`soporte`) viaja en el body del `store` (validado `in:comprobante,soporte`).

### Autorización (policies existentes)
- `editarLiquidacion` (rol `contador`, VIA, estado `enviada`|`liquidada`) para subir/borrar.
- `verDetalle` para descargar.

## Request de validación `GuardarArchivoViajeroRequest`
```
tipo         => 'required|in:comprobante,soporte'
archivos     => 'required|array|min:1'
archivos.*   => 'file|mimes:pdf,jpg,jpeg,png|max:5120'
```
(Subida múltiple, como `SeccionCotizacion`.)

## Frontend — `resources/js/Pages/Viaticos/Liquidacion.jsx`

Los uploads NO van en el `put()` de asignaciones (PUT no soporta multipart en Inertia). Se usan `router.post(..., { forceFormData: true, preserveScroll: true })` a los endpoints dedicados, y el listado de archivos se re-renderiza desde las props (Inertia recarga tras el post).

### Cambio 5 — comprobante en la sección de pago
En el bloque de toggle Efectivo/Transferencia de cada viajero (líneas ~138-160): cuando el `tipo_pago` activo sea `transferencia`, mostrar:
- Lista de comprobantes ya subidos (`viajero.archivos` filtrados por `tipo==='comprobante'`) con enlace de descarga y botón de eliminar.
- Un input file (múltiple) + botón "Adjuntar comprobante" que hace `router.post` a `viaticos.archivos.store` con `tipo='comprobante'`.

### Cambio 6 — editar planilla
- **Bug `diasEntre` (línea 72):** importar `diasComision` desde `@/lib/rubros` (línea 8 ya importa `rubrosPorDefecto`) y reemplazar `diasEntre(...)` por `diasComision(viajero?.fecha_salida, viajero?.fecha_regreso)`. Con esto "Agregar rubro" deja de lanzar `ReferenceError`.
- Aumentar/reducir días ya funciona (input `dias` + `actualizarAsignacion`); tras el fix del bug, agregar/quitar rubros también. Re-editar una planilla `liquidada` ya está permitido por `updateAllocations` + policy.
- **Soportes adicionales:** un bloque por viajero (junto a su planilla) que lista `viajero.archivos` filtrados por `tipo==='soporte'` con descarga/eliminar, y un input file múltiple + "Adjuntar soporte" (`router.post` con `tipo='soporte'`).

### Carga de archivos en props
`ViaticosController::liquidacion()` debe eager-load `solicitable.viajeros.archivos` para que el front tenga la lista. (Hoy carga `solicitable.viajeros.empleado` y `.asignaciones`.)

## Cambio 7 — regla de meriendas (1/día) — `resources/js/lib/rubros.js`

En `conteoComidas` (líneas ~59-81), reemplazar:
```js
if (desayuno) conteo.merienda += 1;
if (cena) conteo.merienda += 1;
```
por:
```js
if (desayuno || almuerzo || cena) conteo.merienda += 1;
```
Actualizar los comentarios de cabecera (líneas ~10-12) y el de la línea ~75 para reflejar "1 merienda por día con alguna comida presente".

No hay tests JS que ejerciten esta función hoy (verificado); si se añade cobertura, hacerlo con un pequeño test de la lógica (opcional, ver Testing).

## Testing

Tests de feature (PHP, SQLite en memoria):

1. **Subir comprobante:** `contador` sube un archivo `tipo=comprobante` a un viajero de una comisión `VIA` en estado válido ⇒ se crea `ArchivoViajero`, el archivo existe en disco (usar `Storage::fake('local')`).
2. **Subir soporte:** análogo con `tipo=soporte`.
3. **Múltiples archivos:** subir 2 archivos en un request ⇒ 2 registros.
4. **Autorización:** un usuario sin rol `contador` (o estado inválido) recibe 403 al subir.
5. **Descargar:** quien ve el detalle descarga; respuesta `200` y nombre correcto (`Storage::fake`).
6. **Eliminar:** `contador` borra ⇒ registro y archivo eliminados; usuario no autorizado ⇒ 403.
7. **Pertenencia:** subir/borrar/descargar con un `viajero`/`archivo` que no pertenece a la comisión ⇒ 404.
8. **Validación:** archivo con mime no permitido o `tipo` inválido ⇒ error de validación.

Cambio 7 (meriendas) — cobertura mínima: como es JS sin runner de tests JS en el proyecto, se valida con `npm run build` y revisión manual de la lógica. Si se desea, añadir un test PHP no aplica (la lógica es de front). El plan documentará la verificación manual con un caso (comisión de N días ⇒ N meriendas).

Bug `diasEntre` — se valida con `npm run build` (el `ReferenceError` es runtime, no de compilación) + revisión de que `diasComision` esté importada y usada. Idealmente un caso manual: agregar un rubro no lanza error y precarga los días de la comisión.

Toda la suite existente (142) debe seguir verde.

## Fuera de alcance

- Alterar el enum `rubro` de `asignaciones_viaticos` (no se añaden rubros nuevos).
- PDF de liquidación / envío por correo (ya existen; no se tocan salvo que usen `nombreMostrado`, ya migrado en Bloque B).
