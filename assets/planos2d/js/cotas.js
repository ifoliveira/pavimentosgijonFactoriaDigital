const COTA_BASE = 24;
const COTA_NIVEL = 22;
const COTA_TICK = 9;
const TEXTO_ALTO = 16;
const TEXTO_PADDING = 7;
const TEXTO_MIN_ANCHO = 42;
const TEXTO_MARGEN_INTERIOR = 10;
const COLISION_MARGEN = 7;
const MAX_NIVELES = 10;

export function calcularCotas(geometria, sistemaElementos = null) {
    const orientacion = calcularOrientacionPoligono(geometria.puntos, geometria.cierre.cerrado);
    const centro = calcularCentro(geometria.boundingBox);
    const perimetro = geometria.segmentos.map((segmento) => ({
        inicio: segmento.inicio,
        fin: segmento.fin,
        segmentoIndex: segmento.index,
        auxiliar: segmento.auxiliar === true,
    }));
    const cotasAceptadas = [];
    const cotasParciales = [];
    const normalesPorMuro = new Map();

    geometria.segmentos.forEach((segmento) => {
        normalesPorMuro.set(segmento.id, calcularNormalExterior(segmento, orientacion, centro));
    });

    geometria.segmentos.forEach((segmento) => {
        if (segmento.auxiliar) {
            return;
        }

        const huecos = sistemaElementos?.huecosPorMuro?.get(segmento.id) ?? [];

        if (huecos.length === 0) {
            return;
        }

        const normal = normalesPorMuro.get(segmento.id);
        const parcialesMuro = construirCotasParciales(segmento, normal, huecos);

        parcialesMuro.forEach((cota) => {
            cotasParciales.push(cota);
        });
    });

    const cotas = [];
    const segmentosOrdenados = [...geometria.segmentos].sort((a, b) => {
        const aCorta = esCotaCorta(a, `${a.longitud} cm`);
        const bCorta = esCotaCorta(b, `${b.longitud} cm`);

        if (aCorta !== bCorta) {
            return aCorta ? 1 : -1;
        }

        return b.longitud - a.longitud;
    });

    segmentosOrdenados.forEach((segmento) => {
        if (segmento.auxiliar) {
            return;
        }

        const normal = normalesPorMuro.get(segmento.id);
        const etiqueta = `${segmento.longitud} cm`;
        const tieneHuecos = (sistemaElementos?.huecosPorMuro?.get(segmento.id) ?? []).length > 0;
        const cota = seleccionarCota(
            segmento,
            normal,
            etiqueta,
            cotasAceptadas,
            perimetro,
            tieneHuecos ? 1 : 0,
            { tipo: 'muro-longitud', muroId: segmento.id, segmentoIndex: segmento.index }
        );

        cotasAceptadas.push(cota);
        cotas[segmento.index] = cota;
    });

    return {
        cotas: [...cotas.filter(Boolean), ...cotasParciales],
        boundingBox: combinarBoundingBoxes([
            geometria.boundingBox,
            calcularBoundingBoxCotas([...cotas.filter(Boolean), ...cotasParciales]),
        ]),
    };
}

function esCotaCorta(segmento, etiqueta) {
    return segmento.longitud < estimarAnchoTexto(etiqueta) + TEXTO_MARGEN_INTERIOR * 2;
}

function seleccionarCota(segmento, normal, etiqueta, cotasAceptadas, perimetro, nivelMinimo = 0, edicion = null) {
    for (let nivel = nivelMinimo; nivel < MAX_NIVELES; nivel += 1) {
        const cota = construirCota(segmento, normal, etiqueta, nivel, false, edicion);

        if (!cotaColisiona(cota, cotasAceptadas, perimetro)) {
            return cota;
        }
    }

    return construirCota(segmento, normal, etiqueta, MAX_NIVELES - 1, false, edicion);
}

function construirCotasParciales(segmento, normal, huecos) {
    return calcularIntervalosParciales(segmento, huecos)
        .map((intervalo) => construirCota(
            construirSubsegmento(segmento, intervalo.inicio, intervalo.fin),
            normal,
            `${formatearCentimetros(intervalo.fin - intervalo.inicio)} cm`,
            0,
            true,
            intervalo.edicion,
        ));
}

