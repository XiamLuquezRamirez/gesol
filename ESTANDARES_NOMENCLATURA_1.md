# Estándares de Nomenclatura — Sistema de Gestión de Solicitudes

> Documento de referencia obligatorio. Referéncialo desde `CLAUDE.md`. Aplica a base de datos, PHP/Laravel y React/JS. **Objetivo: que todo el dominio se escriba en español, de forma consistente, sin mezclar con inglés.**

---

## 1. Principio rector

Todo lo que pertenece al **dominio del negocio** (tablas, columnas, modelos, métodos, variables, componentes, rutas) se nombra en **español**. Solo se mantiene en inglés lo que es **contrato del framework** (ver §2). No se inventan sinónimos: cada concepto tiene un único término canónico (ver §8, Glosario).

---

## 2. Regla de oro: la frontera español / inglés

**Permanecen en inglés (no se traducen — son contrato de Laravel / paquetes):**

- Identificadores reservados de Eloquic: `id`, `created_at`, `updated_at`, `deleted_at`.
- Sufijos de relaciones polimórficas: `{nombre}_type` y `{nombre}_id` (ej. `solicitable_type`, `solicitable_id`).
- Métodos del ciclo de vida del framework que se sobrescriben: `up()`, `down()`, `boot()`, `casts()`, `booted()`, `register()`, `handle()`.
- Métodos de relación que define Laravel cuando se llaman desde dentro (`belongsTo`, `hasMany`, etc.) — pero el **nombre del método de relación SÍ va en español** (ver §4.4).
- Las tablas de paquetes (spatie/laravel-permission: `roles`, `permissions`, `model_has_roles`, etc.) se dejan tal cual las crea el paquete.

**Todo lo demás va en español.**

> **Razón de mantener `created_at`/`updated_at` en inglés:** Eloquent y casi todos los paquetes los asumen. Traducirlos obliga a `const CREATED_AT = '...'` en cada modelo y rompe integraciones. No vale la pena; son columnas "de sistema", no de dominio.

---

## 3. Regla transversal: identificadores SIN tildes ni ñ

Los **identificadores de código y base de datos** (nombres de tablas, columnas, variables, métodos, clases) se escriben en **ASCII**: sin tildes, sin diéresis y sin `ñ`.

| Concepto | ❌ Incorrecto | ✅ Correcto |
|---|---|---|
| justificación | `justificación` | `justificacion` |
| días | `días` | `dias` |
| comisión | `comisión` | `comision` |
| año | `año` | `anio` |
| número | `número` | `numero` |

Las **tildes y la ñ SÍ se usan** en: comentarios, textos de UI, mensajes de validación, etiquetas, y valores de datos (no en los identificadores).

---

## 4. PHP / Laravel

### 4.1 Clases y modelos — `PascalCase`, singular, español
`Solicitud`, `TipoSolicitud`, `SolicitudOficina`, `ItemOficina`, `SolicitudViaticos`, `ViajeroComision`, `AsignacionViatico`, `TarifaViatico`, `TransicionSolicitud`, `Area`, `Usuario`.

> El modelo de usuario se llama `Usuario` (no `User`). Configúralo en `config/auth.php` (`providers.users.model`) y en la migración de autenticación.

### 4.2 Servicios, Policies, Requests, Resources — `PascalCase` español + sufijo estándar
`MotorWorkflow` (service), `SolicitudPolicy`, `GuardarSolicitudOficinaRequest`, `SolicitudResource`, `SolicitudDetalleResource`, `TransicionNoPermitidaException`.

> El sufijo técnico (`Request`, `Resource`, `Policy`, `Exception`) se mantiene en inglés porque es convención de Laravel para el autoload y la ubicación de carpetas. El resto del nombre, en español.

### 4.3 Métodos y funciones — `camelCase`, verbo en español
`recalcularTotal()`, `aplicarTransicion()`, `accionesDisponibles()`, `puede()`, `generarRadicado()`, `notificarSiguientePaso()`.

