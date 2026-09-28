// ==========================================================================
// Navbar - alto real y modo transparente sobre el hero
// ==========================================================================

/**
 * Dos responsabilidades:
 *
 * 1. Publicar el alto real del navbar como `--navbar-h`. El hero del home se
 *    mete por debajo del navbar con un margin negativo de esa medida, y
 *    hardcodear un px fijo se rompe apenas cambia la tipografia o el padding.
 *
 * 2. En las paginas que marcan el navbar con `.navbar-over-hero`, sacarle el
 *    fondo mientras estamos arriba de todo (para que el fotograma del hero se
 *    vea de punta a punta) y devolverselo al scrollear, donde ya no hay imagen
 *    detras y el texto necesita su superficie solida.
 */
export function initNavbar() {
    const nav = document.querySelector('.navbar-cine');
    if (!nav) return;

    // El alto solo se mide con el menu colapsado cerrado. En < lg el menu
    // desplegado agranda el nav y la medida deja de servir — por eso el CSS
    // que consume --navbar-h esta limitado a lg en adelante.
    const publishHeight = () => {
        const isExpanded = nav.querySelector('.navbar-collapse.show');
        if (isExpanded) return;

        document.documentElement.style.setProperty(
            '--navbar-h',
            `${Math.round(nav.getBoundingClientRect().height)}px`
        );
    };

    publishHeight();
    window.addEventListener('resize', publishHeight, { passive: true });

    if (!nav.classList.contains('navbar-over-hero')) return;

    const SCROLL_THRESHOLD = 24;
    const syncScrolled = () => {
        nav.classList.toggle('navbar-scrolled', window.scrollY > SCROLL_THRESHOLD);
    };

    syncScrolled();
    window.addEventListener('scroll', syncScrolled, { passive: true });
}
