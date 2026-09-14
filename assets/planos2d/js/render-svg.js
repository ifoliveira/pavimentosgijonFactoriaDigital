import { renderizarInteriores } from './render-interiores.js';
import { renderizarDistancias } from './render-distancias.js';
import { calcularCentroBoundingBox, calcularOrientacionPoligono, obtenerLineaInteriorMuro } from './muros.js';

const SVG_NS = 'http://www.w3.org/2000/svg';
const MARGEN = 34;
const MIN_VIEWBOX = 240;
const GROSOR_MURO_VISUAL = 2.8;

export function renderizarPlano(svg, geometria, sistemaCotas, sistemaElementos, sistemaInteriores, seleccionadoId, sistemaDistancias, sistemaDistanciasInteriores, opciones = {}) {
    const modo = opciones.modo ?? 'editor';
    const contextoMuros = crearContextoMuros(geometria);

    reemplazarContenido(svg);
    ajustarViewBox(svg, combinarBoundingBoxes([
        calcularBoundingBoxMurosVisuales(geometria, contextoMuros),
        sistemaCotas.boundingBox,
        sistemaElementos.boundingBox,
        sistemaInteriores.boundingBox,
        modo === 'editor' ? sistemaDistancias.boundingBox : null,
        opciones.mostrarDistanciasInteriores ? sistemaDistanciasInteriores.boundingBox : null,
    ]));

    if (geometria.segmentos.length === 0) {
        pintarTextoVacio(svg);
        return;
    }

    sistemaCotas.cotas.forEach((cota) => pintarCota(svg, cota, modo));
    geometria.segmentos.forEach((segmento) => pintarSegmento(svg, segmento, sistemaElementos, contextoMuros));
    sistemaElementos.elementos.forEach((elemento) => pintarElemento(svg, elemento));
    renderizarInteriores(svg, sistemaInteriores, seleccionadoId, opciones.interioresInvalidos ?? new Set());

    if (modo === 'editor' && sistemaDistancias.distancias.length > 0) {
        renderizarDistancias(svg, sistemaDistancias);
    }

    if (opciones.mostrarDistanciasInteriores) {
        renderizarDistancias(svg, sistemaDistanciasInteriores, modo === 'pdf' ? '' : 'is-between-elements', {
            interrumpirLinea: modo === 'pdf',
        });
    }
}

function ajustarViewBox(svg, box) {
    const ancho = Math.max(box.maxX - box.minX, MIN_VIEWBOX);
    const alto = Math.max(box.maxY - box.minY, MIN_VIEWBOX);
    const centroX = (box.minX + box.maxX) / 2;
    const centroY = (box.minY + box.maxY) / 2;
    const minX = centroX - ancho / 2 - MARGEN;
    const maxYConceptual = centroY + alto / 2 + MARGEN;

    svg.setAttribute('viewBox', `${minX} ${-maxYConceptual} ${ancho + MARGEN * 2} ${alto + MARGEN * 2}`);
}

function pintarSegmento(svg, segmento, sistemaElementos, contextoMuros) {
    if (segmento.auxiliar) {
        pintarSegmentoAuxiliar(svg, segmento, contextoMuros);
        return;
    }

    const huecos = sistemaElementos.huecosPorMuro.get(segmento.id) ?? [];
    const tramos = sistemaElementos.calcularTramosVisibles(segmento, huecos);
    const normalExterior = normalExteriorMuro(segmento, contextoMuros);

    tramos.forEach((tramo) => {
        svg.appendChild(crearSvg('polygon', {
            class: 'plano-wall',
            points: puntosPoligonoMuro(tramo, segmento, normalExterior, contextoMuros),
        }));
    });
}

