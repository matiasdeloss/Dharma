{{--
    Botón de registro de la ficha.

    Props: $entries (Collection<Review>, de la más reciente a la más vieja),
           $type, $tmdbId, $oob (bool)

    Con re-visionados un título puede tener N entradas: el botón principal
    siempre registra una nueva, y el desplegable lista las que ya existen para
    editarlas o borrarlas de a una.
--}}
@php
    $entries = $entries ?? collect();
    $count = $entries->count();
    // "Tu nota" es la de la entrada calificada más reciente: un re-visionado
    // sin nota no borra la nota que ya habías puesto.
    $lastRated = $entries->first(fn ($entry) => $entry->rating !== null);
@endphp

<div id="log-action-container" class="d-inline-flex flex-wrap align-items-center gap-2" @if($oob ?? false) hx-swap-oob="true" @endif>
    @if($count === 0)
        <button
            type="button"
            class="btn btn-cine-primary d-flex align-items-center gap-2"
            hx-get="{{ route('reviews.modal', ['type' => $type, 'id' => $tmdbId]) }}"
            hx-target="#logModalContent"
            data-bs-toggle="modal"
            data-bs-target="#logModal"
        >
            <i class="bi bi-plus-circle-fill"></i>
            <span>Registrar / Calificar</span>
        </button>
    @else
        <div class="btn-group">
            {{-- Acción principal: volver a registrarlo. El modal ya se abre en
                 modo re-visionado porque el controller le pasa el conteo. --}}
            <button
                type="button"
                class="btn btn-outline-success d-flex align-items-center gap-2"
                hx-get="{{ route('reviews.modal', ['type' => $type, 'id' => $tmdbId]) }}"
                hx-target="#logModalContent"
                data-bs-toggle="modal"
                data-bs-target="#logModal"
            >
                <i class="bi bi-check-circle-fill text-success"></i>
                <span>
                    @if($lastRated)
                        Tu nota: <strong>{{ number_format($lastRated->rating, 1) }}/10</strong>
                        <span class="text-warning small">({{ number_format($lastRated->star_rating, 1) }} ★)</span>
                    @else
                        Registrada en tu Diario
                    @endif
                </span>
                <span class="badge bg-secondary-subtle text-secondary small ms-1">
                    {{ $count }} {{ $count === 1 ? 'registro' : 'registros' }}
                </span>
            </button>

            <button
                type="button"
                class="btn btn-outline-success dropdown-toggle dropdown-toggle-split"
                data-bs-toggle="dropdown"
                aria-expanded="false"
            >
                <span class="visually-hidden">Ver mis registros de este título</span>
            </button>

            <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow-lg border border-secondary">
                <li>
                    <button
                        class="dropdown-item"
                        hx-get="{{ route('reviews.modal', ['type' => $type, 'id' => $tmdbId]) }}"
                        hx-target="#logModalContent"
                        data-bs-toggle="modal"
                        data-bs-target="#logModal"
                    >
                        <i class="bi bi-arrow-repeat me-2 text-accent"></i> Registrar de nuevo
                    </button>
                </li>
                <li><hr class="dropdown-divider border-secondary"></li>

                @foreach($entries as $entry)
                    <li class="d-flex align-items-center">
                        <button
                            class="dropdown-item"
                            hx-get="{{ route('reviews.edit', $entry) }}"
                            hx-target="#logModalContent"
                            data-bs-toggle="modal"
                            data-bs-target="#logModal"
                        >
                            <i class="bi bi-pencil-square me-2 text-secondary"></i>
                            {{ $entry->watched_date ? $entry->watched_date->translatedFormat('d M Y') : $entry->status_label }}
                            @if($entry->rating !== null)
                                <span class="text-warning small ms-1">{{ number_format($entry->rating, 1) }}</span>
                            @endif
                            @if($entry->is_rewatch)
                                <i class="bi bi-arrow-repeat ms-1 text-purple small"></i>
                            @endif
                        </button>

                        <form
                            action="{{ route('reviews.destroy', $entry) }}"
                            method="POST"
                            onsubmit="return confirm('¿Eliminar este registro? Se perderán la reseña y las notas privadas de esa entrada.');"
                        >
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="dropdown-item text-danger px-2" aria-label="Eliminar este registro">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
