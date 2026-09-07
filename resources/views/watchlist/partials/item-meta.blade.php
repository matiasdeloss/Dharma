{{--
    Prioridad y nota de un ítem de la watchlist.

    Se reemplaza entero por HTMX al guardar, así que el id tiene que coincidir
    con el `hx-target` del formulario que vive adentro.

    Props: $item (Watchlist)
--}}
@php
    $labels = ['high' => 'Alta', 'medium' => 'Media', 'low' => 'Baja'];
    $priority = $item->priority ?: 'medium';
@endphp

<div id="watchlist-meta-{{ $item->id }}" class="watchlist-meta">
    <div class="dropdown">
        {{-- `auto-close="outside"`: sin esto Bootstrap cierra el menú al tocar
             el select y no se llega a guardar. --}}
        <button
            type="button"
            class="wl-priority wl-priority-{{ $priority }}"
            data-bs-toggle="dropdown"
            data-bs-auto-close="outside"
            aria-expanded="false"
            aria-label="Prioridad y nota"
        >
            <span class="wl-priority-dot" aria-hidden="true"></span>
            <span>{{ $labels[$priority] }}</span>
            @if($item->notes)
                <i class="bi bi-sticky-fill wl-priority-note" aria-hidden="true"></i>
            @endif
        </button>

        <div class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow-lg wl-edit-menu">
            <form
                hx-patch="{{ route('watchlist.update', $item) }}"
                hx-target="#watchlist-meta-{{ $item->id }}"
                hx-swap="outerHTML"
            >
                <label class="modal-form-label" for="wl-priority-{{ $item->id }}">Prioridad</label>
                <select id="wl-priority-{{ $item->id }}" name="priority" class="dharma-select w-100 mb-3">
                    @foreach($labels as $value => $label)
                        <option value="{{ $value }}" @selected($priority === $value)>{{ $label }}</option>
                    @endforeach
                </select>

                <label class="modal-form-label" for="wl-notes-{{ $item->id }}">Nota</label>
                <textarea
                    id="wl-notes-{{ $item->id }}"
                    name="notes"
                    class="form-control modal-form-control modal-textarea w-100 mb-3"
                    placeholder="¿Por qué la guardaste?"
                >{{ $item->notes }}</textarea>

                <button type="submit" class="btn btn-cine-primary btn-pill-compact w-100">Guardar</button>
            </form>
        </div>
    </div>

    @if($item->notes)
        <p class="wl-note" title="{{ $item->notes }}">{{ Str::limit($item->notes, 60) }}</p>
    @endif
</div>
