{{--
    Botón de calificar de la ficha.

    Solo aparece mientras el título NO tiene entrada en tu diario. Una vez
    calificado, la nota vive en la columna del hero con su botón de Editar, y
    acá no va nada — pero el contenedor se mantiene: es destino de un fragmento
    out-of-band de ReviewController::store.

    Props: $review (Review|null), $type, $tmdbId, $oob (bool)
--}}
<div id="log-action-container" class="d-inline-flex flex-wrap align-items-center gap-2" @if($oob ?? false) hx-swap-oob="true" @endif>
    @unless($review)
        <button
            type="button"
            class="btn-cine-primary"
            hx-get="{{ route('reviews.rate', ['type' => $type, 'id' => $tmdbId]) }}"
            hx-target="#logModalContent"
            data-bs-toggle="modal"
            data-bs-target="#logModal"
        >
            <i class="bi bi-star-fill"></i>
            <span>Calificar</span>
        </button>
    @endunless
</div>
