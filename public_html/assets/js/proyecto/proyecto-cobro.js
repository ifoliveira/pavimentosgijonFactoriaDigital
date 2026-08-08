document.addEventListener('DOMContentLoaded', function () {

    const modalEl =
        document.getElementById('modalNuevoCobroProyecto');

    const form =
        document.getElementById('formNuevoCobroProyecto');

    if (!modalEl || !form) {
        return;
    }

    const clienteEl =
        document.getElementById('nuevoCobroCliente');

    const importeEl =
        document.getElementById('nuevoCobroImporte');

    const pendienteEl =
        document.getElementById('nuevoCobroPendiente');


    document.addEventListener('click', function (e) {

        const boton = e.target.closest('.btnNuevoCobro');

        if (!boton) {
            return;
        }

        e.preventDefault();

        const url = boton.dataset.url;
        const cliente = boton.dataset.cliente || '';
        const pendiente = parseFloat(
            boton.dataset.pendiente || 0
        );

        /*
         * Cambiamos el destino del formulario
         * según el proyecto seleccionado.
         */
        form.action = url;

        clienteEl.textContent = cliente;

        importeEl.value = '';

        importeEl.placeholder =
            pendiente.toFixed(2);

        pendienteEl.textContent =
            'Pendiente: ' +
            pendiente.toLocaleString('es-ES', {
                style: 'currency',
                currency: 'EUR'
            });

        let modal =
            bootstrap.Modal.getInstance(modalEl);

        if (!modal) {
            modal = new bootstrap.Modal(modalEl);
        }

        modal.show();

    });

    form.addEventListener('submit', async function (e) {

        e.preventDefault();

        const botonSubmit =
            form.querySelector('button[type="submit"]');

        if (botonSubmit) {
            botonSubmit.disabled = true;
        }

        try {

            const response = await fetch(form.action, {
                method: 'POST',
                body: new FormData(form),
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const contentType =
                response.headers.get('content-type') || '';

            /*
            * Esperamos JSON cuando la petición es AJAX
            */
            if (contentType.includes('application/json')) {

                const data = await response.json();

                if (data.success) {
                    window.location.reload();
                    return;
                }

                alert(
                    data.message ||
                    'No se pudo registrar el cobro.'
                );

                return;
            }

            /*
            * Si llegamos aquí, el controlador sigue
            * devolviendo un redirect/HTML.
            */
            const texto = await response.text();

            console.error(
                'La respuesta del cobro no es JSON:',
                texto
            );

            alert(
                'El servidor no ha devuelto la respuesta esperada.'
            );

        } catch (error) {

            console.error(
                'Error registrando cobro:',
                error
            );

            alert('No se pudo registrar el cobro.');

        } finally {

            if (botonSubmit) {
                botonSubmit.disabled = false;
            }
        }

    });   
    
    
    document.addEventListener('click', async function (e) {

        const boton = e.target.closest('.btnJustificantes');

        if (!boton) {
            return;
        }

        e.preventDefault();

        const url = boton.dataset.url;

        const modalElement =
            document.getElementById('modalJustificantes');

        const modalBody =
            document.getElementById('modalJustificantesBody');

        modalBody.innerHTML = `
            <div class="text-muted">
                Cargando justificantes...
            </div>
        `;

        let modal =
            bootstrap.Modal.getInstance(modalElement);

        if (!modal) {
            modal = new bootstrap.Modal(modalElement);
        }

        modal.show();

        try {

            const response = await fetch(url, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const html = await response.text();

            if (!response.ok) {
                modalBody.innerHTML = `
                    <div class="alert alert-danger mb-0">
                        No se pudieron cargar los justificantes.
                    </div>
                `;
                return;
            }

            modalBody.innerHTML = html;

        } catch (error) {

            console.error(error);

            modalBody.innerHTML = `
                <div class="alert alert-danger mb-0">
                    No se pudieron cargar los justificantes.
                </div>
            `;
        }

    });    

});