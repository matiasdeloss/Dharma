<div class="d-flex flex-column">
    <!-- TMDB Score -->
    <div class="rating-item-flat" data-bs-toggle="tooltip" title="{{ number_format($media['vote_count'] ?? 0) }} votos registrados en TMDB">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-star-fill text-warning"></i>
            <span class="fw-semibold text-secondary small">TMDB</span>
        </div>
        <div>
            @if(isset($media['vote_average']) && $media['vote_average'] > 0)
                <strong class="text-white">{{ number_format($media['vote_average'], 1) }}</strong>
                <small class="text-secondary">/10</small>
            @else
                <span class="text-secondary small">N/D</span>
            @endif
        </div>
    </div>

    <!-- IMDb Score -->
    @if(!empty($imdbId))
        <a href="https://www.imdb.com/title/{{ $imdbId }}/" target="_blank" rel="noopener noreferrer" class="rating-item-flat rating-imdb-link" data-bs-toggle="tooltip" title="Ver ficha en IMDb ({{ $omdbRatings['imdb_votes'] ?? 'votos' }})">
            <div class="d-flex align-items-center gap-2">
                <span class="badge-imdb-logo">IMDb</span>
                <span class="fw-semibold text-secondary small">IMDb</span>
            </div>
            <div class="d-flex align-items-baseline gap-1">
                @if(!empty($omdbRatings['imdb']) && $omdbRatings['imdb'] !== 'N/A')
                    <strong class="text-white">{{ $omdbRatings['imdb'] }}</strong>
                    <small class="text-secondary">/10</small>
                @else
                    <span class="text-secondary small">N/D</span>
                @endif
                <i class="bi bi-box-arrow-up-right text-xs opacity-50 ms-1"></i>
            </div>
        </a>
    @else
        <div class="rating-item-flat">
            <div class="d-flex align-items-center gap-2">
                <span class="badge-imdb-logo">IMDb</span>
                <span class="fw-semibold text-secondary small">IMDb</span>
            </div>
            <div>
                <span class="text-secondary small">N/D</span>
            </div>
        </div>
    @endif

    <!-- Rotten Tomatoes (Tomatometer) - SIEMPRE VISIBLE -->
    <div class="rating-item-flat" data-bs-toggle="tooltip" title="Tomatometer de Rotten Tomatoes">
        <div class="d-flex align-items-center gap-2">
            <span>🍅</span>
            <span class="fw-semibold text-secondary small">Rotten Tomatoes</span>
        </div>
        <div>
            @if(!empty($omdbRatings['rotten_tomatoes']) && $omdbRatings['rotten_tomatoes'] !== 'N/A')
                <strong class="text-white">{{ $omdbRatings['rotten_tomatoes'] }}</strong>
            @else
                <span class="text-secondary small">N/D</span>
            @endif
        </div>
    </div>

    <!-- Metacritic Metascore -->
    <div class="rating-item-flat" data-bs-toggle="tooltip" title="Metascore de críticos en Metacritic">
        <div class="d-flex align-items-center gap-2">
            <span class="badge-meta-logo">M</span>
            <span class="fw-semibold text-secondary small">Metacritic</span>
        </div>
        <div>
            @if(!empty($omdbRatings['metacritic']) && $omdbRatings['metacritic'] !== 'N/A')
                <strong class="text-white">{{ $omdbRatings['metacritic'] }}</strong>
                <small class="text-secondary">/100</small>
            @else
                <span class="text-secondary small">N/D</span>
            @endif
        </div>
    </div>

    <!-- Dharma Community Score -->
    <div class="rating-item-flat">
        <div class="d-flex align-items-center gap-2">
            <i class="bi bi-film text-accent"></i>
            <span class="fw-semibold text-accent small">Dharma</span>
        </div>
        <div>
            @if($dharmaAvg !== null)
                <strong class="text-accent">{{ number_format($dharmaAvg, 1) }}</strong>
                <small class="text-accent">/10</small>
            @else
                <span class="text-secondary small fst-italic">Sin notas aún</span>
            @endif
        </div>
    </div>
</div>
