# Diseño — Sección "Pendientes del líder" para el contador

**Fecha:** 2026-08-11
**Proyecto:** Gesol (Laravel 10 + Inertia/React + MariaDB)
**Alcance:** Dar al rol `contador` una pestaña de solo lectura con las solicitudes que están esperando acción
del líder de contabilidad, para que el contador dé seguimiento y el proceso no se demore si el líder no está.

---

## Contexto y decisiones

### Estado actual (explorado)

- `SolicitudController::index` lista solicitudes por pestaña (`tab`): `mias`, `pendientes`, `pendientes_cierre`
  (solo `contabilidad_lider`/`lider_area`), `revisadas`. El patrón de "pestaña visible solo para ciertos roles"
  ya existe (`pendientes_cierre` usa `$usuario->hasAnyRole([...])` y devuelve `collect()` vacío para el resto).
- El frontend [Solicitudes/Index.jsx](../../../resources/js/Pages/Solicitudes/Index.jsx) renderiza las pestañas
  desde un arreglo y una lista de tarjetas que ya muestran radicado, badge de tipo/estado, solicitante, fecha y
  total.
- `HandleInertiaRequests` comparte `auth.user` con su relación `roles` cargada, así que el frontend puede
  filtrar pestañas por rol (patrón ya usado en `AppLayout.jsx`: `usuario.roles?.some(r => r.name === '...')`).
- **Estados donde una solicitud espera al líder de contabilidad** (del `TipoSolicitudSeeder`):
  - **OFI** en `verificada`: RR.HH. ya verificó; el `contabilidad_lider` tiene `aprobar` ("Enviar a gerencia")
    / `rechazar`.
  - **VIA** en `revisada`: el contador ya envió la liquidación al líder; el `contabilidad_lider` tiene
    `cerrar` / `devolver` / `rechazar`.

### Hallazgo clave (verificado con test de reproducción)

Hoy el `contador` **no puede abrir el detalle** de una solicitud OFI en `verificada`: recibe **403**. La policy
`SolicitudPolicy::verDetalle` autoriza al solicitante y a los roles que aparecen en el campo `roles` de alguna
transición del tipo; el `contador` en OFI solo aparece en `notificar`, no en `roles`. Por eso recibe la
notificación pero no puede abrir la solicitud. El diseño debe **ampliar `verDetalle`** para el contador en estos
estados de solo lectura.

### Decisiones del usuario

| Tema | Decisión |
|---|---|
| Alcance | **Oficina (`verificada`) y viáticos (`revisada`)**. |
| Ubicación | **Pestaña** nueva en la lista de solicitudes, visible solo para `contador`. |
| Acciones del contador | **Solo ver (monitoreo)**. No aprueba/rechaza; eso sigue siendo del líder. |
| Distinguir tipo | Sí — el badge de tipo/estado de cada tarjeta ya lo muestra. |
| Etiqueta de la pestaña | **"Pendientes del líder"**. |

---

## Sección 1 — Backend

### a) Nueva pestaña `pendientes_lider` en `SolicitudController::index`
Añadir una rama para `tab === 'pendientes_lider'`. Solo el rol `contador` obtiene resultados; para cualquier
otro rol devuelve `collect()` vacío (mismo patrón que `pendientes_cierre`).

```php
} elseif ($tab === 'pendientes_lider') {
    // Solicitudes esperando accion del lider de contabilidad. Solo el contador
    // las ve, para dar seguimiento y evitar que el proceso se demore.
    $solicitudes = $usuario->hasRole('contador')
        ? Solicitud::with(['tipoSolicitud','solicitante'])
            ->where(function ($q) {
                $q->where(fn ($q) => $q->whereHas('tipoSolicitud', fn ($t) => $t->where('clave', 'OFI'))
                        ->where('estado', 'verificada'))
                  ->orWhere(fn ($q) => $q->whereHas('tipoSolicitud', fn ($t) => $t->where('clave', 'VIA'))
                        ->where('estado', 'revisada'));
            })
            ->oldest() // las mas antiguas primero: prioriza lo que mas se demora
            ->get()
        : collect();
```

