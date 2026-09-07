# Roadmap de features — Dharma

**Fecha:** 2026-09-06
**Autor:** dharma-research
**Estado:** pendiente (nada implementado todavía)

Diagnóstico del estado real del código + lista priorizada. Las 3 candidatas top
tienen brief propio en este mismo directorio.

---

## 1. Diagnóstico del estado actual (sale del código, no de suposiciones)

### 1.1 Pantallas que existen

| Ruta | Vista | Estado |
|---|---|---|
| `/` | `home.blade.php` | OK. 3 carruseles TMDB (trending movie/tv/all) + feed "Últimas Reseñas y Notas" + hero con contadores. |
| `/search` | `media/search.blade.php` | **Callejón parcial:** sin paginación (ver 1.3). |
| `/media/{type}/{id}` | `media/show.blade.php` | OK como ficha, pero **es un callejón sin salida**: no hay ningún enlace a otros títulos (el reparto es texto plano, no hay "relacionadas" aunque el dato ya se pide). |
| `/diary` | `reviews/index.blade.php` | Funciona pero incompleto (ver 1.3): filtros muertos, sin re-visionados. |
| `/watchlist` | `watchlist/index.blade.php` | OK. Grid + paginación + "marcar vista". |
| `/login`, `/register` | `auth/*` | OK. |
| `welcome.blade.php` | — | Archivo default de Laravel, sin ruta. Muerto. |

### 1.2 Modelo: campos que se guardan pero no se muestran / no se pueden editar

| Campo | Migración | Realidad |
|---|---|---|
| `reviews.status` (`watched/watching/plan_to_watch/dropped`) | sí | El modal de log (`resources/views/reviews/partials/log-modal.blade.php:46`) manda **`status=watched` hardcodeado en un hidden**. Los otros 3 estados son inalcanzables desde la UI. El filtro del diario (`reviews/index.blade.php:62-67`) ofrece "En progreso" y "Por ver" → nunca matchean nada. |
| `reviews.is_rewatch` | sí | El modal manda **`is_rewatch=0` hardcodeado** (`log-modal.blade.php:47`). Nunca se puede marcar un re-visionado. La stat "Re-vistas" del diario (`ReviewController.php:65`) siempre da 0. |
| `reviews.contains_spoilers` | sí | Se **guarda** (hay switch en el modal) pero **no se respeta al mostrar**: el texto de reseñas con spoiler se renderiza tal cual en el feed del home (`home.blade.php:229`) y en "Reseñas de la Comunidad" (`show.blade.php:274`). |
| `reviews.watched_date` | sí | Se guarda pero **no es editable**: el modal lo manda en un hidden con `date('Y-m-d')` o la fecha previa (`log-modal.blade.php:48`). No se puede corregir "la vi hace 2 semanas". |
| `media_items.genres` (json) | sí | En `$fillable` y casteado a `array`, pero **`firstOrCreate` en `ReviewController::store` y `WatchlistController::toggle` nunca lo pasa**. Siempre `null` salvo lo que carga el seeder. Bloquea cualquier stat o browse por género. |
| `watchlists.priority` (`high/medium/low`) | sí, default `medium` | En `$fillable`, lo setea el seeder, **cero UI** para verlo o cambiarlo. |
| `watchlists.notes` | sí | Idem: `$fillable` + seeder, sin UI. |
| OMDb `rated` (clasificación por edad) y `awards` | — | `OmdbService::normalizeRatings` (`app/Services/OmdbService.php:80-81`) los normaliza y devuelve. **Nunca se renderizan.** |

### 1.3 Flujos rotos o incompletos

