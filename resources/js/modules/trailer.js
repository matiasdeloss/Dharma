// ==========================================================================
// Trailer: cargar al abrir, descargar al cerrar
// ==========================================================================

/**
 * Ocultar un modal de Bootstrap no pausa un iframe de YouTube: el video sigue
 * sonando detras. Y la API de YouTube para pausarlo necesita cargar un script
 * externo mas. Lo fiable es poner el `src` al abrir y sacarlo al cerrar — que
 * ademas evita cargar YouTube en cada visita a la ficha.
 *
 * Los eventos de Bootstrap burbujean hasta `document`, asi que se escuchan
 * ahi y no hace falta enganchar cada modal.
 */
export function initTrailerModal() {
    document.addEventListener('show.bs.modal', (e) => {
        const iframe = trailerIframe(e.target);
        if (iframe && !iframe.getAttribute('src')) {
            iframe.setAttribute('src', iframe.dataset.src);
        }
    });

    document.addEventListener('hidden.bs.modal', (e) => {
        const iframe = trailerIframe(e.target);
        if (iframe) {
            iframe.removeAttribute('src');
        }
    });
}

function trailerIframe(modal) {
    if (!modal || modal.id !== 'trailerModal') return null;
    return modal.querySelector('iframe[data-src]');
}
