@extends('layouts.app')

@section('title', 'Mi Watchlist - Películas por ver')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="display-6 fw-extrabold text-white mb-1">
                <i class="bi bi-bookmark-heart text-info me-2"></i>Mi Watchlist
            </h1>
            <p class="text-secondary mb-0">Películas y series que planeas ver próximamente.</p>
        </div>
        <a href="{{ route('home') }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-plus-lg me-1"></i> Explorar más
        </a>
    </div>

    @if($watchlist->count() > 0)
        <div class="row">
            @foreach($watchlist as $item)
                @php
                    $media = $item->mediaItem;
                @endphp
                <div class="col-6 col-md-4 col-lg-3 col-xl-2 mb-4">
                    <div class="movie-card d-flex flex-column h-100">
                        <!-- Poster -->
                        <div class="poster-wrapper position-relative">
                            <a href="{{ route('media.show', ['type' => $media->media_type, 'id' => $media->tmdb_id]) }}" class="d-block w-100 h-100">
                                <img src="{{ $media->poster_url }}" alt="{{ $media->title }}" loading="lazy">
                            </a>

                            <!-- Bookmark / Remove from Watchlist (Top-Left Flush) -->
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

                        <!-- Card Body (IMDb Style) -->
                        <div class="p-2 d-flex flex-column justify-content-between flex-grow-1 movie-card-info">
                            <div>
                                <!-- Rating Row -->
                                <div class="d-flex align-items-center justify-content-between mb-2">
                                    <div class="small fw-bold text-white d-flex align-items-center gap-1">
                                        <i class="bi bi-star-fill text-warning"></i>
                                        <span>{{ $media->vote_average ? number_format($media->vote_average, 1) : '-' }}</span>
                                    </div>
                                    <button 
                                        type="button" 
                                        class="badge-rate-btn d-inline-flex align-items-center gap-1" 
                                        title="Registrar como vista y calificar"
                                        hx-get="{{ route('reviews.modal', ['type' => $media->media_type, 'id' => $media->tmdb_id]) }}"
                                        hx-target="#logModalContent"
                                        data-bs-toggle="modal"
                                        data-bs-target="#logModal"
                                    >
                                        <i class="bi bi-check2 text-success"></i>
                                        <span>Vista</span>
                                    </button>
                                </div>

                                <!-- Title & Year -->
                                <a href="{{ route('media.show', ['type' => $media->media_type, 'id' => $media->tmdb_id]) }}" class="text-white text-decoration-none fw-semibold small d-block mb-1 text-truncate-2" title="{{ $media->title }}">
                                    {{ $media->title }}
                                </a>
                                @if($media->release_year)
                                    <div class="text-secondary text-xs">{{ $media->release_year }}</div>
                                @endif
                            </div>

                            <!-- Mark as watched quick action -->
                            <div class="mt-2 pt-1">
                                <button 
                                    type="button" 
                                    class="btn btn-sm btn-outline-success btn-pill-compact w-100 d-flex align-items-center justify-content-center gap-1"
                                    hx-get="{{ route('reviews.modal', ['type' => $media->media_type, 'id' => $media->tmdb_id]) }}"
                                    hx-target="#logModalContent"
                                    data-bs-toggle="modal"
                                    data-bs-target="#logModal"
                                >
                                    <i class="bi bi-check2"></i>
                                    <span>Marcar Vista</span>
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
        <div class="text-center py-5 bg-dark border border-secondary rounded-4">
            <i class="bi bi-bookmark-dash fs-1 text-secondary mb-3 d-block"></i>
            <h4 class="text-white">Tu Watchlist está vacía</h4>
            <p class="text-secondary mb-4">Agrega películas o series que te interesen para recordar verlas más tarde.</p>
            <a href="{{ route('home') }}" class="btn btn-cine-primary px-4 py-2">
                <i class="bi bi-compass me-1"></i> Explorar Títulos
            </a>
        </div>
    @endif
</div>
@endsection
