{{--
    Los títulos de la lista, en orden. Se reemplaza entero al quitar uno
    (cambian los puestos de los de abajo).

    Ordenar arrastrando: modules/list-sorter.js monta SortableJS sobre
    [data-list-sortable]; al soltar, Sortable dispara el evento `end` y htmx
    manda los `items[]` en el orden nuevo del DOM. `hx-include` con selector
    CSS común y no con `find ...`: `find` de htmx trae solo el PRIMER input
    y el servidor recibía un único título.

    Props: $list (MediaList), $items (cards tipo TMDB con entry_id, ver MediaListController::cardsFor)
--}}
<div id="list-items">
    @if(count($items) > 0)
        <div
            class="row list-grid"
            data-list-sortable
            hx-post="{{ route('lists.reorder', $list) }}"
            hx-trigger="end"
            hx-swap="none"
            hx-include="#list-items input[name='items[]']"
        >
            @foreach($items as $item)
                <div class="col-6 col-md-4 col-lg-3 col-xl-2 mb-4 list-item" data-list-item>
                    <input type="hidden" name="items[]" value="{{ $item['entry_id'] }}">
                    <div class="list-item-inner">
                        <button
                            type="button"
                            class="list-remove-btn"
                            hx-delete="{{ route('lists.items.destroy', [$list, $item['entry_id']]) }}"
                            hx-target="#list-items"
                            hx-swap="outerHTML"
                            aria-label="Quitar «{{ $item['title'] }}» de la lista"
                            title="Quitar de la lista"
                        >
                            <i class="bi bi-x-lg"></i>
                        </button>

                        @include('media.partials.movie-card', [
                            'item' => $item,
                            'type' => $item['media_type'],
                            'colClass' => 'h-100',
                            'inWatchlist' => $item['in_watchlist'] ?? false,
                        ] + ($list->is_ranked ? ['rank' => $loop->iteration] : []))
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <div class="empty-state">
            <i class="bi bi-collection empty-state-icon"></i>
            <h4 class="empty-state-title">Esta lista todavía está vacía</h4>
            <p class="empty-state-text">
                Sumale títulos desde la ficha de cualquier película o serie, con «Agregar a lista».
            </p>
            <a href="{{ route('explore.index') }}" class="btn btn-cine-primary px-4 py-2">
                <i class="bi bi-compass me-1"></i> Explorar títulos
            </a>
        </div>
    @endif
</div>
