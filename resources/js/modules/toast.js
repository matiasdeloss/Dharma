/**
 * Toast Notification System - Dharma Theme
 */
export function initToastSystem() {
    function scheduleDismiss(el, delay) {
        setTimeout(() => {
            el.classList.add('toast-hiding');
            setTimeout(() => {
                el.remove();
            }, 300); // 300ms duracion de la animación fadeOutUp
        }, delay);
    }

    // Inicializar cualquier toast de sesión (PHP Blade) presente al cargar
    const staticToasts = document.querySelectorAll('.toast-dharma');
    staticToasts.forEach((el) => {
        scheduleDismiss(el, 3800);
    });

    window.showNotification = function (message, type = 'success', title = null) {
        // Limpiar cualquier toast previo para evitar colisiones al centro
        document.querySelectorAll('.toast-dharma').forEach(t => t.remove());

        let icon = 'bi-check-circle-fill';
        let typeClass = 'toast-success';
        let defaultTitle = '¡Éxito!';
        let actionHtml = '';

        if (type === 'success') {
            icon = 'bi-check-circle-fill';
            typeClass = 'toast-success';
            defaultTitle = '¡Completado!';
        } else if (type === 'info') {
            icon = 'bi-info-circle-fill';
            typeClass = 'toast-info';
            defaultTitle = 'Información';
        } else if (type === 'auth' || type === 'warning') {
            icon = 'bi-lock-fill';
            typeClass = 'toast-auth';
            defaultTitle = 'Acceso Requerido';
            actionHtml = `
                <div class="mt-2 text-center">
                    <a href="/login" class="btn btn-sm btn-cine-primary py-1 px-3 fw-semibold">
                        <i class="bi bi-box-arrow-in-right me-1"></i> Iniciar Sesión
                    </a>
                </div>
            `;
        } else if (type === 'danger' || type === 'error') {
            icon = 'bi-exclamation-triangle-fill';
            typeClass = 'toast-error';
            defaultTitle = 'Atención';
        }

        const toastEl = document.createElement('div');
        toastEl.className = `toast-dharma ${typeClass}`;
        toastEl.setAttribute('role', 'alert');

        toastEl.innerHTML = `
            <div class="toast-dharma-header">
                <i class="bi ${icon}"></i>
                <span class="toast-dharma-title"></span>
            </div>
            <div class="toast-dharma-body"></div>
            ${actionHtml}
        `;

        // Título y mensaje como texto, nunca como HTML: vienen del servidor y
        // pueden llevar datos de afuera (un título de TMDB, lo que escribió
        // alguien). Con innerHTML serían una puerta a inyectar scripts.
        toastEl.querySelector('.toast-dharma-title').textContent = title || defaultTitle;
        toastEl.querySelector('.toast-dharma-body').textContent = message;

        document.body.appendChild(toastEl);

        const delay = type === 'auth' ? 5500 : 3800;
        scheduleDismiss(toastEl, delay);
    };
}
