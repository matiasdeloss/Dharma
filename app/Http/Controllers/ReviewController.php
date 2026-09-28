<?php

namespace App\Http\Controllers;

use App\Models\MediaItem;
use App\Models\Review;
use App\Models\Watchlist;
use App\Services\MediaCatalog;
use App\Services\TmdbService;
use App\Traits\HasLocalBackdrop;
use App\Traits\HasUserStats;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * El diario: una entrada por usuario y título.
 *
 * Dos modales alimentan la misma fila:
 *   - calificar  → nota (estrellas) + fecha en que la viste
 *   - reseñar    → reseña pública, spoilers y nota privada
 *
 * Los dos hacen POST a `store`, que crea la entrada si no existe y si existe
 * actualiza SOLO los campos del modal que la mandó: calificar no borra la
 * reseña y reseñar no toca la nota.
 */
class ReviewController extends Controller
{
    use HasLocalBackdrop;
    use HasUserStats;

    protected TmdbService $tmdb;

    protected MediaCatalog $catalog;

    public function __construct(TmdbService $tmdb, MediaCatalog $catalog)
    {
        $this->tmdb = $tmdb;
        $this->catalog = $catalog;
    }

    /**
     * User's reviews and viewing diary (Logbook)
     */
    public function index(Request $request)
    {
        $userId = Auth::id();

        if (! $userId) {
            return redirect()->route('home')->with('info', 'Inicia sesión o crea una cuenta para ver tu diario.');
        }

        $query = Review::with('mediaItem')->where('user_id', $userId);

        if ($request->filled('rating')) {
            $query->where('rating', '>=', (float) $request->rating);
        }

        if ($request->filled('year')) {
            $query->whereYear('watched_date', $request->year);
        }

        $query->orderByDesc('watched_date')->orderByDesc('id');
        // `buscar` y no `q`: `q` es el del buscador del navbar, que muestra
        // `request('q')` y se llenaba con lo que se buscaba en el diario.
        $search = trim((string) $request->input('buscar', ''));

        // Con búsqueda se filtra en PHP y se pagina a mano: el diario de una
        // persona son decenas o cientos de filas, y así "parasitos" encuentra
        // "Parásitos" (el LIKE de SQLite no ignora tildes).
        $reviews = $search === ''
            ? $query->paginate(12)
            : $this->paginate(
                $query->get()->filter(fn (Review $entry) => $this->matchesSearch($entry, $search))->values(),
                12,
                $request
            );

        return view('reviews.index', [
            'reviews' => $reviews,
            'stats' => $this->getDiaryStats($userId),
            'headerBackdrop' => $this->backdropFrom(
                MediaItem::whereHas('reviews', fn ($q) => $q->where('user_id', $userId))
            ),
            'filterRating' => $request->rating,
            'filterYear' => $request->year,
            'filterSearch' => $search,
            'years' => $this->loggedYears($userId),
        ]);
    }

    /**
     * Modal para calificar: la nota en estrellas y la fecha.
     */
    public function rateModal(string $type, int $tmdbId)
    {
        if ($response = $this->guestModalResponse()) {
            return $response;
        }

        return view('reviews.partials.rate-modal', $this->modalContext($type, $tmdbId));
    }

    /**
     * Modal para reseñar: texto público, spoilers y nota privada.
     */
    public function writeModal(string $type, int $tmdbId)
    {
        if ($response = $this->guestModalResponse()) {
            return $response;
        }

        return view('reviews.partials.review-modal', $this->modalContext($type, $tmdbId));
    }

