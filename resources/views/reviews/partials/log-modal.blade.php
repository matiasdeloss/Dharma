{{--
    Modal de registro del diario.

    Props:
      $media          array con forma de TMDB (o armado desde el MediaItem local)
      $type           'movie' | 'tv'
      $mode           'create' | 'edit'
      $review         Review|null — solo en modo edición
      $existingCount  int — cuántas veces ya registró el usuario este título

    En `create` SIEMPRE nace una entrada nueva: registrar dos veces la misma
    película son dos entradas fechadas, no una que pisa a la otra. Para tocar
    una entrada vieja está `edit`, que apunta a un id concreto.
--}}
@php
    $isEdit = ($mode ?? 'create') === 'edit';
    $existingCount = $existingCount ?? 0;

    $title = $media['title'] ?? $media['name'] ?? 'Título';
    $originalTitle = $media['original_title'] ?? $media['original_name'] ?? null;
    $date = $media['release_date'] ?? $media['first_air_date'] ?? null;
    $year = $date ? substr($date, 0, 4) : null;
    $imageBase = config('services.tmdb.image_base_url', 'https://image.tmdb.org/t/p');
    $stillPath = $media['poster_path'] ?? $media['backdrop_path'] ?? null;
    $still = $stillPath ? $imageBase . '/w500' . $stillPath : asset('images/no-poster.svg');

    $currentRating = $isEdit && $review->rating !== null ? (float) $review->rating : null;
    $currentStatus = $isEdit ? $review->status : 'watched';

    // En edición, la fecha de esa entrada. En un registro nuevo, hoy.
    $currentDate = $isEdit && $review->watched_date
        ? $review->watched_date->format('Y-m-d')
        : date('Y-m-d');

    // Volver a registrar algo ya visto es, por definición, un re-visionado.
    $isRewatch = $isEdit ? $review->is_rewatch : $existingCount > 0;
@endphp

{{-- Botón propio en vez de .btn-close: el filtro invert() de btn-close-white
     también invierte el fondo del botón y lo vuelve un círculo gris claro. --}}
<button type="button" class="modal-close-dharma" data-bs-dismiss="modal" aria-label="Cerrar">
    <i class="bi bi-x-lg"></i>
</button>

