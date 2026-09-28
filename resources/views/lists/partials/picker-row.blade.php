{{--
    Una lista en el modal "Agregar a lista". El form entero se reemplaza al
    sumar o sacar el título (MediaListController::toggleItem).

    Props: $list (MediaList con items_count), $inList (bool), $media (array TMDB con id), $type
--}}
<form
    id="picker-row-{{ $list->id }}"
    class="list-picker-row"
    hx-post="{{ route('lists.items.toggle', $list) }}"
    hx-target="this"
    hx-swap="outerHTML"
>
    @csrf
    @include('lists.partials.media-fields', ['media' => $media, 'type' => $type])

    <button type="submit" class="list-picker-toggle" aria-pressed="{{ $inList ? 'true' : 'false' }}">
        <i class="bi {{ $inList ? 'bi-check-circle-fill' : 'bi-plus-circle' }}" aria-hidden="true"></i>
        <span class="list-picker-name">{{ $list->name }}</span>
        <span class="list-picker-count">
            {{ $list->items_count }} {{ $list->items_count === 1 ? 'título' : 'títulos' }}
        </span>
    </button>
</form>