    /**
     * Crea o actualiza la entrada del usuario para un título.
     *
     * El campo `form` dice qué modal está guardando, y solo se tocan los campos
     * de ese modal. Así calificar y reseñar son independientes aunque vivan en
     * la misma fila.
     */
    public function store(Request $request)
    {
        if (! Auth::check()) {
            if ($request->header('HX-Request')) {
                return response('')->withHeaders([
                    'HX-Trigger' => json_encode([
                        'authRequired' => ['message' => 'Inicia sesión para calificar y guardar notas.'],
                    ]),
                ]);
            }

            return redirect()->route('login')->with('info', 'Inicia sesión para calificar y guardar notas.');
        }

        $form = $request->input('form');

        $validated = $request->validate(array_merge([
            'form' => 'required|in:rating,review',
            'tmdb_id' => 'required|integer',
            'media_type' => 'required|in:movie,tv',
            'title' => 'required|string|max:255',
            'original_title' => 'nullable|string|max:255',
            'release_date' => 'nullable|date',
            'poster_path' => 'nullable|string',
            'backdrop_path' => 'nullable|string',
            'overview' => 'nullable|string',
            'runtime' => 'nullable|integer',
            'vote_average' => 'nullable|numeric',
        ], $form === 'review' ? $this->reviewRules() : $this->ratingRules()), $this->messages());

        $userId = Auth::id();

        // El form manda lo que tenía a mano; géneros, runtime y demás los
        // completa MediaCatalog desde TMDB.
        $mediaItem = $this->catalog->firstOrCreate(
            $validated['media_type'],
            (int) $validated['tmdb_id'],
            [
                'title' => $validated['title'],
                'original_title' => $validated['original_title'] ?? null,
                'release_date' => $validated['release_date'] ?? null,
                'poster_path' => $validated['poster_path'] ?? null,
                'backdrop_path' => $validated['backdrop_path'] ?? null,
                'overview' => $validated['overview'] ?? null,
                'runtime' => $validated['runtime'] ?? null,
                'vote_average' => $validated['vote_average'] ?? null,
            ]
        );

        $review = Review::firstOrNew([
            'user_id' => $userId,
            'media_item_id' => $mediaItem->id,
        ]);

        $isNew = ! $review->exists;

        if ($form === 'review') {
            $review->review_text = $validated['review_text'] ?? null;
            $review->private_notes = $validated['private_notes'] ?? null;
            $review->contains_spoilers = $request->boolean('contains_spoilers');
            $message = $isNew ? 'Reseña guardada en tu diario.' : 'Reseña actualizada.';
        } else {
            $review->rating = $validated['rating'];
            $review->watched_date = $validated['watched_date'] ?? $review->watched_date ?? now()->toDateString();
            $message = $isNew ? 'Calificación guardada en tu diario.' : 'Calificación actualizada.';
        }

        // Toda entrada queda fechada: sin fecha se cae del orden del diario y
        // de los filtros por año.
        $review->watched_date ??= now()->toDateString();
        $review->save();

        // Calificar es dar por vista: ya no tiene sentido tenerla en pendientes.
        Watchlist::where('user_id', $userId)
            ->where('media_item_id', $mediaItem->id)
            ->delete();

        return $this->savedResponse($request, $review, $message, $isNew);
    }

    /**
     * Delete review
     */
    public function destroy(Review $review)
    {
        if ($review->user_id !== Auth::id()) {
            abort(403);
        }

        $title = $review->mediaItem?->title;
        $review->delete();

        $message = $title
            ? "Se eliminó tu registro de «{$title}»."
            : 'Se eliminó el registro de tu diario.';

        if (request()->header('HX-Request')) {
            // Refresco completo en vez de fragmentos: al borrar cambian a la vez
            // el botón de la ficha, la nota del hero, los contadores del home y,
            // en el diario, la fila entera. Reconstruir cada pieza por separado
            // sería mucho más frágil que recargar, y esto es una acción puntual.
            session()->flash('info', $message);

            return response('', 200, ['HX-Refresh' => 'true']);
        }

        return back()->with('success', $message);
    }

    // =====================================================================
    // Internos
    // =====================================================================

    protected function ratingRules(): array
    {
        return [
            // Cinco estrellas con medias: enteros del 1 al 10.
            'rating' => 'required|integer|min:1|max:10',
            'watched_date' => 'nullable|date|before_or_equal:today',
        ];
    }

    protected function reviewRules(): array
    {
        return [
            'review_text' => 'nullable|string|max:5000',
            'private_notes' => 'nullable|string|max:5000',
            'contains_spoilers' => 'nullable|boolean',
        ];
    }

    /**
     * Mensajes de los campos que el usuario puede llenar mal (los ocultos solo
     * fallan si alguien arma el pedido a mano). Desde los modales se ven como
     * toast: ver el render de ValidationException en bootstrap/app.php.
     */
    protected function messages(): array
    {
        return [
            'rating.required' => 'Elegí una nota con las estrellas.',
            'rating.integer' => 'La nota tiene que ser un número entero del 1 al 10.',
            'rating.min' => 'La nota tiene que ser un número entero del 1 al 10.',
            'rating.max' => 'La nota tiene que ser un número entero del 1 al 10.',
            'watched_date.date' => 'La fecha no es válida.',
            'watched_date.before_or_equal' => 'La fecha no puede ser posterior a hoy.',
            'review_text.max' => 'La reseña puede tener hasta 5000 caracteres.',
            'private_notes.max' => 'La nota privada puede tener hasta 5000 caracteres.',
        ];
    }