### 4.4 Métodos de relación Eloquent — `camelCase` español
```php
public function solicitante()  { return $this->belongsTo(Usuario::class, 'solicitante_id'); }
public function beneficiario() { return $this->belongsTo(Usuario::class, 'beneficiario_id'); }
public function items()        { return $this->hasMany(ItemOficina::class, 'solicitud_oficina_id'); }
public function solicitable()  { return $this->morphTo(); }
```
> **CRÍTICO:** como las llaves foráneas van en español (`solicitante_id`, etc.), Eloquent NO las infiere solo. **Siempre** pasa explícitamente la llave foránea como segundo argumento en `belongsTo`/`hasMany`. No confíes en la convención inglesa.

### 4.5 Variables y propiedades — `camelCase`, español
`$solicitud`, `$usuarioActual`, `$accionesDisponibles`, `$totalCalculado`, `$transicion`.

### 4.6 Enums — `PascalCase` para la clase, `PascalCase` para los casos, valor en español ASCII
```php
enum EstadoSolicitud: string {
    case Borrador     = 'borrador';
    case Enviada      = 'enviada';
    case AprobadaMonto = 'aprobada_monto';
    // ...
}
enum Rubro: string {
    case Desayuno = 'desayuno';
    case Almuerzo = 'almuerzo';
    // ...
}
```

---

## 5. Base de datos (MariaDB)

### 5.1 Tablas — `snake_case`, plural, español
`solicitudes`, `tipos_solicitud`, `solicitudes_oficina`, `items_oficina`, `solicitudes_viaticos`, `viajeros_comision`, `asignaciones_viaticos`, `tarifas_viaticos`, `transiciones_solicitud`, `areas`.

### 5.2 Columnas — `snake_case`, español ASCII
`estado`, `radicado`, `total`, `justificacion`, `urgencia`, `categoria`, `cantidad`, `costo_estimado`, `subtotal`, `motivo`, `municipio_destino`, `fecha_salida`, `fecha_regreso`, `valor_unitario`, `dias`, `comentario`, `metadatos`.

### 5.3 Llaves foráneas — `{tabla_singular}_id`, español
`tipo_solicitud_id`, `solicitante_id`, `beneficiario_id`, `area_id`, `usuario_id`, `solicitud_id`, `solicitud_oficina_id`, `viajero_comision_id`.

### 5.4 Relación polimórfica — nombre del morph en español, sufijos en inglés
Morph llamado `solicitable` → columnas `solicitable_type`, `solicitable_id` (los sufijos `_type`/`_id` son obligatorios para Laravel).

### 5.5 Índices y constraints — prefijo descriptivo en español
`idx_solicitudes_estado`, `uq_solicitudes_radicado`, `fk_items_oficina_solicitud`.

### 5.6 Columnas booleanas — prefijo `es_` o `tiene_`
`es_activo`, `tiene_adjuntos`. (Evita nombres ambiguos como `estado` para booleanos.)

---

## 6. Frontend (React + Inertia)

### 6.1 Componentes y páginas — `PascalCase`, español
Archivos y componentes: `TablaSolicitudes.jsx`, `LineaTiempo.jsx`, `BadgeEstado.jsx`, `ModalAccion.jsx`, `CampoMoneda.jsx`. Páginas Inertia: `Solicitudes/Index.jsx`, `Solicitudes/Detalle.jsx`, `Viaticos/Liquidacion.jsx`.

### 6.2 Variables, funciones y props — `camelCase`, español
`const solicitudesPendientes = ...`, `function calcularSubtotal() {}`, props: `<BadgeEstado estado={...} />`, `<TablaSolicitudes solicitudes={...} />`.

### 6.3 Hooks personalizados — `use` + español `camelCase`
`useFormularioSolicitud()`, `useAccionesDisponibles()`. (El prefijo `use` es contrato de React, se mantiene.)

### 6.4 Rutas (URLs) — español, `kebab-case` o palabra simple
`/solicitudes`, `/oficina/crear`, `/viaticos/{solicitud}/liquidar`.

---

## 7. Mapa de traducción del esquema (inglés → español)

Aplica esta tabla al esquema definido en el prompt de construcción. **Este es el nombre canónico definitivo.**

