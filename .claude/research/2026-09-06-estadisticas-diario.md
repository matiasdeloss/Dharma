# Brief — Página de estadísticas del diario ("Tu diario en números")

**Fecha:** 2026-09-06 · **Autor:** dharma-research · **Estado:** pendiente
**Prioridad:** #3 del roadmap
**Bloqueantes:** sub-tarea de backfill de `media_items.genres` (hoy siempre `null`). Sin API nueva.

---

## 1. Qué se quiere lograr

Una página `/diary/stats` (solo con sesión) tipo "Letterboxd Year in Review" pero
personal y siempre disponible, que le muestre al usuario patrones de su propio diario:

1. **Totales** (ya calculados hoy en el diario): registros, horas, promedio, re-visionados.
2. **Histograma de calificaciones** — cuántas pelis calificó con cada valor (0.5–5 estrellas / 1–10).
3. **Actividad por mes** — visionados en los últimos 12 meses (barras).
4. **Top géneros** — géneros más vistos (barras horizontales).
5. **Por década de estreno** — cuántos títulos vio de cada década.
6. **Desglose película vs serie**.
7. **Filtro por año** — "todo el historial" o un año concreto (reusa el patrón del diario).

Esta feature **rinde con un solo usuario** (es introspección personal, no social) y
aprovecha datos que ya se están recolectando.

### Fuera de alcance
- "Más vistos por director/actor" → requiere guardar director/cast en `media_items` (no se hace hoy). Ver §7.4, opcional.
- Comparar con otros usuarios, badges, compartir en redes.

---

## 2. Qué ya existe en Dharma

| Pieza | Ruta | Estado |
|---|---|---|
| Stats del diario | `app/Http/Controllers/ReviewController.php:57-66` | Ya calcula `total_logged`, `total_hours` (`sum(media_items.runtime)/60`), `avg_rating`, `total_rewatches` con joins/aggregates sobre `reviews`. Patrón a reusar. |
| Trait de stats | `app/Traits/HasUserStats.php` | `getHeroStats()` — notas / reseñas / watchlist. |
| Modelo media | `app/Models/MediaItem.php` | `genres` en `$fillable`, `casts` → `array`. Accessor `release_year`. Campo `runtime` (int, minutos). |
| Modelo review | `app/Models/Review.php` | `rating` (float 0.5–10), `watched_date` (date), `is_rewatch`, `status`, `media_item_id`. Accessor `star_rating` (rating/2). |
| Relación | `MediaItem::reviews()`, `User::reviews()` | `hasMany`. |
| Filtro por año (patrón) | `ReviewController::index` | `whereYear('watched_date', $request->year)`. |
| Nav | `resources/views/layouts/app.blade.php:44-53` | Links "Explorar / Mis Notas / Watchlist". Hay que sumar un acceso a stats (dentro de "Mis Notas" o como pestaña de la página del diario). |
| Diario | `resources/views/reviews/index.blade.php` | Cabecera con 4 tiles de stats (`:17-46`). El diseño de tile a reusar: `.p-3.bg-dark.border.border-secondary.rounded-3` + número grande + label. |
| Skill de charts | `dataviz` (frontend) | **Obligatoria** antes de escribir cualquier gráfico. El agente de frontend la tiene que invocar. |
| Colores | `resources/scss/base/_variables.scss` + `_base.scss` | Paleta `--dharma-*` / `$color-*`: dorado `#d4af37`, crema `#dfd0b8`, info `#67b0d1`, púrpura `#b993d6`, sage `#697565`. Los charts salen de acá, no de hex nuevos. |

**Lo que NO existe y hay que crear:**
- Ruta, controller/method, vista de stats.
- `media_items.genres` poblado (hoy `firstOrCreate` en `ReviewController::store` y `WatchlistController::toggle` **no pasa `genres`**).

---

## 3. Datos externos

**Ninguna llamada nueva en runtime.** Los géneros de un título **ya vienen** en la respuesta de `TmdbService::getMovieDetails()` / `getTvDetails()` como:

