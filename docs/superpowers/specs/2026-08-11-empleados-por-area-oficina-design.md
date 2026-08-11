# Diseño — Empleados por departamento y solicitudes institucionales de oficina

**Fecha:** 2026-08-11
**Proyecto:** Gesol (Laravel 10 + Inertia/React + MariaDB)
**Alcance:** Filtrar beneficiarios por departamento al crear una solicitud de oficina, y soportar
solicitudes institucionales (papelería, aseo) mediante un área especial "General".

---

## Contexto y decisiones

### Estado actual (explorado)

- `empleados.area_id` **ya existe en la base de desarrollo**, pero **NO hay migración en el repo** que lo cree;
  en un entorno limpio (CI, tests SQLite) la columna no existiría. El modelo `Empleados` **no** tiene `area_id`
  en `$fillable` ni la relación `area()`. `Area` solo tiene `solicitudes()`, no `empleados()`.
- `areas` tiene `id`, `nombre` (único), `descripcion`, timestamps. No tiene `es_general`.
- La solicitud de oficina ya tiene `area_id` (departamento que la pide) y beneficiarios (empleados) vía la
  tabla pivote `beneficiarios_oficina`.
- `OficinaController::create/edit` cargan **todos** los empleados sin filtrar.
- La gestión de empleados existe dentro de **Parámetros** (`ParametrosController::storeEmpleado/updateEmpleado/
  destroyEmpleado`, página `Parametros/Index.jsx`). Hoy sus métodos **ignoran** cualquier `area_id`.

### Decisiones del usuario

| Tema | Decisión |
|---|---|
| Relación empleado–área | **Un empleado, un departamento** (`area_id` en `empleados`). |
| Caso general (papelería/aseo) | **Área especial "General / Todos"** en el catálogo de áreas. |
| Beneficiarios con "General" | **Sin beneficiarios** (consumo institucional); el bloque se oculta. |
| Asignar área a empleados | **En la gestión de empleados** (Parámetros → CRUD de empleado). |
| Validación beneficiario–área | **Estricta**: el backend rechaza beneficiarios que no pertenezcan al área elegida. |
| Marca institucional | **Derivada** del área general (`es_general`), sin columna nueva en la solicitud. |

---

## Sección 1 — Modelo de datos

### `empleados.area_id` (migración nueva, versionar lo que ya existe en dev)
- Migración que añade `area_id` (FK a `areas`, **nullable**, `nullOnDelete`) a `empleados`, con guardia
  `Schema::hasColumn('empleados','area_id')` para ser idempotente (la base de dev ya la tiene; un entorno
  limpio no). Nullable: un empleado puede quedar sin área (p. ej. recién creado).
- `Empleados`: añadir `area_id` a `$fillable` y relación `area()` (belongsTo `Area`).
- `Area`: añadir relación `empleados()` (hasMany `Empleados`).

### `areas.es_general` (migración nueva)
- Migración que añade `es_general` (boolean, default `false`) a `areas`.
- `Area`: añadir `es_general` a `$fillable` y cast a `boolean`.

### Área "General" (seeder)
- En `AreaSeeder` (o el seeder de áreas correspondiente), `firstOrCreate` de un área con `nombre = 'General'`,
  `descripcion = 'Solicitudes institucionales (papelería, aseo)'` y `es_general = true`. `areas.nombre` es
  único, así que no se duplica.

### Institucional derivado (sin columna nueva)
- Una solicitud de oficina es **institucional** cuando su `area_id` apunta al área con `es_general = true`. No
  se añade columna a `solicitudes_oficina`; el carácter institucional se deriva del área.

---

## Sección 2 — Formulario de solicitud de oficina

### Carga de empleados filtrada por área
- `OficinaController::create/edit`: cargar empleados **con su `area_id`**
  (`Empleados::orderBy('nombres')->get(['id','nombres','apellidos','identificacion','area_id'])`), para que el
  cliente filtre sin llamadas extra. Cargar las áreas **incluyendo** la general (el select las lista todas);
  cada área expone `es_general` para que el frontend sepa cuál oculta beneficiarios.
- `Crear.jsx`: al elegir un departamento, el listado de beneficiarios se filtra a los empleados con ese
  `area_id`. Al cambiar de área, se limpian del estado los beneficiarios que ya no pertenezcan a la nueva área.

### Área "General / Todos" (institucional)
- El select de área lista las áreas reales **y** "General".
- Al elegir un área con `es_general = true`, el bloque de beneficiarios **se oculta** y no se envían
  beneficiarios. Texto de ayuda: "Solicitud institucional (papelería, aseo): aplica a toda la organización".

