const CLAVE_AUTOGUARDADO = 'planos2d.planoTrabajo';

export function guardarPlanoTrabajo(planoSerializado) {
    localStorage.setItem(CLAVE_AUTOGUARDADO, JSON.stringify({
        fecha: new Date().toISOString(),
        plano: planoSerializado,
    }));
}

export function obtenerPlanoTrabajo() {
    const contenido = localStorage.getItem(CLAVE_AUTOGUARDADO);

    if (!contenido) {
        return null;
    }

    try {
        const datos = JSON.parse(contenido);
        return datos?.plano ? datos : null;
    } catch (error) {
        localStorage.removeItem(CLAVE_AUTOGUARDADO);
        return null;
    }
}

export function descartarPlanoTrabajo() {
    localStorage.removeItem(CLAVE_AUTOGUARDADO);
}
