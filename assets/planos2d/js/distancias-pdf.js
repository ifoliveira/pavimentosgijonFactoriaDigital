import { calcularDistanciasElemento, distanciasLongitudinalesElementoEnMuro } from './distancias-elementos.js';

const MAX_DISTANCIAS_PARED_POR_ELEMENTO = 2;
const MAX_DISTANCIAS_PARED_PDF = 10;
const DISTANCIA_CERO = 0.001;
const SEPARACION_LONGITUDINAL = 10;

export function calcularDistanciasParedesPdf(sistemaInteriores, geometria) {
    if (!sistemaInteriores?.elementos?.length || !geometria?.segmentos?.length) {
        return { distancias: [], boundingBox: null };
    }

    const seleccionadas = sistemaInteriores.elementos
        .flatMap((elemento) => seleccionarDistanciasElemento(elemento, geometria))
        .sort((a, b) => a.prioridad - b.prioridad || a.distancia - b.distancia)
        .slice(0, MAX_DISTANCIAS_PARED_PDF)
        .map(({ prioridad, ...distancia }) => distancia);

    return {
        distancias: seleccionadas,
        boundingBox: calcularBoundingBoxDistancias(seleccionadas),
    };
}

export function combinarDistanciasPdf(...sistemas) {
    const distancias = sistemas.flatMap((sistema) => sistema?.distancias ?? []);

    return {
        distancias,
        boundingBox: calcularBoundingBoxDistancias(distancias),
    };
}

function seleccionarDistanciasElemento(elemento, geometria) {
    const sistema = calcularDistanciasElemento(elemento, geometria);
    const apoyadoEnPared = [...sistema.referencias.verticales, ...sistema.referencias.horizontales]
        .some((referencia) => referencia.distancia <= DISTANCIA_CERO);
    const candidatas = [
        ...(apoyadoEnPared ? [] : candidatasPerpendiculares(sistema.referencias.verticales, 'horizontal')),
        ...(apoyadoEnPared ? [] : candidatasPerpendiculares(sistema.referencias.horizontales, 'vertical')),
        ...candidatasLongitudinales(elemento, sistema, geometria),
    ]
        .filter((distancia) => distancia.distancia > DISTANCIA_CERO)
        .filter((distancia) => !cruzaElemento(distancia, elemento))
        .sort((a, b) => a.prioridad - b.prioridad || a.distancia - b.distancia);

    const seleccionadas = [];
    const orientaciones = new Set();

    candidatas.forEach((candidata) => {
        if (seleccionadas.length >= MAX_DISTANCIAS_PARED_POR_ELEMENTO) {
            return;
        }

        if (orientaciones.has(candidata.orientacion)) {
            return;
        }

        if (seleccionadas.some((seleccionada) => cotasRedundantes(seleccionada, candidata))) {
            return;
        }

        seleccionadas.push(candidata);
        orientaciones.add(candidata.orientacion);
    });

    return seleccionadas;
}

function candidatasPerpendiculares(referencias, orientacion) {
    return referencias
        .filter((referencia) => referencia.segmentoOriginal?.auxiliar !== true)
        .filter((referencia) => referencia.distancia > DISTANCIA_CERO)
        .slice(0, 2)
        .map((referencia, index) => ({
            orientacion,
            distancia: referencia.distancia,
            etiqueta: `${formatear(referencia.distancia)} cm`,
            linea: referencia.linea,
            texto: referencia.texto,
            clase: 'is-to-wall',
            prioridad: 20 + index + referencia.distancia / 1000,
            tipoPdf: 'perpendicular-pared',
            muroId: referencia.muroId,
            lado: referencia.lado,
        }));
}

function candidatasLongitudinales(elemento, sistema, geometria) {
    return [...sistema.referencias.verticales, ...sistema.referencias.horizontales]
        .filter((referencia) => referencia.segmentoOriginal?.auxiliar !== true)
        .filter((referencia) => referencia.distancia <= DISTANCIA_CERO)
        .flatMap((referencia) => construirLongitudinales(elemento, referencia, geometria))
        .sort((a, b) => a.distancia - b.distancia)
        .slice(0, 2);
}

