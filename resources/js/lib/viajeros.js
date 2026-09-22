/**
 * Utilidades para armar los viajeros de una comisión de viáticos en el
 * formulario. La expansión de "varios empleados/externos a la vez" se hace aquí
 * como función pura para poder razonarla y probarla sin React.
 */

/** Etiqueta de un empleado: "Nombres Apellidos — identificación". */
export function etiquetaEmpleado(empleado) {
    if (!empleado) return '';
    const nombre = `${empleado.nombres ?? ''} ${empleado.apellidos ?? ''}`.trim();
    return empleado.identificacion ? `${nombre} — ${empleado.identificacion}` : nombre;
}

/**
 * Expande el mini-formulario en una o varias filas de viajero listas para
 * enviar al backend (cada fila es un ViajeroComision individual).
 *
 * Admite internos y externos EN EL MISMO agregado: todos comparten motivo,
 * contrato, fechas y horas.
 *  - `form.empleado_ids`: ids de empleados internos (0..N filas).
 *  - `form.externos`: lista de externos [{nombre_externo, identificacion_externo}] (0..N filas).
 *
 * @param {object} form   estado del mini-formulario.
 * @param {Array}  empleados catálogo [{id, nombres, apellidos, identificacion}].
 * @returns {Array} filas de viajero.
 */
export function expandirViajeros(form, empleados = []) {
    const base = {
        contrato_id:  form.contrato_id ? Number(form.contrato_id) : null,
        motivo:       form.motivo,
        fecha_salida: form.fecha_salida,
        hora_salida:  form.hora_salida,
        fecha_regreso: form.fecha_regreso,
        hora_regreso: form.hora_regreso,
    };

    // Internos: una fila por empleado seleccionado.
    const ids = (form.empleado_ids ?? []).map(Number);
    const internos = ids.map((id) => {
        const empleado = empleados.find((e) => Number(e.id) === id);
        return {
            ...base,
            empleado_id: id,
            es_externo: false,
            nombre_externo: null,
            identificacion_externo: null,
            nombre: empleado ? `${empleado.nombres} ${empleado.apellidos}`.trim() : '',
        };
    });

    // Externos: una fila por externo con nombre no vacío.
    const externos = (form.externos ?? [])
        .filter((x) => (x.nombre_externo ?? '').trim() !== '')
        .map((x) => ({
            ...base,
            empleado_id: null,
            es_externo: true,
            nombre_externo: x.nombre_externo.trim(),
            identificacion_externo: (x.identificacion_externo ?? '').trim() || null,
            nombre: x.nombre_externo.trim(),
        }));

    return [...internos, ...externos];
}
