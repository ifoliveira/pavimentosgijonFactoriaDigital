const SEPARACION_COTA = 8;

export function calcularDimensionesElementosPdf(sistemaInteriores) {
    if (!sistemaInteriores?.elementos?.length) {
        return { distancias: [], boundingBox: null };
    }

    const distancias = sistemaInteriores.elementos.flatMap((elemento) => {
        if (elemento.tipo === 'barra_ducha' || elemento.tipo === 'grifo_higienico') {
            return [];
        }

        if (elemento.tipo === 'radiador_toallero') {
            return [crearCotaAncho(elemento)];
        }

        return [
            crearCotaAncho(elemento),
            crearCotaFondo(elemento),
        ];
    });

    return {
        distancias,
        boundingBox: calcularBoundingBoxDistancias(distancias),
    };
}

function crearCotaAncho(elemento) {
    const y = -elemento.fondo / 2 - SEPARACION_COTA;
    const inicio = puntoElemento(elemento, -elemento.ancho / 2, y);
    const fin = puntoElemento(elemento, elemento.ancho / 2, y);

    return crearDistancia(elemento.ancho, inicio, fin, 'ancho');
}

function crearCotaFondo(elemento) {
    const x = -elemento.ancho / 2 - SEPARACION_COTA;
    const inicio = puntoElemento(elemento, x, -elemento.fondo / 2);
    const fin = puntoElemento(elemento, x, elemento.fondo / 2);

    return crearDistancia(elemento.fondo, inicio, fin, 'fondo');
}

function crearDistancia(valor, inicio, fin, eje) {
    return {
        distancia: valor,
        etiqueta: `${formatear(valor)} cm`,
        linea: { inicio, fin },
        texto: {
            x: (inicio.x + fin.x) / 2,
            y: (inicio.y + fin.y) / 2,
        },
        clase: 'is-element-size',
        eje,
    };
}

function puntoElemento(elemento, localX, localY) {
    const angulo = elemento.rotacion * Math.PI / 180;
    const cos = Math.cos(angulo);
    const sin = Math.sin(angulo);

    return {
        x: elemento.x + localX * cos - localY * sin,
        y: elemento.y + localX * sin + localY * cos,
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

function formatear(valor) {
    const redondeado = Math.round(valor * 10) / 10;

    return Number.isInteger(redondeado) ? String(redondeado) : String(redondeado).replace('.', ',');
}
