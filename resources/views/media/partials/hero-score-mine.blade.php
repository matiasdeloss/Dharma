{{--
    Bloque "Tu nota" del hero de la ficha.

    Vive en un partial porque ReviewController::store lo vuelve a renderizar
    como fragmento out-of-band: al guardar cambia la nota, y sin esto quedaba
    la vieja en pantalla hasta recargar la página.

    Props: $review (Review|null — la entrada calificada más reciente), $type,
           $tmdbId, $oob (bool)
--}}
<div id="hero-score-mine" class="hero-score hero-score-mine" @if($oob ?? false) hx-swap-oob="true" @endif>
    <span class="hero-score-label"><i class="bi bi-journal-check"></i> Tu nota</span>

    {{-- La cifra a la izquierda y la acción a la derecha, en la misma línea. El
         botón dice "Editar" en cuanto existe un registro, aunque todavía no
         tenga nota puesta. --}}
    <div class="hero-score-row">
        <div class="hero-score-body">
            @if($review && $review->rating !== null)
                @php $myStars = $review->star_rating; @endphp
                <div class="hero-score-figure">
                    <span class="hero-score-value">{{ number_format($review->rating, 1) }}</span>
                    <span class="hero-score-scale">/10</span>
                </div>
                <div class="hero-score-stars" aria-hidden="true">
                    @for($i = 1; $i <= 5; $i++)
                        <i class="bi {{ $myStars >= $i ? 'bi-star-fill' : ($myStars >= $i - 0.5 ? 'bi-star-half' : 'bi-star') }}"></i>
                    @endfor
                </div>
            @else
                <div class="hero-score-figure">
                    <span class="hero-score-value hero-score-empty">&mdash;</span>
                </div>
            @endif
        </div>

        {{-- Siempre el modal de calificar: crea la entrada si no existe y
             edita la nota si ya la tiene. --}}
        <button
            type="button"
            class="btn-cine-secondary btn-pill-compact hero-score-cta"
            hx-get="{{ route('reviews.rate', ['type' => $type, 'id' => $tmdbId]) }}"
            hx-target="#logModalContent"
            data-bs-toggle="modal"
            data-bs-target="#logModal"
        >
            <i class="bi {{ $review && $review->rating !== null ? 'bi-pencil-square' : 'bi-star' }}"></i>{{ $review && $review->rating !== null ? 'Editar' : 'Calificar' }}
        </button>
    </div>

    @if($review && $review->rating === null)
        <span class="hero-score-meta">Reseñada, sin nota todavía</span>
    @elseif(! $review)
        <span class="hero-score-meta">Todavía no la calificaste</span>
    @endif
</div>
