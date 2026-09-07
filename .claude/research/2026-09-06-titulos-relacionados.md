# Brief — Riel "Títulos relacionados" en la ficha + ficha enriquecida

**Fecha:** 2026-09-06 · **Autor:** dharma-research · **Estado:** pendiente
**Prioridad:** #2 del roadmap (mejor relación valor/esfuerzo)
**Bloqueantes:** ninguno — sin API nueva, sin migración, sin dependencia.

---

## 1. Qué se quiere lograr

La ficha de detalle (`/media/{type}/{id}`) hoy es un **callejón sin salida**: no hay ni un enlace a otro título. Se cierra la sesión ahí.

1. **Riel "Títulos relacionados"** debajo de "Reseñas de la Comunidad": carrusel horizontal con recomendaciones de TMDB (fallback a "similares"), reusando el carrusel y la `movie-card` que ya existen en el home. Cada card enlaza a su ficha y tiene su botón de watchlist.
2. **Ficha enriquecida con datos que ya se descargan pero no se muestran:**
   - **Clasificación por edad** (`rated` de OMDb: "PG-13", "R", etc.) como badge en los metadatos.
   - **Premios** (`awards` de OMDb: "Won 1 Oscar. 44 wins & 148 nominations total") en la "Ficha Técnica".
3. **(Opcional, mismo esfuerzo)** paginación en `/search` — el controller ya pasa `page`/`totalPages` y no se renderiza nada.

### Fuera de alcance
- Páginas de persona (reparto clickeable), colecciones/sagas, discover por género. Otros briefs.

---

## 2. Qué ya existe en Dharma

| Pieza | Ruta | Estado |
|---|---|---|
| Detalles TMDB | `app/Services/TmdbService.php:178` `getMovieDetails()` / `:188` `getTvDetails()` | Ya piden `append_to_response=credits,videos,recommendations,similar,watch/providers,external_ids`. O sea **`$details['recommendations']` y `$details['similar']` ya vienen en cada llamada**. Cache 24h. |
| Controller de la ficha | `app/Http/Controllers/MediaController.php:124` `show()` | Arma `$media`, `$mediaItem`, `$userReview`, `$inWatchlist`, `$communityReviews`, `$watchProviders`, `$imdbId`, `$omdbRatings`, `$dharmaAvg`, `$dharmaCount`. **No pasa nada de recommendations/similar.** |
| Helper watchlist | `MediaController.php:189` `attachWatchlistStatus(array $items, ?int $userId, string $defaultType)` | Ya existe. Marca `in_watchlist` en cada item de una lista TMDB. **Reutilizable tal cual** para el riel. |
| OMDb ratings | `app/Services/OmdbService.php:73` `normalizeRatings()` | Devuelve `['imdb','imdb_votes','rotten_tomatoes','metacritic','awards','rated']`. `awards` y `rated` ya calculados, **nunca renderizados**. En modo mock (`:103`) también los devuelve. |
| Vista ficha | `resources/views/media/show.blade.php` | Metadatos en `:66-83` (año · director · duración). "Ficha Técnica" en `:287-333` (`<ul>` con director, estreno, estado TMDB, idioma, presupuesto, recaudación). Sección "Reseñas de la Comunidad" termina en `:281`. |
| `movie-card` partial | `resources/views/media/partials/movie-card.blade.php` | Recibe `['item' => [...], 'type' => 'movie'|'tv', 'colClass' => '...', 'rank' => n?]`. `item` puede traer `id`/`tmdb_id`, `title`/`name`, `media_type`, `release_date`/`first_air_date`, `poster_path`, `vote_average`, `in_watchlist`. Incluye el `ribbon-button` de watchlist. |
| Carrusel | `resources/views/home.blade.php:95-117` (markup) + `resources/js/modules/sliders.js` | Estructura: `.media-slider-container > .media-slider-header` + `.media-slider-wrapper > (.slider-nav-prev, .media-slider-track > cards, .slider-nav-next)`. Cards con `colClass => 'media-slider-col'`. `initMediaSliders()` corre en `DOMContentLoaded` **y** en `htmx:load` (`resources/js/app.js:31`). Idempotente (`dataset.sliderInitialized`). |
| Búsqueda | `MediaController.php:85` `search()` + `resources/views/media/search.blade.php` | Controller pasa `page`, `totalPages`. Vista: grid de `movie-card`, **sin paginación**. |
| Tests | `tests/Feature/MediaAppTest.php:45` `test_media_detail_page_loads` | Chequea que la ficha carga y ve "Interstellar", "Sinopsis", "Reparto Principal", "Disponible en:", "TMDB", "IMDb". |

