// ==========================================================================
// Estrellas de calificación del modal
// ==========================================================================

/**
 * Cinco estrellas con medias: cada media estrella es un punto en la escala
 * de 1 a 10, que es lo que viaja en el hidden `[data-star-input]`.
 *
 *   clic en la mitad izquierda de la estrella N  → (N-1)*2 + 1   (media)
 *   clic en la mitad derecha                     → N*2           (entera)
 *
 * El número de arriba y la etiqueta se actualizan al pasar el mouse (vista
 * previa) y al hacer clic (valor definitivo). Con teclado: flechas ±1 punto.
 *
 * Todo delegado en `document` porque el modal lo inyecta htmx después de
 * cargar la página: enganchar las estrellas al cargar no serviría.
 */
export function initStarRater() {
    document.addEventListener('click', (e) => {
        const star = e.target.closest('.star-rater-star');
        if (!star) return;

        const rater = star.closest('[data-star-rater]');
        if (!rater) return;

        commit(rater, valueFromPointer(star, e));
    });

    document.addEventListener('mousemove', (e) => {
        const star = e.target.closest('.star-rater-star');
        if (!star) return;

        const rater = star.closest('[data-star-rater]');
        if (!rater) return;

        preview(rater, valueFromPointer(star, e));
    });

    document.addEventListener('mouseleave', (e) => {
        const stars = e.target.closest?.('.star-rater-stars');
        if (!stars) return;

        const rater = stars.closest('[data-star-rater]');
        if (rater) render(rater, currentValue(rater));
    }, true);

    document.addEventListener('keydown', (e) => {
        const star = e.target.closest?.('.star-rater-star');
        if (!star) return;

        const rater = star.closest('[data-star-rater]');
        if (!rater) return;

        const delta = { ArrowRight: 1, ArrowUp: 1, ArrowLeft: -1, ArrowDown: -1 }[e.key];
        if (!delta) return;

        e.preventDefault();
        const next = Math.min(10, Math.max(1, (currentValue(rater) || 0) + delta));
        commit(rater, next);
    });

    // Estado inicial al abrir el modal (htmx ya insertó el contenido).
    document.body.addEventListener('htmx:afterSwap', (e) => {
        e.target.querySelectorAll?.('[data-star-rater]').forEach((rater) => {
            render(rater, currentValue(rater));
        });
    });
}

/** Valor de 1 a 10 según en qué mitad de la estrella cayó el puntero. */
function valueFromPointer(star, e) {
    const n = parseInt(star.dataset.star, 10);
    const rect = star.getBoundingClientRect();
    const half = (e.clientX - rect.left) < rect.width / 2;
    return half ? (n - 1) * 2 + 1 : n * 2;
}

function currentValue(rater) {
    const v = parseInt(rater.querySelector('[data-star-input]')?.value, 10);
    return Number.isFinite(v) ? v : null;
}

function commit(rater, value) {
    const input = rater.querySelector('[data-star-input]');
    if (input) input.value = value;
    render(rater, value);

    // El botón de guardar arranca deshabilitado mientras no hay nota.
    rater.closest('form')?.querySelector('[data-star-submit]')?.removeAttribute('disabled');
}

function preview(rater, value) {
    render(rater, value, { preview: true });
}

function render(rater, value, { preview = false } = {}) {
    const stars = rater.querySelectorAll('.star-rater-star');
    const halves = value ? value : 0; // cantidad de medias estrellas encendidas

    stars.forEach((star, i) => {
        const icon = star.querySelector('i');
        const filled = halves - i * 2; // 2 = entera, 1 = media, <=0 = vacía
        icon.className = filled >= 2 ? 'bi bi-star-fill' : filled === 1 ? 'bi bi-star-half' : 'bi bi-star';
        star.classList.toggle('is-on', filled >= 1);
    });

    rater.classList.toggle('is-preview', preview);

    const valueEl = rater.querySelector('[data-star-value]');
    if (valueEl) valueEl.textContent = value ? String(value) : '—';

}

