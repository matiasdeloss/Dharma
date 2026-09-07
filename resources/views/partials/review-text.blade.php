{{--
    Texto de una reseña AJENA, con guarda de spoilers.

    `contains_spoilers` se venía guardando desde el modal y se ignoraba al
    mostrar: el texto salía completo igual. Acá va desenfocado hasta que el
    lector decide verlo.

    No se usa para la reseña propia (el diario y "Tu reseña" de la ficha): tus
    propios spoilers ya los conocés.

    Props: $review (Review), $limit (int|null)
--}}
@php
    $texto = !empty($limit)
        ? Str::limit($review->review_text, $limit)
        : $review->review_text;
@endphp

@if($review->contains_spoilers)
    <div class="spoiler-guard">
        <p class="diary-review-text spoiler-guard-text">{{ $texto }}</p>
        <button type="button" class="spoiler-reveal">
            <i class="bi bi-eye-slash"></i> Contiene spoilers · mostrar
        </button>
    </div>
@else
    <p class="diary-review-text">{{ $texto }}</p>
@endif