---

## 3. Datos externos

### 3.1 TMDB — recommendations / similar (ya se descargan)

Vienen embebidos en la respuesta de `GET /movie/{id}?append_to_response=recommendations,similar` (y `/tv/{id}`). No hay que hacer llamada nueva.

**Forma de `$details['recommendations']` (recortada):**
```json
{
  "recommendations": {
    "page": 1,
    "results": [
      {
        "id": 27205,
        "title": "Inception",
        "name": null,
        "original_title": "Inception",
        "overview": "Dom Cobb es un ladrón...",
        "poster_path": "/9gk7adHYeDvHkCSEqAvQNLV5Uge.jpg",
        "backdrop_path": "/s3TBrRGB1iav7gFOCNx3H31MoES.jpg",
        "media_type": "movie",
        "vote_average": 8.369,
        "release_date": "2010-07-15",
        "first_air_date": null,
        "genre_ids": [28, 878, 12]
      }
    ],
    "total_pages": 3,
    "total_results": 40
  }
}
```

- `recommendations.results[]` **incluye `media_type`** (`movie`/`tv`).
- `similar.results[]` tiene la **misma forma pero SIN `media_type`** → hay que asumir el `$type` de la ficha actual.
- Para series los campos de título/fecha son `name` / `first_air_date`.
- Puede venir `results: []` (títulos oscuros). Por eso el fallback: recommendations → si vacío, similar → si vacío, no renderizar la sección.

### 3.2 OMDb — `rated` y `awards` (ya se normalizan)

`$omdbRatings['rated']` → string tipo `"PG-13"`, `"R"`, `"TV-MA"`, o `null`.
`$omdbRatings['awards']` → string tipo `"Won 1 Oscar. 44 wins & 148 nominations total"`, o `null`.
En modo mock (`OMDB_API_KEY` vacío) devuelve `rated: 'PG-13'`, `awards: 'Won 1 Oscar. ...'`, `is_mock: true`.

**Ninguna key nueva.** Misma API que ya se usa.

---

## 4. Plan para el agente de backend

Cambio chico, todo en `MediaController::show`.

### 4.1 Normalizar el riel de relacionados

Agregar un método protegido en `MediaController` (o en `TmdbService` si el agente prefiere que la normalización viva en el service — recomendado: `TmdbService::extractRelated(array $details, string $fallbackType): array`):

```php
protected function extractRelated(array $details, string $fallbackType): array
{
    $rec = $details['recommendations']['results'] ?? [];
    $sim = $details['similar']['results'] ?? [];
    $pool = !empty($rec) ? $rec : $sim;

    return collect($pool)
        ->filter(fn ($i) => !empty($i['poster_path']))               // sin poster no entra
        ->map(function ($i) use ($fallbackType) {
            $i['media_type'] = $i['media_type'] ?? $fallbackType;    // 'similar' no trae media_type
            return $i;
        })
        ->filter(fn ($i) => in_array($i['media_type'], ['movie', 'tv']))
        ->unique('id')
        ->take(18)
        ->values()
        ->all();
}
```

En `show()`:
```php
$related = $this->tmdb->extractRelated($details, $type);
$related = $this->attachWatchlistStatus($related, $userId);   // helper ya existente
```
y pasarlo a la vista: `'related' => $related`.

### 4.2 Pasar `rated`/`awards` (ya están en `$omdbRatings`)

No hace falta tocar nada en el controller: `$omdbRatings` ya se pasa a la vista y ya contiene `rated` y `awards`. Es 100% frontend.

### 4.3 (Opcional) paginación de búsqueda

`MediaController::search` ya pasa `page` y `totalPages`. Solo hace falta que la vista los use (frontend). Si se quiere limitar: TMDB devuelve `total_pages` que puede ser enorme (500 máx); cap a `min($totalPages, 500)`.

### 4.4 Tests

- Extender `test_media_detail_page_loads`: `$response->assertSee('Títulos relacionados')` (con el mock de `TmdbService`, ver 7.1 — **el mock actual NO incluye `recommendations` con resultados**, hay que ampliarlo o el test debe tolerar la ausencia).
- **Ampliar el mock de `TmdbService::getMockData`** (`app/Services/TmdbService.php:327`, rama `/movie/` `/tv/`) para que `recommendations.results` traiga 2-3 items (hoy tiene `'recommendations' => ['results' => []]` en `:367`). Así el riel se puede testear y se ve en modo demo.
- Nuevo `test_related_rail_hidden_when_no_recommendations`: forzar `recommendations` y `similar` vacíos → la sección no aparece.