```json
"genres": [ {"id": 878, "name": "Ciencia ficción"}, {"id": 18, "name": "Drama"} ]
```

El problema es que hoy no se persisten. Dos formas de arreglarlo (hacer **ambas**):

### 3.1 Poblar `genres` al registrar (hacia adelante)

En `ReviewController::store` (y `WatchlistController::toggle`), cuando se crea/actualiza el `MediaItem`, traer los detalles y guardar `genres` (y de paso `runtime` fiable). El modal ya tiene los detalles cargados; lo más limpio es que **el `store` haga la llamada** (está cacheada 24h, costo casi nulo):

```php
// dentro de store(), tras resolver $validated:
$details = $validated['media_type'] === 'tv'
    ? $this->tmdb->getTvDetails($validated['tmdb_id'])
    : $this->tmdb->getMovieDetails($validated['tmdb_id']);

$genres = collect($details['genres'] ?? [])->pluck('name')->values()->all();
$runtime = $details['runtime'] ?? ($details['episode_run_time'][0] ?? $validated['runtime'] ?? null);

$mediaItem = MediaItem::updateOrCreate(
    ['tmdb_id' => $validated['tmdb_id'], 'media_type' => $validated['media_type']],
    [ /* ...campos actuales... */, 'genres' => $genres ?: null, 'runtime' => $runtime ]
);
```

(`ReviewController` ya tiene `TmdbService` inyectado — `:20`. `WatchlistController` **no**; ahí conviene NO llamar a TMDB para no frenar el toggle — dejar que el backfill y el `store` lo cubran.)

### 3.2 Comando de backfill (para lo ya cargado)

`app/Console/Commands/BackfillMediaGenres.php` → `php artisan dharma:backfill-media`:

```php
MediaItem::query()
    ->where(fn ($q) => $q->whereNull('genres')->orWhereNull('runtime'))
    ->chunkById(50, function ($items) {
        foreach ($items as $item) {
            $d = $item->media_type === 'tv'
                ? $this->tmdb->getTvDetails($item->tmdb_id)
                : $this->tmdb->getMovieDetails($item->tmdb_id);
            if (empty($d)) continue;
            $item->update([
                'genres'  => collect($d['genres'] ?? [])->pluck('name')->all() ?: null,
                'runtime' => $d['runtime'] ?? ($d['episode_run_time'][0] ?? $item->runtime),
            ]);
            usleep(250_000); // cortesía con TMDB
        }
    });
```

En modo mock (sin key) `getMovieDetails` devuelve el mock con `genres` (Interstellar) → el comando no rompe.

---

## 4. Plan para el agente de backend

### 4.1 Ruta

`routes/web.php`, dentro del grupo `auth`, sección "Authenticated User Routes":

```php
Route::get('/diary/stats', [ReviewController::class, 'stats'])->name('reviews.stats');
```

(Alternativa: un `DiaryStatsController` dedicado. `ReviewController::stats` alcanza y ya tiene `TmdbService` + `HasUserStats`.)

### 4.2 `ReviewController::stats(Request $request)`

