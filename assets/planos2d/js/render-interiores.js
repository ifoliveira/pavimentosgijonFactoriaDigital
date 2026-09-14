const SVG_NS = 'http://www.w3.org/2000/svg';

export function renderizarInteriores(svg, sistemaInteriores, seleccionadoId, interioresInvalidos = new Set()) {
    sistemaInteriores.elementos.forEach((elemento) => {
        const clases = [
            'plano-interior',
            elemento.id === seleccionadoId ? 'is-selected' : '',
            interioresInvalidos.has(elemento.id) ? 'is-invalid' : '',
        ].filter(Boolean).join(' ');
        const grupo = crearSvg('g', {
            class: clases,
            transform: `translate(${elemento.x} ${-elemento.y}) rotate(${-elemento.rotacion})`,
            'data-interior-id': elemento.id,
        });

        if (elemento.tipo === 'plato_ducha') {
            pintarPlato(grupo, elemento);
        }

        if (elemento.tipo === 'inodoro') {
            pintarInodoro(grupo, elemento);
        }

        if (elemento.tipo === 'bide') {
            pintarBide(grupo, elemento);
        }

        if (elemento.tipo === 'banera') {
            pintarBanera(grupo, elemento);
        }

        if (elemento.tipo === 'mueble_lavabo') {
            pintarMueble(grupo, elemento);
        }

        if (elemento.tipo === 'barra_ducha') {
            pintarBarraDucha(grupo, elemento);
        }

        if (elemento.tipo === 'grifo_higienico') {
            pintarGrifoHigienico(grupo, elemento);
        }

        if (elemento.tipo === 'radiador_toallero') {
            pintarRadiadorToallero(grupo, elemento);
        }

        svg.appendChild(grupo);
    });
}

function pintarPlato(grupo, elemento) {
    grupo.appendChild(crearSvg('rect', rectBase(elemento)));
    grupo.appendChild(crearSvg('rect', {
        class: 'plano-interior-detail',
        x: -elemento.ancho * 0.43,
        y: -elemento.fondo * 0.36,
        width: elemento.ancho * 0.86,
        height: elemento.fondo * 0.72,
        rx: Math.max(2, Math.min(elemento.ancho, elemento.fondo) * 0.035),
    }));
    grupo.appendChild(crearSvg('circle', {
        class: 'plano-interior-symbol',
        cx: elemento.ancho * 0.34,
        cy: elemento.fondo * 0.26,
        r: Math.max(1.4, Math.min(elemento.ancho, elemento.fondo) * 0.03),
    }));
    grupo.appendChild(crearSvg('path', {
        class: 'plano-interior-detail',
        d: `M ${-elemento.ancho * 0.36} ${elemento.fondo * 0.28} L ${elemento.ancho * 0.24} ${-elemento.fondo * 0.28}`,
    }));
    grupo.appendChild(crearSvg('path', {
        class: 'plano-interior-detail',
        d: `M ${-elemento.ancho * 0.26} ${elemento.fondo * 0.31} L ${elemento.ancho * 0.31} ${-elemento.fondo * 0.22}`,
    }));
}

function pintarInodoro(grupo, elemento) {
    grupo.appendChild(crearSvg('rect', {
        ...rectBase(elemento),
        class: 'plano-interior-outline',
    }));
    grupo.appendChild(crearSvg('rect', {
        class: 'plano-interior-symbol',
        x: -elemento.ancho * 0.39,
        y: -elemento.fondo * 0.44,
        width: elemento.ancho * 0.78,
        height: elemento.fondo * 0.17,
        rx: Math.max(1.5, elemento.ancho * 0.04),
    }));
    grupo.appendChild(crearSvg('path', {
        class: 'plano-interior-symbol',
        d: [
            `M ${-elemento.ancho * 0.31} ${-elemento.fondo * 0.18}`,
            `C ${-elemento.ancho * 0.38} ${elemento.fondo * 0.02}, ${-elemento.ancho * 0.28} ${elemento.fondo * 0.34}, 0 ${elemento.fondo * 0.38}`,
            `C ${elemento.ancho * 0.28} ${elemento.fondo * 0.34}, ${elemento.ancho * 0.38} ${elemento.fondo * 0.02}, ${elemento.ancho * 0.31} ${-elemento.fondo * 0.18}`,
            `C ${elemento.ancho * 0.2} ${-elemento.fondo * 0.25}, ${-elemento.ancho * 0.2} ${-elemento.fondo * 0.25}, ${-elemento.ancho * 0.31} ${-elemento.fondo * 0.18}`,
            'Z',
        ].join(' '),
    }));
    grupo.appendChild(crearSvg('ellipse', {
        class: 'plano-interior-detail',
        cx: 0,
        cy: elemento.fondo * 0.08,
        rx: elemento.ancho * 0.17,
        ry: elemento.fondo * 0.16,
    }));
    grupo.appendChild(crearSvg('path', {
        class: 'plano-interior-detail',
        d: `M ${-elemento.ancho * 0.28} ${-elemento.fondo * 0.25} Q 0 ${-elemento.fondo * 0.2} ${elemento.ancho * 0.28} ${-elemento.fondo * 0.25}`,
    }));
}

