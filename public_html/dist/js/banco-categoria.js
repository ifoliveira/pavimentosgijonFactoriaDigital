function initBancoCategoria(tableElement, table) {
            $(tableElement).on('submit', '.bank-category-form', async function (event) {
                event.preventDefault();
                var categoryForm = this;
                var select = categoryForm.querySelector('select');
                var button = categoryForm.querySelector('button');
                var status = categoryForm.querySelector('.bank-category-status');
                if (button.disabled) return;
                var body = new URLSearchParams(new FormData(categoryForm));
                button.disabled = true;
                select.disabled = true;
                categoryForm.setAttribute('aria-busy', 'true');
                status.classList.remove('text-danger');
                status.textContent = 'Guardando…';
                try {
                    var response = await fetch(categoryForm.action, {
                        method: 'POST', body: body,
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    if (response.redirected || !(response.headers.get('content-type') || '').includes('application/json')) {
                        throw new Error('No se ha confirmado el guardado. Comprueba tu sesión e inténtalo de nuevo.');
                    }
                    var data = await response.json();
                    if (!response.ok) throw new Error(data.message || 'No se pudo guardar la categoría. Inténtalo de nuevo.');
                    select.value = data.id === null ? '' : String(data.id);
                    Array.from(select.options).forEach(function (option) { option.defaultSelected = option.selected; });
                    var cell = categoryForm.closest('td');
                    cell.setAttribute('data-search', data.descripcion);
                    cell.setAttribute('data-order', data.descripcion);
                    status.textContent = 'Categoría guardada';
                    // Actualiza la búsqueda y la ordenación sin mover la fila ni cambiar de página.
                    table.cell(cell).invalidate('dom');
                } catch (error) {
                    status.classList.add('text-danger');
                    status.textContent = error.message || 'No se pudo guardar. Comprueba la conexión y vuelve a intentarlo.';
                } finally {
                    button.disabled = false;
                    select.disabled = false;
                    categoryForm.removeAttribute('aria-busy');
                }
            });
}
