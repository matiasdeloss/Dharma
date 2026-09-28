# Brief — Diario real: re-visionados, estados de visionado y fecha editable

**Fecha:** 2026-09-06 · **Autor:** dharma-research · **Estado:** pendiente
**Prioridad:** #1 del roadmap · **Depende de:** nada · **Bloqueante:** migración (quitar índice único)

---

## 1. Qué se quiere lograr

Que el diario sea un **diario** y no "un rating por película":

1. **Re-visionados.** El usuario puede loguear la misma película/serie varias veces, cada una con su **fecha**, su **rating** y su flag de **re-visionado**. El diario lista cada visionado como una entrada propia, ordenada por fecha.
2. **Estados de visionado usables.** El modal deja elegir `watched` / `watching` / `plan_to_watch` / `dropped` en vez del `watched` hardcodeado actual. Así los filtros "En progreso" y "Por ver" del diario (que hoy no matchean nada) funcionan.
3. **Fecha de visionado editable.** Hoy es un `<input type="hidden">`. Pasa a ser un campo de fecha real re-estilizado, para poder registrar algo visto en el pasado.
4. **Editar una entrada concreta.** Con varias entradas por título, "Editar" tiene que apuntar a una entrada específica (por id), no al par tipo+tmdb_id.
5. **Arreglar `contains_spoilers` al mostrar.** Hoy se guarda pero el texto con spoiler se renderiza igual en el feed del home y en "Reseñas de la Comunidad". Debe quedar oculto tras un "Mostrar spoiler".

### Fuera de alcance
- Likes/favoritas, listas, tracking de episodios. Otro brief.
- Cambiar el bloque "Reseñas de la Comunidad" más allá del fix de spoilers.

---

## 2. Qué ya existe en Dharma

| Pieza | Ruta | Estado actual relevante |
|---|---|---|
| Modal de log | `resources/views/reviews/partials/log-modal.blade.php` | Form `hx-post` a `reviews.store`, `hx-target="#log-action-container"`, `hx-swap="outerHTML"`. Hidden `status=watched` (`:46`), hidden `is_rewatch=0` (`:47`), hidden `watched_date` (`:48`). Slider `name="rating"` 1–10 step 0.5. Switch `contains_spoilers`. Textareas `review_text`, `private_notes`. |
| Guardado | `app/Http/Controllers/ReviewController.php:119` `store()` | `Review::updateOrCreate([user_id, media_item_id], [...])`. Valida todo. Usa `$request->boolean('is_rewatch'|'contains_spoilers')`. Si `status===watched` borra de watchlist. Rama HTMX: devuelve `log-button-state` + `hero-stats-oob` + `HX-Trigger: reviewSaved`. |
| Modal loader | `ReviewController.php:79` `createModal($type, $tmdbId)` | Chequea `Auth::check()` a mano → si no, devuelve `auth-required-modal` + `HX-Trigger: authRequired`. Busca `Review` existente por `(user_id, media_item_id)` y lo pasa al modal. |
| Borrado | `ReviewController.php:227` `destroy(Review $review)` | Tras `auth`. `if ($review->user_id !== $userId) abort(403)`. Rama HTMX devuelve `''`. |
| Listado diario | `ReviewController.php:28` `index()` + `resources/views/reviews/index.blade.php` | Filtros `rating` (>=), `status` (=), `year` (`whereYear('watched_date')` — **sin control en la vista**). `paginate(12)` ordenado por `watched_date desc`. Stats: `total_logged`, `total_hours` (suma `media_items.runtime`/60), `avg_rating`, `total_rewatches`. |
| Botón de estado en la ficha | `resources/views/reviews/partials/log-button-state.blade.php` | `#log-action-container`. Si hay `$review`: botón "Tu nota: X/10 · Editar". Si no: "Registrar / Calificar". Ambos abren el modal vía `hx-get` a `reviews.modal`. |
| Ruta modelo→binding | `routes/web.php:33-37,45` | `reviews.modal` (`GET /reviews/modal/{type}/{id}`, fuera de `auth`), `reviews.store` (`POST /reviews`, fuera de `auth`), `reviews.destroy` (`DELETE /reviews/{review}`, dentro de `auth`). |
| Migración | `database/migrations/2026_08_25_015457_create_reviews_table.php` | `$table->unique(['user_id', 'media_item_id'])` en `:27` (en realidad línea del `unique`, ver archivo). Índice `['user_id', 'watched_date']`. |
| Modelo | `app/Models/Review.php` | `$fillable` ya incluye todo. Casts: `watched_date=>date`, `rating=>float`, `is_rewatch/contains_spoilers=>boolean`. Accessors `star_rating`, `rating_label`. |
| JS del modal | `resources/js/modules/htmx-config.js:32` `handleReviewSaved` | Cierra `#logModal` y muestra toast al recibir `reviewSaved`. `syncRateSlider` (`:106`) maneja el slider. |
| Feed home con spoiler sin ocultar | `resources/views/home.blade.php:228-236` | Renderiza `review_text` y `private_notes` directo. **`private_notes` NO debe mostrarse acá — es privado** (ver bug 1.4.1 del roadmap). |
| Comunidad con spoiler sin ocultar | `resources/views/media/show.blade.php:260-280` | `{{ $comReview->review_text }}` directo. |
| Tests | `tests/Feature/MediaAppTest.php:57,117` | `test_storing_a_review_and_personal_notes`, `test_diary_index_page_displays_entries`. Van a necesitar ajuste (ya no hay `updateOrCreate`). |