```php
public function stats(Request $request)
{
    $userId = Auth::id();                      // ruta ya está tras `auth`, sin fallback User::first()
    $year = $request->integer('year') ?: null;

    $base = Review::query()
        ->where('user_id', $userId)
        ->when($year, fn ($q) => $q->whereYear('watched_date', $year));

    // join a media_items para género/runtime/release_date
    $entries = (clone $base)
        ->with('mediaItem:id,media_type,genres,runtime,release_date')
        ->get();

    // --- Totales ---
    $totals = [
        'logged'      => $entries->count(),
        'hours'       => round($entries->sum(fn ($e) => $e->mediaItem?->runtime ?? 0) / 60, 1),
        'avg_rating'  => round($entries->whereNotNull('rating')->avg('rating') ?? 0, 1),
        'rewatches'   => $entries->where('is_rewatch', true)->count(),
        'movies'      => $entries->filter(fn ($e) => $e->mediaItem?->media_type === 'movie')->count(),
        'tv'          => $entries->filter(fn ($e) => $e->mediaItem?->media_type === 'tv')->count(),
    ];

    // --- Histograma de rating (10 buckets: 1..10, step 0.5 agrupado a entero o 20 buckets) ---
    $ratingHistogram = $entries->whereNotNull('rating')
        ->groupBy(fn ($e) => (string) number_format(floor($e->rating), 0))  // o mantené 0.5
        ->map->count()->sortKeys();

    // --- Por mes (últimos 12) ---
    $byMonth = $entries->whereNotNull('watched_date')
        ->groupBy(fn ($e) => $e->watched_date->format('Y-m'))
        ->map->count()->sortKeys();

    // --- Top géneros ---
    $byGenre = $entries->flatMap(fn ($e) => $e->mediaItem?->genres ?? [])
        ->countBy()->sortDesc()->take(10);

    // --- Por década ---
    $byDecade = $entries
        ->filter(fn ($e) => $e->mediaItem?->release_date)
        ->groupBy(fn ($e) => (floor((int) $e->mediaItem->release_date->format('Y') / 10) * 10) . 's')
        ->map->count()->sortKeys();

    $years = Review::where('user_id', $userId)->whereNotNull('watched_date')
        ->get()->map(fn ($r) => $r->watched_date->format('Y'))->unique()->sortDesc()->values();

    return view('reviews.stats', compact('totals','ratingHistogram','byMonth','byGenre','byDecade','years','year')
        + ['genresMissing' => MediaItem::whereNull('genres')->whereHas('reviews', fn($q)=>$q->where('user_id',$userId))->exists()]);
}
```

- **Todo en memoria con Collections** — el diario de un usuario es chico (decenas/cientos de filas). No hace falta SQL de agregación con `strftime` (portabilidad SQLite/MySQL).
- `genresMissing` → bandera para que la vista muestre un aviso "Faltan géneros de N títulos, corré `php artisan dharma:backfill-media`" (o mostrarlo solo en `APP_DEBUG`).
- **Empty state:** si `$totals['logged'] === 0`, la vista muestra un empty state (como el del diario) y no renderiza charts.

### 4.3 Backfill (§3.1 + §3.2)

- `ReviewController::store`: cambiar `MediaItem::firstOrCreate` por `updateOrCreate` y sumar `genres` + `runtime` desde `$this->tmdb->get*Details()`. **Ojo:** esto se cruza con el brief `2026-09-06-diario-revisionados.md` que también toca `store`. **Coordinar:** si se implementan juntos, un solo refactor de `store`; si este va después, respetar los cambios de aquel (create vs update).
- Comando `dharma:backfill-media` (§3.2).
- `MediaItem::updateOrCreate` en vez de `firstOrCreate` para que backfille títulos que ya existían sin género. Cuidado de **no pisar** `title`/`poster_path` con `null` si los detalles vinieran incompletos — pasar solo las claves que tienen valor.

### 4.4 Tests

- `test_diary_stats_page_requires_auth`: guest → redirect a login (grupo `auth`).
- `test_diary_stats_page_renders_with_entries`: crear user + 3 `MediaItem` con `genres` + `runtime` + 3 `Review` → `GET /diary/stats` 200, ve "Tu diario en números", ve un total.
- `test_diary_stats_empty_state`: user sin reviews → 200, ve el empty state, no ve charts.
- `test_backfill_command_fills_genres`: `MediaItem` con `genres` null + review → `artisan('dharma:backfill-media')` → `genres` no null (con el mock de TmdbService, que devuelve Interstellar con géneros).

Verificación: `php artisan test`, `php -l`, `pint --dirty`, `php artisan migrate:fresh --seed` (no hay migración nueva salvo que se haga §7.4).

---

## 5. Plan para el agente de frontend

> Paso 0: `Skill(dharma-frontend)` **y** `Skill(dataviz)` — obligatoria antes de escribir cualquier chart/tile.

### 5.1 Vista `resources/views/reviews/stats.blade.php`

`@extends('layouts.app')`. Estructura:

