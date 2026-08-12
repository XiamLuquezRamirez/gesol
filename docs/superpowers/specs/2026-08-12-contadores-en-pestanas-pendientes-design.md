# Diseño — Contadores en las pestañas de solicitudes pendientes

**Fecha:** 2026-08-12
**Proyecto:** Gesol (Laravel 10 + Inertia/React + MariaDB)
**Alcance:** Mostrar un badge con el número de pendientes en las pestañas "Pendientes de acción",
"Pendientes por cerrar" y "Pendientes del líder" de la lista de solicitudes, para identificar de un vistazo
dónde hay trabajo pendiente.

---

## Contexto y decisiones

### Estado actual (explorado)

- `SolicitudController::index` construye `$solicitudes` **solo para el tab activo** (if/elseif por `$tab`) y
  renderiza `Solicitudes/Index` con `solicitudes.data` + `filtros.tab`.
- Las tres colas:
  - `pendientes`: carga todas las solicitudes y filtra en PHP con `MotorWorkflow::accionesDisponibles`
    (potencialmente costoso, pero aceptable al volumen actual).
  - `pendientes_cierre`: consulta SQL — OFI en `pendiente_cierre`; visible solo para
    `contabilidad_lider`/`lider_area`.
  - `pendientes_lider`: consulta SQL — OFI `verificada` OR VIA `revisada`; visible solo para `contador`.
- El frontend [Solicitudes/Index.jsx](../../../resources/js/Pages/Solicitudes/Index.jsx) arma las pestañas en un
  arreglo `tabs` cuyo `key` ya coincide con la clave de cola (`pendientes`, `pendientes_cierre`,
  `pendientes_lider`), mapeado a botones.

### Decisiones del usuario

| Tema | Decisión |
|---|---|
| Tipo de indicador | **Badge con el número** exacto de pendientes. |
| Rendimiento de `pendientes` | **Calcularlo igual** (recorrer en PHP con `accionesDisponibles`); aceptable al volumen actual. |
| Cero | **Ocultar el badge** cuando el conteo es 0 (solo aparece si hay ≥1). |

---

## Sección 1 — Backend

### Refactor: extraer las colas a métodos privados
`index()` ya crece; para no duplicar la lógica entre "datos del tab activo" y "conteo", se extrae cada cola a un
método privado reutilizable en `SolicitudController`:

- `private function colaPendientes(Usuario $usuario): Collection` — la colección filtrada por
  `accionesDisponibles` (la lógica actual del tab `pendientes`).
- `private function queryPendientesCierre(Usuario $usuario)` — devuelve el query builder de OFI
  `pendiente_cierre` si el usuario puede verla, o `null` si no (para poder contar y listar sin repetir el gate
  de rol). El `index` usa `->count()` para el conteo y `->latest()->get()` para los datos.
- `private function queryPendientesLider(Usuario $usuario)` — igual, para OFI `verificada` OR VIA `revisada`.

> Nota: `pendientes` no es un query builder (se resuelve en PHP), así que su método devuelve la colección ya
> filtrada; el conteo es `->count()` sobre ella. Las otras dos devuelven query builder (o null) para que contar
> sea una consulta SQL barata (`->count()`), sin materializar filas.

### Conteos en cada carga
En `index()`, independientemente del tab activo, calcular los tres conteos y pasarlos como prop `conteos`:

```php
$conteos = [
    'pendientes'        => $this->colaPendientes($usuario)->count(),
    'pendientes_cierre' => optional($this->queryPendientesCierre($usuario))->count() ?? 0,
    'pendientes_lider'  => optional($this->queryPendientesLider($usuario))->count() ?? 0,
];
```

Los conteos respetan el gate de rol (0 para quien no ve esa cola). El render añade `'conteos' => $conteos` al
array de props, junto a `solicitudes` y `filtros`.

### Uso en las ramas del tab
Las ramas del `index` reutilizan los mismos métodos para obtener los datos del tab activo, evitando duplicar la
lógica (p. ej. el tab `pendientes` usa `$this->colaPendientes($usuario)`; `pendientes_cierre` usa el query si no
es null, o `collect()`).

---

## Sección 2 — UI

### Badge de conteo por pestaña
En `Solicitudes/Index.jsx`:
- Recibir el prop `conteos` (objeto `{ pendientes, pendientes_cierre, pendientes_lider }`; puede venir vacío,
  se maneja con default `{}`).
- En el `map` de `tabs`, junto al `label`, si `conteos[key] > 0` renderizar un badge pequeño con el número.
  Las pestañas sin conteo (`mias`, `revisadas`) no tienen entrada en `conteos`, así que nunca muestran badge.
- Estilo del badge: círculo/pill pequeño, color acorde a si la pestaña está activa (indigo) o no (slate), a la
  derecha del texto. Ejemplo de marcado dentro del botón:

```jsx
{label}
{conteos[key] > 0 && (
    <span className="ml-2 inline-flex items-center justify-center min-w-5 h-5 px-1.5 rounded-full text-xs font-semibold bg-indigo-100 text-indigo-700">
        {conteos[key]}
    </span>
)}
```

- El badge se oculta cuando el conteo es 0 (condición `> 0`).
- `pendientes_lider` ya solo aparece como pestaña para el contador; su conteo es 0 para el resto de todas
  formas, así que no se filtra a otros roles.

---

## Testing

- **Backend (Inertia):**
  - El prop `conteos` está presente y trae los tres números.
  - Un usuario con N solicitudes accionables ve `conteos.pendientes === N`.
  - Un `contabilidad_lider` con M solicitudes en `pendiente_cierre` ve `conteos.pendientes_cierre === M`; un rol
    que no ve esa cola lo ve en 0.
  - Un `contador` con K solicitudes pendientes del líder ve `conteos.pendientes_lider === K`; un no-contador lo
    ve en 0.
  - Regresión: `solicitudes.data` del tab activo sigue siendo correcta tras el refactor a métodos privados.
- Suite con SQLite `:memory:` + seeders y usuarios demo.

---

## Fases de implementación (resumen; el detalle va en el plan)

1. **Backend:** extraer las colas a métodos privados, calcular `conteos`, exponer el prop; ajustar las ramas del
   tab para reutilizar los métodos + tests.
2. **UI:** badge de conteo por pestaña (oculto en 0).
3. **Build + suite** completa.