<div class="rate-split-wrapper">
    <!-- Izquierda: formulario -->
    <div class="rate-split-form-col">
        <div class="rate-modal-heading">
            <div class="rate-modal-eyebrow">
                <span class="rate-modal-badge">{{ $type === 'tv' ? 'Serie de TV' : 'Película' }}</span>
                @if($year)
                    <span class="rate-modal-year">{{ $year }}</span>
                @endif
            </div>
            <h4 class="rate-modal-title" id="logModalLabel">{{ $title }}</h4>

            @if($isEdit)
                <p class="rate-modal-context">
                    <i class="bi bi-pencil-square"></i>
                    Editando el registro{{ $review->watched_date ? ' del ' . $review->watched_date->translatedFormat('d M Y') : ' sin fecha' }}
                </p>
            @elseif($existingCount > 0)
                <p class="rate-modal-context">
                    <i class="bi bi-arrow-repeat"></i>
                    Ya lo registraste {{ $existingCount }} {{ $existingCount === 1 ? 'vez' : 'veces' }} · esto crea un re-visionado
                </p>
            @endif
        </div>

        {{-- `hx-swap="none"`: la respuesta son puros fragmentos out-of-band. Antes
             apuntaba a #log-action-container, que solo existe en la ficha: desde
             el diario htmx abortaba el swap entero y no se actualizaba nada. --}}
        <form hx-post="{{ $isEdit ? route('reviews.update', $review) : route('reviews.store') }}" hx-swap="none">
            @csrf

            @if($isEdit)
                {{-- El MediaItem ya existe y no se toca al editar: solo viajan
                     los campos de la entrada. --}}
                @method('PATCH')
            @else
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
            @endif

            {{-- 1. Puntaje. Sin la estrella gigante que había antes: el número
                 solo, y debajo la palabra que le corresponde, que dice más. --}}
            <div class="rate-score-block">
                <div class="rate-score-readout">
                    <span class="rate-score-number" id="rateValue">{{ $currentRating !== null ? number_format($currentRating, 1) : '—' }}</span>
                    <span class="rate-score-max">/10</span>
                </div>
                <span class="rate-score-label" id="rateLabel">{{ $isEdit ? ($review->rating_label ?? '') : '' }}</span>

                {{-- Sin nota previa arranca en el medio (5.5), pero el número no
                     se muestra hasta que se toca — así no parece pre-calificada. --}}
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

            {{-- 2. Estado y fecha. Antes los dos iban hardcodeados en hidden
                 (`watched` y la fecha de hoy): no había forma de registrar algo
                 que estás viendo, que abandonaste, o que viste hace dos
                 semanas. --}}
            <div class="modal-field modal-field-split">
                <div>
                    <label class="modal-form-label" for="status">
                        <i class="bi bi-eye me-1"></i> Estado
                    </label>
                    <select name="status" id="status" class="dharma-select w-100">
                        <option value="watched" {{ $currentStatus === 'watched' ? 'selected' : '' }}>Vista</option>
                        <option value="watching" {{ $currentStatus === 'watching' ? 'selected' : '' }}>Viéndola</option>
                        <option value="plan_to_watch" {{ $currentStatus === 'plan_to_watch' ? 'selected' : '' }}>Quiero verla</option>
                        <option value="dropped" {{ $currentStatus === 'dropped' ? 'selected' : '' }}>Abandonada</option>
                    </select>
                </div>
                <div>
                    <label class="modal-form-label" for="watched_date">
                        <i class="bi bi-calendar-event me-1"></i> Fecha
                    </label>
                    <input
                        type="date"
                        name="watched_date"
                        id="watched_date"
                        class="modal-form-control modal-date-input w-100"
                        max="{{ date('Y-m-d') }}"
                        value="{{ $currentDate }}"
                    >
                </div>
            </div>

            <div class="modal-field">
                <div class="form-check form-switch d-inline-flex align-items-center gap-2 rewatch-switch-wrapper">
                    <input class="form-check-input rewatch-switch-input" type="checkbox" role="switch" id="is_rewatch" name="is_rewatch" value="1" {{ $isRewatch ? 'checked' : '' }}>
                    <label class="form-check-label rewatch-switch-label" for="is_rewatch">
                        Es un re-visionado
                    </label>
                </div>
            </div>

            <!-- 3. Reseña pública -->
            <div class="modal-field">
                <label class="modal-form-label" for="review_text">
                    <i class="bi bi-chat-left-text me-1"></i> Reseña pública
                </label>
                <textarea
                    name="review_text"
                    id="review_text"
                    class="form-control modal-form-control modal-textarea"
                    placeholder="¿Qué te pareció? Esto lo puede leer cualquiera."
                >{{ $isEdit ? $review->review_text : '' }}</textarea>

                <div class="form-check form-switch d-inline-flex align-items-center gap-2 spoiler-switch-wrapper">
                    <input class="form-check-input spoiler-switch-input" type="checkbox" role="switch" id="contains_spoilers" name="contains_spoilers" value="1" {{ $isEdit && $review->contains_spoilers ? 'checked' : '' }}>
                    <label class="form-check-label spoiler-switch-label" for="contains_spoilers">
                        Contiene spoilers
                    </label>
                </div>
            </div>

            <!-- 4. Nota privada -->
            <div class="modal-field">
                <label class="modal-form-label" for="private_notes">
                    <i class="bi bi-lock-fill me-1"></i> Nota privada
                </label>
                <textarea
                    name="private_notes"
                    id="private_notes"
                    class="form-control modal-form-control modal-textarea"
                    placeholder="Tus momentos y citas favoritas. Solo las ves vos."
                >{{ $isEdit ? $review->private_notes : '' }}</textarea>
            </div>

            <!-- 5. Acciones -->
            <div class="modal-actions">
                @if($isEdit)
                    {{-- `hx-swap="none"`: el servidor responde con HX-Refresh, asi que
                         no hay nada que insertar aca. --}}
                    <button
                        type="button"
                        class="btn btn-danger-ghost me-auto"
                        hx-delete="{{ route('reviews.destroy', $review) }}"
                        hx-swap="none"
                        hx-confirm="¿Eliminar este registro con su calificación, su reseña y sus notas privadas? No se puede deshacer."
                    >
                        <i class="bi bi-trash3 me-1"></i>Eliminar
                    </button>
                @endif

                <button type="button" class="btn btn-cine-secondary px-4 py-2" data-bs-dismiss="modal">
                    Cancelar
                </button>
                <button type="submit" class="btn btn-cine-primary px-4 py-2 fw-bold">
                    @if($isEdit)
                        Guardar cambios
                    @elseif($existingCount > 0)
                        Registrar de nuevo
                    @else
                        Calificar
                    @endif
                </button>
            </div>
        </form>
    </div>

    <!-- Derecha: fotograma (mismo patrón que /login) -->
    <div class="rate-split-media-col">
        <div class="split-cinema-still" style="background-image: url('{{ $still }}');" aria-hidden="true"></div>
        <div class="split-seam-gradient" aria-hidden="true"></div>
    </div>
</div>
