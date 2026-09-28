{{--
    Nota personal de un ítem de la watchlist ("¿por qué la guardaste?").

    Se reemplaza entero por HTMX al guardar, así que el id tiene que coincidir
    con el `hx-target` del formulario que vive adentro.

    Props: $item (Watchlist)
--}}
<div id="watchlist-meta-{{ $item->id }}" class="watchlist-meta">
    <div class="dropdown">
        {{-- `auto-close="outside"`: sin esto Bootstrap cierra el menú al tocar
             el textarea y no se llega a guardar. --}}
        <button
            type="button"
            class="wl-note-toggle {{ $item->notes ? 'has-note' : '' }}"
            data-bs-toggle="dropdown"
            data-bs-auto-close="outside"
            aria-expanded="false"
            aria-label="{{ $item->notes ? 'Editar nota' : 'Agregar nota' }}"
        >
            <i class="bi {{ $item->notes ? 'bi-sticky-fill' : 'bi-sticky' }}" aria-hidden="true"></i>
            <span>{{ $item->notes ? 'Nota' : 'Agregar nota' }}</span>
        </button>

        <div class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow-lg wl-edit-menu">
            <form
                hx-patch="{{ route('watchlist.update', $item) }}"
                hx-target="#watchlist-meta-{{ $item->id }}"
                hx-swap="outerHTML"
            >
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