function pintarBide(grupo, elemento) {
    grupo.appendChild(crearSvg('rect', {
        ...rectBase(elemento),
        class: 'plano-interior-outline',
    }));
    grupo.appendChild(crearSvg('ellipse', {
        class: 'plano-interior-symbol',
        cx: 0,
        cy: elemento.fondo * 0.02,
        rx: elemento.ancho * 0.31,
        ry: elemento.fondo * 0.33,
    }));
    grupo.appendChild(crearSvg('path', {
        class: 'plano-interior-detail',
        d: `M ${-elemento.ancho * 0.18} ${elemento.fondo * 0.16} Q 0 ${elemento.fondo * 0.28} ${elemento.ancho * 0.18} ${elemento.fondo * 0.16}`,
    }));
    grupo.appendChild(crearSvg('circle', {
        class: 'plano-interior-detail',
        cx: 0,
        cy: -elemento.fondo * 0.24,
        r: Math.max(1.2, elemento.fondo * 0.028),
    }));
    grupo.appendChild(crearSvg('line', {
        class: 'plano-interior-detail',
        x1: -elemento.ancho * 0.32,
        y1: -elemento.fondo * 0.34,
        x2: elemento.ancho * 0.32,
        y2: -elemento.fondo * 0.34,
    }));
}

function pintarBanera(grupo, elemento) {
    grupo.appendChild(crearSvg('rect', rectBase(elemento)));
    grupo.appendChild(crearSvg('rect', {
        class: 'plano-interior-symbol',
        x: -elemento.ancho * 0.42,
        y: -elemento.fondo * 0.34,
        width: elemento.ancho * 0.84,
        height: elemento.fondo * 0.68,
        rx: Math.max(3, Math.min(elemento.ancho, elemento.fondo) * 0.08),
    }));
    grupo.appendChild(crearSvg('path', {
        class: 'plano-interior-detail',
        d: `M ${-elemento.ancho * 0.34} ${-elemento.fondo * 0.2} Q 0 ${-elemento.fondo * 0.31} ${elemento.ancho * 0.34} ${-elemento.fondo * 0.2}`,
    }));
    grupo.appendChild(crearSvg('circle', {
        class: 'plano-interior-detail',
        cx: elemento.ancho * 0.32,
        cy: -elemento.fondo * 0.02,
        r: Math.max(1.5, Math.min(elemento.ancho, elemento.fondo) * 0.03),
    }));
    grupo.appendChild(crearSvg('line', {
        class: 'plano-interior-detail',
        x1: -elemento.ancho * 0.32,
        y1: -elemento.fondo * 0.22,
        x2: -elemento.ancho * 0.32,
        y2: elemento.fondo * 0.22,
    }));
}

function pintarMueble(grupo, elemento) {
    grupo.appendChild(crearSvg('rect', rectBase(elemento)));
    grupo.appendChild(crearSvg('rect', {
        class: 'plano-interior-detail',
        x: -elemento.ancho * 0.43,
        y: -elemento.fondo * 0.34,
        width: elemento.ancho * 0.86,
        height: elemento.fondo * 0.68,
        rx: Math.max(1.5, elemento.fondo * 0.025),
    }));
    grupo.appendChild(crearSvg('line', {
        class: 'plano-interior-detail',
        x1: -elemento.ancho * 0.42,
        y1: -elemento.fondo * 0.28,
        x2: elemento.ancho * 0.42,
        y2: -elemento.fondo * 0.28,
    }));
    grupo.appendChild(crearSvg('ellipse', {
        class: 'plano-interior-symbol',
        cx: 0,
        cy: elemento.fondo * 0.05,
        rx: elemento.ancho * 0.25,
        ry: elemento.fondo * 0.21,
    }));
    grupo.appendChild(crearSvg('circle', {
        class: 'plano-interior-detail',
        cx: 0,
        cy: -elemento.fondo * 0.16,
        r: Math.max(1.1, elemento.fondo * 0.025),
    }));
    grupo.appendChild(crearSvg('path', {
        class: 'plano-interior-detail',
        d: `M ${-elemento.ancho * 0.08} ${-elemento.fondo * 0.14} Q 0 ${-elemento.fondo * 0.24} ${elemento.ancho * 0.08} ${-elemento.fondo * 0.14}`,
    }));
}

