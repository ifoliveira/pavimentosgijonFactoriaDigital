export function calcularOrientacionPoligono(puntos, cerrado) {
    if (!cerrado || puntos.length < 4) {
        return 'abierto';
    }

    const areaDoble = puntos.slice(0, -1).reduce((area, punto, index) => {
        const siguiente = puntos[index + 1];
        return area + punto.x * siguiente.y - siguiente.x * punto.y;
    }, 0);

    return areaDoble >= 0 ? 'antihorario' : 'horario';
}

export function obtenerLineaInteriorMuro(muro, orientacionPoligono, centro = null) {
    const normal = calcularNormalInterior(muro, orientacionPoligono, centro);

    // Invariante Planos2D:
    // la geometria del muro representa siempre la cara interior terminada del recinto.
    // Cualquier grosor de muro es solo una representacion visual exterior y no desplaza
    // distancias, cotas, snap, puertas, ventanas ni posiciones longitudinales.
    return {
        ...muro,
        inicio: muro.inicio,
        fin: muro.fin,
        lineaInterior: muro,
        normalInterior: normal,
    };
}

export function calcularCentroBoundingBox(box) {
    return {
        x: (box.minX + box.maxX) / 2,
        y: (box.minY + box.maxY) / 2,
    };
}

function calcularNormalInterior(muro, orientacionPoligono, centro) {
    const tangente = calcularTangente(muro);
    const izquierda = { x: -tangente.y, y: tangente.x };
    const derecha = { x: tangente.y, y: -tangente.x };

    if (orientacionPoligono === 'antihorario') {
        return izquierda;
    }

    if (orientacionPoligono === 'horario') {
        return derecha;
    }

    if (!centro) {
        return izquierda;
    }

    const medio = {
        x: (muro.inicio.x + muro.fin.x) / 2,
        y: (muro.inicio.y + muro.fin.y) / 2,
    };
    const haciaCentro = {
        x: centro.x - medio.x,
        y: centro.y - medio.y,
    };

    return productoEscalar(izquierda, haciaCentro) >= productoEscalar(derecha, haciaCentro)
        ? izquierda
        : derecha;
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

function desplazar(punto, vector, distancia) {
    return {
        x: punto.x + vector.x * distancia,
        y: punto.y + vector.y * distancia,
    };
}

function productoEscalar(a, b) {
    return a.x * b.x + a.y * b.y;
}
