{{--
    Una entrada del diario.

    Vive en un partial porque ReviewController::store la re-renderiza como
    fragmento out-of-band: al guardar desde el modal estando en el diario, la
    fila mostraba la nota vieja hasta recargar la página.

    Props: $entry (Review con mediaItem), $oob (bool)
--}}
@php($media = $entry->mediaItem)
@php($mediaUrl = route('media.show', ['type' => $media->media_type, 'id' => $media->tmdb_id]))

<div id="diary-entry-{{ $entry->id }}" class="diary-entry" @if($oob ?? false) hx-swap-oob="true" @endif>
    <div class="row g-3 align-items-start">
        <!-- Póster -->
        <div class="col-auto">
            <a href="{{ $mediaUrl }}">
                <img src="{{ $media->poster_url }}" alt="{{ $media->title }}" class="review-thumb rounded shadow-sm" loading="lazy">
            </a>
        </div>

        <!-- Contenido -->
        <div class="col min-w-0">
            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                <div class="min-w-0">
                    <h5 class="fw-bold mb-1">
                        <a href="{{ $mediaUrl }}" class="text-white text-decoration-none">
                            {{ $media->title }}
                        </a>
                        @if($media->release_year)
                            <span class="text-secondary small fw-normal">({{ $media->release_year }})</span>
                        @endif
                    </h5>

                    <div class="diary-meta">
                        <span class="diary-chip">
                            {{ $media->media_type === 'tv' ? 'Serie' : 'Película' }}
                        </span>
                        @if($entry->watched_date)
                            <span><i class="bi bi-calendar-event me-1"></i>{{ $entry->watched_date->translatedFormat('d M Y') }}</span>
                        @endif
                        @if($entry->review_text)
                            <span class="diary-chip"><i class="bi bi-chat-left-text"></i>Reseñada</span>
                        @endif
                    </div>
                </div>

                <!-- Calificación y acciones -->
                <div class="d-flex align-items-center gap-3 flex-shrink-0">
                    @if($entry->rating !== null)
                        <div class="diary-rating">
                            <div class="diary-rating-value">
                                {{ number_format($entry->rating, 1) }}<span class="diary-rating-max">/10</span>
                            </div>
                            <div class="diary-rating-stars">
                                {{ number_format($entry->star_rating, 1) }} ★
                            </div>
                        </div>
                    @endif

                    <div class="dropdown">
                        <button class="diary-action-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Acciones del registro">
                            <i class="bi bi-three-dots-vertical"></i>
                        </button>
                        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow-lg border border-secondary">
                            <li>
                                <button
                                    class="dropdown-item"
                                    hx-get="{{ route('reviews.rate', ['type' => $media->media_type, 'id' => $media->tmdb_id]) }}"
                                    hx-target="#logModalContent"
                                    data-bs-toggle="modal"
                                    data-bs-target="#logModal"
                                >
                                    <i class="bi bi-star-fill me-2 text-accent"></i> Editar nota y fecha
                                </button>
                            </li>
                            <li>
                                <button
                                    class="dropdown-item"
                                    hx-get="{{ route('reviews.write', ['type' => $media->media_type, 'id' => $media->tmdb_id]) }}"
                                    hx-target="#logModalContent"
                                    data-bs-toggle="modal"
                                    data-bs-target="#logModal"
                                >
                                    <i class="bi bi-chat-left-text me-2 text-accent"></i> {{ $entry->review_text ? 'Editar reseña' : 'Escribir reseña' }}
                                </button>
                            </li>
                            <li>
                                <button
                                    class="dropdown-item"
                                    hx-get="{{ route('lists.picker', ['type' => $media->media_type, 'id' => $media->tmdb_id]) }}"
                                    hx-target="#logModalContent"
                                    data-bs-toggle="modal"
                                    data-bs-target="#logModal"
                                >
                                    <i class="bi bi-collection me-2 text-accent"></i> Agregar a una lista
                                </button>
                            </li>
                            <li>
                                <a class="dropdown-item" href="{{ $mediaUrl }}">
                                    <i class="bi bi-box-arrow-up-right me-2 text-secondary"></i> Ver ficha completa
                                </a>
                            </li>
                            <li><hr class="dropdown-divider border-secondary"></li>
                            <li>
                                <form action="{{ route('reviews.destroy', $entry) }}" method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar este registro? Se perderán la reseña y las notas privadas.');">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="dropdown-item text-danger">
                                        <i class="bi bi-trash3 me-2"></i> Eliminar
                                    </button>
                                </form>
                            </li>
                        </ul>
                    </div>
                </div>
            </div>

            @if($entry->review_text)
                <p class="diary-review-text mb-2">{{ $entry->review_text }}</p>
            @endif

            @if($entry->private_notes)
                <div class="diary-private-note mt-2">
                    <div class="diary-private-note-label">
                        <i class="bi bi-lock-fill"></i> Nota privada
                    </div>
                    <div>{{ $entry->private_notes }}</div>
                </div>
            @endif
        </div>
    </div>
</div>
