{{--
    Notas de la crítica externa. Van en la columna derecha del hero, debajo de
    tu nota y de la de Dharma, en filas compactas: en una columna angosta una
    grilla de tiles no entra, y además las cajas competían con las dos notas de
    arriba, que son las que importan.

    Variables esperadas del scope padre: $media, $imdbId, $omdbRatings.
--}}
<ul class="hero-critics">
    <li class="hero-critic">
        <span class="hero-critic-name">
            <i class="bi bi-star-fill text-accent"></i> TMDB
        </span>
        @if(!empty($media['vote_average']) && $media['vote_average'] > 0)
            <span class="hero-critic-value">
                {{ number_format($media['vote_average'], 1) }}<span class="hero-critic-scale">/10</span>
            </span>
        @else
            <span class="hero-critic-value hero-critic-empty">N/D</span>
        @endif
    </li>

    <li class="hero-critic">
        @if(!empty($imdbId))
            <a href="https://www.imdb.com/title/{{ $imdbId }}/" target="_blank" rel="noopener noreferrer" class="hero-critic-link" title="Ver la ficha en IMDb">
                <span class="hero-critic-name">
                    <span class="badge-imdb-logo">IMDb</span>
                    <i class="bi bi-box-arrow-up-right hero-critic-out"></i>
                </span>
                @if(!empty($omdbRatings['imdb']) && $omdbRatings['imdb'] !== 'N/A')
                    <span class="hero-critic-value">
                        {{ $omdbRatings['imdb'] }}<span class="hero-critic-scale">/10</span>
                    </span>
                @else
                    <span class="hero-critic-value hero-critic-empty">N/D</span>
                @endif
            </a>
        @else
            <span class="hero-critic-name">
                <span class="badge-imdb-logo">IMDb</span>
            </span>
            <span class="hero-critic-value hero-critic-empty">N/D</span>
        @endif
    </li>

    <li class="hero-critic">
        <span class="hero-critic-name">
            <span aria-hidden="true">🍅</span> Rotten Tomatoes
        </span>
        @if(!empty($omdbRatings['rotten_tomatoes']) && $omdbRatings['rotten_tomatoes'] !== 'N/A')
            <span class="hero-critic-value">{{ $omdbRatings['rotten_tomatoes'] }}</span>
        @else
            <span class="hero-critic-value hero-critic-empty">N/D</span>
        @endif
    </li>

    <li class="hero-critic">
        <span class="hero-critic-name">
            <span class="badge-meta-logo">M</span> Metacritic
        </span>
        @if(!empty($omdbRatings['metacritic']) && $omdbRatings['metacritic'] !== 'N/A')
            <span class="hero-critic-value">
                {{ $omdbRatings['metacritic'] }}<span class="hero-critic-scale">/100</span>
            </span>
        @else
            <span class="hero-critic-value hero-critic-empty">N/D</span>
        @endif
    </li>
</ul>
