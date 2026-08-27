@extends('layouts.app')

@php
    $title = $media['title'] ?? $media['name'] ?? 'Título';
    $originalTitle = $media['original_title'] ?? $media['original_name'] ?? null;
    $date = $media['release_date'] ?? $media['first_air_date'] ?? null;
    $year = $date ? substr($date, 0, 4) : null;
    $posterPath = $media['poster_path'] ?? null;
    $poster = $posterPath
        ? config('services.tmdb.image_base_url', 'https://image.tmdb.org/t/p') . '/w500' . $posterPath
        : asset('images/no-poster.svg');
    $backdropPath = $media['backdrop_path'] ?? null;
    $backdrop = $backdropPath
        ? config('services.tmdb.image_base_url', 'https://image.tmdb.org/t/p') . '/original' . $backdropPath
        : null;
    
    // Find director
    $director = null;
    if (isset($media['credits']['crew'])) {
        foreach ($media['credits']['crew'] as $crew) {
            if ($crew['job'] === 'Director') {
                $director = $crew['name'];
                break;
            }
        }
    }

    // Cast members
    $cast = array_slice($media['credits']['cast'] ?? [], 0, 6);

    // Trailer video
    $trailer = null;
    if (isset($media['videos']['results'])) {
        foreach ($media['videos']['results'] as $video) {
            if ($video['site'] === 'YouTube' && ($video['type'] === 'Trailer' || $video['type'] === 'Teaser')) {
                $trailer = $video['key'];
                break;
            }
        }
    }
@endphp

@section('title', $title . ($year ? " ({$year})" : ''))

@section('content')
<!-- Fotograma Superior Limpio (Letterboxd Style - Cero texto adentro) -->
@if($backdrop)
    <div class="media-backdrop-cinema" style="background-image: url('{{ $backdrop }}');"></div>
@else
    <div class="bg-dark py-4 border-bottom border-secondary"></div>
@endif

