// ==========================================================================
// Main JavaScript Entry - Dharma Cinema Platform
// ==========================================================================

import * as bootstrap from 'bootstrap';
import htmx from 'htmx.org';
import { initToastSystem } from './modules/toast.js';
import { initHtmxConfig } from './modules/htmx-config.js';
import { initPasswordToggle } from './modules/auth.js';
import { initMediaSliders } from './modules/sliders.js';
import { initNavbar } from './modules/navbar.js';
import { initSpoilerGuards } from './modules/spoilers.js';
import { initTrailerModal } from './modules/trailer.js';
import { initStarRater } from './modules/star-rater.js';
import { initPersonPanel } from './modules/person-panel.js';
import { initListSorter } from './modules/list-sorter.js';

// Expose on window
window.bootstrap = bootstrap;
window.htmx = htmx;

// Initialize all modules on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    initNavbar();
    initSpoilerGuards();
    initTrailerModal();
    initStarRater();
    initPersonPanel();
    initToastSystem();
    initHtmxConfig();
    initPasswordToggle();
    initMediaSliders();
    initListSorter();

    // Initialize Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map((tooltipTriggerEl) => new bootstrap.Tooltip(tooltipTriggerEl));
});

// Re-init sliders after HTMX dynamic loads
document.body.addEventListener('htmx:load', (e) => {
    initMediaSliders();
    initListSorter(e.target);
});
