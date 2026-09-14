import { calcularBoundingBoxInterior, obtenerEsquinasInterior } from './interiores.js';
import { calcularCentroBoundingBox, calcularOrientacionPoligono, obtenerLineaInteriorMuro } from './muros.js';

const TOLERANCIA_LONGITUDINAL = 0.01;
const TOLERANCIA_CONTACTO = 0.001;

export function calcularDistanciasElemento(elemento, geometria) {
    if (!elemento) {
        return {
            distancias: [],
            referencias: { verticales: [], horizontales: [] },
            boundingBox: null,
        };
    }

    const box = calcularBoundingBoxInterior(elemento);
    const orientacion = calcularOrientacionPoligono(geometria.puntos, geometria.cierre.cerrado);
    const centro = calcularCentroBoundingBox(geometria.boundingBox);
    const lineasInteriores = geometria.segmentos.map((segmento) => (
        obtenerLineaInteriorMuro(segmento, orientacion, centro)
    ));
    const referencias = {
        verticales: calcularReferenciasVerticales(box, lineasInteriores),
        horizontales: calcularReferenciasHorizontales(box, lineasInteriores),
    };
    const distancias = [
        ...construirDistanciasPorLado(referencias.verticales, 'horizontal'),
        ...construirDistanciasPorLado(referencias.horizontales, 'vertical'),
    ];

    return {
        distancias,
        referencias,
        boundingBox: calcularBoundingBoxDistancias(distancias),
    };
}

export function distanciaPerpendicularElementoMuro(elemento, muro, geometria) {
    const box = calcularBoundingBoxInterior(elemento);
    const orientacion = calcularOrientacionPoligono(geometria.puntos, geometria.cierre.cerrado);
    const centro = calcularCentroBoundingBox(geometria.boundingBox);
    const lineaInterior = obtenerLineaInteriorMuro(muro, orientacion, centro);
    const referencias = esVertical(lineaInterior)
        ? calcularReferenciasVerticales(box, [lineaInterior])
        : calcularReferenciasHorizontales(box, [lineaInterior]);

    return referencias[0] ?? null;
}

export function distanciasLongitudinalesElementoEnMuro(elemento, muro) {
    const tangente = calcularTangente(muro);
    const proyecciones = obtenerEsquinasInterior(elemento).map((punto) => proyectarSobreMuro(punto, muro.inicio, tangente));
    const inicioProyectado = Math.min(...proyecciones);
    const finProyectado = Math.max(...proyecciones);
    const inicio = limpiarCasiCero(limitar(inicioProyectado, 0, muro.longitud));
    const finOcupacion = limpiarCasiCero(limitar(finProyectado, 0, muro.longitud));
    const ocupacion = limpiarCasiCero(Math.max(0, finOcupacion - inicio));
    const fin = limpiarCasiCero(Math.max(0, muro.longitud - finOcupacion));
    const suma = limpiarCasiCero(inicio + ocupacion + fin);

    return {
        inicio,
        ocupacion,
        fin,
        suma,
        longitudMuro: muro.longitud,
        valido: Math.abs(suma - muro.longitud) <= TOLERANCIA_LONGITUDINAL,
    };
}

function calcularReferenciasVerticales(box, segmentos) {
    return segmentos
        .filter(esVertical)
        .filter((segmento) => rangosSolapan(box.minY, box.maxY, segmento.inicio.y, segmento.fin.y))
        .flatMap((segmento) => {
            const xMuro = segmento.inicio.x;
            const y = limitar((Math.max(box.minY, Math.min(segmento.inicio.y, segmento.fin.y)) + Math.min(box.maxY, Math.max(segmento.inicio.y, segmento.fin.y))) / 2, box.minY, box.maxY);

            if (xMuro <= box.minX + TOLERANCIA_CONTACTO) {
                const distancia = limpiarContacto(box.minX - xMuro);
                return [crearReferencia(segmento, 'vertical', 'izquierda', distancia, { inicio: { x: xMuro, y }, fin: { x: box.minX, y } })];
            }

            if (xMuro >= box.maxX - TOLERANCIA_CONTACTO) {
                const distancia = limpiarContacto(xMuro - box.maxX);
                return [crearReferencia(segmento, 'vertical', 'derecha', distancia, { inicio: { x: box.maxX, y }, fin: { x: xMuro, y } })];
            }

            return [];
        })
        .sort((a, b) => a.distancia - b.distancia);
}