function pintarBarraDucha(grupo, elemento) {
    grupo.appendChild(crearSvg('rect', rectBase(elemento)));
    grupo.appendChild(crearSvg('line', {
        class: 'plano-interior-symbol',
        x1: -elemento.ancho * 0.36,
        y1: -elemento.fondo * 0.12,
        x2: elemento.ancho * 0.36,
        y2: -elemento.fondo * 0.12,
    }));
    grupo.appendChild(crearSvg('circle', {
        class: 'plano-interior-detail',
        cx: -elemento.ancho * 0.28,
        cy: elemento.fondo * 0.14,
        r: Math.max(1.1, elemento.fondo * 0.18),
    }));
    grupo.appendChild(crearSvg('path', {
        class: 'plano-interior-detail',
        d: `M ${elemento.ancho * 0.14} ${elemento.fondo * 0.18} Q ${elemento.ancho * 0.24} ${-elemento.fondo * 0.02} ${elemento.ancho * 0.34} ${elemento.fondo * 0.18}`,
    }));
}

function pintarGrifoHigienico(grupo, elemento) {
    grupo.appendChild(crearSvg('rect', rectBase(elemento)));
    grupo.appendChild(crearSvg('circle', {
        class: 'plano-interior-symbol',
        cx: -elemento.ancho * 0.28,
        cy: -elemento.fondo * 0.05,
        r: Math.max(1.2, elemento.fondo * 0.2),
    }));
    grupo.appendChild(crearSvg('path', {
        class: 'plano-interior-detail',
        d: [
            `M ${-elemento.ancho * 0.16} ${-elemento.fondo * 0.04}`,
            `C ${elemento.ancho * 0.02} ${-elemento.fondo * 0.32}, ${elemento.ancho * 0.22} ${elemento.fondo * 0.32}, ${elemento.ancho * 0.38} ${elemento.fondo * 0.02}`,
        ].join(' '),
    }));
    grupo.appendChild(crearSvg('line', {
        class: 'plano-interior-symbol',
        x1: elemento.ancho * 0.2,
        y1: -elemento.fondo * 0.18,
        x2: elemento.ancho * 0.36,
        y2: -elemento.fondo * 0.18,
    }));
}

function pintarRadiadorToallero(grupo, elemento) {
    grupo.appendChild(crearSvg('rect', rectBase(elemento)));
    const barras = [-0.28, -0.1, 0.08, 0.26];

    barras.forEach((factor) => {
        grupo.appendChild(crearSvg('line', {
            class: 'plano-interior-symbol',
            x1: -elemento.ancho * 0.38,
            y1: elemento.fondo * factor,
            x2: elemento.ancho * 0.38,
            y2: elemento.fondo * factor,
        }));
    });
    grupo.appendChild(crearSvg('line', {
        class: 'plano-interior-detail',
        x1: -elemento.ancho * 0.42,
        y1: -elemento.fondo * 0.34,
        x2: -elemento.ancho * 0.42,
        y2: elemento.fondo * 0.34,
    }));
    grupo.appendChild(crearSvg('line', {
        class: 'plano-interior-detail',
        x1: elemento.ancho * 0.42,
        y1: -elemento.fondo * 0.34,
        x2: elemento.ancho * 0.42,
        y2: elemento.fondo * 0.34,
    }));
}

function rectBase(elemento) {
    return {
        class: 'plano-interior-shape',
        x: -elemento.ancho / 2,
        y: -elemento.fondo / 2,
        width: elemento.ancho,
        height: elemento.fondo,
        rx: 2,
    };
}

function texto(contenido, x, y, clase) {
    const elemento = crearSvg('text', {
        class: clase,
        x,
        y,
        'text-anchor': 'middle',
        'dominant-baseline': 'middle',
    });
    elemento.textContent = contenido;

    return elemento;
}

function crearSvg(tag, atributos) {
    const elemento = document.createElementNS(SVG_NS, tag);

    Object.entries(atributos).forEach(([nombre, valor]) => {
        elemento.setAttribute(nombre, valor);
    });

    return elemento;
}