<!-- Fila Principal de Información (Letterboxd 3 Columnas) -->
<div class="container media-content-wrapper mb-5">
    <div class="row g-4 g-lg-5 align-items-start">
        <!-- Col 1: Póster Principal -->
        <div class="col-md-4 col-lg-3 text-center text-md-start">
            <img src="{{ $poster }}" alt="{{ $title }}" class="detail-poster img-fluid">
        </div>

        <!-- Col 2: Información Central (Título, Director, Sinopsis, Géneros, Streaming) -->
        <div class="col-md-8 col-lg-6">
            <!-- Título Principal -->
            <h1 class="display-6 fw-extrabold text-white mb-2">{{ $title }}</h1>

            <!-- Metadatos: Año, Director, Duración -->
            <div class="d-flex flex-wrap align-items-center gap-2 text-secondary mb-2">
                @if($year)
                    <span class="text-white fw-bold fs-6">{{ $year }}</span>
                @endif

                @if($director)
                    <span>· Dirigida por <strong class="text-white">{{ $director }}</strong></span>
                @endif

                @if(!empty($media['runtime']))
                    <span>· <i class="bi bi-clock me-1"></i>{{ floor($media['runtime'] / 60) }}h {{ $media['runtime'] % 60 }}m</span>
                @endif
            </div>

            @if($originalTitle && $originalTitle !== $title)
                <div class="text-secondary small mb-3">Título original: <em>{{ $originalTitle }}</em></div>
            @endif

            @if(!empty($media['tagline']))
                <p class="text-uppercase text-secondary small fw-bold tracking-wider mb-3">"{{ $media['tagline'] }}"</p>
            @endif

            <!-- Sinopsis Directa en Columna Central -->
            <div class="mb-4">
                <h6 class="text-xs text-uppercase fw-bold text-secondary tracking-wider mb-2">Sinopsis</h6>
                <p class="text-light-emphasis lh-base mb-0 fs-6">
                    {{ $media['overview'] ?: 'No hay sinopsis disponible en español para este título.' }}
                </p>
            </div>

            <!-- Géneros -->
            @if(isset($media['genres']) && count($media['genres']) > 0)
                <div class="d-flex flex-wrap align-items-center gap-2 mb-4">
                    <span class="badge-media-type">
                        {{ $type === 'tv' ? 'Serie de TV' : 'Película' }}
                    </span>
                    @foreach($media['genres'] as $genre)
                        <span class="badge-genre">{{ $genre['name'] }}</span>
                    @endforeach
                </div>
            @endif

            <!-- Streaming ("Disponible en:" - Siempre visible) -->
            <div class="pt-3 border-top border-secondary border-opacity-25 mb-3">
                <div class="d-flex align-items-center flex-wrap gap-2">
                    <span class="small text-white fw-bold d-flex align-items-center gap-1 me-1">
                        <i class="bi bi-tv text-accent"></i> Disponible en:
                    </span>
                    @if(!empty($watchProviders['has_providers']))
                        @php
                            $allPills = collect($watchProviders['flatrate'] ?? [])
                                ->concat($watchProviders['rent'] ?? [])
                                ->unique('provider_id')
                                ->values();
                        @endphp
                        @foreach($allPills as $provider)
                            <div class="provider-pill-inline" data-bs-toggle="tooltip" title="{{ $provider['provider_name'] }}">
                                <img src="https://image.tmdb.org/t/p/w92{{ $provider['logo_path'] }}" alt="{{ $provider['provider_name'] }}" loading="lazy">
                            </div>
                        @endforeach

                        @if(!empty($watchProviders['link']))
                            <a href="{{ $watchProviders['link'] }}" target="_blank" rel="noopener noreferrer" class="small text-secondary ms-1 text-decoration-none">
                                <span class="badge bg-dark border border-secondary text-secondary">
                                    JustWatch <i class="bi bi-box-arrow-up-right ms-1"></i>
                                </span>
                            </a>
                        @endif
                    @else
                        <span class="text-secondary small fst-italic">
                            No disponible actualmente en plataformas de streaming para tu región.
                        </span>
                    @endif
                </div>
            </div>

            <!-- Botones de Acción (Directamente abajo de Dónde Ver) -->
            <div class="d-flex flex-wrap align-items-center gap-2 pt-2">
                <!-- Log / Review Button (HTMX Loaded) -->
                @include('reviews.partials.log-button-state', [
                    'review' => $userReview,
                    'type' => $type,
                    'tmdbId' => $media['id']
                ])

                <!-- Watchlist Toggle Button (HTMX) -->
                @include('watchlist.partials.toggle-button', [
                    'inWatchlist' => $inWatchlist,
                    'tmdbId' => $media['id'],
                    'mediaType' => $type,
                    'title' => $title,
                    'posterPath' => $media['poster_path'] ?? '',
                    'releaseDate' => $date ?? '',
                    'voteAverage' => $media['vote_average'] ?? null,
                ])

                @if($trailer)
                    <button type="button" class="btn btn-outline-danger d-flex align-items-center gap-2" data-bs-toggle="modal" data-bs-target="#trailerModal">
                        <i class="bi bi-play-circle-fill"></i> Ver Trailer
                    </button>
                @endif
            </div>
        </div>

        <!-- Col 3: Panel Lateral Unificado (Sin card dentro de card) -->
        <div class="col-lg-3">
            <div class="media-sidebar-panel">
                <!-- Encabezado de Calificaciones -->
                <div class="text-xs text-uppercase fw-bold text-secondary mb-2 pb-2 border-bottom border-secondary border-opacity-25 d-flex align-items-center justify-content-between">
                    <span>Calificaciones</span>
                    <i class="bi bi-star-half text-accent"></i>
                </div>

                <!-- Tira de Notas (Limpia y plana, sin cajitas anidadas) -->
                @include('media.partials.ratings-strip')

                <!-- Tu Nota / Registro Personal (Integrado sin card dentro de card) -->
                @if($userReview)
                    <div class="pt-3 mt-3 border-top border-secondary border-opacity-25">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-journal-check text-success"></i>
                                <span class="text-xs text-uppercase fw-bold text-success tracking-wider">Tu Nota</span>
                            </div>
                            @if($userReview->rating !== null)
                                <div class="text-end">
                                    <span class="text-warning fw-extrabold fs-5">{{ number_format($userReview->rating, 1) }}</span>
                                    <span class="text-secondary small">/10</span>
                                    <span class="text-warning small ms-1">({{ number_format($userReview->star_rating, 1) }} ★)</span>
                                </div>
                            @endif
                        </div>

                        <div class="text-secondary small mb-2" style="font-size: 0.78rem;">
                            <span>Vista: <strong class="text-white">{{ $userReview->watched_date ? $userReview->watched_date->format('d/m/Y') : 'Sin fecha' }}</strong></span>
                            @if($userReview->is_rewatch)
                                <span class="badge bg-secondary ms-1 py-0 px-1" style="font-size: 0.68rem;">Rewatch</span>
                            @endif
                        </div>

                        @if($userReview->review_text)
                            <p class="text-light-emphasis small fst-italic mb-2" style="font-size: 0.82rem;">
                                "{{ Str::limit($userReview->review_text, 140) }}"
                            </p>
                        @endif

                        @if($userReview->private_notes)
                            <div class="p-2 rounded-2 bg-dark text-light-emphasis small border border-secondary border-opacity-25" style="font-size: 0.75rem;">
                                <span class="text-warning fw-semibold"><i class="bi bi-lock-fill me-1"></i>Privado:</span>
                                {{ Str::limit($userReview->private_notes, 80) }}
                            </div>
                        @endif
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>

