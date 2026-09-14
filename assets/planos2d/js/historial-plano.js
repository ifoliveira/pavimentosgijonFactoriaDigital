export function crearHistorialPlano(estadoInicial) {
    return {
        pasado: [],
        presente: clonarEstado(estadoInicial),
        futuro: [],
        guardado: JSON.stringify(estadoInicial),
    };
}

export function confirmarCambio(historial, estadoNuevo) {
    const anterior = JSON.stringify(historial.presente);
    const siguiente = JSON.stringify(estadoNuevo);

    if (anterior === siguiente) {
        return false;
    }

    historial.pasado.push(clonarEstado(historial.presente));
    historial.presente = clonarEstado(estadoNuevo);
    historial.futuro = [];

    return true;
}

export function deshacer(historial) {
    if (historial.pasado.length === 0) {
        return null;
    }

    historial.futuro.push(clonarEstado(historial.presente));
    historial.presente = historial.pasado.pop();

    return clonarEstado(historial.presente);
}

export function rehacer(historial) {
    if (historial.futuro.length === 0) {
        return null;
    }

    historial.pasado.push(clonarEstado(historial.presente));
    historial.presente = historial.futuro.pop();

    return clonarEstado(historial.presente);
}

export function marcarGuardado(historial, estado) {
    historial.guardado = JSON.stringify(estado);
}

export function planoModificado(historial) {
    return JSON.stringify(historial.presente) !== historial.guardado;
}

export function puedeDeshacer(historial) {
    return historial.pasado.length > 0;
}

export function puedeRehacer(historial) {
    return historial.futuro.length > 0;
}

export function clonarEstado(estado) {
    return JSON.parse(JSON.stringify(estado));
}
