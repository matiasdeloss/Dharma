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

    $imageBase = config('services.tmdb.image_base_url', 'https://image.tmdb.org/t/p');

    // Reparto: se muestran 12 y el resto queda detras de "ver reparto completo"
    // (antes se cortaba en 6 sin forma de ver los demas).
    $castAll = array_values(array_filter($media['credits']['cast'] ?? [], fn ($p) => !empty($p['name'])));
    $castPrimary = array_slice($castAll, 0, 12);
    $castExtra = array_slice($castAll, 12, 36);

    // Equipo tecnico agrupado por rol (de todo el crew solo se usaba el director).
    $crewIndex = [];
    foreach ($media['credits']['crew'] ?? [] as $member) {
        if (empty($member['name']) || empty($member['job'])) {
            continue;
        }
        $crewIndex[$member['job']][$member['name']] = true;
    }

    $crewGroups = [];
    if ($type === 'tv' && !empty($media['created_by'])) {
        $creators = array_slice(array_filter(array_column($media['created_by'], 'name')), 0, 4);
        if (!empty($creators)) {
            $crewGroups['Creada por'] = $creators;
        }
    }
    foreach ([
        'Dirección' => ['Director'],
        'Guion' => ['Screenplay', 'Writer', 'Story'],
        'Fotografía' => ['Director of Photography', 'Cinematography'],
        'Música' => ['Original Music Composer', 'Music'],
        'Montaje' => ['Editor'],
        'Producción' => ['Producer'],
    ] as $crewLabel => $crewJobs) {
        $crewNames = [];
        foreach ($crewJobs as $crewJob) {
            foreach (array_keys($crewIndex[$crewJob] ?? []) as $crewName) {
                $crewNames[$crewName] = true;
            }
        }
        if (!empty($crewNames)) {
            $crewGroups[$crewLabel] = array_slice(array_keys($crewNames), 0, 4);
        }
    }

    // $related lo arma MediaController::show, que ademas le marca a cada card
    // si ya esta en la watchlist del usuario.

    // Ficha tecnica: TMDB manda campos distintos para pelicula y serie.
    $statusLabels = [
        'Released' => 'Estrenada',
        'Post Production' => 'Postproducción',
        'In Production' => 'En producción',
        'Planned' => 'Anunciada',
        'Rumored' => 'Rumoreada',
        'Canceled' => 'Cancelada',
        'Returning Series' => 'En emisión',
        'Ended' => 'Finalizada',
        'Pilot' => 'Piloto',
    ];
    $status = !empty($media['status']) ? ($statusLabels[$media['status']] ?? $media['status']) : null;

    $formatRuntime = function ($minutes) {
        $minutes = (int) $minutes;
        if ($minutes <= 0) {
            return null;
        }
        return $minutes >= 60
            ? floor($minutes / 60) . 'h ' . ($minutes % 60) . 'm'
            : $minutes . 'm';
    };

    $episodeRuntime = null;
    if (!empty($media['episode_run_time']) && is_array($media['episode_run_time'])) {
        $episodeRuntime = (int) round(array_sum($media['episode_run_time']) / max(count($media['episode_run_time']), 1));
    }

    $seasons = array_values(array_filter(
        $media['seasons'] ?? [],
        fn ($season) => ($season['episode_count'] ?? 0) > 0
    ));

    $networks = array_filter(array_column($media['networks'] ?? [], 'name'));
    $companies = array_slice(array_filter(array_column($media['production_companies'] ?? [], 'name')), 0, 6);
    $countries = array_filter(array_column($media['production_countries'] ?? [], 'name'));
    $languages = array_filter(array_map(
        fn ($lang) => ($lang['name'] ?? '') ?: ($lang['english_name'] ?? null),
        $media['spoken_languages'] ?? []
    ));

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

    // La web oficial viene de TMDB, que puede editar cualquiera: solo se
    // enlaza si es http(s), así un `javascript:` nunca llega a un href.
    $homepage = Str::startsWith($media['homepage'] ?? '', ['http://', 'https://']) ? $media['homepage'] : null;
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
                            // Primero lo que se ve por suscripcion, despues alquiler; y
                            // dentro de eso, las plataformas del usuario adelante.
                            $allPills = collect($watchProviders['flatrate'] ?? [])
                                ->concat($watchProviders['ads'] ?? [])
                                ->concat($watchProviders['free'] ?? [])
                                ->concat($watchProviders['rent'] ?? [])
                                ->unique('provider_id')
                                ->sortBy(fn ($p) => in_array($p['provider_id'], $myProviders, true) ? 0 : 1)
                                ->values();
                        @endphp
                        @foreach($allPills as $provider)
                            {{-- Bloque y no `@php(...)`: la forma corta se traga todo hasta el proximo @endphp del archivo. --}}
                            @php $mine = in_array($provider['provider_id'], $myProviders, true); @endphp
                            <div class="provider-pill-inline {{ $mine ? 'is-mine' : '' }}" data-bs-toggle="tooltip" title="{{ $provider['provider_name'] }}{{ $mine ? ' · tu plataforma' : '' }}">
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

                <button
                    type="button"
                    class="btn-cine-secondary"
                    hx-get="{{ route('lists.picker', ['type' => $type, 'id' => $media['id']]) }}"
                    hx-target="#logModalContent"
                    data-bs-toggle="modal"
                    data-bs-target="#logModal"
                >
                    <i class="bi bi-collection"></i> Agregar a lista
                </button>

                @if($trailer)
                    <button type="button" class="btn-cine-secondary" data-bs-toggle="modal" data-bs-target="#trailerModal">
                        <i class="bi bi-play-circle-fill"></i> Ver Trailer
                    </button>
                @endif
            </div>
        </div>

        {{-- Col 3: Notas. Tu nota primero, despues la de Dharma, y abajo la
             critica externa. Sin cards a proposito: son lineas de datos
             sobre el fondo de la pagina, separadas por filetes. --}}
        <div class="col-lg-3">
            <div class="hero-scores">
                @include('media.partials.hero-score-mine', [
                    'review' => $userReview,
                    'type' => $type,
                    'tmdbId' => $media['id'],
                ])

                @include('media.partials.hero-score-dharma', [
                    'avg' => $dharmaAvg,
                    'count' => $dharmaCount,
                ])

                @include('media.partials.ratings-strip')

                @if(!empty($omdbRatings['awards']))
                    <p class="hero-awards">
                        <i class="bi bi-trophy-fill"></i>{{ $omdbRatings['awards'] }}
                    </p>
                @endif
            </div>
        </div>

    </div>
