export function configurarDragInteriores(svg, callbacks) {
    let drag = null;

    svg.addEventListener('pointerdown', (evento) => {
        const objetivo = evento.target.closest('[data-interior-id]');

        if (!objetivo) {
            return;
        }

        const id = objetivo.dataset.interiorId;
        const punto = puntoSvg(svg, evento);
        const elemento = callbacks.getElemento(id);

        if (!elemento) {
            return;
        }

        drag = {
            id,
            offsetX: punto.x - elemento.x,
            offsetY: punto.y - elemento.y,
        };
        objetivo.setPointerCapture(evento.pointerId);
        callbacks.onDragStart?.(id);
        callbacks.onSelect(id);
    });

    svg.addEventListener('pointermove', (evento) => {
        if (!drag) {
            return;
        }

        const punto = puntoSvg(svg, evento);
        callbacks.onMove(drag.id, {
            x: punto.x - drag.offsetX,
            y: punto.y - drag.offsetY,
        });
    });

    svg.addEventListener('pointerup', () => {
        callbacks.onDragEnd?.(drag?.id);
        drag = null;
    });

    svg.addEventListener('pointercancel', () => {
        callbacks.onDragEnd?.(drag?.id);
        drag = null;
    });
}

function puntoSvg(svg, evento) {
    const punto = svg.createSVGPoint();
    punto.x = evento.clientX;
    punto.y = evento.clientY;
    const transformado = punto.matrixTransform(svg.getScreenCTM().inverse());

    return {
        x: transformado.x,
        y: -transformado.y,
    };
}
