@php
    $id = $item['id'] ?? $item['tmdb_id'] ?? 0;
    $title = $item['title'] ?? $item['name'] ?? 'Sin título';
    $type = $item['media_type'] ?? ($type ?? 'movie');
    $date = $item['release_date'] ?? $item['first_air_date'] ?? null;
    $year = $date ? substr($date, 0, 4) : null;
    $posterPath = $item['poster_path'] ?? null;
    $poster = $posterPath 
        ? config('services.tmdb.image_base_url', 'https://image.tmdb.org/t/p') . '/w500' . $posterPath
        : asset('images/no-poster.svg');
    $rating = $item['vote_average'] ?? null;
@endphp

<div class="{{ $colClass ?? 'col-6 col-md-4 col-lg-3 col-xl-2 mb-4' }}">
    <div class="movie-card d-flex flex-column h-100">
        <!-- Top Poster Section -->
        <div class="poster-wrapper position-relative">
            <a href="{{ route('media.show', ['type' => $type, 'id' => $id]) }}" class="d-block w-100 h-100">
                <img src="{{ $poster }}" alt="{{ $title }}" loading="lazy">
            </a>

            <!-- Quick Watchlist Tab (Top-Left Flush) -->
            <div class="position-absolute top-0 start-0 z-2">
                @include('watchlist.partials.ribbon-button', [
                    'inWatchlist' => $inWatchlist ?? ($item['in_watchlist'] ?? false),
                    'tmdbId' => $id,
                    'mediaType' => $type,
                    'title' => $title,
                    'posterPath' => $posterPath,
                    'releaseDate' => $date,
                    'voteAverage' => $rating,
                ])
            </div>

            @if(isset($rank))
                <div class="position-absolute bottom-0 end-0 z-2 m-2">
                    <span class="badge-rank-number">#{{ $rank }}</span>
                </div>
            @endif
        </div>

        <!-- Bottom Body Section (Clean & Compact) -->
        <div class="p-2 d-flex flex-column justify-content-between flex-grow-1 movie-card-info">
            <div>
                <!-- Rating Row (TMDB Score + Clear "Calificar" Badge Button) -->
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <div class="small fw-bold text-white d-flex align-items-center gap-1">
                        <i class="bi bi-star-fill text-warning"></i>
                        <span>{{ $rating ? number_format($rating, 1) : '-' }}</span>
                    </div>

                    <button 
                        type="button" 
                        class="badge-rate-btn d-inline-flex align-items-center gap-1" 
                        title="Calificar y tomar notas"
                        hx-get="{{ route('reviews.modal', ['type' => $type, 'id' => $id]) }}"
                        hx-target="#logModalContent"
                        data-bs-toggle="modal"
                        data-bs-target="#logModal"
                    >
                        <i class="bi bi-star text-warning"></i>
                        <span>Calificar</span>
                    </button>
                </div>

                <!-- Title & Year -->
                <a href="{{ route('media.show', ['type' => $type, 'id' => $id]) }}" class="text-white text-decoration-none fw-semibold small d-block text-truncate-2 mb-1" title="{{ $title }}">
                    {{ $title }}
                </a>
                @if($year)
                    <div class="text-secondary text-xs">{{ $year }}</div>
                @endif
            </div>
        </div>
    </div>
</div>
