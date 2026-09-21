# Diseño — Proceso "Obras" (Gestión de compras para obra)

**Fecha:** 2026-09-21
**Autor:** Arquitectura (con Claude)

## Objetivo
Agregar un tercer tipo de solicitud, **Obras (OBR)**, junto a Oficina y Viáticos, para
gestionar la compra de materiales, instrumentos y elementos de construcción solicitados
desde las obras en campo. Digitaliza el proceso que hoy se lleva por WhatsApp.

## Actores y roles
- **`residente`** (rol NUEVO) **y `lider_area`**: ambos pueden crear solicitudes de obra.
  El `residente` es el ingeniero en campo; el `lider_area` (rol existente) también puede
  crear en nombre del área de proyectos.
- **`rrhh`**: recibe la solicitud, realiza o revisa la cotización, relaciona el contrato
  y anexa la documentación, y envía a contabilidad.
- **`contabilidad_lider`**: aprueba y gestiona los pagos (abonos parciales o total).
- **`contador`**: recibe copia de aviso; gestiona la **retención** sobre cada pago.

## Flujo de estados (MotorWorkflow, tipo OBR)

```
borrador
  │  enviar (residente | lider_area)        → notifica rrhh
enviada
  │  cotizar (rrhh: revisa/realiza cotización, relaciona contrato, anexa docs)
  │  devolver (rrhh → borrador)             → notifica residente
cotizada        [gate: contrato relacionado obligatorio para 'enviar_contabilidad']
  │  enviar_contabilidad (rrhh)             → notifica contabilidad_lider + copia contador
  │  devolver (rrhh → borrador)
en_contabilidad
  │  aprobar (contabilidad_lider)           → habilita pagos
  │  rechazar (contabilidad_lider → rechazada)
  │  devolver (contabilidad_lider → cotizada)  (p. ej. faltan docs)
aprobada
  │  (primer abono) → pendiente_cierre  [fuera del motor, como en Oficina]
pendiente_cierre
  │  cerrar (contabilidad_lider)            → notifica a todos
cerrada
```

Estados: `borrador, enviada, cotizada, en_contabilidad, aprobada, pendiente_cierre, cerrada, rechazada`.

**Gate de contrato:** la transición `enviar_contabilidad` se bloquea si la solicitud no
tiene `contrato_id`. (Análogo al gate de reliquidación en viáticos.)

## Modelo de datos

### Tabla `solicitudes_obra` (cabecera; solicitable de Solicitud)
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint | |
| nombre_solicitante | varchar | Del formato ("CAMILO … (ING. RESIDENTE)") |
| fecha_solicitud | date | |
| fecha_entrega | date nullable | |
| contrato_id | FK contratos nullable | Se relaciona antes de enviar a contabilidad |
| observacion | text nullable | |
| total_a_pagar | decimal(14,2) nullable | Lo fija RR.HH./contabilidad al cotizar |

### Tabla `items_obra`
Basada en el formato FSM-GC-01 (Especificación, Unidad, Cantidad, Sede).
| Campo | Tipo | Notas |
|---|---|---|
| id | bigint | |
| solicitud_obra_id | FK cascade | |
| especificacion | varchar | "MALLA ELECTROSOLDADA 15X15 4MM X ROLLO" |
| unidad | varchar | rollo, bolsa, viaje, und, mts (texto libre) |
| cantidad | decimal(10,2) | |
| sede | varchar nullable | "SEDE LA LOMA" |
| valor_unitario | decimal(14,2) nullable | Lo llena RR.HH. al cotizar |
| subtotal | decimal(14,2) nullable | cantidad × valor_unitario |

### Tabla `cotizaciones_obra` (archivos de cotización y documentación anexa)
Reutiliza el patrón de `cotizaciones_oficina`.
| Campo | Tipo | Notas |
|---|---|---|
| id, solicitud_obra_id (FK cascade) | | |
| tipo | enum('cotizacion','documento') | Cotización vs. documentación anexa |
| path, nombre_original | varchar | Archivo en storage/local |
| usuario_id | FK | Quién lo subió |