function pintarSegmentoAuxiliar(svg, segmento, contextoMuros) {
    svg.appendChild(crearSvg('line', {
        class: 'plano-wall is-auxiliary',
        x1: segmento.inicio.x,
        y1: toSvgY(segmento.inicio.y),
        x2: segmento.fin.x,
        y2: toSvgY(segmento.fin.y),
    }));

    [segmento.inicio, segmento.fin].forEach((punto) => pintarMarcaContinuidad(svg, punto, segmento, contextoMuros));
}

function crearContextoMuros(geometria) {
    return {
        orientacion: calcularOrientacionPoligono(geometria.puntos, geometria.cierre.cerrado),
        centro: calcularCentroBoundingBox(geometria.boundingBox),
        grosor: GROSOR_MURO_VISUAL,
        segmentos: geometria.segmentos,
        cerrado: geometria.cierre?.cerrado === true,
    };
}

function calcularBoundingBoxMurosVisuales(geometria, contextoMuros) {
    const puntos = [];

    geometria.segmentos.forEach((segmento) => {
        if (segmento.auxiliar) {
            puntos.push(segmento.inicio, segmento.fin);
            return;
        }

        const normalExterior = normalExteriorMuro(segmento, contextoMuros);

        puntos.push(
            segmento.inicio,
            segmento.fin,
            desplazar(segmento.inicio, normalExterior, contextoMuros.grosor),
            desplazar(segmento.fin, normalExterior, contextoMuros.grosor),
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

function pintarMarcaContinuidad(svg, punto, segmento, contextoMuros) {
    const normalExterior = normalExteriorMuro(segmento, contextoMuros);
    const tangente = calcularTangente(segmento);
    const centro = desplazar(punto, normalExterior, contextoMuros.grosor * 0.7);
    const inicio = desplazar(centro, tangente, -3);
    const fin = desplazar(centro, tangente, 3);

    svg.appendChild(crearSvg('line', {
        class: 'plano-wall-continuation',
        x1: inicio.x,
        y1: toSvgY(inicio.y),
        x2: fin.x,
        y2: toSvgY(fin.y),
    }));
}

function normalExteriorMuro(segmento, contextoMuros) {
    const lineaInterior = obtenerLineaInteriorMuro(segmento, contextoMuros.orientacion, contextoMuros.centro);
    const normalInterior = lineaInterior.normalInterior ?? { x: 0, y: 0 };

    return {
        x: -normalInterior.x,
        y: -normalInterior.y,
    };
}

function puntosPoligonoMuro(tramo, segmento, normalExterior, contextoMuros) {
    const exteriorInicio = puntoExteriorInicioTramo(tramo, segmento, normalExterior, contextoMuros);
    const exteriorFin = puntoExteriorFinTramo(tramo, segmento, normalExterior, contextoMuros);

    return [
        puntoSvg(tramo.inicio),
        puntoSvg(tramo.fin),
        puntoSvg(exteriorFin),
        puntoSvg(exteriorInicio),
    ].join(' ');
}

function puntoExteriorInicioTramo(tramo, segmento, normalExterior, contextoMuros) {
    if (!puntosIguales(tramo.inicio, segmento.inicio)) {
        return desplazar(tramo.inicio, normalExterior, contextoMuros.grosor);
    }

    const anterior = segmentoAnterior(segmento, contextoMuros);

    return puntoExteriorEsquina(anterior, segmento, tramo.inicio, contextoMuros)
        ?? desplazar(tramo.inicio, normalExterior, contextoMuros.grosor);
}

function puntoExteriorFinTramo(tramo, segmento, normalExterior, contextoMuros) {
    if (!puntosIguales(tramo.fin, segmento.fin)) {
        return desplazar(tramo.fin, normalExterior, contextoMuros.grosor);
    }

    const siguiente = segmentoSiguiente(segmento, contextoMuros);

    return puntoExteriorEsquina(segmento, siguiente, tramo.fin, contextoMuros)
        ?? desplazar(tramo.fin, normalExterior, contextoMuros.grosor);
}

function puntoExteriorEsquina(segmentoA, segmentoB, esquina, contextoMuros) {
    if (!segmentoA || !segmentoB) {
        return null;
    }

    const lineaA = lineaExterior(segmentoA, contextoMuros);
    const lineaB = lineaExterior(segmentoB, contextoMuros);

    return interseccionLineas(lineaA.inicio, lineaA.fin, lineaB.inicio, lineaB.fin)
        ?? desplazar(esquina, normalExteriorMuro(segmentoB, contextoMuros), contextoMuros.grosor);
}

function lineaExterior(segmento, contextoMuros) {
    const normalExterior = normalExteriorMuro(segmento, contextoMuros);

    return {
        inicio: desplazar(segmento.inicio, normalExterior, contextoMuros.grosor),
        fin: desplazar(segmento.fin, normalExterior, contextoMuros.grosor),
    };
}

function segmentoAnterior(segmento, contextoMuros) {
    if (segmento.index > 0) {
        return contextoMuros.segmentos[segmento.index - 1] ?? null;
    }

    return contextoMuros.cerrado ? contextoMuros.segmentos[contextoMuros.segmentos.length - 1] ?? null : null;
}

function segmentoSiguiente(segmento, contextoMuros) {
    if (segmento.index < contextoMuros.segmentos.length - 1) {
        return contextoMuros.segmentos[segmento.index + 1] ?? null;
    }

    return contextoMuros.cerrado ? contextoMuros.segmentos[0] ?? null : null;
}

function interseccionLineas(a1, a2, b1, b2) {
    const dax = a2.x - a1.x;
    const day = a2.y - a1.y;
    const dbx = b2.x - b1.x;
    const dby = b2.y - b1.y;
    const determinante = dax * dby - day * dbx;

    if (Math.abs(determinante) < 0.001) {
        return null;
    }

    const t = ((b1.x - a1.x) * dby - (b1.y - a1.y) * dbx) / determinante;

    return {
        x: a1.x + dax * t,
        y: a1.y + day * t,
    };
}

function puntosIguales(a, b) {
    return Math.abs(a.x - b.x) < 0.001 && Math.abs(a.y - b.y) < 0.001;
}

function pintarTextoVacio(svg) {
    const texto = crearSvg('text', {
        class: 'plano-empty-text',
        x: 0,
        y: -24,
        'text-anchor': 'middle',
    });
    texto.textContent = 'Sin muros';
    svg.appendChild(texto);
}

function pintarCota(svg, cota, modo) {
    cota.auxiliares.forEach((auxiliar) => {
        svg.appendChild(crearSvg('line', {
            class: claseCota(cota, 'plano-dimension-helper'),
            x1: auxiliar.inicio.x,
            y1: toSvgY(auxiliar.inicio.y),
            x2: auxiliar.fin.x,
            y2: toSvgY(auxiliar.fin.y),
        }));
    });

    const etiquetaVisible = etiquetaTecnica(cota.etiqueta);
    const segmentosLinea = modo === 'pdf'
        ? partirLineaPorEtiqueta(cota.linea, cota.texto.posicion, etiquetaVisible, cota.parcial)
        : [cota.linea];

    segmentosLinea.forEach((linea) => {
        svg.appendChild(crearSvg('line', {
            class: claseCota(cota, 'plano-dimension-line'),
            x1: linea.inicio.x,
            y1: toSvgY(linea.inicio.y),
            x2: linea.fin.x,
            y2: toSvgY(linea.fin.y),
        }));
    });

    const atributosEditables = cota.edicion ? {
        'data-editable-dimension': JSON.stringify(cota.edicion),
        'data-editable-dimension-kind': cota.edicion.tipo,
    } : {};
    const grupo = cota.edicion ? crearSvg('g', {
        class: 'plano-editable-dimension',
        title: 'Clic para editar',
        ...atributosEditables,
    }) : null;
    const destino = grupo ?? svg;

    if (grupo) {
        const caja = cota.texto.caja;
        destino.appendChild(crearSvg('rect', {
            class: 'plano-dimension-hitbox',
            x: caja.minX - 8,
            y: toSvgY(caja.maxY) - 6,
            width: caja.maxX - caja.minX + 16,
            height: caja.maxY - caja.minY + 12,
            rx: 3,
            fill: 'none',
            stroke: 'none',
            opacity: 0,
            'pointer-events': 'all',
        }));
    }

    const rotacionEtiqueta = cota.orientacion === 'vertical' ? rotarEnPunto(-90, cota.texto.posicion) : null;
    const mascara = cajaEtiqueta(cota.texto.posicion, etiquetaVisible, cota.parcial);
    destino.appendChild(crearSvg('rect', {
        class: 'plano-dimension-label-mask',
        x: mascara.x,
        y: toSvgY(mascara.y + mascara.alto),
        width: mascara.ancho,
        height: mascara.alto,
        rx: 1,
        ...(rotacionEtiqueta ? { transform: rotacionEtiqueta } : {}),
    }));

    const texto = crearSvg('text', {
        class: claseCota(cota, 'plano-dimension-label'),
        x: cota.texto.posicion.x,
        y: toSvgY(cota.texto.posicion.y),
        'text-anchor': 'middle',
        'dominant-baseline': 'middle',
        ...(rotacionEtiqueta ? { transform: rotacionEtiqueta } : {}),
    });
    texto.textContent = etiquetaVisible;
    destino.appendChild(texto);

    if (grupo) {
        svg.appendChild(grupo);
    }
}

function etiquetaTecnica(etiqueta) {
    return String(etiqueta).replace(/\s*cm\s*$/i, '');
}

function cajaEtiqueta(posicion, etiqueta, parcial) {
    const ancho = Math.max(parcial ? 11 : 13, etiqueta.length * (parcial ? 3.4 : 4.1) + 4);
    const alto = parcial ? 6 : 7.2;

    return {
        x: posicion.x - ancho / 2,
        y: posicion.y - alto / 2,
        ancho,
        alto,
    };
}

function partirLineaPorEtiqueta(linea, posicionTexto, etiqueta, parcial) {
    const vector = {
        x: linea.fin.x - linea.inicio.x,
        y: linea.fin.y - linea.inicio.y,
    };
    const longitud = Math.hypot(vector.x, vector.y);

    if (longitud <= 0.001) {
        return [linea];
    }

    const direccion = { x: vector.x / longitud, y: vector.y / longitud };
    const hueco = Math.min(longitud / 2 - 0.2, anchoHuecoEtiqueta(etiqueta, parcial) / 2);

    if (hueco <= 0) {
        return [linea];
    }

    const antes = {
        inicio: linea.inicio,
        fin: {
            x: posicionTexto.x - direccion.x * hueco,
            y: posicionTexto.y - direccion.y * hueco,
        },
    };
    const despues = {
        inicio: {
            x: posicionTexto.x + direccion.x * hueco,
            y: posicionTexto.y + direccion.y * hueco,
        },
        fin: linea.fin,
    };

    return [antes, despues].filter((segmento) => Math.hypot(
        segmento.fin.x - segmento.inicio.x,
        segmento.fin.y - segmento.inicio.y,
    ) > 0.2);
}

function anchoHuecoEtiqueta(etiqueta, parcial) {
    return Math.max(parcial ? 11 : 13, String(etiqueta).length * (parcial ? 3.4 : 4.1) + 5);
}

function rotarEnPunto(grados, punto) {
    return `rotate(${grados} ${punto.x} ${toSvgY(punto.y)})`;
}

function claseCota(cota, claseBase) {
    return cota.parcial ? `${claseBase} is-partial` : claseBase;
}

function pintarElemento(svg, elemento) {
    if (elemento.tipo === 'puerta') {
        pintarPuerta(svg, elemento);
        return;
    }

    pintarVentana(svg, elemento);
}

function pintarPuerta(svg, puerta) {
    svg.appendChild(crearSvg('line', {
        class: 'plano-door-leaf',
        x1: puerta.hoja.bisagra.x,
        y1: toSvgY(puerta.hoja.bisagra.y),
        x2: puerta.hoja.extremoHoja.x,
        y2: toSvgY(puerta.hoja.extremoHoja.y),
    }));

    svg.appendChild(crearSvg('path', {
        class: 'plano-door-arc',
        d: describirArcoPuerta(puerta.hoja),
    }));
}

function pintarVentana(svg, ventana) {
    ventana.lineasVentana.forEach((linea) => {
        svg.appendChild(crearSvg('line', {
            class: 'plano-window-line',
            x1: linea.inicio.x,
            y1: toSvgY(linea.inicio.y),
            x2: linea.fin.x,
            y2: toSvgY(linea.fin.y),
        }));
    });

    if (ventana.lineasVentana.length >= 2) {
        const [exterior, interior] = ventana.lineasVentana;
        [
            [exterior.inicio, interior.inicio],
            [exterior.fin, interior.fin],
        ].forEach(([inicio, fin]) => {
            svg.appendChild(crearSvg('line', {
                class: 'plano-window-line is-end-cap',
                x1: inicio.x,
                y1: toSvgY(inicio.y),
                x2: fin.x,
                y2: toSvgY(fin.y),
            }));
        });
    }
}

export function describirArcoPuerta(hoja) {
    const inicio = {
        x: hoja.extremoMuro.x,
        y: toSvgY(hoja.extremoMuro.y),
    };
    const fin = {
        x: hoja.extremoHoja.x,
        y: toSvgY(hoja.extremoHoja.y),
    };
    const bisagra = {
        x: hoja.bisagra.x,
        y: toSvgY(hoja.bisagra.y),
    };
    const vectorCerrado = {
        x: inicio.x - bisagra.x,
        y: inicio.y - bisagra.y,
    };
    const vectorAbierto = {
        x: fin.x - bisagra.x,
        y: fin.y - bisagra.y,
    };
    const sweepFlag = productoVectorial(vectorCerrado, vectorAbierto) > 0 ? 1 : 0;

    return [
        `M ${inicio.x} ${inicio.y}`,
        `A ${hoja.radio} ${hoja.radio} 0 0 ${sweepFlag} ${fin.x} ${fin.y}`,
    ].join(' ');
}

function productoVectorial(a, b) {
    return a.x * b.y - a.y * b.x;
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

function puntoSvg(punto) {
    return `${formatearCoordenada(punto.x)},${formatearCoordenada(toSvgY(punto.y))}`;
}

function formatearCoordenada(valor) {
    const redondeado = Math.round(valor * 1000) / 1000;

    return Math.abs(redondeado) < 0.001 ? '0' : String(redondeado);
}

function reemplazarContenido(svg) {
    while (svg.firstChild) {
        svg.removeChild(svg.firstChild);
    }
}

function crearSvg(tag, atributos) {
    const elemento = document.createElementNS(SVG_NS, tag);

    Object.entries(atributos).forEach(([nombre, valor]) => {
        elemento.setAttribute(nombre, valor);
    });

    return elemento;
}

function toSvgY(y) {
    return -y;
}

function combinarBoundingBoxes(boxes) {
    const validos = boxes.filter(Boolean);

    if (validos.length === 0) {
        return { minX: 0, maxX: 0, minY: 0, maxY: 0 };
    }

    return {
        minX: Math.min(...validos.map((box) => box.minX)),
        maxX: Math.max(...validos.map((box) => box.maxX)),
        minY: Math.min(...validos.map((box) => box.minY)),
        maxY: Math.max(...validos.map((box) => box.maxY)),
    };
}
