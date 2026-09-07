{{--
    Nota promedio de la comunidad de Dharma, en el hero de la ficha.

    Igual que hero-score-mine: es un partial para poder re-renderizarlo como
    fragmento out-of-band al guardar, porque el promedio cambia con cada nota.

    Props: $avg (float|null), $count (int), $oob (bool)
--}}
<div id="hero-score-dharma" class="hero-score" @if($oob ?? false) hx-swap-oob="true" @endif>
    <span class="hero-score-label"><i class="bi bi-film"></i> Nota de Dharma</span>

    @if($avg !== null)
        @php $dharmaStars = $avg / 2; @endphp
        <div class="hero-score-figure">
            <span class="hero-score-value">{{ number_format($avg, 1) }}</span>
            <span class="hero-score-scale">/10</span>
        </div>
        <div class="hero-score-stars" aria-hidden="true">
            @for($i = 1; $i <= 5; $i++)
                <i class="bi {{ $dharmaStars >= $i ? 'bi-star-fill' : ($dharmaStars >= $i - 0.5 ? 'bi-star-half' : 'bi-star') }}"></i>
            @endfor
        </div>
        <span class="hero-score-meta">
            {{ $count }} {{ $count === 1 ? 'calificación' : 'calificaciones' }}
        </span>
    @else
        <div class="hero-score-figure">
            <span class="hero-score-value hero-score-empty">&mdash;</span>
        </div>
        <span class="hero-score-meta">Nadie la calificó todavía</span>
    @endif
</div>