Verificación: `php artisan test`, `php -l`, `php vendor/bin/pint --dirty`.

---

## 5. Plan para el agente de frontend

> Paso 0: `Skill(dharma-frontend)`. Reusar variables/clases existentes; el carrusel tiene matemática propia (ver skill) — **copiar la estructura del home tal cual**, no reinventar.

### 5.1 Riel de relacionados en `resources/views/media/show.blade.php`

Después del cierre de la sección "Reseñas de la Comunidad" (después de `:281`, dentro del `container` o en uno nuevo `container py-2 mb-5`), agregar:

```blade
@if(!empty($related))
<div class="media-slider-container mb-5">
    <div class="media-slider-header">
        <h3 class="section-title">Títulos relacionados</h3>
        <span class="section-subtitle">Si te gustó {{ $title }}, quizás te interese</span>
    </div>
    <div class="media-slider-wrapper position-relative">
        <button type="button" class="slider-nav-arrow slider-nav-prev" aria-label="Anterior" title="Anterior">
            <i class="bi bi-chevron-left"></i>
        </button>
        <div class="media-slider-track">
            @foreach($related as $item)
                @include('media.partials.movie-card', [
                    'item' => $item,
                    'type' => $item['media_type'] ?? $type,
                    'colClass' => 'media-slider-col',
                ])
            @endforeach
        </div>
        <button type="button" class="slider-nav-arrow slider-nav-next" aria-label="Siguiente" title="Siguiente">
            <i class="bi bi-chevron-right"></i>
        </button>
    </div>
</div>
@endif
```

- **No hace falta JS nuevo:** `initMediaSliders()` ya corre en `DOMContentLoaded` (la ficha es carga completa de página). Verificar que el `container` donde se mete no rompa el layout de la grilla de abajo (la ficha tiene 2 `container` separados; meterlo en uno propio).
- La `movie-card` ya trae el `ribbon-button` de watchlist que hace `POST /watchlist/toggle` — funciona sin tocar nada.
- **`section-title` / `section-subtitle` / `media-slider-*`** ya están definidos en `resources/scss/components/_sliders.scss` y usados en el home. Cero SCSS nuevo esperado. Si el riel dentro de la ficha necesita un ajuste de margen, hacerlo con utilidades o una regla mínima en `_media_detail.scss` usando `--dharma-*`.

### 5.2 Clasificación por edad (badge)

En `resources/views/media/show.blade.php`, en la fila de metadatos (`:66-79`, después de la duración) o junto a los badges de género (`:98-107`):

```blade
@if(!empty($omdbRatings['rated']))
    <span class="badge-media-type" title="Clasificación por edad (MPAA / TV)">{{ $omdbRatings['rated'] }}</span>
@endif
```

Reusar `.badge-media-type` o `.badge-genre` (ya en `_ratings.scss`). No crear estilo nuevo salvo que se quiera diferenciar visualmente (entonces `.badge-rated` en `_ratings.scss` con `--dharma-*`).

### 5.3 Premios en "Ficha Técnica"

En el `<ul>` de la Ficha Técnica (`:290-332`), agregar un `<li>` (full-width, no el `justify-content-between` de los demás porque el texto es largo):

```blade
@if(!empty($omdbRatings['awards']))
    <li>
        <span class="text-secondary d-block mb-1"><i class="bi bi-trophy me-1 text-accent"></i>Premios:</span>
        <span class="text-white">{{ $omdbRatings['awards'] }}</span>
    </li>
@endif
```

### 5.4 (Opcional) paginación en `resources/views/media/search.blade.php`

Después de la grilla de resultados (`:41`), si `$totalPages > 1`:

```blade
<nav class="d-flex justify-content-center gap-2 mt-4" aria-label="Paginación de resultados">
    @if($page > 1)
        <a href="{{ route('media.search', ['q' => $query, 'page' => $page - 1]) }}" class="btn btn-cine-secondary">
            <i class="bi bi-chevron-left"></i> Anterior
        </a>
    @endif
    <span class="btn btn-outline-secondary disabled">Página {{ $page }} de {{ min($totalPages, 500) }}</span>
    @if($page < min($totalPages, 500))
        <a href="{{ route('media.search', ['q' => $query, 'page' => $page + 1]) }}" class="btn btn-cine-secondary">
            Siguiente <i class="bi bi-chevron-right"></i>
        </a>
    @endif
</nav>
```

(`btn-cine-secondary` ya existe.)

### 5.5 Verificación

`npm run build` + `grep` sobre `public/build/assets/app-*.css` si se tocó SCSS. Si no se tocó SCSS, confirmar en el navegador de la sesión que el HTML/Blade no tira error (el carrusel no se va a mover ahí porque no llega a Vite, pero se ve la estructura).

