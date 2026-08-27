/**
 * Dharma Cinema - Auth Module
 * Interactive Password Visibility Toggle
 */
export function initPasswordToggle() {
    const syncToggleState = (input) => {
        if (!input || !input.id) return;
        const toggleBtn = document.querySelector(`[data-toggle-password="${input.id}"]`);
        if (!toggleBtn) return;

        if (input.value && input.value.length > 0) {
            toggleBtn.classList.add('is-active');
        } else {
            toggleBtn.classList.remove('is-active');
            // Reset to hidden password if user deletes all characters
            input.setAttribute('type', 'password');
            const icon = toggleBtn.querySelector('i');
            if (icon) {
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
            toggleBtn.setAttribute('aria-label', 'Mostrar contraseña');
        }
    };

    // 1. Click toggle handler (only when active)
    document.addEventListener('click', (e) => {
        const toggleBtn = e.target.closest('[data-toggle-password]');
        if (!toggleBtn || !toggleBtn.classList.contains('is-active')) return;

        const targetId = toggleBtn.getAttribute('data-toggle-password');
        const targetInput = document.getElementById(targetId);
        if (!targetInput) return;

        const icon = toggleBtn.querySelector('i');
        const isPassword = targetInput.getAttribute('type') === 'password';

        if (isPassword) {
            targetInput.setAttribute('type', 'text');
            if (icon) {
                icon.classList.remove('bi-eye');
                icon.classList.add('bi-eye-slash');
            }
            toggleBtn.setAttribute('aria-label', 'Ocultar contraseña');
        } else {
            targetInput.setAttribute('type', 'password');
            if (icon) {
                icon.classList.remove('bi-eye-slash');
                icon.classList.add('bi-eye');
            }
            toggleBtn.setAttribute('aria-label', 'Mostrar contraseña');
        }
    });

    // 2. Input change handler to show/hide the toggle as user types or clears
    document.addEventListener('input', (e) => {
        if (e.target.matches('input[name*="password"], input[id*="password"]')) {
            syncToggleState(e.target);
        }
    });

    // 3. Initial check on load (in case browser auto-completes credentials)
    setTimeout(() => {
        document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
            const targetId = btn.getAttribute('data-toggle-password');
            const input = document.getElementById(targetId);
            if (input) syncToggleState(input);
        });
    }, 150);
}
