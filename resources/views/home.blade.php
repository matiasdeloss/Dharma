@extends('layouts.app')

@section('title', 'Guarda tus reseñas y notas de películas')

@section('content')
<div class="container">
    @if(!$isConfigured)
        <!-- Friendly TMDB configuration tip banner -->
        <div class="alert alert-dark border-warning text-light-emphasis d-flex align-items-center justify-content-between mb-5 p-3 rounded-3" role="alert">
            <div class="d-flex align-items-center gap-3">
                <i class="bi bi-info-circle-fill text-warning fs-3"></i>
                <div>
                    <strong class="text-white">Modo Demo / Clave TMDB no configurada:</strong>
                    <div class="small text-secondary">
                        Para buscar cualquier película o serie en tiempo real con millones de títulos, agrega tu <code>TMDB_API_KEY</code> o <code>TMDB_READ_TOKEN</code> en el archivo <code>.env</code>.
                        ¡Mientras tanto puedes explorar los datos de prueba incluidos!
                    </div>
                </div>
            </div>
            <a href="https://www.themoviedb.org/settings/api" target="_blank" class="btn btn-outline-warning btn-sm text-nowrap">
                Obtener API Key <i class="bi bi-box-arrow-up-right ms-1"></i>
            </a>
        </div>
    @endif

    <!-- Hero Banner -->
    <div class="hero-home-banner mb-5" @if(!empty($heroBackdrop['url'])) style="--hero-backdrop: url('{{ $heroBackdrop['url'] }}');" @endif>
        @if(!empty($heroBackdrop['title']))
            <div class="hero-backdrop-credit">
                <i class="bi bi-camera-reels text-accent"></i>
                <span>{{ $heroBackdrop['title'] }}</span>
            </div>
        @endif

        <div class="row align-items-center position-relative z-2">
            <div class="{{ Auth::check() ? 'col-lg-7' : 'col-lg-10 col-xl-9' }}">
                <h1 class="hero-title display-5 fw-extrabold text-white mb-2">
                    Lleva el registro de cada película y serie que ves.
                </h1>
                <p class="hero-subtitle lead text-secondary mb-3">
                    Califica del 1 al 10, escribe tus reseñas públicas y guarda notas privadas de tus momentos y citas favoritas de cada película.
                </p>
                <div class="hero-tagline mb-4">
                    <i class="bi bi-quote"></i>Live Together. Watch Together.
                </div>
                <div class="d-flex flex-wrap gap-3">
                    @auth
                        <a href="{{ route('reviews.index') }}" class="btn btn-cine-primary px-4 py-2">
                            <i class="bi bi-journal-text me-2"></i> Ver Mi Diario & Notas
                        </a>
                        <a href="{{ route('watchlist.index') }}" class="btn btn-cine-secondary px-4 py-2">
                            <i class="bi bi-bookmark-heart me-2"></i> Mi Watchlist
                        </a>
                    @else
                        <a href="{{ route('register') }}" class="btn btn-cine-primary px-4 py-2">
                            <i class="bi bi-person-plus-fill me-2"></i> Crear Mi Cuenta Gratis
                        </a>
                        <a href="{{ route('login') }}" class="btn btn-cine-secondary px-4 py-2">
                            <i class="bi bi-box-arrow-in-right me-2"></i> Iniciar Sesión
                        </a>
                    @endauth
                </div>
            </div>

            @auth
            <div class="col-lg-5 d-none d-lg-flex align-items-center justify-content-center ps-lg-4">
                <div class="hero-stats-row">
                    <div class="hero-stat">
                        <i class="hero-stat-icon bi bi-journal-text text-cream"></i>
                        <div id="hero-stat-notes" class="hero-stat-number text-cream">{{ $stats['total_notes'] ?? 0 }}</div>
                        <div class="hero-stat-label">Notas</div>
                    </div>
                    <div class="hero-stat-divider"></div>
                    <div class="hero-stat">
                        <i class="hero-stat-icon bi bi-chat-square-quote text-purple"></i>
                        <div id="hero-stat-reviews" class="hero-stat-number text-purple">{{ $stats['total_reviews'] ?? 0 }}</div>
                        <div class="hero-stat-label">Reseñas</div>
                    </div>
                    <div class="hero-stat-divider"></div>
                    <div class="hero-stat">
                        <i class="hero-stat-icon bi bi-bookmark-heart text-info"></i>
                        <div id="hero-stat-watchlist" class="hero-stat-number text-info">{{ $stats['total_watchlist'] ?? 0 }}</div>
                        <div class="hero-stat-label">Watchlist</div>
                    </div>
                </div>
            </div>
            @endauth
        </div>
    </div>

    <!-- 1. Selecciones Populares -->
    <div class="media-slider-container mb-5">
        <div class="media-slider-header">
            <h3 class="section-title">Selecciones Populares</h3>
            <span class="section-subtitle">Los títulos en tendencia más destacados de la semana</span>
        </div>
        <div class="media-slider-wrapper position-relative">
            <button type="button" class="slider-nav-arrow slider-nav-prev" aria-label="Anterior" title="Anterior">
                <i class="bi bi-chevron-left"></i>
            </button>

            <div class="media-slider-track">
                @forelse($popularPicks as $item)
                    @include('media.partials.movie-card', ['item' => $item, 'colClass' => 'media-slider-col'])
                @empty
                    <p class="text-secondary">No hay selecciones disponibles en este momento.</p>
                @endforelse
            </div>

            <button type="button" class="slider-nav-arrow slider-nav-next" aria-label="Siguiente" title="Siguiente">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>
    </div>

    <!-- 2. Mejores 10 Películas de la Semana (Popularidad) -->
    <div class="media-slider-container mb-5">
        <div class="media-slider-header">
            <h3 class="section-title">Top 10 Películas de la Semana</h3>
            <span class="section-subtitle">Las 10 películas más populares en la comunidad esta semana</span>
        </div>
        <div class="media-slider-wrapper position-relative">
            <button type="button" class="slider-nav-arrow slider-nav-prev" aria-label="Anterior" title="Anterior">
                <i class="bi bi-chevron-left"></i>
            </button>

            <div class="media-slider-track">
                @forelse($topMovies as $item)
                    @include('media.partials.movie-card', [
                        'item' => $item, 
                        'type' => 'movie', 
                        'colClass' => 'media-slider-col',
                        'rank' => $loop->iteration
                    ])
                @empty
                    <p class="text-secondary">No hay películas disponibles.</p>
                @endforelse
            </div>

            <button type="button" class="slider-nav-arrow slider-nav-next" aria-label="Siguiente" title="Siguiente">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>
    </div>

    <!-- 3. Mejores 10 Series de la Semana (Popularidad) -->
    <div class="media-slider-container mb-5">
        <div class="media-slider-header">
            <h3 class="section-title">Top 10 Series de la Semana</h3>
            <span class="section-subtitle">Las 10 series con mayor audiencia del momento</span>
        </div>
        <div class="media-slider-wrapper position-relative">
            <button type="button" class="slider-nav-arrow slider-nav-prev" aria-label="Anterior" title="Anterior">
                <i class="bi bi-chevron-left"></i>
            </button>

            <div class="media-slider-track">
                @forelse($topTv as $item)
                    @include('media.partials.movie-card', [
                        'item' => $item, 
                        'type' => 'tv', 
                        'colClass' => 'media-slider-col',
                        'rank' => $loop->iteration
                    ])
                @empty
                    <p class="text-secondary">No hay series disponibles.</p>
                @endforelse
            </div>

            <button type="button" class="slider-nav-arrow slider-nav-next" aria-label="Siguiente" title="Siguiente">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>
    </div>

    <!-- Recent User Reviews Stream -->
    @if($recentReviews->count() > 0)
        <div class="mb-5">
            <div class="d-flex justify-content-between align-items-end mb-4">
                <div class="section-header mb-0">
                    <h3 class="section-title">Últimas Reseñas y Notas</h3>
                    <span class="section-subtitle">Lo que se ha estado viendo y comentando recientemente</span>
                </div>
                <a href="{{ route('reviews.index') }}" class="btn btn-sm btn-outline-secondary">Ver todo el diario <i class="bi bi-arrow-right ms-1"></i></a>
            </div>
            <div class="row">
                @foreach($recentReviews as $review)
                    <div class="col-md-6 mb-4">
                        <div class="review-entry-card h-100 p-3">
                            <div class="d-flex gap-3">
                                <a href="{{ route('media.show', ['type' => $review->mediaItem->media_type, 'id' => $review->mediaItem->tmdb_id]) }}">
                                    <img src="{{ $review->mediaItem->poster_url }}" alt="{{ $review->mediaItem->title }}" class="review-thumb-feed rounded shadow-sm">
                                </a>
                                <div class="flex-grow-1">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h5 class="fw-bold mb-1">
                                                <a href="{{ route('media.show', ['type' => $review->mediaItem->media_type, 'id' => $review->mediaItem->tmdb_id]) }}" class="text-white text-decoration-none">
                                                    {{ $review->mediaItem->title }}
                                                </a>
                                                @if($review->mediaItem->release_year)
                                                    <span class="text-secondary small">({{ $review->mediaItem->release_year }})</span>
                                                @endif
                                            </h5>
                                            <div class="text-secondary small mb-2">
                                                <span>Por <strong class="text-light-emphasis">{{ $review->user->name }}</strong></span>
                                                @if($review->watched_date)
                                                    <span>&bull; Vista el {{ $review->watched_date->format('d/m/Y') }}</span>
                                                @endif
                                                @if($review->is_rewatch)
                                                    <span class="badge bg-secondary-subtle text-secondary ms-1">Re-visionado</span>
                                                @endif
                                            </div>
                                        </div>
                                        @if($review->rating !== null)
                                            <div class="text-warning fw-bold text-end">
                                                <span>{{ number_format($review->rating, 1) }}<span class="fs-6 text-secondary">/10</span></span>
                                                <div class="small text-secondary fw-normal">
                                                    <span class="text-warning">{{ number_format($review->star_rating, 1) }} ★</span>
                                                </div>
                                            </div>
                                        @endif
                                    </div>

                                    @if($review->review_text)
                                        <p class="text-light-emphasis small mb-2">{{ Str::limit($review->review_text, 140) }}</p>
                                    @endif

                                    @if($review->private_notes)
                                        <div class="p-2 rounded bg-dark-subtle border border-secondary-subtle small text-secondary">
                                            <i class="bi bi-lock-fill text-warning me-1"></i><strong>Nota personal:</strong> {{ Str::limit($review->private_notes, 100) }}
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</div>
@endsection
