# Diseño — Total a pagar definido en el primer abono de oficina

**Fecha:** 2026-08-12
**Proyecto:** Gesol (Laravel 10 + Inertia/React + MariaDB)
**Alcance:** Al registrar el primer pago de una solicitud de oficina, el líder de contabilidad ingresa el valor
total real a pagar; a partir de ese valor se definen los abonos (parciales o pago único) y el saldo. Los pagos
y sus soportes se muestran a RR. HH.

---

## Contexto y decisiones

### Estado actual (explorado)

- Los abonos ya existen (`AbonoOficinaController`, tabla `abonos_oficina`): monto, fecha, soporte, observación.
  El primer abono dispara `aprobada → pendiente_cierre`.
- El **saldo** se calcula hoy como `total − totalPagado()`, donde `total` es la **suma estimada de los ítems**
  (`SolicitudOficina::recalcularTotal`). No hay un "total real a pagar" que el líder ingrese.
- **RR. HH. ya ve** las solicitudes de oficina pagadas con total/pagado/saldo y **enlace de descarga de soporte
  por abono** ([Rrhh/Comisiones.jsx:228-230](../../../resources/js/Pages/Rrhh/Comisiones.jsx),
  [ComisionesRrhhController:57-79](../../../app/Http/Controllers/ComisionesRrhhController.php)). Esta parte ya
  cumple "mostrar los pagos con soportes"; se **ajusta** para usar el total real.
- El detalle ya tiene `SeccionPagos` ([Detalle.jsx:484](../../../resources/js/Pages/Solicitudes/Detalle.jsx)):
  resumen Total/Pagado/Saldo, lista de abonos con enlace "Soporte", y formulario "Registrar abono".
- El `pagos` que consume el frontend lo arma `SolicitudDetalleResource` (total, pagado, saldo, puede_registrar,
  abonos[]).

### Decisiones del usuario

| Tema | Decisión |
|---|---|
| Relación total ingresado vs estimado | **Total real, reemplaza el estimado** para el saldo. El estimado (`total`) queda como referencia. |
| Reglas de monto | **No exceder el total; saldo ≥ 0.** Cada abono se valida contra el saldo restante. |
| Primer pago | Ingresa **total a pagar + monto**; atajo "pago total" pone monto = total. El monto puede ser parcial. |
| Mensaje de error | **Claro:** "El monto no puede superar el saldo pendiente de $X" (y análogo para el total en el primer pago). |
| Visibilidad RR. HH. | Ya existe; se ajusta para usar el total real y confirmar el soporte descargable por abono. |

---

## Sección 1 — Modelo de datos

### `solicitudes_oficina.total_a_pagar` (columna nueva)
- Migración que añade `total_a_pagar` (decimal 14,2, **nullable**, tras `total`). Nullable: solo se fija al
  registrar el primer abono.
- `SolicitudOficina`: añadir `total_a_pagar` a `$fillable` y cast `decimal:2`.

### Saldo calculado contra el total real
Reemplazar la base del saldo (hoy `total`) por `total_a_pagar`:

```php
public function saldoPendiente(): float
{
    // Antes de fijar el total real (sin pagos aun), no hay saldo aplicable.
    if ($this->total_a_pagar === null) {
        return 0.0;
    }
    return (float) $this->total_a_pagar - $this->totalPagado();
}

public function estaPagadaCompleta(): bool
{
    return $this->total_a_pagar !== null && $this->totalPagado() >= (float) $this->total_a_pagar;
}
```

- `totalPagado()` no cambia (suma de abonos).
- El helper `saldo()` existente (basado en `total` estimado) se **reemplaza** por `saldoPendiente()`. Sus
  consumidores actuales de código son: `SolicitudDetalleResource:43`, `ComisionesRrhhController:72` y
  `AbonoOficinaTest` (3 asserts, líneas 33/71/85). Los tres se actualizan a `saldoPendiente()`; en
  `AbonoOficinaTest` los valores esperados se recalculan contra `total_a_pagar` (esos tests deberán fijar un
  `total_a_pagar` en el setup para que el saldo tenga sentido). Se elimina `saldo()` tras migrar los llamadores.
- La columna `total` (estimado) se conserva; deja de mandar el saldo.

---

## Sección 2 — Backend (registro de abonos)

### `RegistrarAbonoOficinaRequest`
Reglas condicionales según si la solicitud ya tiene `total_a_pagar`:

- **Primer abono** (`total_a_pagar` de la solicitud es null):
  - `total_a_pagar`: `required|numeric|min:0.01`.
  - `monto`: `required|numeric|min:0.01`, y ≤ `total_a_pagar` enviado (validado en `withValidator`).