function calcularReferenciasHorizontales(box, segmentos) {
    return segmentos
        .filter(esHorizontal)
        .filter((segmento) => rangosSolapan(box.minX, box.maxX, segmento.inicio.x, segmento.fin.x))
        .flatMap((segmento) => {
            const yMuro = segmento.inicio.y;
            const x = limitar((Math.max(box.minX, Math.min(segmento.inicio.x, segmento.fin.x)) + Math.min(box.maxX, Math.max(segmento.inicio.x, segmento.fin.x))) / 2, box.minX, box.maxX);

            if (yMuro <= box.minY + TOLERANCIA_CONTACTO) {
                const distancia = limpiarContacto(box.minY - yMuro);
                return [crearReferencia(segmento, 'horizontal', 'abajo', distancia, { inicio: { x, y: yMuro }, fin: { x, y: box.minY } })];
            }

            if (yMuro >= box.maxY - TOLERANCIA_CONTACTO) {
                const distancia = limpiarContacto(yMuro - box.maxY);
                return [crearReferencia(segmento, 'horizontal', 'arriba', distancia, { inicio: { x, y: box.maxY }, fin: { x, y: yMuro } })];
            }

            return [];
        })
        .sort((a, b) => a.distancia - b.distancia);
}

function crearReferencia(segmento, orientacionMuro, lado, distancia, linea) {
    const segmentoOriginal = segmento.lineaInterior ?? segmento;

    return {
        id: `${orientacionMuro}-${segmentoOriginal.id}-${lado}`,
        muroId: segmentoOriginal.id,
        muroIndex: segmentoOriginal.index,
        orientacionMuro,
        lado,
        distancia,
        etiqueta: `${formatear(distancia)} cm`,
        linea,
        texto: puntoMedio(linea),
        lineaInterior: segmento,
        segmentoOriginal,
        segmento,
    };
}

function construirDistanciasPorLado(referencias, orientacion) {
    const ladosIncluidos = new Set();

    return referencias
        .filter((referencia) => referencia.distancia > TOLERANCIA_CONTACTO)
        .filter((referencia) => referencia.segmentoOriginal?.auxiliar !== true)
        .filter((referencia) => {
            if (ladosIncluidos.has(referencia.lado)) {
                return false;
            }

            ladosIncluidos.add(referencia.lado);
            return true;
        })
        .map((referencia) => ({
            orientacion,
            distancia: referencia.distancia,
            etiqueta: referencia.etiqueta,
            linea: referencia.linea,
            texto: referencia.texto,
            edicion: {
                tipo: 'distancia-pared',
                referenciaId: referencia.id,
                orientacionReferencia: referencia.orientacionMuro,
            },
        }));
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

function esVertical(segmento) {
    return Math.abs(segmento.inicio.x - segmento.fin.x) < 0.001;
}

function esHorizontal(segmento) {
    return Math.abs(segmento.inicio.y - segmento.fin.y) < 0.001;
}

function rangosSolapan(a1, a2, b1, b2) {
    return Math.max(Math.min(a1, a2), Math.min(b1, b2)) <= Math.min(Math.max(a1, a2), Math.max(b1, b2));
}

function limitar(valor, min, max) {
    return Math.min(max, Math.max(min, valor));
}

function calcularTangente(muro) {
    const dx = muro.fin.x - muro.inicio.x;
    const dy = muro.fin.y - muro.inicio.y;
    const longitud = Math.hypot(dx, dy) || 1;

    return {
        x: dx / longitud,
        y: dy / longitud,
    };
}

function proyectarSobreMuro(punto, origen, tangente) {
    return (punto.x - origen.x) * tangente.x + (punto.y - origen.y) * tangente.y;
}

function limpiarCasiCero(valor) {
    if (Math.abs(valor) < TOLERANCIA_LONGITUDINAL) {
        return 0;
    }

    return Math.round(valor * 1000) / 1000;
}

function limpiarContacto(valor) {
    return Math.abs(valor) <= TOLERANCIA_CONTACTO ? 0 : valor;
}

function puntoMedio(linea) {
    return {
        x: (linea.inicio.x + linea.fin.x) / 2,
        y: (linea.inicio.y + linea.fin.y) / 2,
    };
}

function formatear(valor) {
    return Number.isInteger(valor) ? String(valor) : String(Math.round(valor * 10) / 10);
}
