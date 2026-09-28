{{--
    Modal "Agregar a lista": las listas del usuario, cada una con un botón
    para sumar o sacar el título, y abajo una lista nueva que ya lo incluye.

    Props: $media (array TMDB con id), $type, $lists (MediaList con items_count),
           $inLists (ids de las listas donde ya está)
--}}
@php
    $title = $media['title'] ?? $media['name'] ?? 'Título';
    $date = $media['release_date'] ?? $media['first_air_date'] ?? null;
    $year = $date ? substr($date, 0, 4) : null;
    $imageBase = config('services.tmdb.image_base_url', 'https://image.tmdb.org/t/p');
    $stillPath = $media['poster_path'] ?? $media['backdrop_path'] ?? null;
    $still = $stillPath ? $imageBase.'/w500'.$stillPath : asset('images/no-poster.svg');
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
            <p class="rate-modal-context">
                <i class="bi bi-collection"></i> Agregar a una lista
            </p>
        </div>

        @if($lists->isNotEmpty())
            <div class="list-picker">
                @foreach($lists as $list)
                    @include('lists.partials.picker-row', [
                        'list' => $list,
                        'inList' => in_array($list->id, $inLists, true),
                        'media' => $media,
                        'type' => $type,
                    ])
                @endforeach
            </div>
        @endif

        {{-- Lista nueva que ya sale con este título: la respuesta es este mismo modal, con la lista marcada. --}}
        <form class="list-picker-new" hx-post="{{ route('lists.store') }}" hx-target="#logModalContent">
            @csrf
            @include('lists.partials.media-fields', ['media' => $media, 'type' => $type])

            <label class="modal-form-label" for="picker-new-list">
                {{ $lists->isEmpty() ? 'Tu primera lista' : 'O en una lista nueva' }}
            </label>
            <div class="d-flex gap-2">
                <input
                    type="text"
                    id="picker-new-list"
                    name="name"
                    class="form-control modal-form-control"
                    maxlength="100"
                    placeholder="Ej: Maratón de Halloween"
                    required
                >
                <button type="submit" class="btn-cine-primary flex-shrink-0">Crear</button>
            </div>
        </form>

        <div class="modal-actions mt-4">
            <button type="button" class="btn-cine-secondary" data-bs-dismiss="modal">Listo</button>
        </div>
    </div>

    <div class="rate-split-media-col">
        <div class="split-cinema-still" style="background-image: url('{{ $still }}');" aria-hidden="true"></div>
        <div class="split-seam-gradient" aria-hidden="true"></div>
    </div>
</div>
