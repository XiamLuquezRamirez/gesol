# Diseño — Mejoras al flujo de solicitudes de oficina

**Fecha:** 2026-08-06
**Proyecto:** Gesol (Laravel 10 + Inertia/React + MariaDB)
**Alcance:** 7 mejoras sobre el flujo de solicitudes de oficina (OFI). Un solo plan por fases.

---

## Contexto y decisiones previas

De la lista original de 8 puntos, el **punto 1 (gestión de usuarios con roles) queda fuera de alcance**: la
funcionalidad ya existe y funciona ([UsuarioController](../../../app/Http/Controllers/UsuarioController.php),
[Usuarios/Index.jsx](../../../resources/js/Pages/Usuarios/Index.jsx), rutas `usuarios.*` protegidas por
`role:admin`). El enlace en el menú solo aparece para el rol `admin` ([AppLayout.jsx:155](../../../resources/js/Layouts/AppLayout.jsx));
el usuario confirmó que ya encontró la vista entrando como `admin@demo.test`.

### Hallazgos de la exploración (estado actual)

- **Pago:** la transición `pagar` (`aprobada → pagada`) **solo cambia el estado**. Los datos de pago
  (`valor_pagado`, `fecha_pago`, `comprobante`) se capturan en el modal pero terminan en el JSON
  `metadatos` de `transiciones_solicitud`; **nunca** se escriben en `solicitudes_oficina`. `comprobante`
  es texto libre, **no un archivo**.
- **Beneficiario:** `solicitudes_oficina.beneficiario` es un **string de texto libre**, no un empleado.
- **Cotizaciones:** la tabla `cotizaciones_oficina` **no registra quién subió** cada archivo (sin `usuario_id`).
- **Notificación de oficina:** al enviar (`borrador → enviada`) el motor notifica al rol `rrhh`; al verificar
  (`enviada → verificada`) notifica a `contabilidad_lider`. **Los contadores no reciben aviso.**
- **Panel RR.HH.** ([Rrhh/Comisiones.jsx](../../../resources/js/Pages/Rrhh/Comisiones.jsx)): muestra
  **solo viáticos**, no solicitudes de oficina.

### Decisiones del usuario

| Tema | Decisión |
|---|---|
| Paso de gerencia (punto 7) | Solo renombrar, **sin rol nuevo**. `contabilidad_lider` sigue registrando el pago. |
| Pagos parciales (punto 6) | **Múltiples abonos con saldo**; un abono puede cubrir la totalidad. |
| Beneficiarios (punto 2) | **Varios empleados** por solicitud (multi-select del catálogo). |
| Aviso a contadores (punto 4) | **Al llegar a contabilidad** (transición `verificar`). |
| Permiso de anexos (punto 3) | **Solo el autor** del anexo puede eliminar/actualizar, mientras no esté cerrada. |
| RR.HH. oficina (punto 8) | **Nueva pestaña** en el panel RR.HH. existente. |
| Estructura del plan | **Un plan único por fases** (datos → backend → UI). |
| Quién registra abonos | **Solo `contabilidad_lider`**. |

---

## Sección 1 — Modelo de datos

Tres tablas nuevas y un campo nuevo. Todas son `create table` nuevas (compatibles MariaDB y SQLite sin ALTER
especial); el cuidado de detección de driver solo aplicaba a modificaciones de columnas existentes.

### `beneficiarios_oficina` (nueva) — punto 2
Pivote solicitud ↔ empleado.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint auto-inc | |
| `solicitud_oficina_id` | foreignId → `solicitudes_oficina` | `cascadeOnDelete` |
| `empleado_id` | foreignId → `empleados` | |
| `created_at` / `updated_at` | timestamps | |

La columna `solicitudes_oficina.beneficiario` (string) **se conserva** por compatibilidad histórica pero deja
de usarse en el formulario nuevo.

### `cotizaciones_oficina.usuario_id` (columna nueva) — punto 3
Agregar `usuario_id` (foreignId → `usuarios`, **nullable** para filas antiguas) que registra quién subió el
archivo. Filas migradas del backfill antiguo quedan con `usuario_id = null`.

### `abonos_oficina` (nueva) — puntos 5 y 6
Cada pago es un abono con su soporte adjunto.

