# Liquidación y aprobación de reajustes post-cierre (anexos) — Diseño

**Fecha:** 2026-08-24
**Módulo:** Viáticos (Gesol)
**Estado:** Aprobado por el usuario, listo para plan de implementación

---

## 1. Problema y objetivo

Hoy un reajuste de una comisión de viáticos **ya cerrada** se registra como una simple
`TransicionSolicitud` con `accion='ajustar'` y `metadatos`: es un evento suelto, sin estado
ni ciclo de vida. No hay forma de que el contador lo tenga "pendiente por aprobar", ni existe
un proceso de liquidación de ese ajuste para ver el detalle de rubros que se suman o restan
según el tiempo extra (o de menos) que introduce el reajuste.

**Objetivo:** convertir el reajuste post-cierre en una entidad con estado propio
(`AjusteComision`) que:

1. Notifique y quede **pendiente por liquidar** para el contador.
2. Tenga un **proceso de liquidación propio del ajuste**, donde el sistema calcula el
   **delta de rubros** (cuántos desayunos/almuerzos/cenas/meriendas/días de gasolina/transporte
   se suman o restan) según el tiempo extra o de menos, reutilizando la lógica de cálculo.
3. Sea **aprobado por el líder de contabilidad** (igual que el informe principal), con
   posibilidad de **devolverlo** al contador para recalcular.

**La comisión principal nunca sale de `cerrada`** y su `total` histórico queda congelado. El
ajuste vive como **anexo** con su propio estado y su propio total.

### Alcance

- Aplica **solo a reajustes sobre comisiones ya cerradas** (anexos).
- Los ajustes durante el flujo normal (comisión no cerrada) **siguen exactamente como hoy**:
  encienden `requiere_reliquidacion`, sin crear `AjusteComision`.
- Cubre **ambos tipos** de ajuste post-cierre:
  - **Por fechas/horas** (el líder edita salida/regreso del viajero) → el delta de rubros se
    calcula por diferencia de tiempo.
  - **Por rubro puntual** (gasolina/transporte, el actual `reajustarRubro`) → el delta viene
    dado por rubro + cantidad, sin recálculo por fechas.

---

## 2. Modelo de datos

### 2.1. Nueva tabla `ajustes_comision`

| Campo | Tipo | Notas |
|---|---|---|
| `id` | bigint PK | |
| `solicitud_id` | FK `solicitudes` (cascade) | la comisión cerrada a la que anexa |
| `viajero_comision_id` | FK `viajeros_comision` (cascade) | viajero afectado |
| `solicitado_por` | FK `usuarios` | líder de área que lo pidió |
| `tipo` | enum `fechas`, `rubro` | origen del delta |
| `motivo` | text | |
| `estado` | enum `pendiente_liquidacion`, `liquidado`, `aprobado`, `devuelto` | mini-flujo del anexo |
| `fechas_antes` | json nullable | snapshot `{fecha_salida,hora_salida,fecha_regreso,hora_regreso}` (tipo `fechas`) |
| `fechas_despues` | json nullable | snapshot nuevo (tipo `fechas`) |
| `rubro` | string nullable | para tipo `rubro` (gasolina/transporte) |
| `cantidad` | integer nullable | para tipo `rubro` |
| `total_delta` | decimal(14,2) default 0 | suma de subtotales del anexo; puede ser negativo |
| `motivo_devolucion` | text nullable | comentario del líder al devolver |
| `liquidado_por` | FK `usuarios` nullable | auditoría |
| `liquidado_en` | timestamp nullable | |
| `aprobado_por` | FK `usuarios` nullable | auditoría |
| `aprobado_en` | timestamp nullable | |
| `created_at` / `updated_at` | timestamps | |

Migración **driver-aware** (MySQL enum + SQLite CHECK reescrito, siguiendo el patrón de
`2026_08_20_130000_add_transporte_to_asignaciones_rubro_enum.php`).

### 2.2. Columna en `asignaciones_viaticos`