---

## 6. Contrato entre backend y frontend

| Cosa | Valor |
|---|---|
| Var nueva de la vista `media/show` | `$related` — `array` de items estilo TMDB (`id`, `title`/`name`, `media_type`, `poster_path`, `vote_average`, `release_date`/`first_air_date`, `in_watchlist`). Puede ser `[]`. |
| Var ya existente que se empieza a usar | `$omdbRatings['rated']` (string\|null), `$omdbRatings['awards']` (string\|null) |
| Vars ya existentes (búsqueda) | `$page` (int), `$totalPages` (int) |
| Partial reusado | `media.partials.movie-card` con `['item','type','colClass'=>'media-slider-col']` |
| Rutas | **Ninguna nueva.** El riel usa `route('media.show', ...)` y `route('watchlist.toggle')` (vía el ribbon-button del partial). |
| Eventos `HX-Trigger` | **Ninguno nuevo.** (Los botones de watchlist dentro del riel disparan `watchlistUpdated`, ya registrado.) |
| Migración | **Ninguna.** |
| JS | **Ninguno nuevo.** `initMediaSliders()` ya cubre carga de página y `htmx:load`. |

---

## 7. Riesgos y casos borde

1. **El mock de `TmdbService` no trae recommendations.** `getMockData` (`app/Services/TmdbService.php:367`) tiene `'recommendations' => ['results' => []]`. En modo demo (sin `TMDB_API_KEY`) y **en los tests** el riel no aparece. Hay que **ampliar el mock** con 2-3 items en `recommendations.results` (usar los otros títulos que ya define el mock: Inception, Breaking Bad, Dune). Si no, el test que asserta "Títulos relacionados" falla.
2. **`similar` sin `media_type`.** Cubierto en 4.1 (se asume `$type` de la ficha). Riesgo: un `/movie/{id}` cuyo `similar` incluya algo que en realidad es serie — raro, TMDB no mezcla en `similar`. Aceptable.
3. **Items sin poster.** Filtrados en 4.1 (`poster_path` vacío → fuera). Evita cards con `no-poster.svg` en el riel.
4. **Carrusel dentro de la ficha y el layout de 2 columnas de abajo.** La ficha tiene `container` con `row g-5` (cast + comunidad / sidebar). Meter el riel en un `container` **separado** después de ese `row`, no dentro de la `col-lg-8`, o el carrusel queda angosto.
5. **`recommendations` con muchísimos resultados** (`total_results: 40+`). Cap a 18 en backend (4.1). No paginar el riel.
6. **OMDb `rated` para contenido no-US.** Puede venir `"N/A"` → `normalizeRatings` ya lo filtra a `null` (`OmdbService.php:81`). Ok.
7. **`awards` largo** rompe el layout del `<li>` `justify-content-between`. Por eso en 5.3 va como `<li>` full-width con label arriba y texto abajo.
8. **Modo mock de OMDb** siempre muestra "PG-13" y el texto de Oscars aunque no sea real. Es consistente con cómo ya funciona el resto de ratings en demo (el banner de "Modo Demo" del home ya avisa). Aceptable; si molesta, condicionar a `empty($omdbRatings['is_mock'])`.
9. **`test_media_detail_page_loads` es estricto con `assertSee`.** Al agregar contenido no se rompe, pero si se agrega el assert de "Títulos relacionados" hay que haber ampliado el mock primero (orden de trabajo: mock → vista → test).
10. **Paginación de búsqueda + búsqueda vacía.** Si `q` está vacío el controller devuelve `results: []` sin `page`/`totalPages` en una de las ramas (`MediaController.php:94`) — la vista debe usar `$totalPages ?? 1` y `$page ?? 1` para no romper con `Undefined variable`.

---

## 8. Fuentes

- [TMDB — Movie Recommendations endpoint](https://developer.themoviedb.org/reference/movie-recommendations)
- [TMDB — Discover / relacionados por proveedor y metadata](https://www.themoviedb.org/talk/5ff0a2b7176a940045e88665)
- [OMDb API — campos `Rated` y `Awards`](https://www.omdbapi.com/)
- Código auditado: `app/Http/Controllers/MediaController.php`, `app/Services/TmdbService.php`, `app/Services/OmdbService.php`, `resources/views/media/show.blade.php`, `resources/views/media/partials/movie-card.blade.php`, `resources/views/home.blade.php`, `resources/js/modules/sliders.js`, `resources/js/app.js`, `tests/Feature/MediaAppTest.php`.
