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

// Expose on window
window.bootstrap = bootstrap;
window.htmx = htmx;

// Initialize all modules on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    initNavbar();
    initSpoilerGuards();
    initToastSystem();
    initHtmxConfig();
    initPasswordToggle();
    initMediaSliders();

    // Initialize Bootstrap tooltips
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map((tooltipTriggerEl) => new bootstrap.Tooltip(tooltipTriggerEl));
});

// Re-init sliders after HTMX dynamic loads
document.body.addEventListener('htmx:load', () => {
    initMediaSliders();
});
