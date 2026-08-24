# Viáticos — Ajustes v2: ajuste post-cierre, tabla de ajustes y reajuste de rubro (gasolina/transporte)

**Fecha:** 2026-08-20
**Rama:** `feature/viaticos-ajustes-v2`
**Depende de:** el ajuste de comisión existente (endpoint `viaticos.ajustar`, policy `ajustar`, flag `requiere_reliquidacion`).

## Objetivo

1. **Ajuste después de cerrada + tabla de ajustes:** permitir que el líder ajuste una comisión aunque ya esté `cerrada` (aumentar/disminuir horas o días); los ajustes aparecen en una **tabla separada** en el detalle, marcada como "ajuste", con las mismas acciones que la tabla de viajeros.
2. **Reajuste de rubro (gasolina/transporte):** el líder puede solicitar un reajuste **solo del rubro de gasolina** (camioneta de la empresa) o **transporte** (rubro nuevo). El líder solicita; el contador lo aplica al liquidar.

## Decisiones de diseño (acordadas con el usuario)

- **Ajuste post-cierre:** la comisión **queda cerrada** (no reabre el flujo); el ajuste es un anexo histórico. (El código actual ya deja el estado igual cuando el origen no es liquidada/revisada/en_gerencia — `cerrada` cae en ese caso.)
- **Tabla de ajustes:** un ajuste por fila (histórico), en el detalle, para todos los que ven el detalle, **solo si hay ajustes**. Muestra viajero, qué cambió, motivo, fecha, autor, y las mismas acciones que la tabla de viajeros (Ver rubros, Comprobantes, Imprimir PDF, Correo del viajero afectado).
- **Transporte:** rubro nuevo (enum + tarifa + ALTER del enum de la columna).
- **Reajuste de rubro:** el líder indica **viajero + rubro (gasolina/transporte) + cantidad + motivo**. El contador fija el valor y lo aplica. El reajuste enciende `requiere_reliquidacion` y notifica al contador (salvo que la comisión esté cerrada, donde queda como anexo — ver Bloque 3).
- El registro de todos los ajustes (fechas o rubro) se unifica en `transiciones_solicitud` con `accion='ajustar'` y un `metadatos` (JSON) que describe el ajuste.

---

## Bloque 1 — Metadatos del ajuste (base para la tabla)

Para poder mostrar "qué cambió" en la tabla de ajustes, la transición `ajustar` debe guardar `metadatos`.

### Backend
- `ViaticosController::ajustar`: al crear el `TransicionSolicitud`, poblar `metadatos` con:
  ```
  {
    "tipo": "fechas",
    "viajeros": [
      { "viajero_comision_id": N, "nombre": "...",
        "antes": { "fecha_salida","hora_salida","fecha_regreso","hora_regreso" },
        "despues": { ... } }
    ]
  }
  ```
  Se captura el "antes" leyendo el viajero antes de actualizarlo, y el "después" del request.
- `TransicionSolicitud` ya tiene `metadatos` en `$fillable` y casteado a `array`. `TransicionResource` ya lo expone. No requiere migración.

### Tests
- El ajuste de fechas guarda `metadatos.tipo = 'fechas'` con antes/después por viajero.

---

## Bloque 2 — Ajustar después de cerrada + tabla de ajustes

### Policy
- `SolicitudPolicy::ajustar`: quitar `'cerrada'` de la exclusión → `! in_array($estado, ['cancelada'])`. (Sigue siendo solo el solicitante, VIA.)

### Controlador
- `ViaticosController::ajustar`: sin cambios de estado para `cerrada` (el código actual ya la deja igual y no enciende reliquidación cuando el origen no es liquidada/revisada/en_gerencia). El ajuste post-cierre queda como anexo: solo edita fechas del viajero + registra la transición con metadatos + notifica (informativo, sin exigir reliquidación porque está cerrada).
- Mensaje flash específico cuando estaba cerrada: "Ajuste registrado sobre la comisión cerrada."

### Frontend — tabla de ajustes (`Detalle.jsx`, dentro de `DetalleViaticos`)
- Nueva tabla **"Ajustes"** debajo de la tabla de viajeros, visible solo si hay transiciones con `accion === 'ajustar'`.
- Fuente: `solicitud.transiciones.filter(t => t.accion === 'ajustar')`.
- Columnas: Viajero (de `metadatos`), Cambio (fechas antes→después, o rubro solicitado), Motivo (comentario), Fecha (created_at), Autor (usuario), Acciones.
- Acciones por fila: las mismas que la tabla de viajeros — Ver rubros, Comprobantes, Imprimir PDF, Correo — **referidas al viajero afectado por el ajuste** (se resuelve el viajero por `viajero_comision_id` de metadatos). Si el ajuste afectó a varios viajeros, se listan/enlazan por viajero.
- El botón "Ajustar comisión" ahora también aparece cuando la comisión está `cerrada` (gated por `puedeAjustar`, que ya usa la policy).

