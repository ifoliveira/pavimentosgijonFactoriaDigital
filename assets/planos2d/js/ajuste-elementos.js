import { calcularDistanciasElemento } from './distancias-elementos.js';
import { calcularBoundingBoxInterior, obtenerEsquinasInterior } from './interiores.js';

const OBJETIVOS_SNAP = [0, 5, 10, 15, 20, 25, 30];
const TOLERANCIA_SNAP = 1.5;

export function moverAReferencia(elemento, referencia, distancia, geometria) {
    const posicion = posicionParaDistancia(elemento, referencia, Number(distancia));

    if (!posicion || !posicionValida({ ...elemento, ...posicion }, geometria)) {
        return null;
    }

    return posicion;
}

export function aplicarSnap(elemento, posicion, geometria) {
    let ajustado = { ...elemento, ...posicion };
    const sistema = calcularDistanciasElemento(ajustado, geometria);
    const referencias = [
        sistema.referencias.verticales[0],
        sistema.referencias.horizontales[0],
    ].filter(Boolean);

    referencias.forEach((referencia) => {
        const objetivo = OBJETIVOS_SNAP.find((valor) => Math.abs(referencia.distancia - valor) <= TOLERANCIA_SNAP);

        if (objetivo === undefined) {
            return;
        }

        const posicionSnap = posicionParaDistancia(ajustado, referencia, objetivo);

        if (posicionSnap && posicionValida({ ...ajustado, ...posicionSnap }, geometria)) {
            ajustado = { ...ajustado, ...posicionSnap };
        }
    });

    return { x: ajustado.x, y: ajustado.y };
}

function posicionParaDistancia(elemento, referencia, distancia) {
    if (!Number.isFinite(distancia) || distancia < 0 || !referencia) {
        return null;
    }

    const box = calcularBoundingBoxInterior(elemento);
    const lineaReferencia = referencia.lineaInterior ?? referencia.segmento;

    if (referencia.orientacionMuro === 'vertical') {
        const xMuro = lineaReferencia.inicio.x;
        const izquierdaCentro = elemento.x - box.minX;
        const derechaCentro = box.maxX - elemento.x;

        if (referencia.lado === 'izquierda') {
            return { x: xMuro + distancia + izquierdaCentro, y: elemento.y };
        }

        return { x: xMuro - distancia - derechaCentro, y: elemento.y };
    }

    const yMuro = lineaReferencia.inicio.y;
    const abajoCentro = elemento.y - box.minY;
    const arribaCentro = box.maxY - elemento.y;

    if (referencia.lado === 'abajo') {
        return { x: elemento.x, y: yMuro + distancia + abajoCentro };
    }

    return { x: elemento.x, y: yMuro - distancia - arribaCentro };
}

function posicionValida(elemento, geometria) {
    if (!geometria.cierre?.cerrado) {
        return true;
    }

    return obtenerEsquinasInterior(elemento).every((punto) => (
        puntoDentroPoligono(punto, geometria.puntos) || puntoEnBordePoligono(punto, geometria.puntos)
    ));
}

function puntoDentroPoligono(punto, puntos) {
    if (puntos.length < 4) {
        return true;
    }

    let dentro = false;

    for (let i = 0, j = puntos.length - 1; i < puntos.length; j = i, i += 1) {
        const pi = puntos[i];
        const pj = puntos[j];
        const intersecta = ((pi.y > punto.y) !== (pj.y > punto.y))
            && (punto.x < (pj.x - pi.x) * (punto.y - pi.y) / ((pj.y - pi.y) || 1) + pi.x);

        if (intersecta) {
            dentro = !dentro;
        }
    }

    return dentro;
}

function puntoEnBordePoligono(punto, puntos) {
    for (let i = 0; i < puntos.length - 1; i += 1) {
        if (distanciaAPuntoSegmento(punto, puntos[i], puntos[i + 1]) <= 0.001) {
            return true;
        }
    }

    return false;
}

function distanciaAPuntoSegmento(punto, a, b) {
    const dx = b.x - a.x;
    const dy = b.y - a.y;
    const longitud2 = dx * dx + dy * dy;

    if (longitud2 === 0) {
        return Math.hypot(punto.x - a.x, punto.y - a.y);
    }

    const t = Math.max(0, Math.min(1, ((punto.x - a.x) * dx + (punto.y - a.y) * dy) / longitud2));
    const proyeccion = {
        x: a.x + t * dx,
        y: a.y + t * dy,
    };

    return Math.hypot(punto.x - proyeccion.x, punto.y - proyeccion.y);
}