| Columna | Tipo | Notas |
|---|---|---|
| `id` | bigint auto-inc | |
| `solicitud_oficina_id` | foreignId → `solicitudes_oficina` | `cascadeOnDelete` |
| `monto` | decimal(14,2) | |
| `fecha_pago` | date | |
| `soporte_path` | string | archivo en disco `local`, carpeta `soportes_pago` |
| `soporte_nombre` | string | nombre original del archivo |
| `usuario_id` | foreignId → `usuarios` | quién registró el abono |
| `observacion` | string | nullable |
| `created_at` / `updated_at` | timestamps | |

- **Total pagado** = `sum(abonos_oficina.monto)`; **saldo** = `total − pagado`. Se **calculan** (no se cachean
  en la solicitud) para evitar desincronización, siguiendo el patrón de `recalcularTotal()`.
- Las columnas obsoletas `valor_pagado` / `fecha_pago` / `comprobante` de `solicitudes_oficina` **no se tocan**
  (se dejan sin uso para no romper migraciones); el flujo nuevo usa `abonos_oficina`.

### Modelos Eloquent
- `SolicitudOficina`: `beneficiarios()` belongsToMany `Empleado` (vía `beneficiarios_oficina`) o hasMany
  a un modelo `BeneficiarioOficina`; `abonos()` hasMany `AbonoOficina`. Helpers `totalPagado()` y `saldo()`.
- `CotizacionOficina`: `usuario()` belongsTo `Usuario`; agregar `usuario_id` a `$fillable`.
- `AbonoOficina` (nuevo): `$fillable` con las columnas de arriba; `solicitudOficina()`, `usuario()` belongsTo.
  `$casts`: `fecha_pago => 'date'`, `monto => 'decimal:2'`.

---

## Sección 2 — Flujo de estados y transiciones (OFI)

### Estado actual
```
borrador → enviar → enviada → verificar → verificada → aprobar → aprobada → pagar → pagada → cerrar → cerrada
                                                          ↘ rechazar → rechazada → reenviar → verificada
```

### Estado propuesto
```
verificada → [Enviar a gerencia] → aprobada
aprobada → (registrar 1er abono) → pendiente_cierre
pendiente_cierre → (más abonos) → pendiente_cierre
pendiente_cierre → [Cerrar] → cerrada
```

### Cambios
1. **Renombrar "Aprobar" → "Enviar a gerencia"** (punto 7). La transición `verificada → aprobada` mantiene su
   mecánica y su rol (`contabilidad_lider`); solo cambia el `label` a `"Enviar a gerencia"`. La **clave interna
   del estado `aprobada` no se renombra** (para no romper el histórico); se muestra con etiqueta legible
   "En gerencia · pendiente por pagar".
2. **Nuevo estado `pendiente_cierre`** ("Pendiente por cerrar"). Se agrega a la lista `estados` de OFI.
3. **El pago deja de ser una transición simple.** Registrar un abono es una **acción propia** (endpoint
   dedicado, no `MotorWorkflow`), disponible en estados `aprobada` y `pendiente_cierre`. Al registrarse el
   **primer** abono, la solicitud pasa `aprobada → pendiente_cierre` automáticamente. Un abono puede cubrir la
   totalidad (saldo 0) — la distinción parcial/total es solo el monto.
4. **`cerrar`** disponible en `pendiente_cierre` (`pendiente_cierre → cerrada`), ejecutada por
   `contabilidad_lider` (y `lider_area`, como hoy en `cerrar`). Puede cerrarse aunque quede saldo; la UI
   advierte si el saldo no es cero.
5. Se elimina la transición `aprobada → pagar → pagada` y el estado `pagada` deja de usarse en el flujo nuevo
   (se conserva en la lista de estados por compatibilidad con solicitudes históricas, si las hubiera).

`TipoSolicitudSeeder` usa `upsert`; re-ejecutarlo aplica los cambios. Transiciones OFI resultantes:

