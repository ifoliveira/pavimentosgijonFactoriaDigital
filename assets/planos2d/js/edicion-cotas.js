export function configurarEdicionCotas(svg, callbacks) {
    let edicion = null;

    svg.addEventListener('click', (evento) => {
        const objetivo = evento.target.closest('[data-editable-dimension]');

        if (!objetivo) {
            return;
        }

        evento.stopPropagation();
        iniciarEdicion(objetivo, callbacks);
    });

    document.addEventListener('pointerdown', (evento) => {
        if (!edicion || edicion.input.contains(evento.target) || evento.target.closest('[data-editable-dimension]')) {
            return;
        }

        confirmarEdicion(edicion, callbacks);
        edicion = null;
    });

    return {
        cancelar() {
            if (!edicion) {
                return false;
            }

            cerrarEdicion(edicion);
            edicion = null;
            return true;
        },
    };

    function iniciarEdicion(objetivo, acciones) {
        cerrarEdicion(edicion);

        const payload = leerPayload(objetivo);
        const input = document.createElement('input');
        const rect = objetivo.getBoundingClientRect();

        input.type = 'text';
        input.className = 'planos2d-dimension-editor';
        input.value = objetivo.textContent.replace('cm', '').trim();
        input.style.left = `${rect.left + rect.width / 2}px`;
        input.style.top = `${rect.top + rect.height / 2}px`;
        input.style.width = `${Math.max(54, rect.width + 18)}px`;

        document.body.appendChild(input);
        input.focus();
        input.select();

        edicion = { input, payload, acciones };

        input.addEventListener('keydown', (evento) => {
            if (evento.key === 'Enter') {
                evento.preventDefault();
                confirmarEdicion(edicion, acciones);
                edicion = null;
            }

            if (evento.key === 'Escape') {
                evento.preventDefault();
                cerrarEdicion(edicion);
                edicion = null;
            }
        });

        input.addEventListener('blur', () => {
            if (edicion?.input === input) {
                confirmarEdicion(edicion, acciones);
            }
            edicion = null;
        }, { once: true });
    }
}

export function serializarPayloadCota(payload) {
    return JSON.stringify(payload);
}

function leerPayload(texto) {
    try {
        return JSON.parse(texto.dataset.editableDimension);
    } catch (error) {
        return null;
    }
}

function confirmarEdicion(edicion, accionesFallback) {
    if (!edicion || edicion.cerrada) {
        return;
    }

    const acciones = edicion.acciones ?? accionesFallback;
    const valor = edicion.input.value;
    const payload = edicion.payload;

    cerrarEdicion(edicion);
    acciones.confirmar(payload, valor);
}

function cerrarEdicion(edicion) {
    if (!edicion || edicion.cerrada) {
        return;
    }

    edicion.cerrada = true;
    edicion.input?.remove();
}
