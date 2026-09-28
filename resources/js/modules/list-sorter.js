// ==========================================================================
// Ordenar una lista arrastrando las cards
// ==========================================================================

import Sortable from 'sortablejs';

/**
 * Monta SortableJS sobre cada [data-list-sortable] (la grilla de
 * lists/partials/items-grid). No manda nada por su cuenta: al soltar,
 * Sortable dispara el evento `end` sobre la grilla y ahí htmx
 * (hx-trigger="end") postea los `items[]` en el orden nuevo del DOM.
 *
 * - `forceFallback`: arrastre propio de Sortable en vez del nativo, así se
 *   comporta igual en todos los navegadores y no arrastra el link o la
 *   imagen del poster en lugar de la card.
 * - `fallbackTolerance`: unos pixeles de margen para que un clic en la card
 *   siga siendo un clic (abre la ficha) y no un arrastre.
 * - En touch hay que mantener apretado un momento: si no, no se podría
 *   scrollear la página pasando el dedo por encima de la grilla.
 * - `filter`: los botones de la card (quitar, watchlist, calificar) no
 *   arrancan un arrastre.
 *
 * Se vuelve a llamar en cada `htmx:load` porque al quitar un título la
 * grilla llega nueva.
 */
export function initListSorter(root = document) {
    const grids = [
        ...(root.matches?.('[data-list-sortable]') ? [root] : []),
        ...(root.querySelectorAll?.('[data-list-sortable]') ?? []),
    ];

    grids.forEach((grid) => {
        if (grid.dataset.sortableReady) return;
        grid.dataset.sortableReady = 'true';

        Sortable.create(grid, {
            draggable: '[data-list-item]',
            animation: 160,
            forceFallback: true,
            fallbackTolerance: 6,
            delay: 200,
            delayOnTouchOnly: true,
            filter: '.list-remove-btn, .ribbon-btn, .badge-rate-btn',
            preventOnFilter: false,
            ghostClass: 'is-drag-ghost',
            chosenClass: 'is-drag-chosen',
            fallbackClass: 'is-drag-fallback',
            onEnd: () => renumber(grid),
        });
    });
}

/**
 * En una lista numerada, el puesto de cada card (#1, #2...) sigue al orden
 * nuevo sin esperar al servidor.
 */
function renumber(grid) {
    grid.querySelectorAll('[data-list-item]').forEach((item, index) => {
        const badge = item.querySelector('.badge-rank-number');
        if (badge) badge.textContent = `#${index + 1}`;
    });
}