```php
['origen'=>'borrador',   'accion'=>'enviar',    'destino'=>'enviada',          'roles'=>['lider_area'],                            'label'=>'Enviar a RR. HH.'],
['origen'=>'enviada',    'accion'=>'verificar', 'destino'=>'verificada',       'roles'=>['rrhh'], 'notificar'=>['contador'],       'label'=>'Verificar'],
['origen'=>'enviada',    'accion'=>'devolver',  'destino'=>'borrador',         'roles'=>['rrhh'],                                  'label'=>'Devolver'],
['origen'=>'verificada', 'accion'=>'aprobar',   'destino'=>'aprobada',         'roles'=>['contabilidad_lider'],                    'label'=>'Enviar a gerencia'],
['origen'=>'verificada', 'accion'=>'rechazar',  'destino'=>'rechazada',        'roles'=>['contabilidad_lider'],                    'label'=>'Rechazar'],
['origen'=>'rechazada',  'accion'=>'reenviar',  'destino'=>'verificada',       'roles'=>['rrhh'], 'notificar'=>['contabilidad_lider'], 'label'=>'Reenviar a contabilidad'],
['origen'=>'pendiente_cierre', 'accion'=>'cerrar', 'destino'=>'cerrada',       'roles'=>['contabilidad_lider','lider_area'],       'label'=>'Cerrar'],
```

> El paso `aprobada → pendiente_cierre` **no** es una transición del seeder: lo dispara el endpoint de abonos
> al registrar el primero.

---

## Sección 3 — Notificaciones y permisos

### Notificar a contadores al llegar a contabilidad (punto 4)
En la transición `verificar` (`enviada → verificada`) se añade `'notificar'=>['contador']` en el seeder. El
motor emitirá `AvisoTransicionNotification` tipo `informativo` al rol `contador`. El `contabilidad_lider` sigue
recibiendo su aviso de `accion_requerida` por el mecanismo genérico (tiene la transición `aprobar` con origen
`verificada`).

### Permiso de cotizaciones: solo el autor (punto 3)
- **Anexar**: se mantiene la regla por rol (`rrhh`/`lider_area`, estados abiertos), pero ahora graba
  `usuario_id = auth()->id()`.
- **Eliminar / Actualizar (reemplazar)**: **solo el autor** (`cotizacion.usuario_id === auth id`) y **solo si
  la solicitud no está `cerrada`**. Nueva regla en `SolicitudPolicy` (método `gestionarCotizacion(Usuario,
  CotizacionOficina)` o equivalente) que centraliza "es autor Y no cerrada".
- **Actualizar** = reemplazar archivo: endpoint `actualizarCotizacion` que borra el archivo viejo del disco y
  guarda el nuevo, validando autoría. El `comentario_contador` sigue editable como hoy.

### Permiso para registrar/gestionar abonos
- Nueva política `registrarAbono`: rol `contabilidad_lider` y estado ∈ `['aprobada','pendiente_cierre']`.
- Eliminar un abono (corrección): mismo rol, mientras la solicitud **no** esté `cerrada`. Una vez cerrada, los
  abonos quedan inmutables.

### Descarga de soportes de pago (punto 8)
Endpoint `descargarSoporte(Solicitud, AbonoOficina)` autorizado por `verDetalle` (igual que cotizaciones), para
que RR.HH. y demás roles con visibilidad puedan bajar el soporte. Valida pertenencia y existencia del archivo.

---

## Sección 4 — UI y controladores

### a) Creación de oficina — beneficiarios múltiples (punto 2)
- [Oficina/Crear.jsx](../../../resources/js/Pages/Oficina/Crear.jsx): reemplazar el `TextField` `beneficiario`
  por un **multi-select de empleados** (chips/checkboxes).
- `OficinaController::create/edit`: añadir prop `empleados`
  (`Empleado::orderBy('nombres')->get(['id','nombres','apellidos','identificacion'])`).
- `store/update`: sincronizar la tabla pivote `beneficiarios_oficina`.
- `GuardarSolicitudOficinaRequest`: validar `beneficiarios` (array, `min:1`), `beneficiarios.*`
  (`exists:empleados,id`); nombre legible en `attributes()`.

### b) Detalle — cotizaciones como lista gestionable (punto 3)
- [Solicitudes/Detalle.jsx](../../../resources/js/Pages/Solicitudes/Detalle.jsx), `SeccionCotizacion`: cada fila
  muestra nombre, **quién lo subió** y fecha. Botones **Eliminar** y **Actualizar (reemplazar)** solo en las
  cotizaciones con `puede_gestionar === true`.