- Añadir `ajuste_comision_id` bigint **nullable** FK `ajustes_comision` (nullOnDelete).
- Las asignaciones de la liquidación original quedan con `ajuste_comision_id = NULL` (intactas).
- Las asignaciones del anexo apuntan al ajuste. `dias` **puede ser negativo** (tiempo de menos
  → resta). El `subtotal = valor_unitario * dias` (ya existente en el modelo) produce subtotal
  negativo automáticamente.

**Importante:** el `recalcularTotal()` de la cabecera (que suma `asignaciones_viaticos.subtotal`)
**debe excluir** las asignaciones con `ajuste_comision_id != NULL`, para que el total de la
comisión cerrada no se vea afectado por los anexos. El total del anexo se calcula y persiste
en `ajustes_comision.total_delta` aparte.

### 2.3. Modelos Eloquent

- **`AjusteComision`** (`app/Models/AjusteComision.php`): fillable de los campos arriba; casts
  `fechas_antes`/`fechas_despues` → array, `liquidado_en`/`aprobado_en` → datetime,
  `total_delta` → decimal. Relaciones: `solicitud()`, `viajero()` (belongsTo ViajeroComision),
  `solicitante()`, `asignaciones()` (hasMany AsignacionViatico vía `ajuste_comision_id`).
  Método `recalcularTotalDelta()` que suma `asignaciones.subtotal` y persiste en `total_delta`.
- **`AsignacionViatico`**: añadir `ajuste_comision_id` al `fillable`; el hook `saved/deleted`
  debe recalcular el total del **ajuste** cuando la asignación pertenece a un ajuste, y el de la
  **cabecera** (excluyendo anexos) cuando no.

---

## 3. Cálculo del delta — `App\Services\CalculadoraRubrosViaticos`

Portar la lógica de `resources/js/lib/rubros.js` a PHP. Es la **fuente de verdad server-side**
para el anexo. `rubros.js` sigue sirviendo la liquidación normal (duplicación consciente,
cubierta por tests que replican los mismos casos).

### 3.1. Reglas portadas (idénticas a rubros.js)

- **Horas tope** (minutos desde medianoche): desayuno `09:00` (540), almuerzo `14:00` (840),
  cena `18:00` (1080). La **cena aplica si sigue en comisión después de las 18:00**.
- `diasComision(fSal, fReg)`: días inclusivos (salida y regreso cuentan), mínimo 1.
- `conteoComidas(fSal, fReg, hSal, hReg)` → `{desayuno, almuerzo, cena, merienda}`:
  - Mismo día (1 día): comida aplica si `hora_comida` entre salida y regreso.
  - Primer día: `hora_comida >= hora_salida`.
  - Último día: `hora_comida <= hora_regreso`.
  - Días intermedios: todas.
  - **Merienda**: 1 por día si ese día hubo alguna comida.
  - Sin hora → `minSalida=0`, `minRegreso=1440`.
- `diasDeRubro(rubro, fSal, fReg, hSal, hReg)`: comidas → `conteoComidas`;
  gasolina/transporte → `diasComision`.

### 3.2. Método clave del ajuste

```php
// Dado el snapshot ANTES y DESPUÉS de fechas/horas, devuelve el delta de días por rubro.
// Solo incluye rubros con delta != 0.
public function calcularDelta(array $antes, array $despues): array
// Ej: { 'cena' => +1, 'almuerzo' => +1, 'merienda' => +1, 'gasolina' => +1, 'transporte' => +1 }
//     o negativos si se recorta la comisión.
```

`delta_rubro = diasDeRubro(despues) - diasDeRubro(antes)` para cada rubro del enum.

### 3.3. Ojo con los tipos

`hora_salida`/`hora_regreso` en `viajeros_comision` son **strings `HH:MM`** (no casteados).
El Service debe parsearlos a minutos. Fechas vienen como `Y-m-d`.

---

## 4. Valor unitario del anexo

El anexo **reusa el `valor_unitario` de la liquidación original** de ese rubro para ese viajero
(no la tarifa vigente). Se obtiene de las `asignaciones_viaticos` originales
(`ajuste_comision_id = NULL`) del viajero, por rubro.

**Caso de borde:** si el ajuste agrega un rubro que **no existía** en la liquidación original de
ese viajero (no hay valor original de dónde tomarlo), se cae a la **tarifa vigente**
(`tarifas_viaticos.valor_sugerido`) como valor por defecto, editable por el contador.

