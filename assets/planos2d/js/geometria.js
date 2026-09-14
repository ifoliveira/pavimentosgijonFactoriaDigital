export const DIRECCIONES = {
    arriba: { dx: 0, dy: 1, simbolo: '↑' },
    derecha: { dx: 1, dy: 0, simbolo: '→' },
    abajo: { dx: 0, dy: -1, simbolo: '↓' },
    izquierda: { dx: -1, dy: 0, simbolo: '←' },
};

export function calcularGeometria(plano) {
    const puntos = [{ x: 0, y: 0 }];
    const segmentos = [];
    let actual = puntos[0];

    plano.muros.forEach((muro, index) => {
        const vector = DIRECCIONES[muro.direccion];

        if (!vector) {
            throw new Error(`Direccion no soportada: ${muro.direccion}`);
        }

        const fin = {
            x: actual.x + vector.dx * muro.longitud,
            y: actual.y + vector.dy * muro.longitud,
        };

        segmentos.push({
            id: muro.id,
            index,
            longitud: muro.longitud,
            direccion: muro.direccion,
            auxiliar: muro.auxiliar === true,
            inicio: actual,
            fin,
        });

        puntos.push(fin);
        actual = fin;
    });

    return {
        puntos,
        segmentos,
        boundingBox: calcularBoundingBox(puntos),
        cierre: calcularCierre(puntos),
    };
}

export function calcularBoundingBox(puntos) {
    const xs = puntos.map((punto) => punto.x);
    const ys = puntos.map((punto) => punto.y);

    return {
        minX: Math.min(...xs),
        maxX: Math.max(...xs),
        minY: Math.min(...ys),
        maxY: Math.max(...ys),
    };
}

export function calcularCierre(puntos) {
    const inicio = puntos[0] ?? { x: 0, y: 0 };
    const fin = puntos[puntos.length - 1] ?? inicio;
    const deltaX = inicio.x - fin.x;
    const deltaY = inicio.y - fin.y;

    return {
        cerrado: deltaX === 0 && deltaY === 0,
        deltaX,
        deltaY,
    };
}

export function describirCierre(cierre) {
    if (cierre.cerrado) {
        return 'Perimetro cerrado correctamente.';
    }

    const partes = [];

    if (cierre.deltaX !== 0) {
        partes.push(`${Math.abs(cierre.deltaX)} cm hacia ${cierre.deltaX > 0 ? 'la derecha' : 'la izquierda'}`);
    }

    if (cierre.deltaY !== 0) {
        partes.push(`${Math.abs(cierre.deltaY)} cm hacia ${cierre.deltaY > 0 ? 'arriba' : 'abajo'}`);
    }

    return `El perimetro todavia no esta cerrado. Faltan ${partes.join(' y ')}.`;
}
