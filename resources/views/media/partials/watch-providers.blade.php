<div class="card bg-dark border-secondary p-4 rounded-4 mb-4 shadow-sm">
    <div class="d-flex justify-content-between align-items-center mb-3 border-bottom border-secondary pb-2">
        <h5 class="fw-bold text-white mb-0 d-flex align-items-center gap-2">
            <i class="bi bi-tv text-accent"></i>
            <span>¿Dónde Ver?</span>
        </h5>
        <span class="badge bg-dark border border-secondary text-secondary small">
            <i class="bi bi-geo-alt me-1"></i>{{ $watchProviders['region'] ?? 'AR' }}
        </span>
    </div>

    @if(!empty($watchProviders['has_providers']))
        <!-- Streaming Flatrate (Subscription) -->
        @if(!empty($watchProviders['flatrate']))
            <div class="mb-3">
                <label class="text-xs text-secondary text-uppercase fw-bold d-block mb-2">Streaming (Suscripción):</label>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($watchProviders['flatrate'] as $provider)
                        <div class="provider-pill" data-bs-toggle="tooltip" title="{{ $provider['provider_name'] }}">
                            <img src="https://image.tmdb.org/t/p/w92{{ $provider['logo_path'] }}" alt="{{ $provider['provider_name'] }}" class="provider-logo" loading="lazy">
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Rent (Alquiler) -->
        @if(!empty($watchProviders['rent']))
            <div class="mb-3">
                <label class="text-xs text-secondary text-uppercase fw-bold d-block mb-2">Alquiler Digital:</label>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($watchProviders['rent'] as $provider)
                        <div class="provider-pill" data-bs-toggle="tooltip" title="{{ $provider['provider_name'] }}">
                            <img src="https://image.tmdb.org/t/p/w92{{ $provider['logo_path'] }}" alt="{{ $provider['provider_name'] }}" class="provider-logo" loading="lazy">
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- Buy (Compra) -->
        @if(!empty($watchProviders['buy']))
            <div class="mb-2">
                <label class="text-xs text-secondary text-uppercase fw-bold d-block mb-2">Compra Digital:</label>
                <div class="d-flex flex-wrap gap-2">
                    @foreach($watchProviders['buy'] as $provider)
                        <div class="provider-pill" data-bs-toggle="tooltip" title="{{ $provider['provider_name'] }}">
                            <img src="https://image.tmdb.org/t/p/w92{{ $provider['logo_path'] }}" alt="{{ $provider['provider_name'] }}" class="provider-logo" loading="lazy">
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        <!-- JustWatch Attribution Link -->
        @if(!empty($watchProviders['link']))
            <div class="mt-3 pt-2 border-top border-secondary border-opacity-25 text-end">
                <a href="{{ $watchProviders['link'] }}" target="_blank" rel="noopener noreferrer" class="text-xs text-secondary text-decoration-none">
                    Datos por <strong class="text-white">JustWatch</strong> <i class="bi bi-box-arrow-up-right ms-1"></i>
                </a>
            </div>
        @endif
    @else
        <div class="text-center py-3 text-secondary">
            <i class="bi bi-film fs-3 d-block mb-2 opacity-50"></i>
            <p class="small mb-0">No disponible en streaming en tu región actualmente.</p>
        </div>
    @endif
</div>
