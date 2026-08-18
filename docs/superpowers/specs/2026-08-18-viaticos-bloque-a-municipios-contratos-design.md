# Diseño — Viáticos Bloque A: catálogos de municipios y contratos

**Fecha:** 2026-08-18
**Proyecto:** Gesol (Laravel 10 + Inertia/React + MariaDB)
**Alcance:** Primer bloque de una mejora mayor al subsistema de viáticos (7 cambios en 3 bloques). Este bloque
crea el catálogo de **municipios** y la gestión de **contratos**, y conecta los municipios (multiselect) a la
comisión. Los otros dos bloques (viajeros; liquidación/pagos) tienen su propio ciclo spec→plan→implementación.

---

## Contexto: decomposición en 3 bloques

Los 7 cambios pedidos por el usuario se decomponen por dependencia:

- **Bloque A (este):** tabla `municipios` + multiselect de municipios en la comisión + gestión de contratos.
- **Bloque B (futuro):** contrato por viajero (relación); viajero externo (texto libre cuando no está en la
  base). Aquí se implementará la regla "**bloquear el borrado de un contrato si tiene viajeros**".
- **Bloque C (futuro):** soporte de transferencia por viajero (adjuntar capturas); editar planilla (días +/−,
  soportes adicionales); regla de meriendas (solo 1 por día).

Este spec cubre **solo el Bloque A**.

### Estado actual relevante (explorado)

- `solicitudes_viaticos.municipio_destino` es **texto libre** (varchar 255), uno por comisión. En
  `Viaticos/Crear.jsx` es un `<Input>` de texto. **No existe** tabla/modelo `municipios`.
- **No existe** ninguna tabla/modelo/CRUD de `contratos`.
- Parámetros ([Parametros/Index.jsx](../../../resources/js/Pages/Parametros/Index.jsx),
  [ParametrosController](../../../app/Http/Controllers/ParametrosController.php)) tiene tabs `tarifas` y
  `empleados`, con CRUD por tab; rutas bajo `['auth','verified']` sin rol adicional. Patrón a seguir para el tab
  de contratos.
- `ViaticosController::store` crea la cabecera con `municipio_destino` e itera viajeros; `update` hace
  delete+recreate de viajeros. `SolicitudViaticos` tiene `viajeros()` (hasMany) y `solicitud()` (morphOne).
- El CRUD de empleados en Parámetros y los beneficiarios múltiples de oficina
  (`belongsToMany` + multiselect de checkboxes) son los patrones de referencia para municipios/contratos.

### Decisiones del usuario

| Tema | Decisión |
|---|---|
| Nivel de municipios | **Varios por comisión** (multiselect en la cabecera, pivote comisión–municipio). |
| Catálogo de municipios | **Solo seeder** (lista fija, sin CRUD). |
| Contrato–municipio | **Varios municipios por contrato** (pivote contrato–municipio). |
| Gestión de contratos | **CRUD en Parámetros** (tab nuevo). |
| Borrado de contrato con viajeros | **Bloquear** — se implementa en el Bloque B (aquí nada referencia contratos aún). |
| `municipio_destino` texto | Se **conserva** como dato histórico; el detalle muestra la lista de la pivote. |

---

## Sección 1 — Municipios

### Tabla `municipios` (catálogo, solo seeder)
- Migración `create_municipios_table`: `id`, `nombre` (string, **unique**), timestamps.
- Seeder `MunicipiosSeeder` con una lista fija inicial (municipios del Cesar/Colombia; el usuario la ajusta).
  Registrado en `DatabaseSeeder`. Sin CRUD.
- Modelo `Municipio`: `$fillable = ['nombre']`; relación `comisiones()` (belongsToMany `SolicitudViaticos`) y
  `contratos()` (belongsToMany `Contrato`).

### Multiselect de municipios en la comisión
- Migración `create_comision_municipio_table`: pivote `solicitud_viaticos_id` (FK cascade) + `municipio_id`
  (FK), timestamps.
- `SolicitudViaticos`: relación `municipios()` (belongsToMany vía `comision_municipio`).
- `ViaticosController::create` y `edit`: pasar el catálogo `municipios` (`Municipio::orderBy('nombre')->get(['id','nombre'])`).
  `edit` además carga `solicitable.municipios`.
- `ViaticosController::store` y `update`: tras crear/actualizar la cabecera, `$cabecera->municipios()->sync($request->municipios)`.
  La columna `municipio_destino` se sigue guardando vacía (`''`) por compatibilidad (ya no la llena el
  formulario nuevo).
- `Viaticos/Crear.jsx`: reemplazar el `<Input>` de "Municipio destino" por un **multiselect de municipios**
  (checkboxes/chips, patrón de beneficiarios de oficina). Muestra los municipios del catálogo; el estado del
  formulario lleva `municipios: []` (array de ids).

### Validación (`GuardarSolicitudViaticosRequest`)
- Reemplazar la regla `'municipio_destino' => 'required|string|max:255'` por:
  - `'municipios' => 'required|array|min:1'`
  - `'municipios.*' => 'exists:municipios,id'`
