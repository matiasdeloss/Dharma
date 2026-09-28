@extends('layouts.app')

@section('title', 'Mi Watchlist - Películas por ver')

@section('content')
<x-page-header
    title="Mi Watchlist"
    subtitle="Películas y series que planeás ver próximamente."
    icon="bi-bookmark-heart"
    :backdrop="$headerBackdrop"
>
    <x-slot:aside>
        <div class="stat-tile-group">
            <div class="stat-tile">
                <div class="stat-tile-value">{{ $stats['total'] }}</div>
                <span class="stat-tile-label">Títulos</span>
            </div>
            <div class="stat-tile">
                <div class="stat-tile-value">{{ $stats['movies'] }}</div>
                <span class="stat-tile-label">Películas</span>
            </div>
            <div class="stat-tile">
                <div class="stat-tile-value text-purple">{{ $stats['series'] }}</div>
                <span class="stat-tile-label">Series</span>
            </div>
            @if($hasProviders)
                <div class="stat-tile">
                    <div class="stat-tile-value text-accent">{{ $stats['available'] }}</div>
                    <span class="stat-tile-label">Puedo ver hoy</span>
                </div>
            @endif
        </div>
    </x-slot:aside>
</x-page-header>

<div class="container pb-5">
    {{-- Con el filtro puesto y nada disponible, la barra (para sacarlo) y el
         aviso de abajo se tienen que ver igual: antes caía en "Tu watchlist
         está vacía". --}}
    @if($watchlist->count() > 0 || $onlyAvailable)
        <div class="filter-bar mb-4">
            <div class="d-flex flex-wrap align-items-center justify-content-between gap-3">
                {{-- Filtro por disponibilidad en las plataformas del usuario --}}
                @if($hasProviders)
                    <a
                        href="{{ $onlyAvailable ? route('watchlist.index') : route('watchlist.index', ['disponible' => 1]) }}"
                        class="wl-filter-toggle {{ $onlyAvailable ? 'is-on' : '' }}"
                        aria-pressed="{{ $onlyAvailable ? 'true' : 'false' }}"
                    >
                        <i class="bi {{ $onlyAvailable ? 'bi-check-circle-fill' : 'bi-circle' }}"></i>
                        Solo lo que puedo ver hoy
                        <span class="wl-filter-count">{{ $stats['available'] }}</span>
                    </a>
                @else
                    <a href="{{ route('settings.edit') }}" class="text-secondary small text-decoration-none">
                        <i class="bi bi-tv me-1 text-accent"></i> Elegí tus plataformas para saber qué podés ver hoy
                    </a>
                @endif

                <div class="d-flex align-items-center gap-3">
                    <span class="text-secondary small">
                        {{ $watchlist->total() }} {{ $watchlist->total() === 1 ? 'título' : 'títulos' }}{{ $onlyAvailable ? ' disponibles' : ' pendientes' }}
                    </span>
                    @if($watchlist->total() > 0)
                        {{-- Sortea un título de la watchlist (o de lo disponible hoy, si el filtro está puesto). --}}
                        <button
                            type="button"
                            class="btn btn-sm btn-outline-secondary"
                            hx-get="{{ route('watchlist.random', array_filter(['disponible' => $onlyAvailable ? 1 : null])) }}"
                            hx-target="#logModalContent"
                            data-bs-toggle="modal"
                            data-bs-target="#logModal"
                        >
                            <i class="bi bi-shuffle me-1"></i> Elegir al azar
                        </button>
                    @endif
                    <a href="{{ route('explore.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-plus-lg me-1"></i> Explorar más
                    </a>
                </div>
            </div>
        </div>

        <div class="row">
            @foreach($watchlist as $item)
                @php($media = $item->mediaItem)

                <div class="col-6 col-md-4 col-lg-3 col-xl-2 mb-4">
                    <div class="movie-card d-flex flex-column h-100">
                        <!-- Póster -->
                        <div class="poster-wrapper position-relative">
                            <a href="{{ route('media.show', ['type' => $media->media_type, 'id' => $media->tmdb_id]) }}" class="d-block w-100 h-100">
                                <img src="{{ $media->poster_url }}" alt="{{ $media->title }}" loading="lazy">
                            </a>

                            <!-- Quitar de la watchlist -->
                            <div class="position-absolute top-0 start-0 z-2">
                                @include('watchlist.partials.ribbon-button', [
                                    'inWatchlist' => true,
                                    'tmdbId' => $media->tmdb_id,
                                    'mediaType' => $media->media_type,
                                    'title' => $media->title,
                                    'posterPath' => $media->poster_path,
                                    'releaseDate' => $media->release_date,
                                    'voteAverage' => $media->vote_average,
                                ])
                            </div>
                        </div>

                        <!-- Cuerpo de la card (misma meta en una linea que media/partials/movie-card) -->
                        <div class="movie-card-info justify-content-between">
                            <div>
                                <div class="movie-card-meta">
                                    <span class="movie-card-tmdb" title="Nota en TMDB">
                                        <i class="bi bi-star-fill"></i>{{ $media->vote_average ? number_format($media->vote_average, 1) : '–' }}
                                    </span>
                                    @if($media->release_year)
                                        <span class="movie-card-sep" aria-hidden="true">·</span>
                                        <span class="movie-card-year">{{ $media->release_year }}</span>
                                    @endif
                                    <span class="diary-chip ms-auto">
                                        {{ $media->media_type === 'tv' ? 'Serie' : 'Película' }}
                                    </span>
                                </div>

                                <a href="{{ route('media.show', ['type' => $media->media_type, 'id' => $media->tmdb_id]) }}" class="movie-card-title text-truncate-2" title="{{ $media->title }}">
                                    {{ $media->title }}
                                </a>

                                @include('watchlist.partials.availability', ['providers' => $item->availableOn])
                                @include('watchlist.partials.item-meta', ['item' => $item])
                            </div>

                            <!-- Acción principal: registrarla como vista -->
                            <div class="mt-2 pt-1">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-cine-secondary btn-pill-compact w-100 d-flex align-items-center justify-content-center gap-1"
                                    hx-get="{{ route('reviews.rate', ['type' => $media->media_type, 'id' => $media->tmdb_id]) }}"
                                    hx-target="#logModalContent"
                                    data-bs-toggle="modal"
                                    data-bs-target="#logModal"
                                >
                                    <i class="bi bi-check2"></i>
                                    <span>Marcar vista</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if($watchlist->count() === 0 && $onlyAvailable)
            <div class="empty-state">
                <i class="bi bi-tv empty-state-icon"></i>
                <h4 class="empty-state-title">Nada de tu lista está en tus plataformas hoy</h4>
                <p class="empty-state-text">
                    Probá sin el filtro, o revisá tus plataformas en <a href="{{ route('settings.edit') }}">Ajustes</a>.
                </p>
            </div>
        @endif

        <div class="d-flex justify-content-center mt-4">
            {{ $watchlist->links() }}
        </div>
    @else
        <div class="empty-state">
            <i class="bi bi-bookmark-dash empty-state-icon"></i>
            <h4 class="empty-state-title">Tu watchlist está vacía</h4>
            <p class="empty-state-text">
                Guardá acá las películas y series que te interesen. Cuando las veas, las marcás
                como vistas y pasan directo a tu diario.
            </p>
            <a href="{{ route('home') }}" class="btn btn-cine-primary px-4 py-2">
                <i class="bi bi-compass me-1"></i> Explorar títulos
            </a>
        </div>
    @endif
</div>
@endsection
