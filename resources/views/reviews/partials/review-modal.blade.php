{{--
    Modal de reseña: texto público, spoilers y nota privada.

    No toca la nota ni la fecha — eso es del modal de calificación. Si el título
    todavía no tiene entrada en el diario, guardar la reseña la crea.

    Props: $media (array TMDB), $type, $review (Review|null)
--}}
@php
    $title = $media['title'] ?? $media['name'] ?? 'Título';
    $originalTitle = $media['original_title'] ?? $media['original_name'] ?? null;
    $date = $media['release_date'] ?? $media['first_air_date'] ?? null;
    $year = $date ? substr($date, 0, 4) : null;
    $imageBase = config('services.tmdb.image_base_url', 'https://image.tmdb.org/t/p');
    $stillPath = $media['poster_path'] ?? $media['backdrop_path'] ?? null;
    $still = $stillPath ? $imageBase . '/w500' . $stillPath : asset('images/no-poster.svg');
    $hasText = $review && ($review->review_text || $review->private_notes);
@endphp

<button type="button" class="modal-close-dharma" data-bs-dismiss="modal" aria-label="Cerrar">
    <i class="bi bi-x-lg"></i>
</button>

<div class="rate-split-wrapper">
    <div class="rate-split-form-col">
        <div class="rate-modal-heading">
            <div class="rate-modal-eyebrow">
                <span class="rate-modal-badge">{{ $type === 'tv' ? 'Serie de TV' : 'Película' }}</span>
                @if($year)
                    <span class="rate-modal-year">{{ $year }}</span>
                @endif
            </div>
            <h4 class="rate-modal-title" id="logModalLabel">{{ $title }}</h4>
            @if($review && $review->rating !== null)
                <p class="rate-modal-context">
                    <i class="bi bi-star-fill"></i>
                    Tu nota: {{ number_format($review->rating, 1) }}/10
                </p>
            @endif
        </div>

        <form hx-post="{{ route('reviews.store') }}" hx-swap="none">
            @csrf
            <input type="hidden" name="form" value="review">
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

            <div class="modal-field">
                <label class="modal-form-label" for="review_text">
                    <i class="bi bi-chat-left-text me-1"></i> Reseña pública
                </label>
                <textarea
                    name="review_text"
                    id="review_text"
                    class="form-control modal-form-control modal-textarea modal-textarea-tall"
                    placeholder="¿Qué te pareció? Esto lo puede leer cualquiera."
                >{{ $review?->review_text }}</textarea>

                <div class="form-check form-switch d-inline-flex align-items-center gap-2 spoiler-switch-wrapper">
                    <input class="form-check-input spoiler-switch-input" type="checkbox" role="switch" id="contains_spoilers" name="contains_spoilers" value="1" @checked($review?->contains_spoilers)>
                    <label class="form-check-label spoiler-switch-label" for="contains_spoilers">
                        Contiene spoilers
                    </label>
                </div>
            </div>

            <div class="modal-field">
                <label class="modal-form-label" for="private_notes">
                    <i class="bi bi-lock-fill me-1"></i> Nota privada
                </label>
                <textarea
                    name="private_notes"
                    id="private_notes"
                    class="form-control modal-form-control modal-textarea"
                    placeholder="Tus momentos y citas favoritas. Solo las ves vos."
                >{{ $review?->private_notes }}</textarea>
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cine-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn-cine-primary">
                    {{ $hasText ? 'Guardar cambios' : 'Publicar reseña' }}
                </button>
            </div>
        </form>
    </div>

    <div class="rate-split-media-col">
        <div class="split-cinema-still" style="background-image: url('{{ $still }}');" aria-hidden="true"></div>
        <div class="split-seam-gradient" aria-hidden="true"></div>
    </div>
</div>