function construirLongitudinales(elemento, referencia, geometria) {
    const muro = geometria.segmentos.find((segmento) => segmento.id === referencia.muroId);

    if (!muro) {
        return [];
    }

    const longitudinal = distanciasLongitudinalesElementoEnMuro(elemento, muro);
    const tangente = calcularTangente(muro);
    const normalInterior = referencia.segmento?.normalInterior ?? { x: 0, y: 0 };
    const baseInicio = desplazar(muro.inicio, normalInterior, SEPARACION_LONGITUDINAL);
    const baseFin = desplazar(muro.fin, normalInterior, SEPARACION_LONGITUDINAL);
    if (longitudinal.inicio <= DISTANCIA_CERO || longitudinal.fin <= DISTANCIA_CERO) {
        return [];
    }

    if (longitudinal.inicio <= longitudinal.fin) {
        const inicio = baseInicio;
        const fin = desplazar(baseInicio, tangente, longitudinal.inicio);
        return [crearDistanciaLongitudinal(inicio, fin, longitudinal.inicio, referencia, 'inicio')];
    }

    const fin = baseFin;
    const inicio = desplazar(baseFin, tangente, -longitudinal.fin);
    return [crearDistanciaLongitudinal(inicio, fin, longitudinal.fin, referencia, 'fin')];
}

function crearDistanciaLongitudinal(inicio, fin, distancia, referencia, extremo) {
    return {
        orientacion: Math.abs(inicio.y - fin.y) < 0.001 ? 'horizontal' : 'vertical',
        distancia,
        etiqueta: `${formatear(distancia)} cm`,
        linea: { inicio, fin },
        texto: puntoMedio({ inicio, fin }),
        clase: 'is-to-wall',
        prioridad: 10 + distancia / 1000,
        tipoPdf: 'longitudinal-pared',
        muroId: referencia.muroId,
        lado: referencia.lado,
        extremo,
    };
}

function cotasRedundantes(a, b) {
    return a.tipoPdf === b.tipoPdf
        && a.muroId === b.muroId
        && a.orientacion === b.orientacion;
}

function cruzaElemento(distancia, elemento) {
    const box = elemento.boundingBox;

    if (!box) {
        return false;
    }

    const cajaTexto = cajaEtiqueta(distancia.texto, distancia.etiqueta);

    return lineaCruzaCaja(distancia.linea, box) || cajasSolapan(cajaTexto, ampliarCaja(box, 2));
}

function lineaCruzaCaja(linea, caja) {
    const margen = 0.01;

    if (Math.abs(linea.inicio.y - linea.fin.y) < 0.001) {
        const y = linea.inicio.y;
        return y > caja.minY + margen
            && y < caja.maxY - margen
            && rangosSolapanInterior(linea.inicio.x, linea.fin.x, caja.minX, caja.maxX, margen);
    }

    if (Math.abs(linea.inicio.x - linea.fin.x) < 0.001) {
        const x = linea.inicio.x;
        return x > caja.minX + margen
            && x < caja.maxX - margen
            && rangosSolapanInterior(linea.inicio.y, linea.fin.y, caja.minY, caja.maxY, margen);
    }

    return false;
}

function cajaEtiqueta(posicion, etiqueta) {
    const texto = String(etiqueta).replace(/\s*cm\s*$/i, '');
    const ancho = Math.max(10, texto.length * 3.1 + 5);
    const alto = 5.5;

    return {
        minX: posicion.x - ancho / 2,
        maxX: posicion.x + ancho / 2,
        minY: posicion.y - alto / 2,
        maxY: posicion.y + alto / 2,
    };
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

function calcularTangente(segmento) {
    const dx = segmento.fin.x - segmento.inicio.x;
    const dy = segmento.fin.y - segmento.inicio.y;
    const longitud = Math.hypot(dx, dy) || 1;

    return { x: dx / longitud, y: dy / longitud };
}

function desplazar(punto, vector, distancia) {
    return {
        x: punto.x + vector.x * distancia,
        y: punto.y + vector.y * distancia,
    };
}

function puntoMedio(linea) {
    return {
        x: (linea.inicio.x + linea.fin.x) / 2,
        y: (linea.inicio.y + linea.fin.y) / 2,
    };
}

function rangosSolapan(a1, a2, b1, b2) {
    return Math.max(Math.min(a1, a2), Math.min(b1, b2)) <= Math.min(Math.max(a1, a2), Math.max(b1, b2));
}

function rangosSolapanInterior(a1, a2, b1, b2, margen) {
    return Math.max(Math.min(a1, a2), Math.min(b1, b2) + margen)
        < Math.min(Math.max(a1, a2), Math.max(b1, b2) - margen);
}

function cajasSolapan(a, b) {
    return a.minX <= b.maxX && a.maxX >= b.minX && a.minY <= b.maxY && a.maxY >= b.minY;
}

function ampliarCaja(caja, margen) {
    return {
        minX: caja.minX - margen,
        maxX: caja.maxX + margen,
        minY: caja.minY - margen,
        maxY: caja.maxY + margen,
    };
}

function formatear(valor) {
    const redondeado = Math.round(valor * 10) / 10;
    return Number.isInteger(redondeado) ? String(redondeado) : String(redondeado).replace('.', ',');
}