- `attributes()`: `'municipios' => 'municipios'`, `'municipios.*' => 'municipio'`.

### Detalle de la comisión
- `SolicitudDetalleResource` (bloque de viáticos) expone la lista de municipios de la comisión
  (`solicitable.municipios` → nombres). `Detalle.jsx` (`DetalleViaticos`) muestra "Municipios" con la lista en
  vez del texto libre `municipio_destino`.

---

## Sección 2 — Contratos

### Tabla `contratos` (gestionable en Parámetros)
- Migración `create_contratos_table`: `id`, `descripcion` (string), `objeto` (text), timestamps.
- Migración `create_contrato_municipio_table`: pivote `contrato_id` (FK cascade) + `municipio_id` (FK), timestamps.
- Modelo `Contrato`: `$fillable = ['descripcion', 'objeto']`; relación `municipios()` (belongsToMany vía
  `contrato_municipio`). (La relación `viajeros()` hasMany se añade en el Bloque B.)

### CRUD en Parámetros
- `ParametrosController::index`: pasar `contratos` (con `municipios`) y el catálogo `municipios` para el
  multiselect: `Contrato::with('municipios:id,nombre')->orderBy('descripcion')->get()` y
  `Municipio::orderBy('nombre')->get(['id','nombre'])`.
- Métodos nuevos: `storeContrato`, `updateContrato`, `destroyContrato` (mismo estilo que empleados/tarifas).
  - `store`/`update`: validan `descripcion` (required|string|max:255), `objeto` (required|string|max:2000),
    `municipios` (required|array|min:1), `municipios.*` (exists:municipios,id). Crean/actualizan el contrato y
    `sync` de municipios en la pivote.
  - `destroy`: por ahora borra libremente (nada referencia contratos aún). **Nota para Bloque B:** cuando
    exista la relación viajero–contrato, `destroy` debe **abortar (409/422) si el contrato tiene viajeros**.
- Rutas: `parametros.contratos.store` (POST), `parametros.contratos.update` (PUT), `parametros.contratos.destroy`
  (DELETE), bajo `['auth','verified']`.

### UI del tab (`Parametros/Index.jsx`)
- Añadir `{ id: 'contratos', label: 'Contratos' }` al arreglo `TABS` (junto a tarifas/empleados).
- Nuevo componente `TabContratos({ contratos, municipios })`: formulario crear/editar (descripción, objeto,
  multiselect de municipios) + tabla que lista contratos con sus municipios y acciones editar/eliminar. Sigue
  el patrón de `TabEmpleados`.

---

## Sección 3 — Datos, orden e integración

### Migraciones (orden)
1. `create_municipios_table`.
2. `create_contratos_table`.
3. `create_contrato_municipio_table` (depende de contratos + municipios).
4. `create_comision_municipio_table` (depende de solicitudes_viaticos + municipios).

### Seeders
- `MunicipiosSeeder` (lista fija) + registro en `DatabaseSeeder` **antes** de cualquier seeder que dependa de
  municipios (ninguno hoy, pero por orden lógico va con los catálogos).

### Alcance del Bloque A
**Entra:** catálogo de municipios (tabla+seeder+modelo); multiselect de municipios en crear/editar comisión
(reemplaza el texto libre en el formulario); CRUD de contratos en Parámetros (con multiselect de municipios);
el detalle de la comisión muestra la lista de municipios.

**No entra (Bloques B/C):** contrato por viajero; viajero externo; soportes de transferencia; editar planilla;
regla de meriendas.

---

## Testing

- **Municipios:**
  - Una comisión guarda y sincroniza varios municipios (pivote); `municipios()` los devuelve.
  - Validación: crear comisión sin municipios → rechazada (`min:1`); con municipio inexistente → rechazada.
  - El detalle (Resource) expone la lista de municipios de la comisión.
  - El seeder crea el catálogo de municipios.
- **Contratos:**
  - Crear un contrato con municipios desde Parámetros lo guarda con su pivote.
  - Editar un contrato re-sincroniza sus municipios.
  - Validación: contrato sin descripción/objeto/municipios → rechazado; municipio inexistente → rechazado.
  - Eliminar un contrato (sin viajeros, caso actual) lo borra.
- Suite con SQLite `:memory:`; se reutilizan seeders y usuarios demo.

---

## Fases de implementación (resumen; el detalle va en el plan)

1. **Datos municipios:** migración + modelo `Municipio` + seeder + registro en DatabaseSeeder.
2. **Datos contratos:** migraciones (`contratos`, `contrato_municipio`) + modelo `Contrato`.
3. **Comisión ↔ municipios:** pivote `comision_municipio`, relación en `SolicitudViaticos`, sync en
   store/update, validación en el request, Resource + detalle.
4. **UI comisión:** multiselect de municipios en `Viaticos/Crear.jsx`.
5. **CRUD contratos (backend):** métodos en `ParametrosController` + rutas + props en index.
6. **UI contratos:** tab "Contratos" en `Parametros/Index.jsx`.
7. **Build + suite** completa.
