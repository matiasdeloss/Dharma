{{--
    Modal "Elegir al azar": un título de la watchlist sorteado
    (WatchlistController::random). "Elegir otro" vuelve a sortear sin
    repetir el que está en pantalla.

    Props: $item (Watchlist con mediaItem y availableOn, o null), $onlyAvailable, $canReroll
--}}
@php
    $media = $item?->mediaItem;
@endphp

<button type="button" class="modal-close-dharma" data-bs-dismiss="modal" aria-label="Cerrar">
    <i class="bi bi-x-lg"></i>
</button>

@if(! $media)
    <div class="auth-modal-body">
        <span class="auth-modal-icon"><i class="bi bi-shuffle"></i></span>
        <h4 class="auth-modal-title" id="logModalLabel">No hay nada para sortear</h4>
        <p class="auth-modal-text">
            {{ $onlyAvailable ? 'Nada de tu watchlist está hoy en tus plataformas.' : 'Tu watchlist está vacía.' }}
        </p>
        <button type="button" class="btn-cine-secondary" data-bs-dismiss="modal">Cerrar</button>
    </div>
@else
    @php
        $mediaUrl = route('media.show', ['type' => $media->media_type, 'id' => $media->tmdb_id]);
    @endphp
    <div class="rate-split-wrapper">
        <div class="rate-split-form-col">
            <div class="rate-modal-heading">
                <div class="rate-modal-eyebrow">
                    <span class="rate-modal-badge">{{ $media->media_type === 'tv' ? 'Serie de TV' : 'Película' }}</span>
                    @if($media->release_year)
                        <span class="rate-modal-year">{{ $media->release_year }}</span>
                    @endif
                </div>
                <h4 class="rate-modal-title" id="logModalLabel">{{ $media->title }}</h4>
                <p class="rate-modal-context">
                    <i class="bi bi-shuffle"></i>
                    Elegido al azar de tu watchlist{{ $onlyAvailable ? ', entre lo que podés ver hoy' : '' }}
                </p>
            </div>

            {{-- Bajo lg la columna del fotograma se oculta: el póster va acá. --}}
            <img src="{{ $media->poster_url }}" alt="{{ $media->title }}" class="random-pick-poster d-lg-none">

            @if($media->overview)
                <p class="diary-review-text mb-3">{{ Str::limit($media->overview, 280) }}</p>
            @endif

            @include('watchlist.partials.availability', ['providers' => $item->availableOn])

            @if($item->notes)
                <p class="wl-note mt-2" title="Tu nota">{{ $item->notes }}</p>
            @endif

            <div class="modal-actions mt-3">
                @if($canReroll)
                    <button
                        type="button"
                        class="btn-cine-secondary"
                        hx-get="{{ route('watchlist.random', array_filter(['excepto' => $item->id, 'disponible' => $onlyAvailable ? 1 : null])) }}"
                        hx-target="#logModalContent"
                    >
                        <i class="bi bi-shuffle"></i> Elegir otro
                    </button>
                @endif
                <a href="{{ $mediaUrl }}" class="btn-cine-primary">Ver ficha</a>
            </div>
        </div>

        <div class="rate-split-media-col">
            <div class="split-cinema-still" style="background-image: url('{{ $media->poster_url }}');" aria-hidden="true"></div>
            <div class="split-seam-gradient" aria-hidden="true"></div>
        </div>
    </div>
@endif
