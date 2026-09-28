// ==========================================================================
// Panel lateral de persona (#personPanel): limpiar al cerrar
// ==========================================================================

/**
 * El contenido lo pone HTMX al tocar una card del reparto. Al cerrar el
 * offcanvas se vuelve al spinner: si no, al abrir otra persona se ve un
 * instante la anterior mientras carga la nueva.
 */
const LOADING = `
    <div class="modal-loading">
        <div class="spinner-border text-accent" role="status">
            <span class="visually-hidden">Cargando...</span>
        </div>
        <span>Cargando</span>
    </div>`;

export function initPersonPanel() {
    document.addEventListener('hidden.bs.offcanvas', (e) => {
        if (e.target?.id !== 'personPanel') return;
        const body = document.getElementById('personPanelContent');
        if (body) body.innerHTML = LOADING;
    });
}
