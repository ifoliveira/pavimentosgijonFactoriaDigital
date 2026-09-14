import { calcularCentroBoundingBox, calcularOrientacionPoligono, obtenerLineaInteriorMuro } from './muros.js';

export const TIPOS_MURALES = ['barra_ducha', 'grifo_higienico', 'radiador_toallero'];

export function esElementoMural(elemento) {
    return Boolean(elemento && TIPOS_MURALES.includes(elemento.tipo));
}

export function ajustarMuralAReferencia(elemento, referencia, geometria) {
    if (!esElementoMural(elemento) || !referencia?.muroId) {
        return null;
    }

    return ajustarMuralAMuro(elemento, geometria, referencia.muroId, elemento);
}

export function ajustarMuralAMuro(elemento, geometria, muroId, posicionPreferida = elemento) {
    const muro = geometria.segmentos.find((segmento) => segmento.id === muroId);

    if (!muro) {
        return null;
    }

    const tangente = calcularTangente(muro);
    const normalInterior = obtenerNormalInterior(muro, geometria);
    const distanciaSobreMuro = limitar(
        proyectar(posicionPreferida, muro.inicio, tangente),
        elemento.ancho / 2,
        Math.max(elemento.ancho / 2, muro.longitud - elemento.ancho / 2),
    );
    const puntoMuro = desplazar(muro.inicio, tangente, distanciaSobreMuro);
    const centro = desplazar(puntoMuro, normalInterior, elemento.fondo / 2);

    return {
        x: centro.x,
        y: centro.y,
        rotacion: rotacionDesdeNormalInterior(normalInterior),
        muroId,
    };
}

function obtenerNormalInterior(muro, geometria) {
    const orientacion = calcularOrientacionPoligono(geometria.puntos, geometria.cierre.cerrado);
    const centro = calcularCentroBoundingBox(geometria.boundingBox);
    const lineaInterior = obtenerLineaInteriorMuro(muro, orientacion, centro);

    return lineaInterior.normalInterior;
}

function rotacionDesdeNormalInterior(normal) {
    if (Math.abs(normal.x) > Math.abs(normal.y)) {
        return normal.x > 0 ? 90 : 270;
    }

    return normal.y > 0 ? 180 : 0;
}

function calcularTangente(segmento) {
    const dx = segmento.fin.x - segmento.inicio.x;
    const dy = segmento.fin.y - segmento.inicio.y;
    const longitud = Math.hypot(dx, dy) || 1;

    return { x: dx / longitud, y: dy / longitud };
}

function proyectar(punto, origen, tangente) {
    return (punto.x - origen.x) * tangente.x + (punto.y - origen.y) * tangente.y;
}

function desplazar(punto, vector, distancia) {
    return {
        x: punto.x + vector.x * distancia,
        y: punto.y + vector.y * distancia,
    };
}

function limitar(valor, min, max) {
    return Math.min(max, Math.max(min, valor));
}
