{{--
    Composición de la lista para el encabezado. También viaja como fragmento
    out-of-band al quitar un título (MediaListController::removeItem).

    Props: $counts (total, movies, series), $oob (bool)
--}}
<div id="list-stats" class="stat-tile-group" @if($oob ?? false) hx-swap-oob="true" @endif>
    <div class="stat-tile">
        <div class="stat-tile-value">{{ $counts['total'] }}</div>
        <span class="stat-tile-label">{{ $counts['total'] === 1 ? 'Título' : 'Títulos' }}</span>
    </div>
    <div class="stat-tile">
        <div class="stat-tile-value">{{ $counts['movies'] }}</div>
        <span class="stat-tile-label">Películas</span>
    </div>
    <div class="stat-tile">
        <div class="stat-tile-value text-purple">{{ $counts['series'] }}</div>
        <span class="stat-tile-label">Series</span>
    </div>
</div>