1. **Cabecera** con título "Tu diario en números", subtítulo, y `<select name="year">` (mismo patrón `onchange="this.form.submit()"` que el diario) con opción "Todo el historial" + `$years`.
2. **Fila de tiles** — reusar el diseño exacto de `reviews/index.blade.php:17-46` (mismas clases). 6 tiles: Registros, Horas, Promedio/10, Re-vistas, Películas, Series. Colores: `text-success`, `text-info`, `text-warning`, `text-light`, `text-accent`, `text-purple` (ya en uso).
3. **Histograma de ratings** — barras verticales. `dataviz` decide SVG inline vs librería; **preferir SVG inline** (el proyecto no tiene lib de charts y no hay que agregar una — ver §7.1). Eje X: valores de rating; eje Y: cantidad.
4. **Actividad por mes** — barras verticales, últimos 12 meses, label mes abreviado.
5. **Top géneros** — barras horizontales (nombre + barra + count), máx 10.
6. **Por década** — barras verticales o lista.
7. Si `$genresMissing` → alerta discreta (`.alert.alert-dark.border-warning`, como el banner de "Modo Demo" del home) explicando que faltan géneros.

**Empty state** (si `$totals['logged'] == 0`): copiar el patrón de `reviews/index.blade.php:192-201` ("Aún no tienes... / Explorar Películas").

### 5.2 Charts — reglas

- **Sin dependencia nueva.** SVG inline generado en Blade con un `@php` que calcula el `max` y escala. `dataviz` da la guía de proporciones, ejes, accesibilidad (`role="img"` + `<title>`/`<desc>`, o tabla oculta para lectores de pantalla).
- Colores **solo** de la paleta: barras en dorado `$color-accent-primary` / crema; hover/segundos en sage o info. Definir las que falten como CSS custom props `--dharma-chart-*` en `_base.scss` **derivadas de las existentes**, no hex nuevos.
- Nuevo parcial CSS si hace falta: `resources/scss/components/_charts.scss` importado desde `app.scss`. Mantener el patrón de archivos por componente.
- Responsive: `viewBox` + `preserveAspectRatio`, contenedor `overflow-x:auto` en mobile si el histograma no entra.

### 5.3 Acceso a la página

- En `resources/views/layouts/app.blade.php`: dentro del dropdown de usuario (`:104-113`) agregar `<a href="{{ route('reviews.stats') }}"><i class="bi bi-bar-chart-line ..."></i> Estadísticas</a>`.
- Y/o en `resources/views/reviews/index.blade.php` cabecera: un botón/pestaña "Ver estadísticas" que enlace a `route('reviews.stats')`. Sugerido: pestañas "Diario | Estadísticas" arriba de la lista.

### 5.4 Verificación

`npm run build` + `grep` en `public/build/assets/app-*.css` de las reglas de `_charts.scss`. Confirmar en el navegador de la sesión que el SVG renderiza (esto sí se ve sin Vite, es HTML). `php artisan test` si se tocó algún `.php` (no debería).

---

## 6. Contrato entre backend y frontend

| Acción | Ruta | Método | Campos | id / target | Evento HX |
|---|---|---|---|---|---|
| Ver estadísticas | `route('reviews.stats')` → `GET /diary/stats` (**auth**) | GET | query opcional `year` | página completa (no HTMX) | — |
| Cambiar año | mismo endpoint con `?year=YYYY` | GET (submit de `<form method="GET">`) | `year` | — | — |

**Vars que el backend pasa a `reviews.stats`:**

| Var | Tipo | Forma |
|---|---|---|
| `$totals` | array | `['logged'=>int,'hours'=>float,'avg_rating'=>float,'rewatches'=>int,'movies'=>int,'tv'=>int]` |
| `$ratingHistogram` | Collection | `{ "1"=>0, "2"=>1, ... }` clave = rating (entero o "x.5"), valor = count. Ordenada por clave. |
| `$byMonth` | Collection | `{ "2026-01"=>3, "2026-02"=>5, ... }` clave `Y-m`, valor count. Ordenada. |
| `$byGenre` | Collection | `{ "Drama"=>12, "Ciencia ficción"=>8, ... }` top 10 desc. |
| `$byDecade` | Collection | `{ "1990s"=>2, "2000s"=>7, "2010s"=>15, ... }` ordenada. |
| `$years` | Collection<string> | `["2026","2025",...]` para el `<select>`. |
| `$year` | int\|null | año activo del filtro. |
| `$genresMissing` | bool | mostrar aviso de backfill. |

