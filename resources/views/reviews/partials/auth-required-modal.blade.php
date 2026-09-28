<div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content modal-content-dharma position-relative">
        <button type="button" class="modal-close-dharma" data-bs-dismiss="modal" aria-label="Cerrar">
            <i class="bi bi-x-lg"></i>
        </button>

        <div class="auth-modal-body">
            <span class="auth-modal-icon">
                <i class="bi bi-lock-fill"></i>
            </span>

            <h5 class="auth-modal-title">Iniciá sesión</h5>
            <p class="auth-modal-text">
                Necesitás una cuenta para calificar títulos, escribir reseñas y guardar
                notas privadas en tu diario.
            </p>

            <div class="d-grid gap-2">
                <a href="{{ route('login') }}" class="btn btn-cine-primary py-2 fw-semibold">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Iniciar sesión
                </a>
                <a href="{{ route('register') }}" class="btn btn-cine-secondary py-2 fw-semibold">
                    Crear cuenta gratis
                </a>
            </div>
        </div>
    </div>
</div>
