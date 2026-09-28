// ==========================================================================
// Guarda de spoilers
// ==========================================================================

/**
 * Las resenas marcadas como "contiene spoilers" se muestran desenfocadas hasta
 * que el lector toca el boton.
 *
 * Delegado en `document` porque estos bloques llegan tanto por navegacion
 * normal como por fragmentos de htmx: enganchar los botones al cargar dejaria
 * sin funcionar todo lo que se inserte despues.
 */
export function initSpoilerGuards() {
    document.addEventListener('click', (e) => {
        const btn = e.target.closest('.spoiler-reveal');
        if (!btn) return;

        btn.closest('.spoiler-guard')?.classList.add('is-revealed');
    });
}
