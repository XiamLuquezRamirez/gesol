# Viáticos — Ajustes: confirmar salida, cancelar, ajustar y notificar

**Fecha:** 2026-08-20
**Rama:** `feature/viaticos-ajustes` (bloques por dependencia)
**Depende de:** todo el módulo de viáticos actual (Bloques A/B/C + en_gerencia).

## Objetivo

Cuatro ajustes al flujo de comisiones de viáticos:

1. **Confirmar salida (RR.HH.):** RR.HH. marca por viajero que efectivamente salió de la oficina.
2. **Cancelar comisión (solicitante):** cancelar en cualquier momento; desaparece de los paneles de RR.HH. y contabilidad.
3. **Ajustar comisión (líder de área):** extender/reducir el tiempo de cada viajero (fechas/horas) en cualquier momento; se reporta a contabilidad y RR.HH.
4. **Notificar a RR.HH. al crear:** la notificación de comisión enviada aparece como "pendiente por revisar" en el panel de notificaciones.

## Decisiones de diseño (acordadas con el usuario)

- **Ajuste 1:** check por viajero, **informativo** (no bloquea flujo ni liquidación).
- **Ajuste 2:** **solo el solicitante** cancela; **endpoint propio** (fuera del MotorWorkflow) porque "cualquier momento" abarca muchos orígenes; nuevo estado `cancelada`.
- **Ajuste 3:** el líder edita **fechas/horas por viajero + motivo**, **en cualquier momento** mientras no esté `cerrada`/`cancelada` (el empleado puede estar en comisión y decidirse regresar antes o quedarse más). **NO recalcula rubros automáticamente**: solo notifica para que el contador recalcule.
- **Notificaciones:** ajuste → contabilidad y RR.HH.; cancelar → RR.HH. y contabilidad; enviar → RR.HH. "pendiente por revisar".

---

## Bloque 1 — Confirmar salida (RR.HH.)

### Datos
Migración: `viajeros_comision.salida_confirmada` → `boolean` default `false` (idempotente `hasColumn`). Añadir a `$fillable` y castear a `boolean`.

### Backend
- `ComisionesRrhhController::index`: exponer `salida_confirmada` en el mapeo por viajero (líneas ~37-62).
- Nuevo endpoint `PATCH viaticos/{solicitud}/viajeros/{viajero}/salida` → `ComisionesRrhhController::confirmarSalida` (o un método dedicado). Recibe `confirmada` (boolean). Autoriza con una nueva policy `confirmarSalida` (rol `rrhh`, comisión VIA no borrador/rechazada/cancelada). Valida pertenencia viajero↔comisión.
- Ruta nombrada `viaticos.salida.confirmar`.

### Frontend `Rrhh/Comisiones.jsx`
- Nueva columna "Salió" en la tabla de comisionados (thead ~163, celda en la fila).
- Checkbox por viajero que hace `router.patch(route('viaticos.salida.confirmar', [solicitudId, viajeroId]), { confirmada }, { preserveScroll: true })`. El `solicitudId` debe exponerse en el mapeo del controlador (hoy expone `radicado` pero se necesita el id de la Solicitud para la ruta).

### Tests
- RR.HH. marca salida ⇒ `salida_confirmada = true`; desmarca ⇒ false.
- Un usuario no-rrhh recibe 403.
- Pertenencia cruzada ⇒ 404.

---

## Bloque 2 — Cancelar comisión (solicitante)

### Estado
Añadir `cancelada` a la lista de estados VIA en `TipoSolicitudSeeder` (para que el badge/UX lo reconozca), **sin** transiciones de motor hacia/desde él (se maneja fuera del motor).

### Backend
- Nuevo endpoint `POST viaticos/{solicitud}/cancelar` → `ViaticosController::cancelar`. Autoriza con nueva policy `cancelar`: `$usuario->id === $solicitud->solicitante_id` y `clave === 'VIA'` y estado NOT IN `['cerrada','cancelada']`.
- Efecto: `$solicitud->update(['estado' => 'cancelada'])`, registra un `TransicionSolicitud` (origen=estado actual, destino=`cancelada`, accion=`cancelar`, usuario, comentario opcional) para el historial, y notifica a RR.HH. + contabilidad (contador + contabilidad_lider) con una notificación `AvisoTransicionNotification` tipo informativo (o un tipo `cancelada`).
- Ruta nombrada `viaticos.cancelar`.

### "Desaparece de los registros"
- RR.HH.: incluir `'cancelada'` en el `whereNotIn` de `ComisionesRrhhController` (línea 27) → `['borrador','rechazada','cancelada']`.
- Contabilidad: `colaPendientes` filtra por `accionesDisponibles` (motor). Como `cancelada` no tiene transiciones de motor, no aparece en "pendientes de acción". `queryPendientesLider` filtra por estado exacto (`revisada`), así que tampoco. No requiere cambio adicional allí.
- `SolicitudPolicy::verDetalle`: RR.HH. ve VIA salvo `borrador`/`rechazada`; añadir `'cancelada'` a esa exclusión (línea ~34) para coherencia (el solicitante sí puede ver su comisión cancelada por ser dueño).