---

## 3. Datos externos

**Ninguno.** Esta feature no toca TMDB ni OMDb. Todo es esquema local + UI.

(Nota para el backfill de `runtime`: hoy el modal manda `runtime` en un hidden desde la ficha. Se mantiene igual. No hace falta llamar a TMDB en `store`.)

---

## 4. Plan para el agente de backend

### 4.1 Migración nueva — quitar el candado de "1 por título"

`database/migrations/2026_09_06_000001_reviews_allow_rewatches.php`:

```php
public function up(): void {
    Schema::table('reviews', function (Blueprint $table) {
        $table->dropUnique('reviews_user_id_media_item_id_unique');
        // índice no-único para seguir resolviendo "mis entradas de este título" rápido
        $table->index(['user_id', 'media_item_id']);
    });
}
public function down(): void {
    Schema::table('reviews', function (Blueprint $table) {
        $table->dropIndex(['user_id', 'media_item_id']);
        $table->unique(['user_id', 'media_item_id']);
    });
}
```

> SQLite: `dropUnique` recrea la tabla; con Laravel 13 + doctrine/dbal ausente puede fallar. Si falla, hacer la migración recreando el índice a mano vía `DB::statement` o, más simple, `Schema::table` dentro de `Schema::disableForeignKeyConstraints`. Probar `php artisan migrate:fresh` en dev.

### 4.2 Semántica de "entrada canónica" vs "visionado"

Se mantiene **un solo modelo `Review`**, pero ahora hay **N filas por (user, media)**. Reglas:

- **Entrada más reciente = la "vigente"** para la ficha ("Tu Nota") y para el botón de estado.
- El **rating de comunidad** (`MediaController::show` `$dharmaAvg`/`$dharmaCount`, `:168`) hoy hace `->reviews()->whereNotNull('rating')->avg('rating')`. Con re-visionados eso promedia varias filas del mismo user. **Cambiar** a promediar **una por usuario** (la última con rating):
  ```php
  $dharmaAvg = $mediaItem
      ? Review::query()
          ->where('media_item_id', $mediaItem->id)
          ->whereNotNull('rating')
          ->whereIn('id', function ($q) use ($mediaItem) {
              $q->selectRaw('MAX(id)')->from('reviews')
                ->where('media_item_id', $mediaItem->id)
                ->whereNotNull('rating')
                ->groupBy('user_id');
          })
          ->avg('rating')
      : null;
  ```
  (idem `$dharmaCount` con `count()`).
- **`getHeroStats`** (`app/Traits/HasUserStats.php`): `total_notes` y `total_reviews` hoy cuentan filas. Con re-visionados van a inflarse. Decisión: dejar que cuenten **entradas** (un re-visionado con nota nueva es una nota nueva, es razonable) pero **quitar el fallback raro** de `total_reviews` (`?: Review::where(...)->count()` en `:22` y `:31`) que cae a "contar todo" cuando no hay texto. Que sea simplemente el count de reseñas con `review_text` no vacío.
- **"Reseñas de la Comunidad"** en la ficha (`MediaController::show:156`): hoy `->reviews()->with('user')->latest()->take(10)`. Dejar igual pero deduplicar a **la última entrada con `review_text` por usuario** (mismo patrón `MAX(id) group by user_id`), para no repetir al mismo user 3 veces.