function calcularIntervalosParciales(segmento, huecos) {
    const ordenados = huecos
        .map((hueco) => ({
            id: hueco.id,
            muroId: hueco.muroId,
            inicio: Math.max(0, Math.min(segmento.longitud, hueco.inicio)),
            fin: Math.max(0, Math.min(segmento.longitud, hueco.fin)),
        }))
        .filter((hueco) => hueco.fin > hueco.inicio)
        .sort((a, b) => a.inicio - b.inicio);
    const intervalos = [];
    let cursor = 0;

    ordenados.forEach((hueco) => {
        if (hueco.inicio > cursor) {
            intervalos.push({
                inicio: cursor,
                fin: hueco.inicio,
                edicion: {
                    tipo: 'hueco-distancia-inicio',
                    elementoId: hueco.id,
                    muroId: hueco.muroId,
                },
            });
        }

        if (hueco.fin > cursor) {
            intervalos.push({
                inicio: Math.max(cursor, hueco.inicio),
                fin: hueco.fin,
                edicion: {
                    tipo: 'hueco-ancho',
                    elementoId: hueco.id,
                    muroId: hueco.muroId,
                },
            });
        }

        cursor = Math.max(cursor, hueco.fin);
    });

    if (cursor < segmento.longitud) {
        const ultimoHueco = ordenados.at(-1);
        intervalos.push({
            inicio: cursor,
            fin: segmento.longitud,
            edicion: ultimoHueco ? {
                tipo: 'hueco-distancia-final',
                elementoId: ultimoHueco.id,
                muroId: ultimoHueco.muroId,
                muroLongitud: segmento.longitud,
                ancho: ultimoHueco.fin - ultimoHueco.inicio,
            } : null,
        });
    }

    return intervalos.filter((intervalo) => intervalo.fin > intervalo.inicio);
}

function construirSubsegmento(segmento, inicio, fin) {
    return {
        ...segmento,
        longitud: fin - inicio,
        inicio: puntoEnSegmento(segmento, inicio),
        fin: puntoEnSegmento(segmento, fin),
    };
}

function construirCota(segmento, normal, etiqueta, nivel, parcial = false, edicion = null) {
    const distancia = COTA_BASE + COTA_NIVEL * nivel;
    const tangente = calcularTangente(segmento);
    const medio = puntoMedio(segmento);
    const semiLongitud = segmento.longitud / 2;
    const centroLinea = desplazar(medio, normal, distancia);
    const linea = {
        inicio: desplazar(centroLinea, tangente, -semiLongitud),
        fin: desplazar(centroLinea, tangente, semiLongitud),
    };
    const texto = {
        posicion: centroLinea,
        caja: construirCajaDesdeCentro(centroLinea, etiqueta),
    };

    return {
        segmentoIndex: segmento.index,
        etiqueta,
        orientacion: segmento.direccion === 'arriba' || segmento.direccion === 'abajo' ? 'vertical' : 'horizontal',
        nivel,
        parcial,
        edicion,
        linea,
        auxiliares: [
            {
                inicio: desplazar(segmento.inicio, normal, COTA_TICK),
                fin: desplazar(segmento.inicio, normal, distancia + COTA_TICK),
            },
            {
                inicio: desplazar(segmento.fin, normal, COTA_TICK),
                fin: desplazar(segmento.fin, normal, distancia + COTA_TICK),
            },
        ],
        texto,
    };
}

function puntoEnSegmento(segmento, distancia) {
    const tangente = calcularTangente(segmento);

    return {
        x: segmento.inicio.x + tangente.x * distancia,
        y: segmento.inicio.y + tangente.y * distancia,
    };
}

function formatearCentimetros(valor) {
    return Number.isInteger(valor) ? String(valor) : String(Number(valor.toFixed(2)));
}

function cotaColisiona(cota, cotasAceptadas, perimetro) {
    const primitivas = primitivasCota(cota);

    if (colisionaConPerimetro(cota, primitivas, perimetro)) {
        return true;
    }

    return cotasAceptadas.some((aceptada) => primitivasColisionan(primitivas, primitivasCota(aceptada)));
}

function primitivasCota(cota) {
    return {
        linea: cota.linea,
        auxiliares: cota.auxiliares,
        cajas: [cota.texto.caja],
    };
}

function primitivasColisionan(a, b) {
    return a.cajas.some((cajaA) => b.cajas.some((cajaB) => cajasSolapan(cajaA, cajaB)))
        || lineasColisionan(a.linea, b.linea)
        || b.cajas.some((caja) => lineaColisionaCaja(a.linea, caja))
        || a.cajas.some((caja) => lineaColisionaCaja(b.linea, caja))
        || a.auxiliares.some((linea) => b.cajas.some((caja) => lineaColisionaCaja(linea, caja)))
        || b.auxiliares.some((linea) => a.cajas.some((caja) => lineaColisionaCaja(linea, caja)));
}

function colisionaConPerimetro(cota, primitivas, perimetro) {
    return primitivas.cajas.some((caja) => perimetro.some((muro) => {
        if (muro.segmentoIndex === cota.segmentoIndex) {
            return false;
        }

        return lineaColisionaCaja(muro, caja);
    }))
        || perimetro.some((muro) => {
            if (muro.segmentoIndex === cota.segmentoIndex) {
                return false;
            }

            return lineasColisionan(cota.linea, muro);
        });
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

    const medio = puntoMedio(segmento);
    const vectorDesdeCentro = {
        x: medio.x - centro.x,
        y: medio.y - centro.y,
    };

    return productoEscalar(izquierda, vectorDesdeCentro) >= productoEscalar(derecha, vectorDesdeCentro)
        ? izquierda
        : derecha;
}

