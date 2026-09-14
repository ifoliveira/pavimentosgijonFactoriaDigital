export function configurarAtajosEditor(acciones) {
    document.addEventListener('keydown', (evento) => {
        if (estaEscribiendo(evento.target)) {
            return;
        }

        const tecla = evento.key.toLowerCase();

        if ((evento.ctrlKey || evento.metaKey) && tecla === 'z' && evento.shiftKey) {
            evento.preventDefault();
            acciones.rehacer();
            return;
        }

        if ((evento.ctrlKey || evento.metaKey) && tecla === 'z') {
            evento.preventDefault();
            acciones.deshacer();
            return;
        }

        if ((evento.ctrlKey || evento.metaKey) && tecla === 'y') {
            evento.preventDefault();
            acciones.rehacer();
            return;
        }

        if (evento.key === 'Delete') {
            evento.preventDefault();
            acciones.eliminarSeleccion();
            return;
        }

        if (tecla === 'r') {
            evento.preventDefault();
            acciones.girarSeleccion();
            return;
        }

        if (evento.key === 'Escape') {
            acciones.cancelar();
        }
    });
}

function estaEscribiendo(elemento) {
    if (!elemento) {
        return false;
    }

    return ['INPUT', 'TEXTAREA', 'SELECT'].includes(elemento.tagName) || elemento.isContentEditable;
}
