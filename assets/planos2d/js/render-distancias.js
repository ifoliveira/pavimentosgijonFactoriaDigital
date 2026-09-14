const SVG_NS = 'http://www.w3.org/2000/svg';

export function renderizarDistancias(svg, sistemaDistancias, clase = '', opciones = {}) {
    sistemaDistancias.distancias
        .filter((distancia) => distancia.distancia > 0.001)
        .forEach((distancia) => {
        const etiquetaVisible = etiquetaTecnica(distancia.etiqueta);
        const claseLinea = claseDistancia('plano-dynamic-distance-line', clase, distancia.clase);
        const claseEtiqueta = claseDistancia('plano-dynamic-distance-label', clase, distancia.clase);
        const segmentosLinea = opciones.interrumpirLinea
            ? partirLineaPorEtiqueta(distancia.linea, distancia.texto, etiquetaVisible, false)
            : [distancia.linea];

        segmentosLinea.forEach((linea) => {
            svg.appendChild(crearSvg('line', {
                class: claseLinea,
                x1: linea.inicio.x,
                y1: toSvgY(linea.inicio.y),
                x2: linea.fin.x,
                y2: toSvgY(linea.fin.y),
            }));
        });

        const atributosEditables = distancia.edicion ? {
            'data-editable-dimension': JSON.stringify(distancia.edicion),
            'data-editable-dimension-kind': distancia.edicion.tipo,
        } : {};
        const grupo = distancia.edicion ? crearSvg('g', {
            class: claseDistancia('plano-editable-dimension', clase),
            title: 'Clic para editar',
            ...atributosEditables,
        }) : null;
        const destino = grupo ?? svg;

        if (grupo) {
            const ancho = Math.max(48, distancia.etiqueta.length * 6 + 18);
            destino.appendChild(crearSvg('rect', {
                class: 'plano-dimension-hitbox',
                x: distancia.texto.x - ancho / 2,
                y: toSvgY(distancia.texto.y) - 13,
                width: ancho,
                height: 26,
                rx: 3,
                fill: 'none',
                stroke: 'none',
                opacity: 0,
                'pointer-events': 'all',
            }));
        }

        const rotacionEtiqueta = esVertical(distancia.linea) ? rotarEnPunto(-90, distancia.texto) : null;
        const mascara = cajaEtiqueta(distancia.texto, etiquetaVisible);
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
            class: claseEtiqueta,
            x: distancia.texto.x,
            y: toSvgY(distancia.texto.y),
            'text-anchor': 'middle',
            'dominant-baseline': 'middle',
            ...(rotacionEtiqueta ? { transform: rotacionEtiqueta } : {}),
        });
        texto.textContent = etiquetaVisible;
        destino.appendChild(texto);

        if (grupo) {
            svg.appendChild(grupo);
        }
    });
}

function etiquetaTecnica(etiqueta) {
    return String(etiqueta).replace(/\s*cm\s*$/i, '');
}

function cajaEtiqueta(posicion, etiqueta) {
    const ancho = Math.max(12, etiqueta.length * 3.3 + 4);
    const alto = 6;

    return {
        x: posicion.x - ancho / 2,
        y: posicion.y - alto / 2,
        ancho,
        alto,
    };
}

function esVertical(linea) {
    return Math.abs(linea.inicio.x - linea.fin.x) < 0.001;
}

function rotarEnPunto(grados, punto) {
    return `rotate(${grados} ${punto.x} ${toSvgY(punto.y)})`;
}

function partirLineaPorEtiqueta(linea, texto, etiqueta, parcial) {
    const vector = {
        x: linea.fin.x - linea.inicio.x,
        y: linea.fin.y - linea.inicio.y,
    };
    const longitud = Math.hypot(vector.x, vector.y);

    if (longitud <= 0.001) {
        return [linea];
    }

    const direccion = { x: vector.x / longitud, y: vector.y / longitud };
    const hueco = Math.min(longitud / 2 - 0.2, anchoEtiqueta(etiqueta, parcial) / 2);

    if (hueco <= 0) {
        return [linea];
    }

    const antes = {
        inicio: linea.inicio,
        fin: {
            x: texto.x - direccion.x * hueco,
            y: texto.y - direccion.y * hueco,
        },
    };
    const despues = {
        inicio: {
            x: texto.x + direccion.x * hueco,
            y: texto.y + direccion.y * hueco,
        },
        fin: linea.fin,
    };

    return [antes, despues].filter((segmento) => Math.hypot(
        segmento.fin.x - segmento.inicio.x,
        segmento.fin.y - segmento.inicio.y,
    ) > 0.2);
}

function anchoEtiqueta(etiqueta, parcial) {
    return Math.max(parcial ? 9 : 10, String(etiqueta).length * (parcial ? 2.9 : 3.2) + 5);
}

function claseDistancia(claseBase, ...modificadores) {
    return [claseBase, ...modificadores].filter(Boolean).join(' ');
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
