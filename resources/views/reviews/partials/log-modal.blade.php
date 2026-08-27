@php
    $title = $media['title'] ?? $media['name'] ?? 'Título';
    $originalTitle = $media['original_title'] ?? $media['original_name'] ?? null;
    $date = $media['release_date'] ?? $media['first_air_date'] ?? null;
    $year = $date ? substr($date, 0, 4) : null;
    $poster = !empty($media['poster_path'])
        ? config('services.tmdb.image_base_url', 'https://image.tmdb.org/t/p') . '/w300' . $media['poster_path']
        : asset('images/no-poster.svg');
    $currentRating = $review ? (float)$review->rating : null;
@endphp

<div class="modal-header border-secondary">
    <div class="d-flex align-items-center gap-2">
        <i class="bi bi-journal-plus text-success fs-5"></i>
        <h5 class="modal-title fw-bold" id="logModalLabel">
            {{ $review ? 'Editar Reseña & Notas' : 'Registrar en tu Diario' }}
        </h5>
    </div>
    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
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

    <div class="modal-body">
        <div class="row g-4">
            <!-- Left Column: Poster & Quick Info -->
            <div class="col-md-4 text-center">
                <img src="{{ $poster }}" alt="{{ $title }}" class="modal-poster-preview img-fluid mb-3">
                <h6 class="fw-bold text-white mb-1">{{ $title }}</h6>
                @if($year)
                    <span class="text-secondary small">{{ $year }} &bull; {{ $type === 'tv' ? 'Serie de TV' : 'Película' }}</span>
                @endif
            </div>

            <!-- Right Column: Rating, Notes & Review -->
            <div class="col-md-8">
                <!-- Status & Date -->
                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <label class="form-label small fw-semibold text-secondary">Estado</label>
                        <select name="status" class="form-select form-select-sm bg-dark text-white border-secondary">
                            <option value="watched" {{ ($review && $review->status === 'watched') || !$review ? 'selected' : '' }}>✓ Vista</option>
                            <option value="watching" {{ $review && $review->status === 'watching' ? 'selected' : '' }}>En progreso (Viendo)</option>
                            <option value="plan_to_watch" {{ $review && $review->status === 'plan_to_watch' ? 'selected' : '' }}>Por ver</option>
                            <option value="dropped" {{ $review && $review->status === 'dropped' ? 'selected' : '' }}>Abandonada</option>
                        </select>
                    </div>
                    <div class="col-sm-6">
                        <label class="form-label small fw-semibold text-secondary">Fecha en que la viste</label>
                        <input type="date" name="watched_date" class="form-control form-control-sm bg-dark text-white border-secondary" value="{{ $review && $review->watched_date ? $review->watched_date->format('Y-m-d') : date('Y-m-d') }}">
                    </div>
                </div>

                <!-- 1 to 10 Rating (with Star conversion) -->
                <div class="mb-3">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <label class="form-label small fw-semibold text-secondary mb-0">
                            Calificación (1 al 10 / 5 Estrellas)
                        </label>
                        <div class="form-check form-check-inline mb-0">
                            <input class="form-check-input" type="checkbox" id="is_rewatch" name="is_rewatch" value="1" {{ $review && $review->is_rewatch ? 'checked' : '' }}>
                            <label class="form-check-label small text-secondary" for="is_rewatch">
                                <i class="bi bi-arrow-repeat me-1"></i> Re-visionado
                            </label>
                        </div>
                    </div>
                    <select name="rating" class="form-select bg-dark text-white border-secondary">
                        <option value="">Sin calificar</option>
                        <optgroup label="🌟 Obras Maestras & Sobresalientes">
                            <option value="10.0" {{ $currentRating === 10.0 ? 'selected' : '' }}>10 / 10 ★★★★★ — (Obra Maestra)</option>
                            <option value="9.5" {{ $currentRating === 9.5 ? 'selected' : '' }}>9.5 / 10 ★★★★★ — (Casi Perfecta)</option>
                            <option value="9.0" {{ $currentRating === 9.0 ? 'selected' : '' }}>9.0 / 10 ★★★★½ — (Excelente)</option>
                        </optgroup>
                        <optgroup label="👍 Muy Buenas & Buenas">
                            <option value="8.5" {{ $currentRating === 8.5 ? 'selected' : '' }}>8.5 / 10 ★★★★½ — (Muy Buena+)</option>
                            <option value="8.0" {{ $currentRating === 8.0 ? 'selected' : '' }}>8.0 / 10 ★★★★☆ — (Muy Buena)</option>
                            <option value="7.5" {{ $currentRating === 7.5 ? 'selected' : '' }}>7.5 / 10 ★★★½☆ — (Notable)</option>
                            <option value="7.0" {{ $currentRating === 7.0 ? 'selected' : '' }}>7.0 / 10 ★★★½☆ — (Buena)</option>
                        </optgroup>
                        <optgroup label="👌 Interesantes & Regulares">
                            <option value="6.5" {{ $currentRating === 6.5 ? 'selected' : '' }}>6.5 / 10 ★★★☆☆ — (Interesante)</option>
                            <option value="6.0" {{ $currentRating === 6.0 ? 'selected' : '' }}>6.0 / 10 ★★★☆☆ — (Decente / Pasable)</option>
                            <option value="5.5" {{ $currentRating === 5.5 ? 'selected' : '' }}>5.5 / 10 ★★½☆☆ — (Justita)</option>
                            <option value="5.0" {{ $currentRating === 5.0 ? 'selected' : '' }}>5.0 / 10 ★★½☆☆ — (Regular)</option>
                        </optgroup>
                        <optgroup label="👎 Flojas & Malas">
                            <option value="4.0" {{ $currentRating === 4.0 ? 'selected' : '' }}>4.0 / 10 ★★☆☆☆ — (Floja)</option>
                            <option value="3.0" {{ $currentRating === 3.0 ? 'selected' : '' }}>3.0 / 10 ★½☆☆☆ — (Mala)</option>
                            <option value="2.0" {{ $currentRating === 2.0 ? 'selected' : '' }}>2.0 / 10 ★☆☆☆☆ — (Muy Mala)</option>
                            <option value="1.0" {{ $currentRating === 1.0 ? 'selected' : '' }}>1.0 / 10 ½☆☆☆☆ — (Pésima)</option>
                        </optgroup>
                    </select>
                </div>

                <!-- Public Review Text -->
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-secondary">
                        <i class="bi bi-chat-left-text me-1"></i> Reseña Pública
                    </label>
                    <textarea name="review_text" rows="3" class="form-control bg-dark text-white border-secondary" placeholder="¿Qué te pareció la dirección, actuaciones, guión o fotografía?">{{ $review ? $review->review_text : '' }}</textarea>
                    <div class="form-check mt-1">
                        <input class="form-check-input" type="checkbox" id="contains_spoilers" name="contains_spoilers" value="1" {{ $review && $review->contains_spoilers ? 'checked' : '' }}>
                        <label class="form-check-label small text-secondary" for="contains_spoilers">
                            Contiene spoilers
                        </label>
                    </div>
                </div>

                <!-- Private Notes -->
                <div class="mb-3">
                    <label class="form-label small fw-semibold text-warning">
                        <i class="bi bi-lock-fill me-1"></i> Notas Privadas (Solo visibles para ti)
                    </label>
                    <textarea name="private_notes" rows="2" class="form-control bg-dark text-white border-secondary" placeholder="Anota con quién la viste, recuerdos personales, citas favoritas o detalles para recordar...">{{ $review ? $review->private_notes : '' }}</textarea>
                </div>
            </div>
        </div>
    </div>

    <div class="modal-footer border-secondary">
        <button type="button" class="btn btn-cine-secondary" data-bs-dismiss="modal">Cancelar</button>
        <button type="submit" class="btn btn-cine-primary">
            <i class="bi bi-check-lg me-1"></i> {{ $review ? 'Actualizar Registro' : 'Guardar en mi Diario' }}
        </button>
    </div>
</form>
