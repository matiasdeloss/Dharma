<div id="log-action-container" class="d-inline-flex flex-wrap align-items-center gap-2">
    @if($review)
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
                @if($review->rating !== null)
                    Tu nota: <strong>{{ number_format($review->rating, 1) }}/10</strong> 
                    <span class="text-warning small">({{ number_format($review->star_rating, 1) }} ★)</span>
                @else
                    Registrada en tu Diario
                @endif
            </span>
            <span class="badge bg-secondary-subtle text-secondary small ms-1">Editar</span>
        </button>
    @else
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
    @endif
</div>
