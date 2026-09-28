/**
 * HTMX Configuration & Custom Event Listeners
 */
export function initHtmxConfig() {
    // CSRF Token setup for all HTMX requests
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (token) {
        document.body.addEventListener('htmx:configRequest', (event) => {
            event.detail.headers['X-CSRF-TOKEN'] = token;
        });
    }

    let lastTriggerTime = 0;
    function handleWatchlist(data) {
        const now = Date.now();
        if (now - lastTriggerTime < 300) return;
        lastTriggerTime = now;

        const message = (typeof data === 'object' && data !== null) ? data.message : data;
        let type = (typeof data === 'object' && data !== null && data.type) ? data.type : 'success';
        const title = (typeof data === 'object' && data !== null && data.title) ? data.title : 'Watchlist';

        if (typeof message === 'string' && message.toLowerCase().includes('eliminad')) {
            type = 'info';
        }

        if (window.showNotification) {
            window.showNotification(message || '¡Lista actualizada!', type, title);
        }
    }

    function handleReviewSaved(data) {
        const message = (typeof data === 'object' && data !== null) ? data.message : data;
        const type = (typeof data === 'object' && data !== null && data.type) ? data.type : 'success';
        const title = (typeof data === 'object' && data !== null && data.title) ? data.title : 'Mi Diario';

        const modalEl = document.getElementById('logModal');
        if (modalEl && window.bootstrap) {
            const modal = window.bootstrap.Modal.getInstance(modalEl);
            if (modal) modal.hide();
        }
        if (window.showNotification) {
            window.showNotification(message || '¡Reseña guardada con éxito!', type, title);
        }
    }

    function handleAuthRequired(data) {
        const message = (typeof data === 'object' && data !== null) ? data.message : data;
        const title = (typeof data === 'object' && data !== null && data.title) ? data.title : 'Iniciar Sesión';

        if (window.showNotification) {
            window.showNotification(message || 'Necesitas iniciar sesión para realizar esta acción.', 'auth', title);
        }
    }

    // Sumar/sacar títulos de una lista (MediaListController::toast).
    function handleListUpdated(data) {
        const message = (typeof data === 'object' && data !== null) ? data.message : data;
        const type = (typeof data === 'object' && data !== null && data.type) ? data.type : 'success';
        const title = (typeof data === 'object' && data !== null && data.title) ? data.title : 'Listas';

        if (window.showNotification) {
            window.showNotification(message || 'Lista actualizada.', type, title);
        }
    }

    // Validación fallida en un form por HTMX (422, ver bootstrap/app.php). El
    // form queda como estaba para que se pueda corregir y reenviar.
    function handleFormInvalid(data) {
        const message = (typeof data === 'object' && data !== null) ? data.message : data;

        if (window.showNotification) {
            window.showNotification(message || 'Revisá los datos e intentá de nuevo.', 'error', 'No se pudo guardar');
        }
    }

    // Listener 1: Eventos nativos de HTMX en document.body
    document.body.addEventListener('watchlistUpdated', (e) => handleWatchlist(e.detail));
    document.body.addEventListener('reviewSaved', (e) => handleReviewSaved(e.detail));
    document.body.addEventListener('authRequired', (e) => handleAuthRequired(e.detail));
    // Sin respaldo en el Listener 2: con un 422 htmx no reemplaza nada, así
    // que el form que disparó el evento sigue en la página y el evento llega.
    document.body.addEventListener('formInvalid', (e) => handleFormInvalid(e.detail));
    document.body.addEventListener('listUpdated', (e) => handleListUpdated(e.detail));

    // Listener 2: Respaldo directo en cabecera HTTP de respuesta
    document.addEventListener('htmx:afterOnLoad', (event) => {
        try {
            const xhr = event.detail.xhr;
            if (!xhr) return;
            const triggerHeader = xhr.getResponseHeader('HX-Trigger');
            if (triggerHeader) {
                const triggers = JSON.parse(triggerHeader);
                if (triggers.watchlistUpdated) {
                    handleWatchlist(triggers.watchlistUpdated);
                }
                if (triggers.reviewSaved) {
                    handleReviewSaved(triggers.reviewSaved);
                }
                if (triggers.authRequired) {
                    handleAuthRequired(triggers.authRequired);
                }
            }
        } catch (e) {
            console.warn('HTMX trigger parse warning:', e);
        }
    });

    // Close search dropdown on click outside
    document.addEventListener('click', (e) => {
        const dropdown = document.getElementById('search-results-dropdown');
        if (dropdown && !dropdown.contains(e.target) && !e.target.matches('input[name="q"]')) {
            dropdown.innerHTML = '';
        }
    });

    // Micro-feedback táctil: pulso inmediato en el botón de watchlist/calificar al
    // hacer clic, sin esperar la respuesta del servidor (delegado porque htmx
    // reemplaza estos botones dinámicamente).
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.ribbon-btn, .badge-rate-btn');
        if (!btn) return;

        btn.classList.remove('btn-pulse');
        void btn.offsetWidth; // reinicia la animación si se hace doble clic rápido
        btn.classList.add('btn-pulse');
    });
}
