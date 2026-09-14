export function calcularSistemaElementos(plano, geometria) {
    const segmentosPorId = new Map(geometria.segmentos.map((segmento) => [segmento.id, segmento]));
    const exteriorPorMuro = calcularNormalesExteriores(geometria);
    const elementos = plano.elementos
        .map((elemento) => calcularElemento(elemento, segmentosPorId.get(elemento.muroId), exteriorPorMuro.get(elemento.muroId)))
        .filter(Boolean);

    return {
        elementos,
        huecosPorMuro: calcularHuecosPorMuro(elementos),
        boundingBox: calcularBoundingBoxElementos(elementos),
        calcularTramosVisibles,
    };
}

export function calcularTramosVisibles(segmento, huecos = []) {
    const ordenados = huecos
        .filter((hueco) => hueco.inicio < segmento.longitud && hueco.fin > 0)
        .map((hueco) => ({
            inicio: Math.max(0, hueco.inicio),
            fin: Math.min(segmento.longitud, hueco.fin),
        }))
        .sort((a, b) => a.inicio - b.inicio);
    const tramos = [];
    let cursor = 0;

    ordenados.forEach((hueco) => {
        if (hueco.inicio > cursor) {
            tramos.push({ inicio: cursor, fin: hueco.inicio });
        }
        cursor = Math.max(cursor, hueco.fin);
    });

    if (cursor < segmento.longitud) {
        tramos.push({ inicio: cursor, fin: segmento.longitud });
    }

    return tramos.map((tramo) => ({
        inicio: puntoEnSegmento(segmento, tramo.inicio),
        fin: puntoEnSegmento(segmento, tramo.fin),
    }));
}

function calcularElemento(elemento, segmento, exterior) {
    if (!segmento || !exterior) {
        return null;
    }

    const tangente = calcularTangente(segmento);
    const inicioHueco = puntoEnSegmento(segmento, elemento.distanciaDesdeInicio);
    const finHueco = puntoEnSegmento(segmento, elemento.distanciaDesdeInicio + elemento.ancho);
    const lado = elemento.ladoApertura === 'exterior' ? exterior : { x: -exterior.x, y: -exterior.y };
    const base = {
        ...elemento,
        segmento,
        tangente,
        normalExterior: exterior,
        inicioHueco,
        finHueco,
        hueco: {
            id: elemento.id,
            tipo: elemento.tipo,
            muroId: elemento.muroId,
            inicio: elemento.distanciaDesdeInicio,
            fin: elemento.distanciaDesdeInicio + elemento.ancho,
        },
    };

    if (elemento.tipo === 'puerta') {
        return {
            ...base,
            hoja: calcularHojaPuerta(elemento, inicioHueco, finHueco, lado),
        };
    }

    return {
        ...base,
        lineasVentana: calcularLineasVentana(inicioHueco, finHueco, exterior),
    };
}

function calcularHojaPuerta(elemento, inicioHueco, finHueco, lado) {
    const abreDerecha = elemento.sentidoApertura === 'derecha';
    const bisagra = abreDerecha ? inicioHueco : finHueco;
    const extremoMuro = abreDerecha ? finHueco : inicioHueco;
    const extremoHoja = {
        x: bisagra.x + lado.x * elemento.ancho,
        y: bisagra.y + lado.y * elemento.ancho,
    };

    return {
        bisagra,
        extremoMuro,
        extremoHoja,
        radio: elemento.ancho,
    };
}

function calcularLineasVentana(inicioHueco, finHueco, exterior) {
    const separacion = 4;
    const normalInterior = { x: -exterior.x, y: -exterior.y };

    return [
        {
            inicio: desplazar(inicioHueco, exterior, separacion),
            fin: desplazar(finHueco, exterior, separacion),
        },
        {
            inicio: desplazar(inicioHueco, normalInterior, separacion),
            fin: desplazar(finHueco, normalInterior, separacion),
        },
    ];
}

function calcularHuecosPorMuro(elementos) {
    const huecos = new Map();

    elementos.forEach((elemento) => {
        if (!huecos.has(elemento.muroId)) {
            huecos.set(elemento.muroId, []);
        }

        huecos.get(elemento.muroId).push(elemento.hueco);
    });

    return huecos;
}

function calcularNormalesExteriores(geometria) {
    const orientacion = calcularOrientacionPoligono(geometria.puntos, geometria.cierre.cerrado);
    const centro = calcularCentro(geometria.boundingBox);
    const normales = new Map();

    geometria.segmentos.forEach((segmento) => {
        normales.set(segmento.id, calcularNormalExterior(segmento, orientacion, centro));
    });

    return normales;
}

function calcularNormalExterior(segmento, orientacion, centro) {
    const tangente = calcularTangente(segmento);
    const izquierda = { x: -tangente.y, y: tangente.x };
    const derecha = { x: tangente.y, y: -tangente.x };

    if (orientacion === 'antihorario') {
        return derecha;
    }

    if (orientacion === 'horario') {
        return izquierda;
    }

    const medio = {
        x: (segmento.inicio.x + segmento.fin.x) / 2,
        y: (segmento.inicio.y + segmento.fin.y) / 2,
    };
    const vectorDesdeCentro = {
        x: medio.x - centro.x,
        y: medio.y - centro.y,
    };

    return productoEscalar(izquierda, vectorDesdeCentro) >= productoEscalar(derecha, vectorDesdeCentro)
        ? izquierda
        : derecha;
}

function calcularOrientacionPoligono(puntos, cerrado) {
    if (!cerrado || puntos.length < 4) {
        return 'abierto';
    }

    const areaDoble = puntos.slice(0, -1).reduce((area, punto, index) => {
        const siguiente = puntos[index + 1];
        return area + punto.x * siguiente.y - siguiente.x * punto.y;
    }, 0);

    return areaDoble >= 0 ? 'antihorario' : 'horario';
}

function calcularBoundingBoxElementos(elementos) {
    const puntos = [];

    elementos.forEach((elemento) => {
        puntos.push(elemento.inicioHueco, elemento.finHueco);

        if (elemento.tipo === 'puerta') {
            puntos.push(elemento.hoja.bisagra, elemento.hoja.extremoMuro, elemento.hoja.extremoHoja);
        }

        if (elemento.tipo === 'ventana') {
            elemento.lineasVentana.forEach((linea) => puntos.push(linea.inicio, linea.fin));
        }
    });

    if (puntos.length === 0) {
        return null;
    }

    return {
        minX: Math.min(...puntos.map((punto) => punto.x)),
        maxX: Math.max(...puntos.map((punto) => punto.x)),
        minY: Math.min(...puntos.map((punto) => punto.y)),
        maxY: Math.max(...puntos.map((punto) => punto.y)),
    };
}

function puntoEnSegmento(segmento, distancia) {
    const tangente = calcularTangente(segmento);

    return {
        x: segmento.inicio.x + tangente.x * distancia,
        y: segmento.inicio.y + tangente.y * distancia,
    };
}

function calcularTangente(segmento) {
    const dx = segmento.fin.x - segmento.inicio.x;
    const dy = segmento.fin.y - segmento.inicio.y;
    const longitud = Math.hypot(dx, dy) || 1;

    return {
        x: dx / longitud,
        y: dy / longitud,
    };
}

function desplazar(punto, vector, distancia) {
    return {
        x: punto.x + vector.x * distancia,
        y: punto.y + vector.y * distancia,
    };
}

function calcularCentro(box) {
    return {
        x: (box.minX + box.maxX) / 2,
        y: (box.minY + box.maxY) / 2,
    };
}

function productoEscalar(a, b) {
    return a.x * b.x + a.y * b.y;
}
