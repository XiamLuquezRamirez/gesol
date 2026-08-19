/**
 * Utilidades para armar los viajeros de una comisión de viáticos en el
 * formulario. La expansión de "varios empleados a la vez" se hace aquí como
 * función pura para poder razonarla y probarla sin React.
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
 * - Modo externo: devuelve UNA fila con nombre/identificación libres.
 * - Modo empleados: devuelve N filas (una por empleado seleccionado) que
 *   comparten motivo, contrato, fechas y horas.
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

    if (form.es_externo) {
        return [{
            ...base,
            empleado_id: null,
            es_externo: true,
            nombre_externo: form.nombre_externo,
            identificacion_externo: form.identificacion_externo || null,
            nombre: form.nombre_externo,
        }];
    }

    const ids = (form.empleado_ids ?? []).map(Number);
    return ids.map((id) => {
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
}