### Tabla `abonos_obra` (pagos con retención)
Basada en `abonos_oficina`, MÁS retención por pago.
| Campo | Tipo | Notas |
|---|---|---|
| id, solicitud_obra_id (FK cascade) | | |
| monto | decimal(14,2) | Valor del pago/abono |
| retencion_tipo | enum('porcentaje','valor') nullable | Lo gestiona el contador |
| retencion_valor | decimal(14,2) nullable | % o monto según tipo |
| retencion_monto | decimal(14,2) default 0 | Monto calculado de retención |
| retencion_por | FK usuarios nullable | El contador que la aplicó |
| fecha_pago | date | |
| soporte_path, soporte_nombre | varchar | Comprobante descargable |
| usuario_id | FK | Quién registró el pago |
| observacion | text nullable | |

## Creación (residente)
Formulario con:
1. Cabecera: nombre solicitante (autollenado con su nombre), fecha solicitud, fecha entrega.
2. **Ítems manuales** (tabla editable): especificación, unidad, cantidad, sede.
3. **Y/O adjuntar cotización** (archivo). Al menos una de las dos (ítems o cotización).
4. Botón "Guardar borrador" y "Enviar a RR.HH.".

## Cotización y envío (RR.HH.)
- RR.HH. revisa/completa valores unitarios de los ítems (o sube su propia cotización).
- Relaciona el **contrato** (obligatorio para enviar a contabilidad).
- Anexa **documentación** necesaria (archivos tipo 'documento').
- Envía a contabilidad → notifica a `contabilidad_lider` + copia a `contador`.

## Pagos y retención (contabilidad)
- El `contabilidad_lider` aprueba y registra pagos: abonos parciales o pago total
  (reutiliza la lógica de saldo de Oficina).
- Al registrar un pago, se adjunta el **soporte** (descargable por cualquiera del proceso).
- El **contador** puede gestionar la **retención** de cada pago (% o valor); el sistema
  calcula `retencion_monto`. La retención se muestra en el detalle y en reportes.
- El primer pago lleva la solicitud a `pendiente_cierre`; luego se cierra.

## Notificaciones (trazabilidad total)
En **cada** transición se notifica (campana + correo, fail-safe vía `Avisos`) a **todos
los involucrados** de esa solicitud: el residente (creador) + todos los que ya
intervinieron (RR.HH., contabilidad_lider, contador). Se implementa recolectando los
`usuario_id` distintos de las transiciones + el solicitante + los roles del paso actual.

## Permisos (SolicitudPolicy, ampliada para OBR)
- crear OBR: `residente` o `lider_area`.
- ver detalle: involucrados (solicitante, o rol que corresponde por estado) + admin.
- cotizar/relacionar contrato/anexar docs: `rrhh` en estados enviada/cotizada.
- aprobar/pagar/cerrar: `contabilidad_lider`.
- gestionar retención: `contador`.
- descargar soportes de pago: cualquiera con acceso al detalle.

## Reutilización / Integración
- `MotorWorkflow`, `Solicitud` (morphTo), `TipoSolicitudSeeder` (nuevo tipo OBR).
- Menú lateral: "Obras" junto a Oficina y Viáticos (sección "Nueva solicitud").
- Listados/inicio: OBR aparece en "Mis solicitudes" y "Pendientes por aprobar" según rol.
- Reportes: se puede añadir un informe de Obras luego (fuera del alcance inicial).

## Fuera de alcance (por ahora)
- Reporte dedicado de Obras (se puede agregar después, con el patrón de Reportes).
- Exportación PDF del formato FSM-GC-01 (posible fase 2).

## Verificación
- Tests de: creación (items y/o cotización), flujo completo de estados, gate de contrato,
  pagos con retención, permisos por rol, notificaciones a todos los involucrados.