- **No se puede registrar un re-visionado.** `Review::updateOrCreate` sobre la clave única `(user_id, media_item_id)` (`ReviewController.php:176` + índice único en la migración de `reviews`) implica **una fila por usuario+título**. El "diario" no es un diario: es "mi rating actual por película". Loguear otra vez pisa la entrada anterior. Es la diferencia estructural con Letterboxd/Serializd, donde el diario tiene N entradas fechadas por título.
- **Editar una reseña ya guardada:** sí se puede (dropdown "Editar reseña y notas" en `reviews/index.blade.php:141` y botón en la ficha), pero solo edita rating / texto / notas / spoilers. Fecha, estado y re-visionado no (ver 1.2).
- **Búsqueda sin paginación.** `MediaController::search` pasa `page` y `totalPages` a la vista (`MediaController.php:116-117`) pero `media/search.blade.php` **no renderiza ningún control de página**. Solo se ven los primeros ~20 resultados de TMDB para cualquier query.
- **Estados vacíos:** diario, watchlist y búsqueda tienen empty state. El feed de reseñas del home simplemente se oculta si no hay. OK.
- **Título sin poster / sin fecha:** manejado. `movie-card`, `show.blade.php` y los helpers caen a `images/no-poster.svg`; el año se omite con `@if`. La ficha con `backdrop` nulo pinta una banda oscura vacía (aceptable).
- **Ficha técnica:** muestra `budget`/`revenue`/`status`/`original_language` de TMDB solo si vienen; para series casi nunca vienen → panel muy vacío en series.

### 1.4 Bugs de privacidad / seguridad (reportar, NO arreglar acá)

1. **`private_notes` se muestra en público.** El feed "Últimas Reseñas y Notas" del home (`home.blade.php:232-236`) renderiza `private_notes` de cualquier usuario, visible **sin login**. Las notas privadas dejan de serlo.
2. **Fallback `User::first()` para invitados.** `MediaController` (`:47`, `:102`), `ReviewController::index` (`:30`) y `WatchlistController::index` (`:21`) hacen `Auth::id() ?? User::first()?->id`. Consecuencias con la sesión cerrada:
   - `/diary` muestra el diario **y las notas privadas** del usuario #1 sin pedir login (la ruta está en el grupo `auth`, pero el controller igual resuelve un id).  *(en realidad `/diary` sí está tras `auth` middleware; el problema real es el de abajo)*
   - En el home y las fichas, un invitado ve los marcadores de watchlist del usuario #1.
3. **`ReviewController::destroy` con fallback de id.** `$userId = Auth::id() ?? User::first()?->id` y luego `if ($review->user_id !== $userId) abort(403)`. La ruta está tras `auth`, así que hoy no es explotable, pero el patrón es frágil: si la ruta saliera del grupo `auth` un invitado podría borrar reseñas del usuario #1.
4. **`contains_spoilers` ignorado** al renderizar (ver 1.2) — no es seguridad pero sí un defecto de producto visible.
5. `getHeroStats` (`app/Traits/HasUserStats.php:20-22`): `total_reviews` cuenta reseñas con texto y **si da 0 cae a contar TODAS las reseñas**. Contador inconsistente.

### 1.5 Capacidades TMDB/OMDb ya integradas pero subutilizadas

| Capacidad | Dónde ya está | Uso actual |
|---|---|---|
| `recommendations` + `similar` | `TmdbService::getMovieDetails` / `getTvDetails` piden `append_to_response=...,recommendations,similar,...` (`app/Services/TmdbService.php:181,191`) | **Cero.** Se descargan en cada ficha y se tiran. |
| `genres` de la ficha (`[{id,name}]`) | Viene en `getMovieDetails`/`getTvDetails`, se muestra como badges en `show.blade.php:103` | No se persiste en `media_items.genres`. |
| `external_ids` / `imdb_id` | Se pide y se usa para OMDb e link a IMDb | OK, bien usado. |
| `videos` | Se usa para el trailer | OK. |
| `watch/providers` | `extractWatchProviders`, se muestra inline en la ficha | OK. Nota: el partial `media/partials/watch-providers.blade.php` quedó **huérfano** (la ficha tiene su propia versión inline). |
| OMDb `rated` / `awards` | `normalizeRatings` | No se muestran (ver 1.2). |
| `TmdbService::getTvImages` | método completo | **Nunca se llama.** |
| TMDB `/discover/*` | no integrado | No existe browse por género/año/plataforma. |
| TMDB per-season / episodios | no integrado | Series se tratan como unidad. |