    /**
     * Lo que necesitan los dos modales: la ficha de TMDB y la entrada del
     * usuario, si ya la tiene.
     */
    protected function modalContext(string $type, int $tmdbId): array
    {
        $details = $type === 'tv'
            ? $this->tmdb->getTvDetails($tmdbId)
            : $this->tmdb->getMovieDetails($tmdbId);

        $mediaItem = MediaItem::where('tmdb_id', $tmdbId)
            ->where('media_type', $type)
            ->first();

        $review = $mediaItem
            ? Review::of(Auth::id(), $mediaItem->id)->first()
            : null;

        return [
            'media' => $details,
            'type' => $type,
            'review' => $review,
        ];
    }

    /**
     * Modal de "iniciá sesión" para las rutas de modal fuera del grupo `auth`.
     */
    protected function guestModalResponse()
    {
        if (Auth::check()) {
            return null;
        }

        return response(view('reviews.partials.auth-required-modal'))
            ->withHeaders([
                'HX-Trigger' => json_encode([
                    'authRequired' => ['message' => 'Inicia sesión para calificar y escribir reseñas.'],
                ]),
            ]);
    }

    /**
     * Respuesta compartida por calificar y reseñar.
     *
     * Son SOLO fragmentos out-of-band (el form del modal va con
     * `hx-swap="none"`): cada uno se aplica si su destino está en la página y
     * htmx descarta los que no. Así la misma respuesta sirve para la ficha, el
     * diario y el home sin que ninguno dependa de un target concreto.
     */
    protected function savedResponse(Request $request, Review $review, string $message, bool $isNew)
    {
        if (! $request->header('HX-Request')) {
            return back()->with('success', $message);
        }

        $userId = $review->user_id;
        $mediaItem = $review->mediaItem;

        $html = view('reviews.partials.log-button-state', [
            'review' => $review,
            'type' => $mediaItem->media_type,
            'tmdbId' => $mediaItem->tmdb_id,
            'oob' => true,
        ])->render();

        // Bloque "Tu nota" del hero de la ficha.
        $html .= view('media.partials.hero-score-mine', [
            'review' => $review,
            'type' => $mediaItem->media_type,
            'tmdbId' => $mediaItem->tmdb_id,
            'oob' => true,
        ])->render();

        // El promedio de la comunidad cambia con cada nota.
        $ratings = Review::where('media_item_id', $mediaItem->id)->whereNotNull('rating');

        $html .= view('media.partials.hero-score-dharma', [
            'avg' => (clone $ratings)->avg('rating'),
            'count' => (clone $ratings)->count(),
            'oob' => true,
        ])->render();

        // Fila del diario, por si el modal se abrió estando en /diary. Al
        // crear no hay fila que reemplazar: se antepone a la lista.
        $html .= $isNew
            ? view('reviews.partials.diary-entry-new', ['entry' => $review->load('mediaItem')])->render()
            : view('reviews.partials.diary-entry', ['entry' => $review->load('mediaItem'), 'oob' => true])->render();

        // Contadores de la banda del diario.
        $html .= view('reviews.partials.diary-stats', [
            'stats' => $this->getDiaryStats($userId),
            'oob' => true,
        ])->render();

        return response($html)->withHeaders([
            'HX-Trigger' => json_encode([
                'reviewSaved' => [
                    'title' => 'Mi Diario',
                    'message' => $message,
                    'type' => 'success',
                ],
            ]),
        ]);
    }

    /**
     * ¿La entrada tiene el texto buscado en el título (el de acá o el
     * original) o en lo que escribió el usuario? Sin distinguir mayúsculas
     * ni tildes.
     */
    protected function matchesSearch(Review $entry, string $search): bool
    {
        $needle = $this->normalizeForSearch($search);

        return collect([
            $entry->mediaItem?->title,
            $entry->mediaItem?->original_title,
            $entry->review_text,
            $entry->private_notes,
        ])->contains(fn ($text) => $text && str_contains($this->normalizeForSearch($text), $needle));
    }

    protected function normalizeForSearch(string $text): string
    {
        return Str::lower(Str::ascii($text));
    }

    /**
     * Pagina una colección ya filtrada, con los mismos links que `paginate()`.
     */
    protected function paginate(Collection $items, int $perPage, Request $request): LengthAwarePaginator
    {
        $page = Paginator::resolveCurrentPage();

        return new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'query' => $request->query()],
        );
    }

    /**
     * Años con actividad en el diario, para el filtro de la vista.
     *
     * Se resuelve en PHP y no con `strftime`/`YEAR()` para no atarse al driver.
     *
     * @return Collection<int, string>
     */
    protected function loggedYears(int $userId)
    {
        return Review::where('user_id', $userId)
            ->whereNotNull('watched_date')
            ->orderByDesc('watched_date')
            ->pluck('watched_date')
            ->map(fn ($date) => $date instanceof \DateTimeInterface
                ? $date->format('Y')
                : substr((string) $date, 0, 4))
            ->unique()
            ->values();
    }
}
