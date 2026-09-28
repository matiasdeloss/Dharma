{{--
    Envoltorio out-of-band para una entrada RECIÉN creada.

    Al editar alcanza con `diary-entry` + `hx-swap-oob="true"`, porque la fila
    ya está en el DOM. Al crear no existe todavía: htmx inserta los HIJOS de
    este div al principio de #diary-list. Si la página no es el diario no hay
    target y el fragmento se descarta solo, igual que el resto de los OOB.

    Props: $entry (Review con mediaItem)
--}}
<div hx-swap-oob="afterbegin:#diary-list">
    @include('reviews.partials.diary-entry', ['entry' => $entry, 'oob' => false])
</div>