**Nombres nuevos:**
- Ruta: `reviews.stats` (`GET /diary/stats`), en el grupo `auth`.
- Vista: `resources/views/reviews/stats.blade.php`.
- Comando: `php artisan dharma:backfill-media` (clase `App\Console\Commands\BackfillMediaGenres`).
- SCSS: `resources/scss/components/_charts.scss` (si se necesita), import en `app.scss`.
- **Sin `HX-Trigger` nuevo, sin migración** (salvo §7.4 opcional).

---

## 7. Riesgos y casos borde

1. **Sin librería de charts.** El proyecto no tiene Chart.js/ApexCharts y **no hay que agregarla** (regla de los agentes: no sumar deps sin justificar). SVG inline en Blade. `dataviz` guía el diseño; si el agente insiste en una lib, que lo justifique en el reporte y lo apruebe el usuario primero.
2. **`genres` null tras el deploy** hasta correr el backfill → todos los charts de género vacíos. Por eso `$genresMissing` + aviso. El comando hay que correrlo una vez (y queda cubierto hacia adelante por el cambio en `store`).
3. **Cruce con el brief de re-visionados.** Ambos modifican `ReviewController::store` y la relación reviews↔media. Si se hacen en paralelo, coordinar en un solo refactor. Si `stats` va primero y después entra re-visionados, el de re-visionados debe preservar el backfill de `genres`.
4. **"Más vistos por director" (opcional).** Requiere `media_items.director` (string) — migración nueva + poblarlo en `store`/backfill desde `$details['credits']['crew']` (job Director) o `$details['created_by']` para TV. **No incluido** en el alcance base; si se quiere, es una migración `add_director_to_media_items` + una barra horizontal más. Bajo esfuerzo si el backfill ya está.
5. **Re-visionados inflan `hours`.** Consistente con Letterboxd (ver brief de re-visionados §7.5). Si el usuario prefiere "horas de contenido único", agregar un toggle — fuera de alcance base.
6. **Década con `release_date` null.** Filtrado (`->filter(fn($e)=>$e->mediaItem?->release_date)`). Contar los excluidos y mostrarlos como "sin fecha: N" si se quiere.
7. **Histograma con step 0.5.** Decidir: 10 barras (enteros, `floor`) es más legible; 19 barras (0.5→10) es más fiel. Sugerido: enteros, con tooltip del detalle. `dataviz` tiene la última palabra.
8. **Performance.** `->with('mediaItem')->get()` sobre todo el diario: para cientos de filas es trivial. Si algún día son miles, mover a agregación SQL. No optimizar ahora.
9. **`$request->integer('year')`** devuelve 0 si no viene → `?: null` lo normaliza. Validar que `year` esté en `$years` para no permitir `?year=1` (cosmético; el `whereYear` simplemente no matchea).
10. **Timezone de `watched_date`.** Es `date` (sin hora), cast a Carbon a medianoche. `format('Y-m')` es estable. Ok.

---

## 8. Fuentes

- [Letterboxd — 2025 Year in Review FAQ (qué métricas muestran: total, horas, década, género, más vistos)](https://letterboxd.com/journal/2025-letterboxd-year-in-review-faq/)
- [Serializd — "Time spent watching TV" stat](https://www.tvscholar.com/p/is-serializd-the-tv-fans-letterboxd)
- [TMDB — géneros en la respuesta de detalles](https://developer.themoviedb.org/reference/movie-details)
- Código auditado: `app/Http/Controllers/ReviewController.php`, `app/Http/Controllers/WatchlistController.php`, `app/Models/MediaItem.php`, `app/Models/Review.php`, `app/Services/TmdbService.php`, `resources/views/reviews/index.blade.php`, `resources/scss/base/_variables.scss`, `routes/web.php`.
