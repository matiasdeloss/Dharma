{{-- Fragmento HTMX out-of-band: si el hero del home está en pantalla, actualiza
     sus 3 contadores en vivo. Si no está (otra página), htmx lo ignora solo. --}}
<div id="hero-stat-notes" hx-swap-oob="true" class="hero-stat-number text-cream">{{ $stats['total_notes'] ?? 0 }}</div>
<div id="hero-stat-reviews" hx-swap-oob="true" class="hero-stat-number text-purple">{{ $stats['total_reviews'] ?? 0 }}</div>
<div id="hero-stat-watchlist" hx-swap-oob="true" class="hero-stat-number text-accent-info">{{ $stats['total_watchlist'] ?? 0 }}</div>
