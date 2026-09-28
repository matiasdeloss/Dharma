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

            {{-- Plataformas del usuario donde se ve hoy (lo llena Recommender). --}}
            @if(!empty($item['my_providers']))
                <div class="movie-card-providers" title="Disponible en {{ collect($item['my_providers'])->pluck('name')->join(', ', ' y ') }}">
                    @foreach(array_slice($item['my_providers'], 0, 3) as $provider)
                        @if($provider['logo_path'])
                            <img src="https://image.tmdb.org/t/p/w92{{ $provider['logo_path'] }}" alt="{{ $provider['name'] }}" loading="lazy">
                        @endif
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Cuerpo: una linea de meta (nota TMDB · año) con la accion a la
             derecha, y el titulo debajo. Sin fila aparte para el año: la card
             se acorta y se ve mas poster. --}}
        @php $myRating = $item['my_rating'] ?? null; @endphp
        <div class="movie-card-info">
            <div class="movie-card-meta">
                <span class="movie-card-tmdb" title="Nota en TMDB">
                    <i class="bi bi-star-fill"></i>{{ $rating ? number_format($rating, 1) : '–' }}
                </span>
                @if($year)
                    <span class="movie-card-sep" aria-hidden="true">·</span>
                    <span class="movie-card-year">{{ $year }}</span>
                @endif

                {{-- Ya calificada: TU nota, siempre visible (y abre el modal para
                     editarla). Sin nota: "Calificar" en fantasma, que aparece al
                     pasar el mouse; en touch queda siempre. --}}
                <button
                    type="button"
                    class="badge-rate-btn {{ $myRating !== null ? 'is-rated' : '' }}"
                    title="{{ $myRating !== null ? 'Tu nota: ' . number_format($myRating, 1) . ' · editar' : 'Calificar y tomar notas' }}"
                    hx-get="{{ route('reviews.rate', ['type' => $type, 'id' => $id]) }}"
                    hx-target="#logModalContent"
                    data-bs-toggle="modal"
                    data-bs-target="#logModal"
                >
                    @if($myRating !== null)
                        <span class="badge-rate-mine">{{ number_format($myRating, 1) }}</span><span class="badge-rate-scale">/10</span>
                    @else
                        Calificar
                    @endif
                </button>
            </div>

            <a href="{{ route('media.show', ['type' => $type, 'id' => $id]) }}" class="movie-card-title text-truncate-2" title="{{ $title }}">
                {{ $title }}
            </a>
        </div>
    </div>
</div>