---

## 5. Flujo y endpoints

La comisión principal permanece `cerrada` en todo momento. El `estado` que transiciona es el del
`AjusteComision`.

| Acción | Método / Ruta | Rol | Efecto |
|---|---|---|---|
| **Solicitar ajuste (fechas)** | `POST viaticos/{solicitud}/ajustes` | líder de área (solicitante) | Crea `AjusteComision` tipo `fechas`, estado `pendiente_liquidacion`, con snapshot antes/después. Notifica al contador (`accion_requerida`). No cambia el estado de la comisión ni edita las fechas del viajero real. |
| **Solicitar ajuste (rubro)** | `POST viaticos/{solicitud}/ajustes-rubro` | líder de área | Crea `AjusteComision` tipo `rubro` (gasolina/transporte + cantidad), estado `pendiente_liquidacion`. Notifica al contador. |
| **Ver/Liquidar ajuste** | `GET viaticos/ajustes/{ajuste}/liquidacion` · `PUT .../liquidacion` | contador | Abre liquidación del anexo. El Service propone el delta (valor unitario de la original; tarifa vigente si rubro nuevo). Contador edita/confirma → persiste asignaciones del anexo, recalcula `total_delta`, estado `liquidado`. Notifica al líder de contabilidad (`accion_requerida`). |
| **Aprobar ajuste** | `POST viaticos/ajustes/{ajuste}/aprobar` | contabilidad_lider | Estado `aprobado`, sella `aprobado_por/en`. Notifica al líder de área y contador (informativo). |
| **Devolver ajuste** | `POST viaticos/ajustes/{ajuste}/devolver` | contabilidad_lider | Estado `devuelto` + `motivo_devolucion`. Notifica al contador (`accion_requerida`, recalcular). |

**Ciclo de estados del ajuste:**
`pendiente_liquidacion → liquidado → aprobado`
`liquidado → devuelto → (contador reliquida) → liquidado → aprobado`

- **Solicitud tipo `fechas`:** NO modifica las fechas reales del `viajero_comision`. El snapshot
  "después" queda en el ajuste; el delta se calcula contra el "antes" (las fechas vigentes al
  momento de solicitar). Esto preserva la comisión cerrada intacta. *(El "antes" se toma de las
  fechas actuales del viajero; el "después" del request del líder.)*
- **Total:** `total_delta` vive solo en el anexo. La comisión cerrada mantiene su `total`.

### 5.1. Compatibilidad con lo existente

- Los métodos actuales `ajustar` y `reajustarRubro` siguen manejando el caso **no cerrado**
  (encienden `requiere_reliquidacion`). Cuando la comisión está **cerrada**, el frontend enruta
  a los nuevos endpoints de `ajustes` en vez de a `ajustar`/`reajustarRubro`.
- La tabla de "Ajustes" en el detalle listará los registros `AjusteComision` (nuevos, post-cierre)
  **y** las transiciones `ajustar` históricas (ajustes en flujo normal), diferenciadas.

---

## 6. Frontend

### 6.1. Detalle (`resources/js/Pages/Solicitudes/Detalle.jsx`)

Tabla **"Ajustes"** ampliada, ahora con los registros `AjusteComision`:

- Columnas: Viajero · Cambio (fechas antes→después, o rubro+cantidad) · Motivo · Total delta ·
  **Estado** (badge legible) · Fecha · Acciones.
- **Badges de estado:** `Pendiente de liquidación` (amber) · `Liquidado` (blue) ·
  `Aprobado` (green) · `Devuelto` (red).
- **Acciones por rol y estado:**
  - contador + `pendiente_liquidacion`/`devuelto` → **"Liquidar ajuste"**.
  - contabilidad_lider + `liquidado` → **"Aprobar"** / **"Devolver"** (modal con motivo).
  - acceso + `aprobado` → **"Ver liquidación del anexo"** (+ PDF/correo del anexo si aplica).
- El botón **"Ajustar"** / **"Reajustar transporte/gasolina"** del líder, cuando la comisión está
  **cerrada**, ahora crea un `AjusteComision` (postea a los nuevos endpoints).