### 4.3 `ReviewController::store` — crear vs actualizar explícito

Partir en dos caminos:

- **`store(Request $request)`** (`POST /reviews`): **siempre crea** una entrada nueva (`Review::create`). Sigue creando el `MediaItem` con `firstOrCreate`. Validación: agregar `watched_date` como `required|date|before_or_equal:today` (ya no nullable), y sacar el default `now()`.
- **`update(Request $request, Review $review)`** (`PATCH /reviews/{review}`, **dentro del grupo `auth`**): valida pertenencia (`if ($review->user_id !== Auth::id()) abort(403)`), hace `$review->update([...])` con los mismos campos (sin tocar `media_item_id`).

Ambos, en rama HTMX, devuelven lo mismo que hoy: `log-button-state` (recalculado, ver 4.5) + `hero-stats-oob` + `HX-Trigger: reviewSaved`. Mantener el mensaje en español.

Validación compartida (extraer a un `array` o `FormRequest` si el agente prefiere):

```php
'rating'            => 'nullable|numeric|min:0.5|max:10',
'review_text'       => 'nullable|string|max:5000',
'private_notes'     => 'nullable|string|max:5000',
'watched_date'      => 'required|date|before_or_equal:today',
'is_rewatch'        => 'nullable|boolean',   // leer con $request->boolean()
'contains_spoilers' => 'nullable|boolean',   // idem
'status'            => 'required|in:watched,watching,plan_to_watch,dropped',
```

Regla de negocio: si `status !== 'watched'`, **ignorar `watched_date` del form y guardar `null`** (una peli "por ver" no tiene fecha de visto), y **forzar `rating=null`, `is_rewatch=false`**. Si `status === 'watched'` y no vino fecha, error de validación (ya cubierto por `required`).

Mantener: si `status === 'watched'`, borrar de watchlist ese `media_item_id`.

### 4.4 `ReviewController::createModal` → dos entradas

- **`createModal($type, $tmdbId)`** (`GET /reviews/modal/{type}/{id}`): sigue igual pero **ya no busca `$review`** para prellenar como "edición". Ahora es **siempre "nueva entrada"**. Si el user ya tiene entradas de ese título, pasar `$existingCount` y `$lastEntry` al modal para el copy ("Ya registraste esto N veces — esto crea un re-visionado") y para prellenar `is_rewatch=true` por defecto cuando `$existingCount > 0`.
- **`editModal(Review $review)`** (`GET /reviews/{review}/edit`, **dentro de `auth`**): valida pertenencia, carga `$review->mediaItem`, arma `$media` desde el `MediaItem` local (no hace falta TMDB) o desde TMDB si se quiere el still — con el `MediaItem` alcanza (`poster_path`, `title`, etc.). Devuelve `log-modal` en modo edición (`$mode = 'edit'`, `$review` cargado).

El modal (`log-modal.blade.php`) recibe una nueva var `$mode` (`'create'|'edit'`) y `$review` (nullable en create). El `<form>` cambia:
- `create`: `hx-post="{{ route('reviews.store') }}"`.
- `edit`: `hx-post="{{ route('reviews.update', $review) }}"` + `<input type="hidden" name="_method" value="PATCH">`.

### 4.5 `log-button-state` — reflejar N entradas

`resources/views/reviews/partials/log-button-state.blade.php` pasa a recibir `$entriesCount` y `$lastEntry` (en vez de `$review`). Lógica:
- `$entriesCount === 0` → botón "Registrar / Calificar" (igual que hoy) → `hx-get` a `reviews.modal`.
- `$entriesCount >= 1` → botón principal "Tu nota: {{ última con rating }} · {{ $entriesCount }} registro(s)" que abre un pequeño panel/dropdown con: **"Registrar de nuevo"** (`hx-get` a `reviews.modal`, crea re-visionado) + lista de entradas con **"Editar"** (`hx-get` a `reviews.{review}.edit`) y **"Eliminar"** (`DELETE /reviews/{review}`, ya existe).