- `oldest()` ordena por `created_at` ascendente para que lo más rezagado aparezca arriba.
- La consulta cubre ambos flujos en una sola lista; el tipo se distingue por el badge de cada tarjeta.

### b) Permiso de solo lectura en `SolicitudPolicy::verDetalle`
Ampliar `verDetalle` para que el `contador` pueda abrir el detalle de OFI `verificada` y VIA `revisada`, sin
concederle ninguna transición (el motor sigue sin ofrecerle botones de acción).

```php
public function verDetalle(Usuario $usuario, Solicitud $solicitud): bool
{
    if ($usuario->id === $solicitud->solicitante_id) return true;

    // El contador puede consultar (solo lectura) lo que espera al lider de contabilidad.
    $clave = $solicitud->tipoSolicitud->clave;
    if ($usuario->hasRole('contador')
        && (($clave === 'OFI' && $solicitud->estado === 'verificada')
            || ($clave === 'VIA' && $solicitud->estado === 'revisada'))) {
        return true;
    }

    $rolesUsuario = $usuario->getRoleNames()->toArray();
    return collect($solicitud->tipoSolicitud->transiciones)
        ->pluck('roles')->flatten()->unique()
        ->intersect($rolesUsuario)->isNotEmpty();
}
```

> El contador no gana ninguna acción: `MotorWorkflow::accionesDisponibles` filtra por el campo `roles` de las
> transiciones, donde el contador no aparece para estos estados. El detalle se muestra sin botones.

---

## Sección 2 — UI

### Pestaña en `Solicitudes/Index.jsx`
- Recibir el usuario autenticado (`usePage().props.auth.user`) y calcular `esContador =
  usuario.roles?.some(r => r.name === 'contador')`.
- Construir el arreglo de pestañas condicionalmente: la entrada
  `{ key: 'pendientes_lider', label: 'Pendientes del líder' }` se incluye **solo si `esContador`**. El resto de
  pestañas se mantienen igual para todos.
- La lista de tarjetas se reutiliza sin cambios: cada solicitud ya muestra el badge de tipo/estado, así que
  oficina y viáticos se distinguen visualmente.
- Empty state para el tab: "No hay solicitudes pendientes de aprobación del líder de contabilidad."
- Al hacer clic en una tarjeta se abre el detalle; gracias al cambio de policy, el contador lo ve en solo
  lectura (sin botones de acción, porque el motor no le ofrece transiciones).

---

## Testing

- **Backend (HTTP + policy):**
  - `contador` ve en el tab `pendientes_lider` una solicitud OFI `verificada` y una VIA `revisada`; no ve las
    que están en otros estados (p. ej. OFI `enviada`, VIA `liquidada`).
  - Un rol que no es contador (p. ej. `rrhh`) recibe la pestaña vacía.
  - `contador->can('verDetalle', ...)` es true para OFI `verificada` y VIA `revisada`, y el `GET
    solicitudes.show` responde 200 (antes daba 403).
  - `contador` **no** obtiene acciones: `MotorWorkflow::accionesDisponibles($ofiVerificada, $contador)` está
    vacío (no puede aprobar/rechazar).
  - Regresión: el `contador` sigue **sin** poder ver una OFI en un estado no contemplado (p. ej. `aprobada`) si
    no tenía acceso antes — la ampliación es acotada a `verificada`/`revisada`.
- Suite con SQLite `:memory:`; se reutilizan los seeders y usuarios demo (`contador@demo.test`,
  `rrhh@demo.test`, `lider.area@demo.test`).

---

## Fases de implementación (resumen; el detalle va en el plan)

1. **Policy:** ampliar `verDetalle` para el contador (OFI verificada / VIA revisada) + test.
2. **Controlador:** tab `pendientes_lider` en `index` (solo contador) + test.
3. **UI:** pestaña condicional "Pendientes del líder" en `Index.jsx` + empty state.
4. **Build + suite** completa.