### 6.2. Nueva pantalla `resources/js/Pages/Viaticos/LiquidacionAjuste.jsx`

Reusa el patrón de `Liquidacion.jsx`:

- Banner: "Este es un ajuste (anexo) sobre la comisión VIA-XXXX ya cerrada. No modifica la
  liquidación original."
- Tabla de rubros del **delta propuesto**: `rubro`, `dias` (negativos marcados visualmente, p.ej.
  "−1"), `valor_unitario` (precargado), `subtotal`. Editable por el contador.
- Resumen del `total_delta` (puede ser negativo).
- Guardar → `PUT viaticos/ajustes/{ajuste}/liquidacion` → estado `liquidado`.

### 6.3. Badge "Ajuste pendiente" en el listado

En el listado de solicitudes, badge/indicador "Ajuste pendiente" en las comisiones que tengan un
`AjusteComision` en `pendiente_liquidacion` (para contador) o `liquidado` (para
contabilidad_lider), que lleva al detalle. **Sin pantalla de bandeja dedicada.**

---

## 7. Notificaciones

Reusar `AvisoTransicionNotification`, con tipos y copy:

| Evento | Destinatario | Tipo | Copy |
|---|---|---|---|
| Ajuste solicitado | contador | `accion_requerida` | "Ajuste pendiente de liquidar" |
| Ajuste solicitado | rrhh (informativo) | `ajustada` | "Comisión ajustada" |
| Ajuste liquidado | contabilidad_lider | `accion_requerida` | "Ajuste pendiente de aprobar" |
| Ajuste aprobado | líder de área + contador | informativo | "Ajuste aprobado" |
| Ajuste devuelto | contador | `accion_requerida` | "Ajuste devuelto: recalcular" |

Agregar los mensajes/estilos nuevos al `switch` de `resources/js/Components/PanelNotificaciones.jsx`.

---

## 8. Permisos (`SolicitudPolicy` o `AjusteComisionPolicy` nueva)

- `solicitarAjuste`: `usuario->id === solicitud->solicitante_id`, clave `VIA`, comisión `cerrada`.
- `liquidarAjuste`: rol `contador`, ajuste en `pendiente_liquidacion` o `devuelto`.
- `aprobarAjuste` / `devolverAjuste`: rol `contabilidad_lider`, ajuste en `liquidado`.
- `verAjuste`: quien pueda ver el detalle de la comisión (`verDetalle`).

---

## 9. Tests (TDD, PHPUnit + SQLite :memory:)

- **`CalculadoraRubrosViaticosTest`**: replica casos de `rubros.js` (mismo día, primer/último día,
  tope cena 18:00, merienda 1/día, sin horas) + `calcularDelta` (extiende → positivos, recorta →
  negativos, sin cambio → vacío).
- **`AjusteComisionFlujoTest`**: solicitar (crea `pendiente_liquidacion` + notifica contador),
  liquidar (delta correcto, estado `liquidado`, notifica líder contabilidad), aprobar, devolver →
  recalcular. Gating por rol/estado (403 fuera de rol/estado).
- **`AjusteComisionValorUnitarioTest`**: reusa `valor_unitario` de la liquidación original; cae a
  tarifa vigente si el rubro es nuevo.
- **`AjusteComisionAislamientoTest`**: la comisión nunca cambia de `cerrada` ni su `total`;
  `recalcularTotal()` excluye asignaciones de anexos.

---

## 10. Migraciones

1. `create_ajustes_comision_table` (driver-aware enum estado + tipo).
2. `add_ajuste_comision_id_to_asignaciones_viaticos` (nullable FK nullOnDelete).

---

## 11. Fuera de alcance

- Vista/bandeja dedicada de "Ajustes pendientes" (se usa notificación + badge).
- Ajustes en flujo **no cerrado** (siguen con `requiere_reliquidacion` como hoy).
- Incorporar `total_delta` al `total` de la comisión (se mantiene congelado).
- PDF/correo del anexo: se reusa la infraestructura de impresión existente por viajero; su diseño
  detallado no se cubre aquí más allá de exponer la acción cuando el ajuste está `aprobado`.