- **Abonos siguientes** (ya hay `total_a_pagar`):
  - `total_a_pagar`: ignorado (no se re-ingresa).
  - `monto`: `required|numeric|min:0.01`, y ≤ `saldoPendiente()` de la solicitud (validado en `withValidator`).
- El resto (`fecha_pago`, `soporte`, `observacion`) igual que hoy.
- La solicitud se obtiene por el route-model binding (`$this->route('solicitud')`) para leer su estado de pago
  en `withValidator`.

Mensajes claros:
- Primer pago, monto > total: "El monto no puede superar el total a pagar de $X."
- Abono siguiente, monto > saldo: "El monto no puede superar el saldo pendiente de $X."

### `AbonoOficinaController::store`
- Si la solicitud **no** tiene `total_a_pagar`, guardarlo en la cabecera (`$cabecera->update(['total_a_pagar' =>
  $request->total_a_pagar])`) dentro de la transacción existente, antes/junto con crear el abono y el paso
  `aprobada → pendiente_cierre`.
- Defensa: si `estaPagadaCompleta()` es true, rechazar (no debería llegar aquí porque la policy/UI ya lo
  impiden, pero se valida). Se puede cubrir con la regla de monto ≤ saldo (saldo 0 → cualquier monto ≥ 0.01
  falla).

### `SolicitudDetalleResource` (bloque `pagos`)
- Exponer `total_a_pagar` (o null), `pagado`, `saldo` (= `saldoPendiente()`), `total_estimado` (el `total`
  de referencia), `tiene_total` (bool: `total_a_pagar !== null`), además de `puede_registrar` y `abonos[]` como
  hoy (cada abono ya trae id/monto/fecha/soporte).

---

## Sección 3 — UI y RR. HH.

### Formulario de pago en el detalle (`SeccionPagos` en `Detalle.jsx`)
- **Resumen:** mostrar "Total a pagar" (el real) cuando `tiene_total`; si no, mostrar el estimado con la nota
  "(estimado — se confirmará al registrar el primer pago)". Pagado y Saldo debajo.
- **Primer pago** (`!tiene_total`): el formulario incluye un campo **"Total a pagar"** (además de monto, fecha,
  soporte, observación) y un botón **"Pago total"** que copia el total al campo monto. Validación en cliente:
  monto ≤ total.
- **Pagos siguientes** (`tiene_total`): sin campo "Total a pagar"; se muestra el saldo pendiente como
  referencia y el monto se valida en cliente para no superarlo. Cuando el saldo es 0, no se muestra el
  formulario (ya está pagada).
- Errores del backend (monto > total / monto > saldo) se muestran bajo el campo monto.

### Visibilidad para RR. HH. (`ComisionesRrhhController` + `Rrhh/Comisiones.jsx`)
- Cambiar la columna Total/Saldo para usar `total_a_pagar` (real) en vez del estimado: `total` → mostrar
  `total_a_pagar` (o "—" si aún null), `saldo` → `saldoPendiente()`.
- Confirmar que cada abono lista su **soporte descargable** (ya existe el enlace
  `route('oficina.abono.soporte', [o.id, ab.id])` en la tabla). Sin cambios funcionales aquí salvo el ajuste de
  las cifras al total real.

---

## Testing

- **Modelo:** `saldoPendiente()` = total_a_pagar − pagado; 0 si total_a_pagar null. `estaPagadaCompleta()`
  correcto.
- **Backend (HTTP):**
  - Primer abono guarda `total_a_pagar` y el abono; saldo se calcula contra él.
  - Primer abono con monto > total_a_pagar → rechazado con mensaje claro.
  - Abono siguiente con monto > saldo → rechazado con mensaje claro.
  - Pago único (monto = total_a_pagar) deja saldo 0 y `estaPagadaCompleta()` true; un abono adicional se
    rechaza.
  - Abono parcial deja saldo > 0 y admite otro abono que lo cubra.
- **Resource/RR. HH.:** el bloque `pagos` expone `total_a_pagar`/saldo correctos; RR. HH. muestra el total real,
  pagado, saldo y los soportes descargables por abono.
- Suite con SQLite `:memory:`; `Storage::fake('local')`, `UploadedFile::fake()`.

---

## Fases de implementación (resumen; el detalle va en el plan)

1. **Datos:** migración `total_a_pagar`, modelo (`saldoPendiente`, `estaPagadaCompleta`, fillable/cast) +
   actualizar consumidores del saldo.
2. **Backend abonos:** request condicional (total en primer pago, monto ≤ saldo) + controlador guarda el total;
   Resource expone total/saldo reales.
3. **UI:** formulario con "Total a pagar" y "Pago total" en el primer pago, saldo en siguientes; resumen con el
   total real.
4. **RR. HH.:** ajustar cifras al total real (soportes ya descargables).
5. **Build + suite** completa.