</div>

<!-- ==========================================================================
     Reparto, equipo tecnico, temporadas, resenas + ficha tecnica lateral
     ========================================================================== -->
<div class="container mb-5">
    <div class="row g-4 g-lg-5">
        <div class="col-lg-8">
            <!-- Reparto Principal -->
            @if(count($castPrimary) > 0)
                <section class="info-section" aria-labelledby="cast-heading">
                    <div class="section-header">
                        <h2 class="section-title" id="cast-heading">Reparto Principal</h2>
                        <span class="section-subtitle">
                            {{ count($castAll) }} {{ count($castAll) === 1 ? 'intérprete acreditado' : 'intérpretes acreditados' }} en TMDB
                        </span>
                    </div>

                    <div class="cast-grid">
                        @foreach($castPrimary as $actor)
                            @include('media.partials.cast-card', ['actor' => $actor, 'imageBase' => $imageBase])
                        @endforeach
                    </div>

                    @if(count($castExtra) > 0)
                        <div class="collapse" id="castExtra">
                            <div class="cast-grid mt-2">
                                @foreach($castExtra as $actor)
                                    @include('media.partials.cast-card', ['actor' => $actor, 'imageBase' => $imageBase])
                                @endforeach
                            </div>
                        </div>

                        <button
                            type="button"
                            class="btn btn-cine-secondary btn-pill-compact btn-cast-toggle collapsed mt-3"
                            data-bs-toggle="collapse"
                            data-bs-target="#castExtra"
                            aria-expanded="false"
                            aria-controls="castExtra"
                        >
                            <span class="cast-toggle-more">
                                <i class="bi bi-chevron-down me-1"></i>Ver reparto completo ({{ count($castExtra) }} más)
                            </span>
                            <span class="cast-toggle-less">
                                <i class="bi bi-chevron-up me-1"></i>Mostrar solo el reparto principal
                            </span>
                        </button>
                    @endif
                </section>
            @endif

            <!-- Equipo Técnico -->
            @if(count($crewGroups) > 0)
                <section class="info-section" aria-labelledby="crew-heading">
                    <div class="section-header">
                        <h2 class="section-title" id="crew-heading">Equipo Técnico</h2>
                        <span class="section-subtitle">Quiénes están detrás de cámara</span>
                    </div>

                    <div class="crew-grid">
                        @foreach($crewGroups as $crewLabel => $crewNames)
                            <div class="crew-item">
                                <span class="crew-job">{{ $crewLabel }}</span>
                                <span class="crew-names">{{ implode(' · ', $crewNames) }}</span>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <!-- Temporadas (solo series) -->
            @if($type === 'tv' && count($seasons) > 0)
                <section class="info-section" aria-labelledby="seasons-heading">
                    <div class="section-header">
                        <h2 class="section-title" id="seasons-heading">Temporadas</h2>
                        <span class="section-subtitle">
                            {{ $media['number_of_seasons'] ?? count($seasons) }} {{ ($media['number_of_seasons'] ?? count($seasons)) === 1 ? 'temporada' : 'temporadas' }}
                            @if(!empty($media['number_of_episodes']))
                                · {{ $media['number_of_episodes'] }} episodios en total
                            @endif
                        </span>
                    </div>

                    @if(!empty($media['next_episode_to_air']))
                        @php $nextEp = $media['next_episode_to_air']; @endphp
                        <div class="next-episode">
                            <i class="bi bi-broadcast text-accent-info"></i>
                            <span>
                                <strong>Próximo episodio:</strong>
                                T{{ $nextEp['season_number'] ?? '?' }}E{{ $nextEp['episode_number'] ?? '?' }}
                                @if(!empty($nextEp['name'])) · {{ $nextEp['name'] }} @endif
                                @if(!empty($nextEp['air_date'])) · {{ date('d/m/Y', strtotime($nextEp['air_date'])) }} @endif
                            </span>
                        </div>
                    @elseif(!empty($media['last_episode_to_air']))
                        @php $lastEp = $media['last_episode_to_air']; @endphp
                        <div class="next-episode">
                            <i class="bi bi-broadcast-pin text-accent-info"></i>
                            <span>
                                <strong>Último episodio emitido:</strong>
                                T{{ $lastEp['season_number'] ?? '?' }}E{{ $lastEp['episode_number'] ?? '?' }}
                                @if(!empty($lastEp['name'])) · {{ $lastEp['name'] }} @endif
                                @if(!empty($lastEp['air_date'])) · {{ date('d/m/Y', strtotime($lastEp['air_date'])) }} @endif
                            </span>
                        </div>
                    @endif

                    <div class="season-grid">
                        @foreach($seasons as $season)
                            @php
                                $seasonPoster = !empty($season['poster_path'])
                                    ? $imageBase . '/w185' . $season['poster_path']
                                    : asset('images/no-poster.svg');
                                $seasonYear = !empty($season['air_date']) ? substr($season['air_date'], 0, 4) : null;
                            @endphp
                            <div class="season-item">
                                <img src="{{ $seasonPoster }}" alt="{{ $season['name'] ?? 'Temporada' }}" class="season-poster" loading="lazy">
                                <div class="season-item-body">
                                    <div class="season-name">{{ $season['name'] ?? 'Temporada ' . ($season['season_number'] ?? '') }}</div>
                                    <div class="season-meta">
                                        {{ $season['episode_count'] }} {{ $season['episode_count'] === 1 ? 'episodio' : 'episodios' }}
                                        @if($seasonYear) · {{ $seasonYear }} @endif
                                    </div>
                                    @if(!empty($season['overview']))
                                        <p class="season-overview">{{ Str::limit($season['overview'], 130) }}</p>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <!-- Reseñas de la Comunidad -->
            <section class="info-section" aria-labelledby="community-heading">
                <div class="d-flex flex-wrap align-items-end justify-content-between gap-2 mb-3">
                    <div class="section-header mb-0">
                        <h2 class="section-title" id="community-heading">Reseñas de la Comunidad</h2>
                        <span class="section-subtitle">Lo que escribieron otros usuarios de Dharma</span>
                    </div>
                    @auth
                        @unless($userReview && ($userReview->review_text || $userReview->private_notes))
                            <button
                                type="button"
                                class="btn btn-cine-secondary btn-pill-compact"
                                hx-get="{{ route('reviews.write', ['type' => $type, 'id' => $media['id']]) }}"
                                hx-target="#logModalContent"
                                data-bs-toggle="modal"
                                data-bs-target="#logModal"
                            >
                                <i class="bi bi-pencil-square me-1"></i>Escribir reseña
                            </button>
                        @endunless
                    @endauth
                </div>

                {{-- Tu resena va primero y destacada. Antes vivia dentro de la
                     card de "Tu nota"; en la columna del hero no entra. --}}
                @if($userReview && ($userReview->review_text || $userReview->private_notes))
                    <article class="community-review community-review-mine">
                        <div class="community-review-head">
                            <div class="review-author">
                                <span class="review-avatar"><i class="bi bi-person-fill"></i></span>
                                <div class="review-author-body">
                                    <span class="review-name">Tu rese&ntilde;a</span>
                                    <span class="review-date">
                                        {{ $userReview->watched_date ? 'Vista el ' . $userReview->watched_date->translatedFormat('d M Y') : 'Sin fecha' }}
                                    </span>
                                </div>
                            </div>

                            <button
                                type="button"
                                class="btn btn-cine-secondary btn-pill-compact"
                                hx-get="{{ route('reviews.write', ['type' => $type, 'id' => $media['id']]) }}"
                                hx-target="#logModalContent"
                                data-bs-toggle="modal"
                                data-bs-target="#logModal"
                            >
                                <i class="bi bi-pencil-square me-1"></i>Editar
                            </button>
                        </div>

                        @if($userReview->contains_spoilers)
                            <div class="diary-meta mb-2">
                                <span class="diary-chip"><i class="bi bi-eye-slash"></i>Contiene spoilers</span>
                            </div>
                        @endif

                        @if($userReview->review_text)
                            <p class="diary-review-text">{{ $userReview->review_text }}</p>
                        @endif

                        @if($userReview->private_notes)
                            <div class="diary-private-note mt-2">
                                <span class="diary-private-note-label">
                                    <i class="bi bi-lock-fill"></i>Nota privada
                                </span>
                                {{ $userReview->private_notes }}
                            </div>
                        @endif
                    </article>
                @endif

                @forelse($communityReviews as $comReview)
                    <article class="community-review">
                        <div class="community-review-head">
                            <div class="review-author">
                                <span class="review-avatar">{{ Str::substr($comReview->user->name ?? '?', 0, 1) }}</span>
                                <div class="review-author-body">
                                    <span class="review-name">{{ $comReview->user->name ?? 'Usuario' }}</span>
                                    <span class="review-date">
                                        {{ $comReview->watched_date ? 'Vista el ' . $comReview->watched_date->translatedFormat('d M Y') : $comReview->created_at->translatedFormat('d M Y') }}
                                    </span>
                                </div>
                            </div>

                            @if($comReview->rating !== null)
                                <div class="diary-rating">
                                    <div class="diary-rating-value">
                                        {{ number_format($comReview->rating, 1) }}<span class="diary-rating-max">/10</span>
                                    </div>
                                    <div class="diary-rating-stars">
                                        {{ number_format($comReview->star_rating, 1) }} ★
                                    </div>
                                </div>
                            @endif
                        </div>

                        @if($comReview->contains_spoilers)
                            <div class="diary-meta mb-2">
                                <span class="diary-chip"><i class="bi bi-eye-slash"></i>Contiene spoilers</span>
                            </div>
                        @endif

                        @if($comReview->review_text)
                            @include('partials.review-text', ['review' => $comReview])
                        @endif
                    </article>
                @empty
                    <div class="empty-state">
                        <i class="bi bi-chat-square-quote empty-state-icon"></i>
                        <h3 class="empty-state-title">Todavía nadie escribió sobre este título</h3>
                        <p class="empty-state-text">
                            Si ya lo viste, tu reseña puede ser la primera. También podés dejar notas privadas que solo vos vas a leer.
                        </p>
                        <button
                            type="button"
                            class="btn btn-cine-primary"
                            hx-get="{{ route('reviews.write', ['type' => $type, 'id' => $media['id']]) }}"
                            hx-target="#logModalContent"
                            data-bs-toggle="modal"
                            data-bs-target="#logModal"
                        >
                            <i class="bi bi-pencil-square me-1"></i>Escribir la primera reseña
                        </button>
                    </div>
                @endforelse
            </section>
        </div>

        <!-- Ficha Técnica -->
        <div class="col-lg-4">
            <aside class="spec-panel sticky-sidebar sticky-top">
                <h2 class="spec-panel-title">
                    <i class="bi bi-clipboard-data text-accent"></i>Ficha Técnica
                </h2>

                <dl class="spec-list">
                    <div class="spec-row">
                        <dt class="spec-key">Tipo</dt>
                        <dd class="spec-value">{{ $type === 'tv' ? 'Serie de TV' : 'Película' }}</dd>
                    </div>

                    @if($originalTitle)
                        <div class="spec-row">
                            <dt class="spec-key">Título original</dt>
                            <dd class="spec-value">{{ $originalTitle }}</dd>
                        </div>
                    @endif

                    @if($status)
                        <div class="spec-row">
                            <dt class="spec-key">Estado</dt>
                            <dd class="spec-value">{{ $status }}</dd>
                        </div>
                    @endif

                    @if($date)
                        <div class="spec-row">
                            <dt class="spec-key">{{ $type === 'tv' ? 'Primera emisión' : 'Estreno' }}</dt>
                            <dd class="spec-value">{{ date('d/m/Y', strtotime($date)) }}</dd>
                        </div>
                    @endif

                    @if($type === 'tv' && !empty($media['last_air_date']))
                        <div class="spec-row">
                            <dt class="spec-key">Última emisión</dt>
                            <dd class="spec-value">{{ date('d/m/Y', strtotime($media['last_air_date'])) }}</dd>
                        </div>
                    @endif

                    @if($type === 'tv' && !empty($media['number_of_seasons']))
                        <div class="spec-row">
                            <dt class="spec-key">Temporadas</dt>
                            <dd class="spec-value spec-value-num">{{ $media['number_of_seasons'] }}</dd>
                        </div>
                    @endif

                    @if($type === 'tv' && !empty($media['number_of_episodes']))
                        <div class="spec-row">
                            <dt class="spec-key">Episodios</dt>
                            <dd class="spec-value spec-value-num">{{ $media['number_of_episodes'] }}</dd>
                        </div>
                    @endif

                    @if($type === 'tv' && $episodeRuntime)
                        <div class="spec-row">
                            <dt class="spec-key">Duración por episodio</dt>
                            <dd class="spec-value">≈ {{ $formatRuntime($episodeRuntime) }}</dd>
                        </div>
                    @elseif(!empty($media['runtime']))
                        <div class="spec-row">
                            <dt class="spec-key">Duración</dt>
                            <dd class="spec-value">{{ $formatRuntime($media['runtime']) }}</dd>
                        </div>
                    @endif

                    @if(count($networks) > 0)
                        <div class="spec-row">
                            <dt class="spec-key">{{ count($networks) === 1 ? 'Cadena' : 'Cadenas' }}</dt>
                            <dd class="spec-value">{{ implode(' · ', $networks) }}</dd>
                        </div>
                    @endif

                    @if(!empty($omdbRatings['rated']))
                        <div class="spec-row">
                            <dt class="spec-key">Clasificación</dt>
                            <dd class="spec-value">
                                <span class="diary-chip" title="Clasificación por edad (MPAA / TV)">{{ $omdbRatings['rated'] }}</span>
                            </dd>
                        </div>
                    @endif

                    @if(!empty($media['original_language']))
                        <div class="spec-row">
                            <dt class="spec-key">Idioma original</dt>
                            <dd class="spec-value text-uppercase">{{ $media['original_language'] }}</dd>
                        </div>
                    @endif

                    @if(count($languages) > 0)
                        <div class="spec-row">
                            <dt class="spec-key">Idiomas</dt>
                            <dd class="spec-value">{{ implode(' · ', array_slice($languages, 0, 4)) }}</dd>
                        </div>
                    @endif

                    @if(count($countries) > 0)
                        <div class="spec-row">
                            <dt class="spec-key">{{ count($countries) === 1 ? 'País' : 'Países' }}</dt>
                            <dd class="spec-value">{{ implode(' · ', array_slice($countries, 0, 4)) }}</dd>
                        </div>
                    @endif

                    @if(!empty($media['budget']) && $media['budget'] > 0)
                        <div class="spec-row">
                            <dt class="spec-key">Presupuesto</dt>
                            <dd class="spec-value spec-value-num">${{ number_format($media['budget'], 0, ',', '.') }}</dd>
                        </div>
                    @endif

                    @if(!empty($media['revenue']) && $media['revenue'] > 0)
                        <div class="spec-row">
                            <dt class="spec-key">Recaudación</dt>
                            <dd class="spec-value spec-value-num">${{ number_format($media['revenue'], 0, ',', '.') }}</dd>
                        </div>
                    @endif
                </dl>

                @if(count($companies) > 0)
                    <div class="spec-block">
                        <span class="spec-block-label">{{ count($companies) === 1 ? 'Productora' : 'Productoras' }}</span>
                        <div class="diary-meta">
                            @foreach($companies as $company)
                                <span class="diary-chip">{{ $company }}</span>
                            @endforeach
                        </div>
                    </div>
                @endif

                @if($imdbId || $homepage)
                    <div class="spec-block">
                        <span class="spec-block-label">Enlaces</span>
                        <div class="d-flex flex-wrap gap-2">
                            @if($imdbId)
                                <a href="https://www.imdb.com/title/{{ $imdbId }}/" target="_blank" rel="noopener noreferrer" class="diary-chip">
                                    <span class="badge-imdb-logo">IMDb</span> Ficha en IMDb
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            @endif
                            @if($homepage)
                                <a href="{{ $homepage }}" target="_blank" rel="noopener noreferrer" class="diary-chip">
                                    <i class="bi bi-globe2"></i> Sitio oficial
                                    <i class="bi bi-box-arrow-up-right"></i>
                                </a>
                            @endif
                        </div>
                    </div>
                @endif
            </aside>
        </div>
    </div>
