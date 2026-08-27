@php
    $title = $media['title'] ?? $media['name'] ?? 'Título';
    $originalTitle = $media['original_title'] ?? $media['original_name'] ?? null;
    $date = $media['release_date'] ?? $media['first_air_date'] ?? null;
    $year = $date ? substr($date, 0, 4) : null;
    $imageBase = config('services.tmdb.image_base_url', 'https://image.tmdb.org/t/p');
    $stillPath = $media['poster_path'] ?? $media['backdrop_path'] ?? null;
    $still = $stillPath ? $imageBase . '/w500' . $stillPath : asset('images/no-poster.svg');
    $currentRating = $review && $review->rating !== null ? (float) $review->rating : null;
@endphp

{{-- Botón propio en vez de .btn-close: el filtro invert() de btn-close-white
     también invierte el fondo del botón y lo vuelve un círculo gris claro. --}}
<button type="button" class="rate-modal-close" data-bs-dismiss="modal" aria-label="Cerrar">
    <i class="bi bi-x-lg"></i>
</button>

<div class="rate-split-wrapper">
    <!-- Izquierda: Formulario -->
    <div class="rate-split-form-col">
        <!-- Header: Título con tipo (serie o película) y año -->
        <div class="rate-modal-heading">
            <h4 class="rate-modal-title mb-1" id="logModalLabel">{{ $title }}</h4>
            <div class="rate-modal-meta d-flex justify-content-center align-items-center gap-2">
                <span class="rate-modal-badge">{{ $type === 'tv' ? 'Serie de TV' : 'Película' }}</span>
                @if($year)
                    <span class="rate-modal-year">&bull; {{ $year }}</span>
                @endif
            </div>
        </div>

        <form hx-post="{{ route('reviews.store') }}" hx-target="#log-action-container" hx-swap="outerHTML">
            @csrf
            <input type="hidden" name="tmdb_id" value="{{ $media['id'] }}">
            <input type="hidden" name="media_type" value="{{ $type }}">
            <input type="hidden" name="title" value="{{ $title }}">
            <input type="hidden" name="original_title" value="{{ $originalTitle }}">
            <input type="hidden" name="release_date" value="{{ $date }}">
            <input type="hidden" name="poster_path" value="{{ $media['poster_path'] ?? '' }}">
            <input type="hidden" name="backdrop_path" value="{{ $media['backdrop_path'] ?? '' }}">
            <input type="hidden" name="overview" value="{{ $media['overview'] ?? '' }}">
            <input type="hidden" name="runtime" value="{{ $media['runtime'] ?? ($media['episode_run_time'][0] ?? null) }}">
            <input type="hidden" name="vote_average" value="{{ $media['vote_average'] ?? null }}">

            {{-- Si la calificamos es porque la vimos: sin estado ni re-visionado --}}
            <input type="hidden" name="status" value="watched">
            <input type="hidden" name="is_rewatch" value="0">
            <input type="hidden" name="watched_date" value="{{ $review && $review->watched_date ? $review->watched_date->format('Y-m-d') : date('Y-m-d') }}">

            <!-- 1. Estrella + Número grande + Línea desplazable -->
            <div class="text-center rate-score-block">
                <div class="d-flex align-items-center justify-content-center gap-3 mb-2">
                    <i class="bi bi-star-fill rate-huge-star"></i>
                    <span class="rate-huge-number" id="rateValue">{{ $currentRating !== null ? number_format($currentRating, 1) : '—' }}</span>
                </div>

                <!-- Línea desplazable del 1 al 10. Sin nota previa, arranca en el
                     medio (5.5) pero el número de arriba no se muestra hasta que
                     se toca — así no parece que ya tiene una calificación puesta. -->
                <div class="rate-slider-wrapper">
                    <input
                        type="range"
                        name="rating"
                        id="rateSlider"
                        class="rate-slider"
                        min="1"
                        max="10"
                        step="0.5"
                        value="{{ $currentRating ?? 5.5 }}"
                        data-has-rating="{{ $currentRating !== null ? 'true' : 'false' }}"
                        aria-label="Calificación del 1 al 10"
                    >
                    <div class="rate-scale">
                        <span>1</span>
                        <span>5</span>
                        <span>10</span>
                    </div>
                </div>
            </div>

            <!-- 2. Reseña Pública -->
            <div class="mb-3 text-center">
                <label class="modal-form-label text-accent" for="review_text">
                    <i class="bi bi-chat-left-text me-1"></i> Reseña Pública
                </label>
                <textarea
                    name="review_text"
                    id="review_text"
                    class="form-control modal-form-control modal-textarea"
                    placeholder="¿Qué te pareció la película? Escribe tu opinión..."
                >{{ $review ? $review->review_text : '' }}</textarea>

                <!-- Interruptor tipo Switch estilizado para Spoilers -->
                <div class="form-check form-switch d-inline-flex align-items-center gap-2 mt-2 spoiler-switch-wrapper">
                    <input class="form-check-input spoiler-switch-input" type="checkbox" role="switch" id="contains_spoilers" name="contains_spoilers" value="1" {{ $review && $review->contains_spoilers ? 'checked' : '' }}>
                    <label class="form-check-label spoiler-switch-label" for="contains_spoilers">
                        Contiene spoilers
                    </label>
                </div>
            </div>

            <!-- 3. Comentario Privado -->
            <div class="mb-3 text-center">
                <label class="modal-form-label text-accent" for="private_notes">
                    <i class="bi bi-lock-fill me-1"></i> Comentario Privado
                </label>
                <textarea
                    name="private_notes"
                    id="private_notes"
                    class="form-control modal-form-control modal-textarea"
                    placeholder="Tus notas personales, recuerdos o detalles que solo tú puedes ver..."
                >{{ $review ? $review->private_notes : '' }}</textarea>
            </div>

            <!-- 4. Botones Cancelar y Calificar centrados -->
            <div class="d-flex justify-content-center gap-3 pt-1">
                <button type="button" class="btn btn-cine-secondary px-4 py-2" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-cine-primary px-4 py-2 fw-bold">
                    Calificar
                </button>
            </div>
        </form>
    </div>

    <!-- Derecha: Fotograma de la película (mismo patrón que /login) -->
    <div class="rate-split-media-col">
        <div class="split-cinema-still" style="background-image: url('{{ $still }}');" aria-hidden="true"></div>
        <div class="split-seam-gradient" aria-hidden="true"></div>
    </div>
</div>
