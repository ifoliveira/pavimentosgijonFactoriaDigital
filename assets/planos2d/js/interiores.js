export function calcularSistemaInteriores(plano) {
    const elementos = plano.interiores.map((elemento) => ({
        ...elemento,
        boundingBox: calcularBoundingBoxElemento(elemento),
    }));

    return {
        elementos,
        boundingBox: calcularBoundingBoxInteriores(elementos),
    };
}

export function posicionInicialInterior(geometria) {
    const box = geometria.boundingBox;

    return {
        x: (box.minX + box.maxX) / 2,
        y: (box.minY + box.maxY) / 2,
    };
}

function calcularBoundingBoxInteriores(elementos) {
    if (elementos.length === 0) {
        return null;
    }

    return {
        minX: Math.min(...elementos.map((elemento) => elemento.boundingBox.minX)),
        maxX: Math.max(...elementos.map((elemento) => elemento.boundingBox.maxX)),
        minY: Math.min(...elementos.map((elemento) => elemento.boundingBox.minY)),
        maxY: Math.max(...elementos.map((elemento) => elemento.boundingBox.maxY)),
    };
}

function calcularBoundingBoxElemento(elemento) {
    const puntos = esquinasElemento(elemento);

    return {
        minX: Math.min(...puntos.map((punto) => punto.x)),
        maxX: Math.max(...puntos.map((punto) => punto.x)),
        minY: Math.min(...puntos.map((punto) => punto.y)),
        maxY: Math.max(...puntos.map((punto) => punto.y)),
    };
}

export function calcularBoundingBoxInterior(elemento) {
    return calcularBoundingBoxElemento(elemento);
}

export function obtenerEsquinasInterior(elemento) {
    return esquinasElemento(elemento);
}

function esquinasElemento(elemento) {
    const medioAncho = elemento.ancho / 2;
    const medioFondo = elemento.fondo / 2;
    const angulo = elemento.rotacion * Math.PI / 180;
    const cos = Math.cos(angulo);
    const sin = Math.sin(angulo);

    return [
        { x: -medioAncho, y: -medioFondo },
        { x: medioAncho, y: -medioFondo },
        { x: medioAncho, y: medioFondo },
        { x: -medioAncho, y: medioFondo },
    ].map((punto) => ({
        x: elemento.x + punto.x * cos - punto.y * sin,
        y: elemento.y + punto.x * sin + punto.y * cos,
    }));
}
