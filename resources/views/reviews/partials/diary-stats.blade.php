{{--
    Contadores de la banda del diario. Vive en un partial porque
    ReviewController::store y WatchlistController::toggle lo devuelven como
    fragmento out-of-band: si el diario esta en pantalla al guardar, los numeros
    se actualizan solos; si no, htmx lo ignora.

    Props: $stats (ver HasUserStats::getDiaryStats), $oob (bool)
--}}
<div id="diary-stats" class="stat-tile-group" @if($oob ?? false) hx-swap-oob="true" @endif>
    <div class="stat-tile">
        <div class="stat-tile-value">{{ $stats['total_logged'] }}</div>
        <span class="stat-tile-label">Registros</span>
    </div>
    <div class="stat-tile">
        <div class="stat-tile-value text-accent">
            {{ $stats['avg_rating'] > 0 ? number_format($stats['avg_rating'], 1) : '—' }}
        </div>
        <span class="stat-tile-label">Promedio</span>
    </div>
    <div class="stat-tile">
        <div class="stat-tile-value">{{ $stats['total_notes'] }}</div>
        <span class="stat-tile-label">Notas</span>
    </div>
    <div class="stat-tile">
        <div class="stat-tile-value text-purple">{{ $stats['total_reviews'] }}</div>
        <span class="stat-tile-label">Reseñas</span>
    </div>
    <div class="stat-tile">
        <div class="stat-tile-value text-accent-info">{{ $stats['total_watchlist'] }}</div>
        <span class="stat-tile-label">Watchlist</span>
    </div>
</div>