### Validación (`GuardarSolicitudOficinaRequest`)
- Regla condicional según el `area_id` recibido:
  - **Área general** (`es_general = true`): `beneficiarios` se ignora; la solicitud se guarda sin beneficiarios
    (sync a `[]`). No es error enviar un array vacío ni omitirlo.
  - **Área normal**: `beneficiarios` requerido (`required|array|min:1`), `beneficiarios.*` debe existir en
    `empleados`, **y cada beneficiario debe tener `area_id` igual al área de la solicitud** (regla estricta;
    rechaza empleados de otro departamento). Un empleado sin área (`area_id = null`) nunca coincide con un área
    normal, por lo que queda excluido como beneficiario — consistente con la regla. Mensaje legible cuando falla.
- La determinación de "es general" se hace consultando el `Area` del `area_id` recibido dentro del request
  (método `withValidator` o reglas condicionales con `Rule`).

### Controlador (`store/update`)
- Tras crear/actualizar la cabecera: si el área es general, `beneficiarios()->sync([])`; si es normal,
  `beneficiarios()->sync($request->beneficiarios)`.

### Detalle (`SolicitudDetalleResource` + `Detalle.jsx`)
- El Resource expone `institucional` (boolean, derivado de `area->es_general`) para OFI.
- `Detalle.jsx`: si `institucional`, mostrar "Beneficiario: Institucional (todos)" en lugar de la lista de
  empleados. Si no, la lista actual de beneficiarios.

---

## Sección 3 — Gestión de empleados (área)

### Parámetros (`ParametrosController` + `Parametros/Index.jsx`)
- `index()`: pasar `areas` (excluyendo la general) para poblar el select de departamento del empleado.
- `storeEmpleado`/`updateEmpleado`: añadir a las reglas `'area_id' => 'nullable|exists:areas,id'` y guardar
  `area_id`. Nullable → el empleado puede quedar sin área.
- El **área "General" se excluye** del select de empleados (un empleado no pertenece a General; General es solo
  para solicitudes institucionales). Filtrar por `es_general = false` al pasar las áreas.
- `Parametros/Index.jsx`: el formulario de crear/editar empleado gana un select de departamento; la tabla de
  empleados muestra el área (o "—" si no tiene).

### Migraciones y seeders (resumen)
1. `area_id` en `empleados` (idempotente).
2. `es_general` en `areas`.
3. Seeder: área "General" (`firstOrCreate`, `es_general = true`); asignar `area_id` a los empleados demo del
   `EmpleadosSeeder` (para que el filtrado tenga datos de prueba). Distribuir los 5 empleados demo entre las
   áreas reales existentes.

---

## Testing

- **Modelo:** empleado pertenece a un área (`area()`); área lista sus empleados (`empleados()`); área general
  identificable por `es_general`.
- **Filtrado / validación (HTTP):**
  - Solicitud normal con beneficiarios del área elegida → OK.
  - Solicitud normal con un beneficiario de **otra** área → rechazada (regla estricta).
  - Solicitud normal **sin** beneficiarios → rechazada.
  - Solicitud con área **general** sin beneficiarios → OK (institucional).
  - Solicitud con área general **con** beneficiarios → se ignoran (se guarda sin beneficiarios).
- **Parámetros:** crear/editar empleado con `area_id` lo guarda; `area_id` inválido → rechazado; empleado sin
  área permitido (nullable).
- **Detalle:** solicitud institucional expone `institucional = true` y no lista beneficiarios.
- Suite con SQLite `:memory:`; las migraciones nuevas deben correr en limpio (por eso se versiona `area_id`).

---

## Fases de implementación (resumen; el detalle va en el plan)

1. **Datos:** migraciones (`empleados.area_id`, `areas.es_general`), modelos (`Empleados.area()`/fillable,
   `Area.empleados()`/`es_general`), seeders (área General + área de empleados demo).
2. **Backend oficina:** `create/edit` (empleados con area_id, áreas con es_general), validación condicional en
   `GuardarSolicitudOficinaRequest`, sync en `store/update`, `institucional` en el Resource.
3. **Backend parámetros:** `area_id` en store/update de empleado, áreas (sin general) en `index`.
4. **UI:** `Crear.jsx` (filtrado por área + ocultar beneficiarios en general), `Parametros/Index.jsx` (select de
   área en empleado + columna área), `Detalle.jsx` (mostrar institucional).
5. **Tests + build** y verificación de la suite completa.