| Concepto | Tabla | Modelo |
|---|---|---|
| Áreas | `areas` | `Area` |
| Tipos de solicitud | `tipos_solicitud` | `TipoSolicitud` |
| Solicitudes (núcleo) | `solicitudes` | `Solicitud` |
| Transiciones | `transiciones_solicitud` | `TransicionSolicitud` |
| Cabecera oficina | `solicitudes_oficina` | `SolicitudOficina` |
| Ítems de oficina | `items_oficina` | `ItemOficina` |
| Cabecera viáticos | `solicitudes_viaticos` | `SolicitudViaticos` |
| Viajeros | `viajeros_comision` | `ViajeroComision` |
| Asignaciones por rubro | `asignaciones_viaticos` | `AsignacionViatico` |
| Tarifas sugeridas | `tarifas_viaticos` | `TarifaViatico` |

Columnas clave (inglés → español):

| Inglés | Español |
|---|---|
| `key` | `clave` |
| `name` | `nombre` |
| `initial_status` | `estado_inicial` |
| `statuses` | `estados` |
| `transitions` | `transiciones` |
| `request_type_id` | `tipo_solicitud_id` |
| `requester_id` | `solicitante_id` |
| `status` | `estado` |
| `from_status` / `to_status` | `estado_origen` / `estado_destino` |
| `action` | `accion` |
| `comment` | `comentario` |
| `metadata` | `metadatos` |
| `beneficiary_id` | `beneficiario_id` |
| `urgency` | `urgencia` |
| `justification` | `justificacion` |
| `category` | `categoria` |
| `quantity` | `cantidad` |
| `notes` | `notas` |
| `committee_name` | `nombre_comision` |
| `destination_municipality` | `municipio_destino` |
| `reason` | `motivo` |
| `depart_date` / `return_date` | `fecha_salida` / `fecha_regreso` |
| `role_in_committee` | `rol_en_comision` |
| `unit_value` | `valor_unitario` |
| `days` | `dias` |
| `suggested_value` | `valor_sugerido` |

Llaves de la matriz de workflow (`transitions` JSON) — se dejan en español: `from`→`origen`, `to`→`destino`, `action`→`accion`, `roles`→`roles`, `notify`→`notificar`. *(Si prefieres mantener la matriz en inglés porque es estructura técnica interna, indícalo; es la única zona "gris".)*

---

## 8. Glosario canónico del dominio

Usa **siempre** estos términos; nunca sus sinónimos.

| Término canónico | Significado | Evitar |
|---|---|---|
| **solicitud** | petición que recorre un flujo | request, petición, trámite |
| **tipo de solicitud** | configuración de un proceso | request type |
| **solicitante** | quien crea la solicitud | requester, autor |
| **beneficiario** | integrante que recibe el elemento | beneficiary |
| **estado** | situación actual en el flujo | status, fase |
| **transición** | cambio de un estado a otro | transition, movimiento |
| **accion** | operación que dispara una transición | action |
| **radicado** | número único de la solicitud | folio, consecutivo |
| **elemento de oficina** | producto o servicio solicitado | supply, insumo |
| **ítem** | línea de detalle de la solicitud | item, renglón |
| **viaticos** | dinero asignado por comisión | per diem |
| **comision** | viaje oficial a otro municipio | viaje, comité |
| **viajero** | persona que sale en comisión | traveler, comisionado |
| **rubro** | categoría de gasto (desayuno, gasolina…) | concepto, categoría de gasto |
| **asignacion** | monto de un rubro para un viajero | allocation, distribución |
| **tarifa** | valor sugerido por rubro | rate, tope |

---

## 9. Checklist antes de hacer commit

- [ ] ¿Todo identificador nuevo (tabla, columna, clase, método, variable, componente) está en español?
- [ ] ¿Sin tildes ni `ñ` en los identificadores?
- [ ] ¿Las llaves foráneas en español llevan su FK explícita en las relaciones Eloquent?
- [ ] ¿Solo `id`, `created_at`, `updated_at`, `deleted_at` y los sufijos `_type`/`_id` quedaron en inglés?
- [ ] ¿El término usado coincide con el Glosario (§8) y no es un sinónimo?
- [ ] ¿Comentarios y textos de UI con su ortografía correcta (con tildes)?
