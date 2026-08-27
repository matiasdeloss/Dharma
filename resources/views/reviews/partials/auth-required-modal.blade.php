<div class="modal-dialog modal-dialog-centered modal-sm">
    <div class="modal-content bg-dark border border-secondary shadow-lg rounded-4 overflow-hidden">
        <div class="modal-header border-0 pb-0">
            <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body text-center p-4 pt-0">
            <div class="mb-3 d-inline-flex p-3 rounded-circle bg-dark-subtle border border-warning">
                <i class="bi bi-lock-fill text-warning fs-3"></i>
            </div>
            <h5 class="fw-bold text-white mb-2">Inicia Sesión</h5>
            <p class="text-secondary small mb-4">
                Necesitas tener una cuenta activa para calificar películas, escribir reseñas y guardar notas privadas en tu diario.
            </p>
            <div class="d-grid gap-2">
                <a href="{{ route('login') }}" class="btn btn-cine-primary py-2 fw-semibold">
                    <i class="bi bi-box-arrow-in-right me-1"></i> Iniciar Sesión
                </a>
                <a href="{{ route('register') }}" class="btn btn-outline-light py-2 fw-semibold">
                    Registrarse Gratis
                </a>
            </div>
        </div>
    </div>
</div>
