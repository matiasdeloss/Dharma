{{--
    Card de una lista en "Mis listas": los primeros pósters como un mazo
    abierto, el nombre y cuántos títulos tiene. Los huecos (lista con menos de
    cinco) quedan como pósters vacíos para que todas las cards tengan la misma
    forma.

    Props: $list (MediaList con items_count y los primeros items con mediaItem)
--}}
@php
    $posters = $list->items->pluck('mediaItem')->filter()->take(5);
@endphp

<a href="{{ route('lists.show', $list) }}" class="list-card">
    <div class="list-card-posters" aria-hidden="true">
        @foreach($posters as $media)
            <span class="list-card-poster">
                <img src="{{ $media->poster_url }}" alt="" loading="lazy">
            </span>
        @endforeach
        @for($i = $posters->count(); $i < 5; $i++)
            <span class="list-card-poster is-empty"></span>
        @endfor
    </div>

    <h3 class="list-card-title">{{ $list->name }}</h3>

    <div class="list-card-meta">
        <span>{{ $list->items_count }} {{ $list->items_count === 1 ? 'título' : 'títulos' }}</span>
        @if($list->is_ranked)
            <span aria-hidden="true">·</span>
            <span>Numerada</span>
        @endif
    </div>

    @if($list->description)
        <p class="list-card-description">{{ $list->description }}</p>
    @endif
</a>