</div>

<!-- ==========================================================================
     Titulos relacionados: la ficha dejaba de ser un callejon sin salida
     ========================================================================== -->
@if(count($related) > 0)
    <div class="container mb-5">
        <div class="media-slider-container">
            <div class="media-slider-header">
                <h2 class="section-title">Títulos relacionados</h2>
                <span class="section-subtitle">Si te gustó {{ $title }}, quizás te interese</span>
            </div>
            <div class="media-slider-wrapper position-relative">
                <button type="button" class="slider-nav-arrow slider-nav-prev" aria-label="Anterior" title="Anterior">
                    <i class="bi bi-chevron-left"></i>
                </button>

                <div class="media-slider-track">
                    @foreach($related as $relatedItem)
                        @include('media.partials.movie-card', [
                            'item' => $relatedItem,
                            'type' => $relatedItem['media_type'],
                            'colClass' => 'media-slider-col',
                            'inWatchlist' => $relatedItem['in_watchlist'] ?? false,
                        ])
                    @endforeach
                </div>

                <button type="button" class="slider-nav-arrow slider-nav-next" aria-label="Siguiente" title="Siguiente">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
@endif

<!-- Trailer Modal (if available) -->
@if($trailer)
    <div class="modal fade" id="trailerModal" tabindex="-1" aria-labelledby="trailerModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-xl">
            {{-- Sin modal-header propio: el video ocupa todo y el boton de
                 cerrar flota encima, como en el modal de calificacion. --}}
            <div class="modal-content trailer-modal-content position-relative">
                <button type="button" class="modal-close-dharma" data-bs-dismiss="modal" aria-label="Cerrar">
                    <i class="bi bi-x-lg"></i>
                </button>
                <div class="modal-body p-0 ratio ratio-16x9">
                    {{-- `data-src` y no `src`: modules/trailer.js lo carga al abrir y
                         lo saca al cerrar, asi el video no sigue sonando de fondo. --}}
                    <iframe data-src="https://www.youtube.com/embed/{{ $trailer }}?autoplay=1" title="Trailer de {{ $title }}" allow="autoplay; encrypted-media" allowfullscreen></iframe>
                </div>
            </div>
        </div>
    </div>
@endif
@endsection
