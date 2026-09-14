const MAX_DISTANCIAS_ENTRE_ELEMENTOS = 2;
const MAX_DISTANCIAS_TODOS_ELEMENTOS = 12;
const DISTANCIA_VISIBLE_MINIMA = 0.001;

export function calcularDistanciasEntreInteriores(elementoSeleccionado, sistemaInteriores) {
    if (!elementoSeleccionado || !sistemaInteriores?.elementos?.length) {
        return { distancias: [], boundingBox: null };
    }

    const seleccionado = sistemaInteriores.elementos.find((elemento) => elemento.id === elementoSeleccionado.id);

    if (!seleccionado) {
        return { distancias: [], boundingBox: null };
    }

    const distancias = sistemaInteriores.elementos
        .filter((elemento) => elemento.id !== seleccionado.id)
        .flatMap((elemento) => distanciasEntreBoxes(seleccionado, elemento))
        .filter(Boolean)
        .filter((distancia) => distancia.distancia > DISTANCIA_VISIBLE_MINIMA)
        .sort((a, b) => a.distancia - b.distancia)
        .slice(0, MAX_DISTANCIAS_ENTRE_ELEMENTOS);

    return {
        distancias,
        boundingBox: calcularBoundingBoxDistancias(distancias),
    };
}

export function calcularDistanciasEntreTodosInteriores(sistemaInteriores) {
    if (!sistemaInteriores?.elementos || sistemaInteriores.elementos.length < 2) {
        return { distancias: [], boundingBox: null };
    }

    const distanciasPorElemento = sistemaInteriores.elementos.flatMap((elemento) => (
        sistemaInteriores.elementos
            .filter((candidato) => candidato.id !== elemento.id)
            .flatMap((candidato) => distanciasEntreBoxes(elemento, candidato, elemento.id, candidato.id))
            .filter((distancia) => distancia.distancia > DISTANCIA_VISIBLE_MINIMA)
            .sort((a, b) => a.distancia - b.distancia)
            .slice(0, 1)
    ));
    const distancias = quitarDuplicadas(distanciasPorElemento)
        .sort((a, b) => a.distancia - b.distancia)
        .slice(0, MAX_DISTANCIAS_TODOS_ELEMENTOS);

    return {
        distancias,
        boundingBox: calcularBoundingBoxDistancias(distancias),
    };
}

function distanciasEntreBoxes(a, b) {
    const distancias = [];
    const boxA = a.boundingBox;
    const boxB = b.boundingBox;

    if (rangosSolapan(boxA.minY, boxA.maxY, boxB.minY, boxB.maxY)) {
        const y = centroRangoSolapado(boxA.minY, boxA.maxY, boxB.minY, boxB.maxY);

        if (boxA.maxX <= boxB.minX) {
            distancias.push(crearDistancia(boxA.maxX, y, boxB.minX, y, a.id, b.id));
        } else if (boxB.maxX <= boxA.minX) {
            distancias.push(crearDistancia(boxB.maxX, y, boxA.minX, y, a.id, b.id));
        }
    }

    if (rangosSolapan(boxA.minX, boxA.maxX, boxB.minX, boxB.maxX)) {
        const x = centroRangoSolapado(boxA.minX, boxA.maxX, boxB.minX, boxB.maxX);

        if (boxA.maxY <= boxB.minY) {
            distancias.push(crearDistancia(x, boxA.maxY, x, boxB.minY, a.id, b.id));
        } else if (boxB.maxY <= boxA.minY) {
            distancias.push(crearDistancia(x, boxB.maxY, x, boxA.minY, a.id, b.id));
        }
    }

    return distancias;
}

function crearDistancia(x1, y1, x2, y2, elementoAId = null, elementoBId = null) {
    const distancia = Math.hypot(x2 - x1, y2 - y1);

    return {
        distancia,
        etiqueta: `${formatear(distancia)} cm`,
        linea: {
            inicio: { x: x1, y: y1 },
            fin: { x: x2, y: y2 },
        },
        texto: {
            x: (x1 + x2) / 2,
            y: (y1 + y2) / 2,
        },
        clave: claveDistancia(elementoAId, elementoBId, x1, y1, x2, y2),
    };
}

function quitarDuplicadas(distancias) {
    const usadas = new Set();

    return distancias.filter((distancia) => {
        if (usadas.has(distancia.clave)) {
            return false;
        }

        usadas.add(distancia.clave);
        return true;
    });
}

function claveDistancia(elementoAId, elementoBId, x1, y1, x2, y2) {
    const ids = [elementoAId, elementoBId].filter(Boolean).sort().join('-');
    const puntos = [
        `${redondearClave(x1)},${redondearClave(y1)}`,
        `${redondearClave(x2)},${redondearClave(y2)}`,
    ].sort().join('|');

    return `${ids}:${puntos}`;
}

function redondearClave(valor) {
    return Math.round(valor * 1000) / 1000;
}

function calcularBoundingBoxDistancias(distancias) {
    if (distancias.length === 0) {
        return null;
    }

    const puntos = [];

    distancias.forEach((distancia) => {
        puntos.push(distancia.linea.inicio, distancia.linea.fin, distancia.texto);
    });

    return {
        minX: Math.min(...puntos.map((punto) => punto.x)),
        maxX: Math.max(...puntos.map((punto) => punto.x)),
        minY: Math.min(...puntos.map((punto) => punto.y)),
        maxY: Math.max(...puntos.map((punto) => punto.y)),
    };
}

function rangosSolapan(a1, a2, b1, b2) {
    return Math.max(Math.min(a1, a2), Math.min(b1, b2)) <= Math.min(Math.max(a1, a2), Math.max(b1, b2));
}

function centroRangoSolapado(a1, a2, b1, b2) {
    const inicio = Math.max(Math.min(a1, a2), Math.min(b1, b2));
    const fin = Math.min(Math.max(a1, a2), Math.max(b1, b2));

    return (inicio + fin) / 2;
}

function formatear(valor) {
    const redondeado = Math.round(valor * 10) / 10;
    return Number.isInteger(redondeado) ? String(redondeado) : String(redondeado).replace('.', ',');
}
