{{--
    Modal para crear o editar una lista. Al editar suma el botón de eliminar,
    igual que el modal de calificar.

    Props: $list (MediaList|null), $still (URL de un póster para la columna de la derecha)
--}}
@php
    $editing = $list !== null;
@endphp

<button type="button" class="modal-close-dharma" data-bs-dismiss="modal" aria-label="Cerrar">
    <i class="bi bi-x-lg"></i>
</button>

<div class="rate-split-wrapper">
    <div class="rate-split-form-col">
        <div class="rate-modal-heading">
            <div class="rate-modal-eyebrow">
                <span class="rate-modal-badge">Lista</span>
            </div>
            <h4 class="rate-modal-title" id="logModalLabel">{{ $editing ? 'Editar lista' : 'Nueva lista' }}</h4>
        </div>

        {{-- `hx-swap="none"`: al guardar el servidor recarga o redirige (HX-Refresh / HX-Redirect). --}}
        <form
            @if($editing)
                hx-put="{{ route('lists.update', $list) }}"
            @else
                hx-post="{{ route('lists.store') }}"
            @endif
            hx-swap="none"
        >
            @csrf

            <div class="modal-field">
                <label class="modal-form-label" for="list-name">Nombre</label>
                <input
                    type="text"
                    id="list-name"
                    name="name"
                    class="form-control modal-form-control"
                    value="{{ $list?->name }}"
                    maxlength="100"
                    placeholder="Ej: Mi top de Nolan"
                    required
                >
            </div>

            <div class="modal-field">
                <label class="modal-form-label" for="list-description">Descripción</label>
                <textarea
                    id="list-description"
                    name="description"
                    class="form-control modal-form-control modal-textarea"
                    maxlength="1000"
                    placeholder="Opcional: de qué se trata la lista."
                >{{ $list?->description }}</textarea>
            </div>

            <div class="modal-field">
                <div class="form-check form-switch d-inline-flex align-items-center gap-2 spoiler-switch-wrapper">
                    <input class="form-check-input spoiler-switch-input" type="checkbox" role="switch" id="list-ranked" name="is_ranked" value="1" @checked($list?->is_ranked)>
                    <label class="form-check-label spoiler-switch-label" for="list-ranked">
                        Numerada: muestra el puesto de cada título
                    </label>
                </div>
            </div>

            <div class="modal-actions">
                @if($editing)
                    <button
                        type="button"
                        class="btn-cine-danger me-auto"
                        hx-delete="{{ route('lists.destroy', $list) }}"
                        hx-swap="none"
                        hx-confirm="¿Eliminar la lista «{{ $list->name }}»? Los títulos siguen en tu diario y tu watchlist."
                    >
                        <i class="bi bi-trash3 me-1"></i>Eliminar
                    </button>
                @endif

                <button type="button" class="btn-cine-secondary" data-bs-dismiss="modal">Cancelar</button>
                <button type="submit" class="btn-cine-primary">{{ $editing ? 'Guardar' : 'Crear lista' }}</button>
            </div>
        </form>
    </div>

    <div class="rate-split-media-col">
        <div class="split-cinema-still" style="background-image: url('{{ $still }}');" aria-hidden="true"></div>
        <div class="split-seam-gradient" aria-hidden="true"></div>
    </div>
</div>