- `SolicitudDetalleResource`: exponer por cada cotización `id`, `nombre`, `autor`, `puede_gestionar` (autor +
  no cerrada).
- Se mantiene el input multi-archivo de anexado.

### c) Detalle — sección Pagos/Abonos (puntos 5, 6)
- Nueva sección "Pagos" en el detalle de oficina, visible en estados `aprobada`, `pendiente_cierre`, `cerrada`.
- **Resumen**: Total · Pagado · Saldo (color según haya saldo).
- **Lista de abonos**: monto, fecha, quién registró, observación, enlace de descarga del soporte.
- **Formulario "Registrar abono"** (solo `contabilidad_lider`, estados abiertos): monto, fecha, soporte
  (pdf/jpg/png), observación. El primer abono pasa la solicitud a `pendiente_cierre`.
- Se elimina del `ModalAccion` el paso `pagar` (ya no es transición simple).

### d) Etiquetas de estado (punto 7)
- `BadgeEstado`: `aprobada` → "En gerencia · pendiente por pagar"; `pendiente_cierre` → "Pendiente por cerrar".
- Botón de la transición `verificada→aprobar`: "Enviar a gerencia".

### e) Lista — pestaña "Pendientes por cerrar" (punto 6)
- [Solicitudes/Index.jsx](../../../resources/js/Pages/Solicitudes/Index.jsx): nueva pestaña **"Pendientes por
  cerrar"** que lista OFI en `pendiente_cierre` visibles para el usuario.
- `SolicitudController::index`: soportar el nuevo `tab`.

### f) Panel RR.HH. — pestaña "Solicitudes de oficina" (punto 8)
- [Rrhh/Comisiones.jsx](../../../resources/js/Pages/Rrhh/Comisiones.jsx): convertir en dos pestañas — "Personal
  en comisión" (actual) y **"Solicitudes de oficina"** (nueva).
- Nueva pestaña: lista OFI con abonos, mostrando total/pagado/saldo, estado y **descarga de soportes**.
- Backend: método/consulta dedicada (se decidirá en el plan si va en `ComisionesRrhhController` o en un
  `OficinaRrhhController` nuevo, por limpieza de responsabilidades).

### Testing
- Beneficiarios múltiples (sincronización pivote, validación `min:1`).
- Autoría de cotizaciones: solo el autor elimina/actualiza; otro rol o no-autor → 403; no gestionable si cerrada.
- Abonos: suma de montos, cálculo de saldo, paso automático `aprobada → pendiente_cierre` en el primer abono,
  abono total (saldo 0), descarga de soporte, eliminación por corrección, bloqueo si cerrada.
- Notificación a `contador` en `verificar`.
- Visibilidad en RR.HH. (pestaña oficina) y en la pestaña "Pendientes por cerrar".
- Actualizar tests OFI existentes (`MotorWorkflowOficinaTest`) por el nuevo estado y la eliminación de `pagar`.
- Suite con SQLite `:memory:`; `Storage::fake('local')`, `UploadedFile::fake()`, `Notification::fake()`.

---

## Fases de implementación (resumen; el detalle va en el plan)

1. **Datos:** migraciones (`beneficiarios_oficina`, `abonos_oficina`, `cotizaciones_oficina.usuario_id`),
   modelos (`AbonoOficina`, relaciones, helpers `totalPagado`/`saldo`).
2. **Workflow:** actualizar `TipoSolicitudSeeder` (label "Enviar a gerencia", `notificar` contador, estado
   `pendiente_cierre`, quitar `pagar`); badges de estado.
3. **Backend cotizaciones + beneficiarios:** autoría en anexar, política `gestionarCotizacion`, endpoint
   `actualizarCotizacion`; beneficiarios en `create/store/update` + request.
4. **Backend abonos:** `AbonoOficinaController` (registrar, eliminar, descargar soporte), política
   `registrarAbono`, transición automática a `pendiente_cierre`.
5. **UI:** Crear.jsx (multi-select empleados), Detalle.jsx (lista cotizaciones + sección Pagos), Index.jsx
   (tab pendientes por cerrar), Rrhh/Comisiones.jsx (tab oficina).
6. **Tests + build** y verificación de la suite completa.
