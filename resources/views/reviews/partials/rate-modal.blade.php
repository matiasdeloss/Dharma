{{--
    Modal de calificación: SOLO la nota y la fecha.

    Cinco estrellas con medias (1 a 10) y el número arriba. Calificar es dar
    por vista, así que no hay estado. La reseña va en otro modal.

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

    $currentRating = $review && $review->rating !== null ? (int) round($review->rating) : null;
    $currentDate = $review && $review->watched_date ? $review->watched_date->format('Y-m-d') : date('Y-m-d');
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
        </div>

        {{-- `hx-swap="none"`: la respuesta son puros fragmentos out-of-band. --}}
        <form hx-post="{{ route('reviews.store') }}" hx-swap="none">
            @csrf
            <input type="hidden" name="form" value="rating">
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

            {{-- El número arriba, las estrellas abajo. El valor real viaja en el
                 hidden; las estrellas son solo la interfaz (ver modules/star-rater.js). --}}
            <div class="rate-score-block star-rater" data-star-rater>
                <div class="rate-score-readout">
                    <span class="rate-score-number" data-star-value>{{ $currentRating !== null ? $currentRating : '—' }}</span>
                    <span class="rate-score-max">/10</span>
                </div>
                <input type="hidden" name="rating" value="{{ $currentRating ?? '' }}" data-star-input required>

                <div class="star-rater-stars" role="radiogroup" aria-label="Calificación de 1 a 5 estrellas, con medias">
                    @for($i = 1; $i <= 5; $i++)
                        <button type="button" class="star-rater-star" data-star="{{ $i }}" aria-label="{{ $i }} {{ $i === 1 ? 'estrella' : 'estrellas' }}">
                            <i class="bi bi-star"></i>
                        </button>
                    @endfor
                </div>
                <span class="star-rater-hint">Clic en la mitad izquierda de una estrella para media</span>
            </div>

            <div class="modal-field">
                <label class="modal-form-label" for="watched_date">
                    <i class="bi bi-calendar-event me-1"></i> La viste el
                </label>
                <input
                    type="date"
                    name="watched_date"
                    id="watched_date"
                    class="form-control modal-form-control"
                    value="{{ $currentDate }}"
                    max="{{ date('Y-m-d') }}"
                >
            </div>

            <div class="modal-actions">
                @if($review)
                    <button
                        type="button"
                        class="btn-cine-danger me-auto"
                        hx-delete="{{ route('reviews.destroy', $review) }}"
                        hx-swap="none"
                        hx-confirm="¿Eliminar tu registro de este título? Se pierden la nota, la reseña y las notas privadas. No se puede deshacer."
                    >
                        <i class="bi bi-trash3 me-1"></i>Eliminar
                    </button>
                @endif

                <button type="button" class="btn-cine-secondary" data-bs-dismiss="modal">Cancelar</button>
                {{-- Deshabilitado hasta que haya estrellas: la nota viaja en un
                     hidden, y el `required` de un hidden no frena el envío.
                     Lo habilita modules/star-rater.js al elegir una nota. --}}
                <button type="submit" class="btn-cine-primary" data-star-submit @disabled($currentRating === null)>
                    {{ $currentRating !== null ? 'Guardar nota' : 'Calificar' }}
                </button>
            </div>
        </form>
    </div>

    <div class="rate-split-media-col">
        <div class="split-cinema-still" style="background-image: url('{{ $still }}');" aria-hidden="true"></div>
        <div class="split-seam-gradient" aria-hidden="true"></div>
    </div>
</div>