function calcularBoundingBoxCotas(cotas) {
    const puntos = [];

    cotas.forEach((cota) => {
        puntos.push(cota.linea.inicio, cota.linea.fin);
        cota.auxiliares.forEach((auxiliar) => puntos.push(auxiliar.inicio, auxiliar.fin));
        puntos.push(
            { x: cota.texto.caja.minX, y: cota.texto.caja.minY },
            { x: cota.texto.caja.maxX, y: cota.texto.caja.maxY },
        );
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

function combinarBoundingBoxes(boxes) {
    const validos = boxes.filter(Boolean);

    return {
        minX: Math.min(...validos.map((box) => box.minX)),
        maxX: Math.max(...validos.map((box) => box.maxX)),
        minY: Math.min(...validos.map((box) => box.minY)),
        maxY: Math.max(...validos.map((box) => box.maxY)),
    };
}

function cajasSolapan(a, b) {
    return !(
        a.maxX + COLISION_MARGEN < b.minX
        || a.minX - COLISION_MARGEN > b.maxX
        || a.maxY + COLISION_MARGEN < b.minY
        || a.minY - COLISION_MARGEN > b.maxY
    );
}

function lineasColisionan(a, b) {
    const cajaA = cajaLinea(a);
    const cajaB = cajaLinea(b);

    if (!cajasSolapan(cajaA, cajaB)) {
        return false;
    }

    if (esHorizontal(a) && esHorizontal(b)) {
        return Math.abs(a.inicio.y - b.inicio.y) <= COLISION_MARGEN
            && rangosSolapan(a.inicio.x, a.fin.x, b.inicio.x, b.fin.x);
    }

    if (esVertical(a) && esVertical(b)) {
        return Math.abs(a.inicio.x - b.inicio.x) <= COLISION_MARGEN
            && rangosSolapan(a.inicio.y, a.fin.y, b.inicio.y, b.fin.y);
    }

    const horizontal = esHorizontal(a) ? a : b;
    const vertical = esVertical(a) ? a : b;

    return entre(vertical.inicio.x, horizontal.inicio.x, horizontal.fin.x)
        && entre(horizontal.inicio.y, vertical.inicio.y, vertical.fin.y);
}

function lineaColisionaCaja(linea, caja) {
    const cajaAmpliada = expandirCaja(caja, COLISION_MARGEN);

    if (esHorizontal(linea)) {
        return linea.inicio.y >= cajaAmpliada.minY
            && linea.inicio.y <= cajaAmpliada.maxY
            && rangosSolapan(linea.inicio.x, linea.fin.x, cajaAmpliada.minX, cajaAmpliada.maxX);
    }

    return linea.inicio.x >= cajaAmpliada.minX
        && linea.inicio.x <= cajaAmpliada.maxX
        && rangosSolapan(linea.inicio.y, linea.fin.y, cajaAmpliada.minY, cajaAmpliada.maxY);
}

function cajaLinea(linea) {
    return {
        minX: Math.min(linea.inicio.x, linea.fin.x),
        maxX: Math.max(linea.inicio.x, linea.fin.x),
        minY: Math.min(linea.inicio.y, linea.fin.y),
        maxY: Math.max(linea.inicio.y, linea.fin.y),
    };
}

function expandirCaja(caja, margen) {
    return {
        minX: caja.minX - margen,
        maxX: caja.maxX + margen,
        minY: caja.minY - margen,
        maxY: caja.maxY + margen,
    };
}

function construirCajaDesdeCentro(centro, etiqueta) {
    const ancho = estimarAnchoTexto(etiqueta);
    const medioAncho = ancho / 2;
    const medioAlto = TEXTO_ALTO / 2;

    return {
        minX: centro.x - medioAncho,
        maxX: centro.x + medioAncho,
        minY: centro.y - medioAlto,
        maxY: centro.y + medioAlto,
    };
}

function estimarAnchoTexto(etiqueta) {
    return Math.max(TEXTO_MIN_ANCHO, etiqueta.length * TEXTO_PADDING);
}

function calcularCentro(box) {
    return {
        x: (box.minX + box.maxX) / 2,
        y: (box.minY + box.maxY) / 2,
    };
}

function puntoMedio(segmento) {
    return {
        x: (segmento.inicio.x + segmento.fin.x) / 2,
        y: (segmento.inicio.y + segmento.fin.y) / 2,
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

function productoEscalar(a, b) {
    return a.x * b.x + a.y * b.y;
}

function esHorizontal(linea) {
    return Math.abs(linea.inicio.y - linea.fin.y) < 0.001;
}

function esVertical(linea) {
    return Math.abs(linea.inicio.x - linea.fin.x) < 0.001;
}

function rangosSolapan(a1, a2, b1, b2) {
    const minA = Math.min(a1, a2);
    const maxA = Math.max(a1, a2);
    const minB = Math.min(b1, b2);
    const maxB = Math.max(b1, b2);

    return maxA + COLISION_MARGEN >= minB && maxB + COLISION_MARGEN >= minA;
}

function entre(valor, inicio, fin) {
    return valor + COLISION_MARGEN >= Math.min(inicio, fin)
        && valor - COLISION_MARGEN <= Math.max(inicio, fin);
}