<div class="container py-2 mb-5">
    <div class="row g-5">
        <!-- Main Details Column -->
        <div class="col-lg-8">
            <!-- Cast Members -->
            @if(count($cast) > 0)
                <div class="mb-5">
                    <h4 class="fw-bold text-white mb-3">Reparto Principal</h4>
                    <div class="row g-3">
                        @foreach($cast as $actor)
                            @php
                                $actorImage = !empty($actor['profile_path'])
                                    ? config('services.tmdb.image_base_url', 'https://image.tmdb.org/t/p') . '/w185' . $actor['profile_path']
                                    : asset('images/no-poster.svg');
                            @endphp
                            <div class="col-6 col-sm-4 col-md-4">
                                <div class="d-flex align-items-center gap-3 p-2 rounded-3 bg-dark border border-secondary">
                                    <img src="{{ $actorImage }}" alt="{{ $actor['name'] }}" class="cast-avatar">
                                    <div class="min-w-0">
                                        <div class="fw-bold text-white small text-truncate">{{ $actor['name'] }}</div>
                                        <div class="text-secondary small text-truncate">{{ $actor['character'] }}</div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- Community Reviews -->
            <div>
                <h4 class="fw-bold text-white mb-3">
                    <i class="bi bi-chat-square-quote text-success me-2"></i>Reseñas de la Comunidad
                </h4>
                @forelse($communityReviews as $comReview)
                    <div class="card bg-dark border-secondary p-3 mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi bi-person-circle text-secondary fs-5"></i>
                                <strong class="text-white">{{ $comReview->user->name }}</strong>
                            </div>
                            @if($comReview->rating !== null)
                                <div class="text-warning fw-bold small text-end">
                                    <span>{{ number_format($comReview->rating, 1) }}/10</span>
                                    <span class="text-secondary">({{ number_format($comReview->star_rating, 1) }} ★)</span>
                                </div>
                            @endif
                        </div>
                        @if($comReview->review_text)
                            <p class="text-light-emphasis mb-0 small">{{ $comReview->review_text }}</p>
                        @endif
                    </div>
                @empty
                    <p class="text-secondary small">Sé el primero en registrar una reseña para esta película.</p>
                @endforelse
            </div>
        </div>

        <!-- Sidebar Info -->
        <div class="col-lg-4">
            <!-- Ficha Técnica -->
            <div class="card bg-dark border-secondary p-4 rounded-4 sticky-sidebar sticky-top">
                <h5 class="fw-bold text-white mb-3 border-bottom border-secondary pb-2">Ficha Técnica</h5>

                <ul class="list-unstyled mb-0 d-flex flex-column gap-3 small">
                    @if($director)
                        <li class="d-flex justify-content-between">
                            <span class="text-secondary">Director:</span>
                            <span class="text-white fw-semibold">{{ $director }}</span>
                        </li>
                    @endif

                    @if($date)
                        <li class="d-flex justify-content-between">
                            <span class="text-secondary">Fecha de Estreno:</span>
                            <span class="text-white">{{ date('d/m/Y', strtotime($date)) }}</span>
                        </li>
                    @endif

                    @if(!empty($media['status']))
                        <li class="d-flex justify-content-between">
                            <span class="text-secondary">Estado TMDB:</span>
                            <span class="text-white">{{ $media['status'] }}</span>
                        </li>
                    @endif

                    @if(!empty($media['original_language']))
                        <li class="d-flex justify-content-between">
                            <span class="text-secondary">Idioma Original:</span>
                            <span class="text-white text-uppercase">{{ $media['original_language'] }}</span>
                        </li>
                    @endif

                    @if(!empty($media['budget']) && $media['budget'] > 0)
                        <li class="d-flex justify-content-between">
                            <span class="text-secondary">Presupuesto:</span>
                            <span class="text-white">${{ number_format($media['budget']) }}</span>
                        </li>
                    @endif

                    @if(!empty($media['revenue']) && $media['revenue'] > 0)
                        <li class="d-flex justify-content-between">
                            <span class="text-secondary">Recaudación:</span>
                            <span class="text-white">${{ number_format($media['revenue']) }}</span>
                        </li>
                    @endif
                </ul>
            </div>
        </div>
    </div>
</div>

<!-- Trailer Modal (if available) -->
@if($trailer)
    <div class="modal fade" id="trailerModal" tabindex="-1" aria-labelledby="trailerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            <div class="modal-content bg-black border-0">
                <div class="modal-header border-0 pb-0">
                    <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-0 ratio ratio-16x9">
                    <iframe src="https://www.youtube.com/embed/{{ $trailer }}?enablejsapi=1" title="Trailer de {{ $title }}" allowfullscreen></iframe>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection
