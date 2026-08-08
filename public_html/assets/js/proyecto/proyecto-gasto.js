document.addEventListener('DOMContentLoaded', function () {

    const botonesNuevoGasto = document.querySelectorAll('.btnNuevoGasto');

    const modalElement = document.getElementById('modalProyectoGasto');
    const modalBody = document.getElementById('modalProyectoGastoBody');
    const modalTitle = document.getElementById('modalProyectoGastoLabel');

    if (!modalElement || !modalBody) {
        return;
    }

    /*
     * ============================================================
     * FUNCIÓN COMÚN PARA ABRIR EL MODAL
     * ============================================================
     */
    async function abrirModalGasto(url, titulo) {

        modalTitle.textContent = titulo;

        modalBody.innerHTML = `
            <div class="text-muted">
                Cargando formulario...
            </div>
        `;

        let modalInstance = bootstrap.Modal.getInstance(modalElement);

        if (!modalInstance) {
            modalInstance = new bootstrap.Modal(modalElement);
        }

        modalInstance.show();

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
                        No se pudo cargar el formulario.
                    </div>
                `;
                return;
            }

            modalBody.innerHTML = html;

            if (modalBody.querySelector('#formProyectoGasto')) {
                bindProyectoGastoForm(url);
            }

        } catch (error) {

            console.error(error);

            modalBody.innerHTML = `
                <div class="alert alert-danger mb-0">
                    No se pudo cargar el formulario.
                </div>
            `;
        }
    }

    /*
     * ============================================================
     * NUEVO GASTO
     * ============================================================
     */
    botonesNuevoGasto.forEach(function (boton) {

        boton.addEventListener('click', function () {

            const url = this.dataset.url;

            abrirModalGasto(
                url,
                'Nuevo gasto del proyecto'
            );
        });

    });

    /*
     * ============================================================
     * EDITAR GASTO
     * ============================================================
     */
    document.addEventListener('click', function (e) {

        const boton = e.target.closest('.btnEditarGasto');

        if (!boton) {
            return;
        }

        e.preventDefault();

        const url = boton.dataset.url;

        abrirModalGasto(
            url,
            'Editar gasto del proyecto'
        );
    });

    /*
     * ============================================================
     * MARCAR GASTO COMO PAGADO
     * ============================================================
     */

    const modalEl = document.getElementById('modalGastoPagado');

    if (!modalEl) {
        return;
    }

    let urlPago = null;

    document.addEventListener('click', function (e) {

        const btn = e.target.closest('.btnMarcarGastoPagado');

        if (!btn) {
            return;
        }

        e.preventDefault();

        urlPago = btn.dataset.url;

        document.getElementById('gastoPagadoConcepto').textContent =
            btn.dataset.concepto;

        const importe = parseFloat(btn.dataset.importe || 0);

        document.getElementById('gastoPagadoImporte').textContent =
            importe.toLocaleString('es-ES', {
                style: 'currency',
                currency: 'EUR'
            });

        /*
        * Si estamos dentro del modal de gastos pendientes,
        * lo cerramos antes de abrir el modal de pago.
        */
        const modalPendientesEl =
            document.getElementById('modalGastosPendientes');

        if (modalPendientesEl) {

            const modalPendientes =
                bootstrap.Modal.getInstance(modalPendientesEl);

            if (modalPendientes) {

                modalPendientesEl.addEventListener(
                    'hidden.bs.modal',
                    function abrirModalPago() {

                        mostrarModalPago();

                    },
                    { once: true }
                );

                modalPendientes.hide();

                return;
            }
        }

        /*
        * En show.html.twig no existe modalGastosPendientes,
        * así que abre directamente el pago.
        */
        mostrarModalPago();
    });

    function mostrarModalPago() {

        let modal = bootstrap.Modal.getInstance(modalEl);

        if (!modal) {
            modal = new bootstrap.Modal(modalEl);
        }

        modal.show();
    }

    document.getElementById('btnGastoPagoEfectivo')
        ?.addEventListener('click', async function () {
            await marcarPagado('efectivo');
        });

    document.getElementById('btnGastoPagoBanco')
        ?.addEventListener('click', async function () {
            await marcarPagado('banco');
        });

    async function marcarPagado(origen) {

        if (!urlPago) {
            return;
        }

        const formData = new FormData();
        formData.append('origen', origen);

        try {

            const response = await fetch(urlPago, {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });

            const texto = await response.text();

            let data;

            try {
                data = JSON.parse(texto);
            } catch (e) {
                console.error('La respuesta NO es JSON');
                console.error(texto);

                alert('El servidor no ha devuelto JSON. Mira la consola.');
                return;
            }

            if (data.success) {
                window.location.reload();
                return;
            }

            alert(data.message || 'No se pudo actualizar el gasto.');

        } catch (error) {

            console.error('ERROR FETCH:', error);

            alert('No se pudo actualizar el gasto.');
        }
    }

    /*
     * ============================================================
     * SUBMIT FORMULARIO
     * ============================================================
     */
    function bindProyectoGastoForm(url) {

        const form = modalBody.querySelector('#formProyectoGasto');

        if (!form) {
            console.error('No se encontró #formProyectoGasto');
            return;
        }

        form.addEventListener('submit', async function (e) {

            e.preventDefault();

            const botonSubmit = form.querySelector('button[type="submit"]');

            if (botonSubmit) {
                botonSubmit.disabled = true;
            }

            try {

                const action = form.getAttribute('action') || url;
                const method = form.getAttribute('method') || 'POST';

                const response = await fetch(action, {
                    method: method.toUpperCase(),
                    body: new FormData(form),
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                const contentType =
                    response.headers.get('content-type') || '';

                if (contentType.includes('application/json')) {

                    const data = await response.json();

                    if (data.success) {
                        window.location.reload();
                        return;
                    }

                    alert(
                        data.message || 'No se pudo guardar el gasto.'
                    );

                    return;
                }

                const html = await response.text();

                modalBody.innerHTML = html;

                const nuevoFormulario =
                    modalBody.querySelector('#formProyectoGasto');

                if (nuevoFormulario) {
                    bindProyectoGastoForm(url);
                }

            } catch (error) {

                console.error('Error guardando gasto:', error);

                alert('No se pudo guardar el gasto.');

            } finally {

                if (botonSubmit) {
                    botonSubmit.disabled = false;
                }
            }
        });
    }

});