Los controllers que renderizan este partial (`MediaController::show`, `ReviewController::store/update`) tienen que pasar `entriesCount`/`lastEntry`. En `show`:
```php
$userEntries = ($userId && $mediaItem)
    ? Review::where('user_id',$userId)->where('media_item_id',$mediaItem->id)->orderByDesc('watched_date')->orderByDesc('id')->get()
    : collect();
```

### 4.6 Vista del diario — filtro por año + estado "dropped"

`ReviewController::index`: pasar a la vista `$years = Review::where('user_id',$userId)->whereNotNull('watched_date')->selectRaw('DISTINCT strftime("%Y", watched_date) as y')->orderByDesc('y')->pluck('y')` (SQLite; para portabilidad usar `->get()->map(fn($r)=>$r->watched_date?->format('Y'))` sobre las reviews o `DB::raw` según el driver). Y `filterYear => $request->year`.

El filtro `status` ya soporta `dropped` en el `where`; solo falta la opción en el `<select>` (frontend).

### 4.7 Tests (obligatorio)

Actualizar `tests/Feature/MediaAppTest.php`:
- `test_storing_a_review_and_personal_notes`: sigue válido, pero agregar `watched_date` requerido.
- **Nuevo** `test_logging_same_movie_twice_creates_two_entries`: `POST /reviews` dos veces mismo `tmdb_id` → `assertDatabaseCount('reviews', 2)`.
- **Nuevo** `test_updating_an_entry_edits_in_place`: `PATCH /reviews/{id}` cambia rating → `assertDatabaseCount('reviews',1)` y `assertDatabaseHas` con el nuevo rating.
- **Nuevo** `test_cannot_edit_another_users_entry`: `PATCH` sobre entrada ajena → 403.
- **Nuevo** `test_plan_to_watch_status_saves_without_date`: `status=plan_to_watch` sin `watched_date` → OK, `watched_date` null en DB.
- `test_diary_index_page_displays_entries`: sin cambios de fondo.

Verificación: `php artisan test`, `php -l` sobre los `.php` tocados, `php vendor/bin/pint --dirty`, `php artisan migrate:fresh --seed`.

### 4.8 Bug de privacidad a arreglar de paso (está en el alcance de este brief)

En `resources/views/home.blade.php` **quitar el render de `$review->private_notes`** del feed "Últimas Reseñas y Notas" (`:232-236`). El feed es público; las notas privadas no van ahí. Dejar solo `review_text` (y respetando spoilers, ver frontend).

---

## 5. Plan para el agente de frontend

> Paso 0: invocar `Skill(dharma-frontend)`. Formularios nativos siempre re-estilizados con las clases del proyecto (`.modal-form-control`, `.rate-slider`), nunca `form-control` pelado.

### 5.1 `resources/views/reviews/partials/log-modal.blade.php`

- Recibe `$mode` (`'create'|'edit'`), `$review` (nullable), `$existingCount` (int, solo create).
- **Form action dinámico:**
  - create → `hx-post="{{ route('reviews.store') }}"`
  - edit → `hx-post="{{ route('reviews.update', $review) }}"` + `<input type="hidden" name="_method" value="PATCH">`
  - `hx-target="#log-action-container"`, `hx-swap="outerHTML"` (igual que hoy).
- **Reemplazar los 3 hidden** (`status`, `is_rewatch`, `watched_date`) por controles reales:
  - **Selector de estado** (`<select name="status">` re-estilizado): Vista / Viéndola / Quiero verla / Abandonada. Default `watched`. En `edit`, `{{ $review->status }}` seleccionado.
  - **Fecha de visionado** (`<input type="date" name="watched_date">` re-estilizado, `max="{{ date('Y-m-d') }}"`). Default hoy (o `$review->watched_date` en edit). **Se oculta con JS cuando `status` ∈ {plan_to_watch}** (opcional; el backend igual lo ignora).
  - **Switch "Es un re-visionado"** (`name="is_rewatch" value="1"`, mismo estilo que el switch de spoilers). En create con `$existingCount > 0`, `checked` por defecto y un texto "Ya lo registraste {{ $existingCount }} vez/veces".
  - El slider de rating y los textareas quedan igual. En `plan_to_watch`/`dropped` conviene deshabilitar el slider vía JS (el backend fuerza `rating=null`).