### Frontend
- `BadgeEstado`: añadir `cancelada` → etiqueta "Cancelada", color rojo/gris; y etiqueta corta para el historial.
- Botón "Cancelar comisión" en el detalle (`Detalle.jsx`), visible solo al solicitante y si el estado lo permite (flag `puedeCancelar` desde el backend). Confirmación con `ModalAccion` o un modal simple con motivo opcional.

### Tests
- Solicitante cancela una comisión `enviada`/`liquidada` ⇒ estado `cancelada`, transición registrada, notificaciones enviadas.
- No-solicitante ⇒ 403.
- Cancelar una `cerrada` ⇒ 403/422.
- Comisión `cancelada` NO aparece en el panel de RR.HH.

---

## Bloque 3 — Ajustar comisión (líder de área)

### Datos
El ajuste edita `fecha_salida`, `hora_salida`, `fecha_regreso`, `hora_regreso` **por viajero** (columnas existentes). Se registra el ajuste como un `TransicionSolicitud` (accion=`ajustar`) con el `comentario` = motivo, y en `metadatos` un resumen de los cambios por viajero (fechas antes/después). No se crea tabla nueva.

### Backend
- Nuevo endpoint `PUT viaticos/{solicitud}/ajustar` → `ViaticosController::ajustar`. Autoriza con nueva policy `ajustar`: `$usuario->id === $solicitud->solicitante_id`, `clave === 'VIA'`, estado NOT IN `['cerrada','cancelada']`.
- Request `AjustarComisionRequest`: `motivo` (required, string), `viajeros` (array) con `viajero_comision_id` (exists, pertenece a la comisión), `fecha_salida`, `hora_salida`, `fecha_regreso`, `hora_regreso` (required, formatos como en creación).
- Efecto: por cada viajero, actualizar sus fechas/horas. Registrar `TransicionSolicitud` (origen=destino=estado actual, accion=`ajustar`, comentario=motivo, metadatos=cambios). Notificar a contador, contabilidad_lider y RR.HH. con `AvisoTransicionNotification` tipo `ajustada` (nuevo copy) indicando el motivo.
- **NO recalcula rubros** — el contador recalcula manualmente en la liquidación.
- Ruta nombrada `viaticos.ajustar`.

### Frontend
- Botón "Ajustar comisión" en el detalle (visible al solicitante si estado lo permite, flag `puedeAjustar`).
- Modal/pantalla de ajuste: lista los viajeros con sus fechas/horas actuales editables + un campo motivo. Al guardar, `router.put(route('viaticos.ajustar', solicitudId), { motivo, viajeros })`.

### Notificaciones (panel)
- `PanelNotificaciones.jsx`: añadir copy/estilo para el tipo `ajustada` (ej. "Comisión ajustada: {radicado}").

### Tests
- Líder ajusta fechas de un viajero con motivo ⇒ fechas actualizadas, transición `ajustar` registrada con el motivo, notificaciones a contador/contabilidad_lider/rrhh.
- No-solicitante ⇒ 403.
- Ajustar una `cerrada`/`cancelada` ⇒ 403/422.
- Validación: sin motivo ⇒ error.

---

## Bloque 4 — Notificación "pendiente por revisar" a RR.HH.

El disparo ya existe (`SolicitudController::transicion`, acción `enviar` → `ComisionCerradaNotification` a RR.HH., tipo `comision_reportada`). Falta la presentación:

### Frontend `PanelNotificaciones.jsx`
- En `mensajeNotificacion()` (switch por `n.tipo`): añadir caso `comision_reportada` → "Comisión pendiente por revisar: {radicado}" (o similar).
- En `ESTILO_TIPO`: añadir `comision_reportada` con un estilo distintivo (ej. ámbar/"pendiente").

### Backend (opcional, claridad)
- Mantener el `tipo => 'comision_reportada'` de `ComisionCerradaNotification` (ya existe). No requiere cambio de datos; solo el front lo interpreta ahora.

### Tests
- Ya existe `NotificacionRrhhViaticosTest` que verifica que RR.HH. recibe la notificación con tipo `comision_reportada`. Añadir (opcional) un assert de que el copy del front no rompe — pero como es front sin runner JS, basta el build.

---

## Orden de implementación (bloques por dependencia)

1. **Bloque 4** (más simple, sin backend): copy/estilo del panel. Rápido.
2. **Bloque 1** (confirmar salida): migración + endpoint + UI RR.HH.
3. **Bloque 2** (cancelar): estado + policy + endpoint + UI + exclusión de paneles.
4. **Bloque 3** (ajustar): policy + request + endpoint + UI de ajuste + notificaciones.

Cada bloque: TDD, build, y verificación. Al final, merge a `main` + push.

## Fuera de alcance

- Recálculo automático de rubros al ajustar (decisión explícita: contador recalcula).
- Ajuste por parte de roles distintos al solicitante.
- Reactivar una comisión cancelada (queda cancelada; si se reprograma, se crea una nueva).
