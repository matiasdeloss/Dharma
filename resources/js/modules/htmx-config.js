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

    // Listener 1: Eventos nativos de HTMX en document.body
    document.body.addEventListener('watchlistUpdated', (e) => handleWatchlist(e.detail));
    document.body.addEventListener('reviewSaved', (e) => handleReviewSaved(e.detail));
    document.body.addEventListener('authRequired', (e) => handleAuthRequired(e.detail));

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

    // Slider de calificación del modal. Delegado en document porque htmx inyecta
    // el modal después de cargar la página.
    function syncRateSlider(slider, { touched = false } = {}) {
        const wrapper = slider.closest('.rate-hero-card') || slider.closest('.rate-hero-content') || slider.closest('.rate-hero-inner') || slider.closest('form');
        if (!wrapper) return;

        const value = parseFloat(slider.value);

        // Progreso para pintar el tramo recorrido de la barra (ver _modals.scss).
        // Se actualiza siempre, se haya tocado el slider o no.
        const pct = ((value - slider.min) / (slider.max - slider.min)) * 100;
        slider.style.setProperty('--rate-progress', `${pct}%`);

        // El número grande solo se muestra si ya había una nota guardada, o si
        // el usuario recién movió el slider — así no parece pre-calificada.
        if (touched) slider.dataset.hasRating = 'true';
        if (slider.dataset.hasRating !== 'true') return;

        const valueEl = wrapper.querySelector('#rateValue');
        if (valueEl) valueEl.textContent = value.toFixed(1);
    }

    document.addEventListener('input', (e) => {
        if (e.target.id === 'rateSlider') syncRateSlider(e.target, { touched: true });
    });

    // Estado inicial al abrir el modal (htmx ya insertó el contenido).
    document.body.addEventListener('htmx:afterSwap', (e) => {
        const slider = e.target.querySelector?.('#rateSlider');
        if (slider) syncRateSlider(slider);
    });
}