- Header del modal: en `edit` que diga "Editar registro" y muestre la fecha de esa entrada; en `create` con count>0, "Nuevo re-visionado".

### 5.2 `resources/views/reviews/partials/log-button-state.blade.php`

Reescribir según 4.5: recibe `$entriesCount`, `$lastEntry`, `$type`, `$tmdbId` (+ para el listado, `$userEntries` o se arma un segundo partial `log-entries-list`). Mantener `id="log-action-container"` en el wrapper (es el `hx-target`). Botones con los mismos patrones `hx-get` + `data-bs-toggle="modal"` + `data-bs-target="#logModal"` + `hx-target="#logModalContent"` que ya se usan.

### 5.3 `resources/views/reviews/index.blade.php`

- Agregar `<option value="dropped">Abandonadas</option>` al `<select name="status">` (`:62`).
- Agregar un tercer `<select name="year">` con `@foreach($years as $y)`, `onchange="this.form.submit()"`, opción vacía "Todos los años".
- El botón "Limpiar filtros" ya existe: extender la condición a `|| $filterYear`.
- Cada entrada del diario: mostrar badge de `status` cuando no sea `watched` ("Viéndola" / "Por ver" / "Abandonada") y el badge de re-visionado ya existe (`:111`).
- **Spoilers:** si `$entry->contains_spoilers`, envolver `review_text` en un `<details>` re-estilizado ("Mostrar reseña con spoilers") en vez de mostrarlo directo.

### 5.4 Spoilers en el resto de superficies

- `resources/views/media/show.blade.php` "Reseñas de la Comunidad" (`:274`): si `$comReview->contains_spoilers`, ocultar el texto tras un `<details><summary>Contiene spoilers — mostrar</summary>…</details>` re-estilizado.
- `resources/views/home.blade.php` feed (`:228-230`): idem para `review_text`; y **eliminar** el bloque de `private_notes` (`:232-236`) — lo hace backend/frontend, pero confirmar que se fue.
- Estilo del `<details>` de spoiler: sumar una clase (`.spoiler-reveal`) en `resources/scss/components/_cards.scss` o `_ratings.scss` usando las variables existentes (superficie oscura, borde sutil, `--dharma-*`). No inventar hex.

### 5.5 JS

- `resources/js/modules/htmx-config.js`: `handleReviewSaved` ya cierra el modal y togglea toast — **sin cambios**. `syncRateSlider` ya funciona; si se deshabilita el slider por estado, agregar un pequeño listener `change` sobre `#status` en el mismo módulo (patrón delegado en `document`, como el resto).
- No hace falta evento `HX-Trigger` nuevo: se reusa `reviewSaved`.

---

## 6. Contrato entre backend y frontend

| Acción | Ruta | Método | Campos del form | `hx-target` / id | Evento `HX-Trigger` |
|---|---|---|---|---|---|
| Abrir modal "nueva entrada / re-visionado" | `route('reviews.modal', ['type'=>$type,'id'=>$tmdbId])` | GET | — | `#logModalContent` (swap innerHTML) | `authRequired` si no hay sesión |
| Abrir modal "editar entrada" | `route('reviews.edit', $review)` → `GET /reviews/{review}/edit` (**auth**) | GET | — | `#logModalContent` | — (403 si no es dueño) |
| Crear entrada (visionado / re-visionado) | `route('reviews.store')` → `POST /reviews` | POST (HTMX) | `tmdb_id, media_type, title, original_title, release_date, poster_path, backdrop_path, overview, runtime, vote_average, rating, review_text, private_notes, watched_date, is_rewatch, contains_spoilers, status` | `#log-action-container` (outerHTML) | `reviewSaved {title,message,type}` |
| Editar entrada | `route('reviews.update', $review)` → `PATCH /reviews/{review}` (**auth**) vía `_method=PATCH` | POST+`_method` (HTMX) | `rating, review_text, private_notes, watched_date, is_rewatch, contains_spoilers, status` | `#log-action-container` (outerHTML) | `reviewSaved` |
| Eliminar entrada | `route('reviews.destroy', $review)` → `DELETE /reviews/{review}` (**auth**) | DELETE | — | fila del diario / `#log-action-container` | — (devuelve `''`) |
| Contadores hero OOB | (piggyback en la respuesta de store/update) | — | — | `#hero-stat-notes`, `#hero-stat-reviews`, `#hero-stat-watchlist` (`hx-swap-oob`) | — |