### Tests
- Reemplazar `test_no_ajusta_cerrada` por `test_lider_ajusta_comision_cerrada` (permitido; estado sigue cerrada; transición ajustar registrada; NO enciende requiere_reliquidacion).
- El detalle expone las transiciones `ajustar` con sus metadatos.

---

## Bloque 3 — Rubro transporte + reajuste de rubro por el líder

### Rubro transporte
- `app/Enums/Rubro.php`: añadir `case Transporte = 'transporte';`.
- `TarifaViaticosSeeder`: añadir `['rubro' => 'transporte', 'valor_sugerido' => 40000]` (valor a confirmar; placeholder razonable).
- Migración: ALTER del enum `asignaciones_viaticos.rubro` para incluir `'transporte'`. Driver-aware:
  - MySQL/MariaDB: `DB::statement("ALTER TABLE asignaciones_viaticos MODIFY rubro ENUM('desayuno','almuerzo','cena','merienda','gasolina','transporte')")`.
  - SQLite (tests): la columna enum se crea como texto/check; para :memory: recrear no es trivial, pero SQLite no impone el CHECK del enum de la misma forma — verificar en implementación. Alternativa: la migración original de asignaciones en SQLite no aplica el CHECK estricto, así que insertar 'transporte' podría funcionar sin ALTER. El implementador confirma con un test.
- `resources/js/lib/rubros.js`: `transporte` es rubro **no-comida** (como gasolina) — hereda `diasComision` automáticamente por no estar en `comidas`. No requiere cambio de lógica, solo estará disponible en la lista `rubros` que ya viene del backend.

### Reajuste de rubro (líder solicita, contador aplica)
- Nuevo endpoint `POST viaticos/{solicitud}/reajustar-rubro` → `ViaticosController::reajustarRubro`. Policy `ajustar` (mismo gate: solicitante, VIA, no cancelada — incluye cerrada).
- Request `ReajustarRubroRequest`: `viajero_comision_id` (exists, pertenece a la comisión), `rubro` (in:gasolina,transporte), `cantidad` (integer, min:1), `motivo` (required).
- Efecto: NO edita asignaciones directamente (el líder no fija montos). Registra un `TransicionSolicitud accion='ajustar'` con `metadatos = { tipo: 'rubro', viajero_comision_id, nombre, rubro, cantidad }` y comentario=motivo. Si la comisión NO está cerrada y ya pasó por el contador (liquidada/revisada/en_gerencia) → regresa a liquidada + enciende `requiere_reliquidacion`; si está cerrada → queda cerrada (anexo); si está enviada → no cambia estado. Notifica al contador (acción requerida) + RR.HH. (info).
- Ruta `viaticos.reajustar-rubro`.

### Frontend — modal de reajuste de rubro
- Botón "Reajustar transporte/gasolina" en el detalle (gated por `puedeAjustar`), abre un modal con: select de viajero, select de rubro (Gasolina / Transporte), input de cantidad, textarea motivo. Envía a `viaticos.reajustar-rubro`.
- El reajuste de rubro aparece en la tabla de ajustes (Bloque 2) con su `metadatos.tipo = 'rubro'` (Cambio = "Gasolina × 3 (solicitado)").

### Contador reincorpora
- Al reabrir la liquidación con `requiere_reliquidacion` encendido por un reajuste de rubro, el contador ve el aviso (ya existe) y puede agregar/editar el rubro solicitado en la pantalla de liquidación (que ya permite agregar gasolina/transporte). El detalle del reajuste queda en la tabla de ajustes como referencia de lo solicitado.

### Tests
- Rubro `transporte` existe (enum, tarifa) y se puede asignar a un viajero.
- El líder reajusta rubro (gasolina) → transición ajustar con metadatos tipo rubro; enciende requiere_reliquidacion si aplica; notifica al contador.
- Un no-solicitante no puede reajustar rubro.
- Validación: rubro fuera de gasolina/transporte → error.

---

## Orden de implementación

1. **Bloque 1** (metadatos en ajustar) — base para la tabla, cambio pequeño.
2. **Bloque 2** (policy cerrada + tabla de ajustes en el detalle).
3. **Bloque 3** (rubro transporte + reajuste de rubro por el líder + modal).

Cada bloque: TDD, build, verificación. Al final: re-seed de tarifas + migración enum en MariaDB, y merge a `main` + push.

## Fuera de alcance

- Que el líder fije los montos de gasolina/transporte (los aplica el contador).
- Reabrir una comisión cerrada por un ajuste (queda cerrada; el ajuste es anexo).
- Aprobación/flujo formal del reajuste de rubro (es una solicitud + reliquidación, no un nuevo estado de workflow).
