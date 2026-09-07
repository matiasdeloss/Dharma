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
            <div class="stat-tile">
                <div class="stat-tile-value text-accent">{{ $stats['hours'] }}<span class="fs-6">h</span></div>
                <span class="stat-tile-label">Aprox.</span>
            </div>
        </div>
    </x-slot:aside>
</x-page-header>

<div class="container pb-5">
    @if($watchlist->count() > 0)
        <div class="filter-bar mb-4">
            <form action="{{ route('watchlist.index') }}" method="GET" class="row g-2 align-items-center">
                <div class="col-md-4 col-sm-6">
                    <label for="filter-priority" class="visually-hidden">Filtrar por prioridad</label>
                    <select id="filter-priority" name="priority" class="dharma-select w-100" onchange="this.form.submit()">
                        <option value="">Todas las prioridades</option>
                        <option value="high" @selected($filterPriority === 'high')>Alta</option>
                        <option value="medium" @selected($filterPriority === 'medium')>Media</option>
                        <option value="low" @selected($filterPriority === 'low')>Baja</option>
                    </select>
                </div>
                <div class="col-md-8 d-flex align-items-center justify-content-md-end gap-3">
                    <span class="text-secondary small">
                        {{ $stats['total'] }} {{ $stats['total'] === 1 ? 'título pendiente' : 'títulos pendientes' }}
                    </span>
                    @if($filterPriority)
                        <a href="{{ route('watchlist.index') }}" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-x-circle me-1"></i> Limpiar
                        </a>
                    @endif
                    <a href="{{ route('home') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-plus-lg me-1"></i> Explorar más
                    </a>
                </div>
            </form>
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

                        <!-- Cuerpo de la card -->
                        <div class="p-2 d-flex flex-column justify-content-between flex-grow-1 movie-card-info">
                            <div>
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="small fw-bold text-white d-flex align-items-center gap-1">
                                        <i class="bi bi-star-fill text-accent"></i>
                                        <span>{{ $media->vote_average ? number_format($media->vote_average, 1) : '—' }}</span>
                                    </div>
                                    <span class="diary-chip">
                                        {{ $media->media_type === 'tv' ? 'Serie' : 'Película' }}
                                    </span>
                                </div>

                                <a href="{{ route('media.show', ['type' => $media->media_type, 'id' => $media->tmdb_id]) }}" class="text-white text-decoration-none fw-semibold small d-block mb-1 text-truncate-2" title="{{ $media->title }}">
                                    {{ $media->title }}
                                </a>
                                @if($media->release_year)
                                    <div class="text-secondary text-xs">{{ $media->release_year }}</div>
                                @endif

                                @include('watchlist.partials.item-meta', ['item' => $item])
                            </div>

                            <!-- Acción principal: registrarla como vista -->
                            <div class="mt-2 pt-1">
                                <button
                                    type="button"
                                    class="btn btn-sm btn-cine-secondary btn-pill-compact w-100 d-flex align-items-center justify-content-center gap-1"
                                    hx-get="{{ route('reviews.modal', ['type' => $media->media_type, 'id' => $media->tmdb_id]) }}"
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