**Nombres nuevos que ambos agentes deben respetar:**
- Rutas nuevas: `reviews.edit` (`GET /reviews/{review}/edit`), `reviews.update` (`PATCH /reviews/{review}`). Ambas **dentro del grupo `auth`** de `routes/web.php`.
- Var nueva del modal: `$mode` (`'create'|'edit'`), `$existingCount` (int).
- Vars nuevas de `log-button-state`: `$entriesCount` (int), `$lastEntry` (`Review|null`), `$userEntries` (`Collection<Review>`).
- Var nueva del diario: `$years` (`Collection<string>`), `$filterYear`.
- Sin `HX-Trigger` nuevo (se reusa `reviewSaved`).

---

## 7. Riesgos y casos borde

1. **SQLite + `dropUnique`.** Es el riesgo técnico principal. Si `doctrine/dbal` no está y la migración falla, recrear el índice manualmente (`DB::statement('DROP INDEX ...')`) o hacer `migrate:fresh`. Confirmar en dev antes de reportar.
2. **Datos existentes.** El seeder crea 1 review con `is_rewatch=true`. Tras la migración sigue válida. No hay datos de prod (dev con SQLite).
3. **`dharmaAvg` inflado.** Si no se aplica el dedupe por usuario (4.2), un usuario que loguea 5 visionados con rating cuenta 5 veces en el promedio "de la comunidad". Aplicar el `MAX(id) GROUP BY user_id`.
4. **Diario ordenado por `watched_date` con entradas `plan_to_watch` sin fecha.** Esas van con `watched_date = null` → en `orderBy('watched_date','desc')` SQLite las manda al final (o al principio según config). Añadir `->orderByRaw('watched_date IS NULL')` o filtrarlas del diario "visto" y mostrarlas en una sección aparte "Pendientes". Decisión sugerida: el diario muestra todo, pero ordena `NULLs last` y las pinta con el badge de estado.
5. **`total_hours` del diario** suma `runtime` por cada entrada (join reviews→media_items). Con re-visionados eso es **correcto** (si viste Interstellar 3 veces, son 3× 169 min). Confirmar que es el comportamiento deseado (probablemente sí — es lo que hace Letterboxd).
6. **`status` que cambia de `watched` a `plan_to_watch` en un edit.** El backend fuerza `rating=null`, `watched_date=null`. Avisar en el toast o dejarlo silencioso; sugerido: dejarlo, es poco frecuente.
7. **Botón "Registrar de nuevo" sin sesión.** El `hx-get` a `reviews.modal` ya maneja el caso (devuelve `auth-required-modal` + `authRequired`). No hay regresión.
8. **`contains_spoilers` + `<details>`:** que el `<summary>` no filtre las primeras palabras de la reseña. Usar texto genérico fijo ("Esta reseña contiene spoilers").
9. **Paginación del diario** (`paginate(12)`) con `withQueryString()` ya está; asegurarse de que el nuevo filtro `year` se propaga (lo hace `withQueryString`).
10. **Test `test_diary_index_page_displays_entries` asume `Nota secreta.` visible** en el diario propio — eso está bien (el diario es privado del dueño). No confundir con el fix del home.

---

## 8. Fuentes

- [Letterboxd FAQ — logging, diary, rewatches](https://letterboxd.com/about/faq/)
- [Letterboxd Journal — how rewatches count](https://letterboxd.com/journal/2025-letterboxd-year-in-review-faq/)
- [Serializd — currently watching / dropped / diary con timestamps](https://www.tvscholar.com/p/is-serializd-the-tv-fans-letterboxd)
- Código auditado: `app/Http/Controllers/ReviewController.php`, `app/Models/Review.php`, `database/migrations/2026_08_25_015457_create_reviews_table.php`, `resources/views/reviews/**`, `resources/views/media/show.blade.php`, `resources/views/home.blade.php`, `app/Traits/HasUserStats.php`, `resources/js/modules/htmx-config.js`.
