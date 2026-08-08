document.addEventListener('DOMContentLoaded', function () {

    const modalElement =
        document.getElementById('modalPresupuesto');

    const modalBody =
        document.getElementById('modalPresupuestoBody');

    if (!modalElement || !modalBody) {
        return;
    }

    document.addEventListener('click', async function (e) {

        const boton = e.target.closest('.btnPresupuesto');

        if (!boton) {
            return;
        }

        e.preventDefault();

        const url = boton.dataset.url;

        modalBody.innerHTML = `
            <div class="text-muted py-3">
                Cargando presupuesto...
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
                        No se pudo cargar el presupuesto.
                    </div>
                `;

                return;
            }

            modalBody.innerHTML = html;

        } catch (error) {

            console.error(
                'Error cargando presupuesto:',
                error
            );

            modalBody.innerHTML = `
                <div class="alert alert-danger mb-0">
                    No se pudo cargar el presupuesto.
                </div>
            `;
        }

    });

});