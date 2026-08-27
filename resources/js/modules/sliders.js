/**
 * Media Horizontal Sliders — Manual requestAnimationFrame Animation
 *
 * Native `scrollTo({behavior:'smooth'})` was tried and dropped: whether it actually
 * animates depends on the browser/OS (e.g. Windows "Show animations" accessibility
 * setting can silently force it instant), which we can't detect or control. Driving
 * scrollLeft ourselves via rAF + easing guarantees the motion always happens exactly
 * as coded, everywhere.
 */
export function initMediaSliders() {
    const containers = document.querySelectorAll('.media-slider-container');

    containers.forEach((container) => {
        if (container.dataset.sliderInitialized === 'true') return;
        container.dataset.sliderInitialized = 'true';

        const track = container.querySelector('.media-slider-track');
        const prevBtn = container.querySelector('.slider-nav-prev, .slider-arrow-prev');
        const nextBtn = container.querySelector('.slider-nav-next, .slider-arrow-next');

        if (!track) return;

        let animationId = null;

        const updateButtons = () => {
            if (!prevBtn || !nextBtn) return;
            const maxScroll = track.scrollWidth - track.clientWidth - 4;
            prevBtn.disabled = track.scrollLeft <= 4;
            nextBtn.disabled = track.scrollLeft >= maxScroll;
        };

        function getScrollStep() {
            const firstCard = track.querySelector('.media-slider-col');
            if (!firstCard) return track.clientWidth;

            const style = window.getComputedStyle(track);
            const gap = parseFloat(style.gap) || 18.4;
            const cardPitch = firstCard.offsetWidth + gap;

            // Número exacto de cartas visibles por pantalla
            const visibleCards = Math.max(1, Math.round((track.clientWidth + gap) / cardPitch));
            return visibleCards * cardPitch;
        }

        function easeInOutCubic(t) {
            return t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2;
        }

        function animateTo(target) {
            if (animationId) cancelAnimationFrame(animationId);

            const start = track.scrollLeft;
            const change = target - start;
            const duration = 500;
            const startTime = performance.now();

            track.classList.add('is-sliding');

            function step(now) {
                const elapsed = now - startTime;
                const progress = Math.min(elapsed / duration, 1);

                track.scrollLeft = start + change * easeInOutCubic(progress);

                if (progress < 1) {
                    animationId = requestAnimationFrame(step);
                } else {
                    track.scrollLeft = target;
                    animationId = null;
                    track.classList.remove('is-sliding');
                    updateButtons();
                }
            }

            animationId = requestAnimationFrame(step);
        }

        if (prevBtn) {
            prevBtn.addEventListener('click', () => {
                animateTo(Math.max(0, track.scrollLeft - getScrollStep()));
            });
        }

        if (nextBtn) {
            nextBtn.addEventListener('click', () => {
                const maxScroll = track.scrollWidth - track.clientWidth;
                animateTo(Math.min(maxScroll, track.scrollLeft + getScrollStep()));
            });
        }

        track.addEventListener('scroll', () => {
            if (!animationId) updateButtons();
        }, { passive: true });

        window.addEventListener('resize', updateButtons, { passive: true });

        setTimeout(updateButtons, 100);
    });
}