---

## 2. Comparación con apps de referencia

- **Letterboxd:** diario de películas con **entradas fechadas y re-visionados**, rating en medias estrellas, reseñas, **likes/corazón**, **listas** (públicas/privadas, rankeadas, drag&drop), watchlist, **estadísticas anuales** ("Year in Review": total, horas, por década, por género, más vistos), 4 favoritas en el perfil.  [Letterboxd FAQ](https://letterboxd.com/about/faq/) · [Lists](https://letterboxd.com/lists/)
- **Serializd:** "Letterboxd para TV". Rating y reseña **por episodio y por temporada**, "currently watching", **"dropped" / graveyard**, "tiempo viendo TV", badges, feed social.  [tvscholar](https://www.tvscholar.com/p/is-serializd-the-tv-fans-letterboxd)
- **Trakt:** scrobbling automático, **progreso de series**, **calendario de estrenos** de lo que seguís, historial.  [makeuseof](https://www.makeuseof.com/tv-time-vs-trakt-vs-serializd-best-movies-shows-tracker/)
- **TMDB:** **discover / browse por género, año, plataforma**, recomendaciones, colecciones (sagas).  [TMDB discover](https://www.themoviedb.org/talk/5ff0a2b7176a940045e88665)

### Qué define la categoría y Dharma no tiene

1. Diario con re-visionados (entradas fechadas múltiples) — **Letterboxd + Serializd + Trakt lo tienen; Dharma no.**
2. Navegación entre títulos (relacionadas / recomendadas / "más de este género") — **todas.**
3. Estadísticas / retrospectiva del año — **Letterboxd, Serializd.**
4. Listas / colecciones curadas — **Letterboxd (feature estrella).**
5. Estados de visionado usables (viendo / por ver / abandonada) — **Serializd, Trakt.**
6. Likes / favoritas — **Letterboxd.**
7. Tracking de episodios/temporadas — **Serializd, Trakt.**
8. Calendario de estrenos — **Trakt.**

---

## 3. Lista priorizada

Criterio: **valor para UN usuario solo** (features que necesitan masa de usuarios pesan menos), esfuerzo, y bloqueantes (API key / dependencia / migración). Ordenada por relación valor/esfuerzo para Dharma tal como está hoy.

| # | Feature | Valor (solo) | Esfuerzo | Bloqueantes | Notas |
|---|---|---|---|---|---|
| **1** | **Diario real: re-visionados + estados + fecha editable** | Muy alto — arregla el núcleo del producto (que hoy es "1 rating por peli", no un diario) y desbloquea 3 filtros y 1 stat que ya están en la UI pero muertos | Medio | Migración: quitar índice único `reviews(user_id, media_item_id)`. Sin API nueva. | **Brief:** `2026-09-06-diario-revisionados.md`. Incluye arreglar el render de `contains_spoilers`. |
| **2** | **Riel "Títulos relacionados" en la ficha + ficha enriquecida (clasificación por edad, premios)** | Alto — convierte la ficha (hoy callejón sin salida) en punto de navegación; mejora retención de sesión | Bajo — el dato de `recommendations`/`similar` YA se descarga; `rated`/`awards` YA se normalizan | Ninguno. Sin API nueva, sin migración, sin dependencia. | **Brief:** `2026-09-06-titulos-relacionados.md`. Mejor relación valor/esfuerzo del roadmap. |
| **3** | **Página de estadísticas del diario ("Tu diario en números")** | Alto para un diarista solo — histograma de ratings, por género, por década, por mes, horas | Medio | Sub-tarea: backfill de `media_items.genres` (hoy siempre null). Sin API nueva (los géneros vienen en la ficha que ya se pide). | **Brief:** `2026-09-06-estadisticas-diario.md`. |
| 4 | **Listas / colecciones curadas** (rankeadas, privadas/públicas) | Medio-alto — sirve solo (organizar maratones, "mi top 100"), es la feature estrella de Letterboxd | Alto — tabla nueva + pivote + CRUD + rutas + vistas + reorder | Migración (2 tablas: `lists`, `list_items`). Sin API. | Candidata a 4º brief cuando se libere alguna de las top 3. |
| 5 | **Browse / discover por género y año** | Medio — complementa la búsqueda; da un modo "exploración" real | Medio | Métodos nuevos en `TmdbService` (`/discover/movie`, `/discover/tv`, `/genre/*/list`) + página. Sin key nueva (misma API). | Sinergiza con el backfill de géneros del #3. |
| 6 | **Watchlist con prioridad + orden + notas** | Medio-bajo — las columnas `priority`/`notes` ya existen | Bajo-medio | Ninguno (columnas ya migradas). | Quick win de "terminar lo empezado". |
| 7 | **Paginación en `/search`** | Medio — hoy solo se ven ~20 resultados | Muy bajo | Ninguno (el controller ya pasa `page`/`totalPages`). | Fix chico, se puede colar en el brief #2 o #5. |
| 8 | **Calendario de estrenos de la watchlist** (Trakt-style) | Medio para solo — "qué se viene de lo que quiero ver" | Medio | `TmdbService`: fechas de estreno / `/tv/{id}` next_episode_to_air. Sin key nueva. | |
| 9 | **Likes / favoritas (4 en perfil)** | Bajo estando solo — el corazón brilla en lo social | Bajo-medio | Migración (`reviews.liked` o tabla `favorites`). | Requiere página de perfil para las 4 favoritas → depende de #11. |
| 10 | **Tracking de episodios / temporadas (Serializd-style)** | Bajo-medio para el estado actual — el producto trata las series como unidad y es coherente | **Muy alto** — llamadas per-season a TMDB, esquema nuevo grande, UI de progreso | Migración grande + muchas llamadas TMDB. | **NO construir ahora** (ver §4). |
| 11 | **Perfiles públicos + feed social + follow + comentarios** | Bajo — un dev solo no tiene con quién socializar; "Reseñas de la Comunidad" ya es un stub | Alto | — | **NO construir ahora** (ver §4). |

---

## 4. Qué NO conviene construir todavía (y por qué)

- **#10 Tracking de episodios/temporadas.** El esfuerzo es enorme (esquema de temporadas/episodios, N llamadas `/tv/{id}/season/{n}` por serie, UI de progreso, migración pesada) y el producto hoy es **coherente** tratando cada serie como una unidad con un rating. No hay señal de que el usuario quiera esto. Si en algún momento se quiere "diario de TV en serio", es un proyecto aparte, no un incremento.
- **#11 Social (perfiles públicos, follow, feed de actividad, likes sociales, comentarios).** Dharma lo desarrolla **una sola persona**. Todas estas features necesitan masa crítica para tener sentido; el bloque "Reseñas de la Comunidad" en la ficha ya existe como stub y alcanza. Construir follow/feed ahora es tiempo invertido en algo que se va a ver vacío. Las "4 favoritas" (#9) tienen algo de valor personal pero dependen de una página de perfil que hoy no existe.
- **Letterboxd "Video Store" / alquiler digital.** Irrelevante para el proyecto.

---

## 5. Recomendación de arranque

Empezar por **#2 (títulos relacionados)** para un golpe de valor rápido y sin riesgo
(no toca esquema ni API), y en paralelo o inmediatamente después **#1 (diario real)**,
que es el arreglo de fondo más importante. **#3 (estadísticas)** después, porque su
sub-tarea de backfill de géneros además destraba el #5.
