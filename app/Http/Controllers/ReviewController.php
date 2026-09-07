<?php

namespace App\Http\Controllers;

use App\Models\MediaItem;
use App\Models\Review;
use App\Models\Watchlist;
use App\Services\TmdbService;
use App\Traits\HasLocalBackdrop;
use App\Traits\HasUserStats;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
    use HasLocalBackdrop;
    use HasUserStats;

    protected TmdbService $tmdb;

    public function __construct(TmdbService $tmdb)
    {
        $this->tmdb = $tmdb;
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

        $query = Review::with('mediaItem')
            ->where('user_id', $userId);

        // Filter by rating
        if ($request->filled('rating')) {
            $query->where('rating', '>=', (float) $request->rating);
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by year
        if ($request->filled('year')) {
            $query->whereYear('watched_date', $request->year);
        }

        // Las entradas sin fecha (por ver / viéndola) van al final: si no,
        // SQLite manda los NULL arriba de todo y tapan el diario real.
        $reviews = $query
            ->orderByRaw('watched_date is null')
            ->orderByDesc('watched_date')
            ->orderByDesc('id')
            ->paginate(12);

        // Statistics
        $totalHours = Review::where('user_id', $userId)
            ->where('status', 'watched')
            ->join('media_items', 'reviews.media_item_id', '=', 'media_items.id')
            ->sum('media_items.runtime');

        $stats = [
            'total_logged' => Review::where('user_id', $userId)->count(),
            'total_hours' => round($totalHours / 60, 1),
            'avg_rating' => round(Review::where('user_id', $userId)->whereNotNull('rating')->avg('rating') ?? 0, 1),
            'total_rewatches' => Review::where('user_id', $userId)->where('is_rewatch', true)->count(),
        ];

        return view('reviews.index', [
            'reviews' => $reviews,
            'stats' => $stats,
            'headerBackdrop' => $this->backdropFrom(
                MediaItem::whereHas('reviews', fn ($q) => $q->where('user_id', $userId))
            ),
            'filterRating' => $request->rating,
            'filterStatus' => $request->status,
            'filterYear' => $request->year,
            'years' => $this->loggedYears($userId),
        ]);
    }

    /**
     * Modal de registro nuevo (o de re-visionado, si ya hay entradas previas).
     */
    public function createModal(string $type, int $tmdbId)
    {
        if ($response = $this->guestModalResponse()) {
            return $response;
        }

        $details = $type === 'tv'
            ? $this->tmdb->getTvDetails($tmdbId)
            : $this->tmdb->getMovieDetails($tmdbId);

        $mediaItem = MediaItem::where('tmdb_id', $tmdbId)
            ->where('media_type', $type)
            ->first();

        // Con entradas previas el modal arranca en modo "re-visionado": es lo
        // que el usuario está haciendo el 100% de las veces que vuelve acá.
        $entries = $mediaItem
            ? Review::entriesFor(Auth::id(), $mediaItem->id)->get()
            : collect();

        return view('reviews.partials.log-modal', [
            'media' => $details,
            'type' => $type,
            'mode' => 'create',
            'review' => null,
            'existingCount' => $entries->count(),
        ]);
    }

    /**
     * Modal de edición de UNA entrada concreta del diario.
     *
     * Va fuera del middleware `auth` como el resto de las acciones HTMX: así
     * una sesión vencida devuelve el modal de "iniciá sesión" en vez de un
     * redirect al login que htmx metería dentro del modal.
     */
    public function editModal(Review $review)
    {
        if ($response = $this->guestModalResponse()) {
            return $response;
        }

        if ($review->user_id !== Auth::id()) {
            abort(403);
        }

        $mediaItem = $review->mediaItem;

        return view('reviews.partials.log-modal', [
            // Con el MediaItem local alcanza: no hace falta ir a TMDB para
            // editar algo que ya está guardado.
            'media' => [
                'id' => $mediaItem->tmdb_id,
                'title' => $mediaItem->title,
                'original_title' => $mediaItem->original_title,
                'release_date' => $mediaItem->release_date?->format('Y-m-d'),
                'poster_path' => $mediaItem->poster_path,
                'backdrop_path' => $mediaItem->backdrop_path,
                'overview' => $mediaItem->overview,
                'runtime' => $mediaItem->runtime,
                'vote_average' => $mediaItem->vote_average,
            ],
            'type' => $mediaItem->media_type,
            'mode' => 'edit',
            'review' => $review,
            'existingCount' => 0,
        ]);
    }

    /**
     * Crea una entrada nueva del diario (visionado o re-visionado).
     *
     * Antes era un `updateOrCreate` sobre (user_id, media_item_id): volver a
     * registrar un título pisaba la entrada anterior y el diario nunca podía
     * tener más de una fila por película.
     */
    public function store(Request $request)
    {
        if (! Auth::check()) {
            if ($request->header('HX-Request')) {
                return response('')
                    ->withHeaders([
                        'HX-Trigger' => json_encode([
                            'authRequired' => [
                                'message' => 'Inicia sesión para calificar y guardar notas.',
                            ],
                        ]),
                    ]);
            }

            return redirect()->route('login')->with('info', 'Inicia sesión para calificar y guardar notas.');
        }

        $validated = $request->validate(array_merge([
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
        ], $this->entryRules()));

        $userId = Auth::id();

        // Find or create local MediaItem record
        $mediaItem = MediaItem::firstOrCreate(
            [
                'tmdb_id' => $validated['tmdb_id'],
                'media_type' => $validated['media_type'],
            ],
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

        $review = Review::create(array_merge(
            [
                'user_id' => $userId,
                'media_item_id' => $mediaItem->id,
            ],
            $this->entryAttributes($request, $validated)
        ));

        $this->clearFromWatchlist($review);

        return $this->savedResponse($request, $review, 'Registro añadido a tu diario.', true);
    }

    /**
     * Edita una entrada existente, sin crear otra.
     */
    public function update(Request $request, Review $review)
    {
        if (! Auth::check()) {
            if ($request->header('HX-Request')) {
                return response('')
                    ->withHeaders([
                        'HX-Trigger' => json_encode([
                            'authRequired' => [
                                'message' => 'Inicia sesión para editar tus registros.',
                            ],
                        ]),
                    ]);
            }

            return redirect()->route('login')->with('info', 'Inicia sesión para editar tus registros.');
        }

        if ($review->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate($this->entryRules());

        $review->update($this->entryAttributes($request, $validated));

        $this->clearFromWatchlist($review);

        return $this->savedResponse($request, $review, 'Registro actualizado.', false);
    }

    /**
     * Delete review
     */
    public function destroy(Review $review)
    {
        $userId = Auth::id();

        if ($review->user_id !== $userId) {
            abort(403);
        }

        $title = $review->mediaItem?->title;
        $review->delete();

        $message = $title
            ? "Se eliminó tu registro de «{$title}»."
            : 'Se eliminó el registro de tu diario.';

        if (request()->header('HX-Request')) {
            // Refresco completo en vez de fragmentos: al borrar cambian a la vez
            // el boton de la ficha, la nota del hero, los contadores del home y,
            // en el diario, la fila entera. Reconstruir cada pieza por separado
            // seria mucho mas fragil que recargar, y esto es una accion puntual.
            session()->flash('info', $message);

            return response('', 200, ['HX-Refresh' => 'true']);
        }

        return back()->with('success', $message);
    }

    // =====================================================================
    // Internos
    // =====================================================================

    /**
     * Reglas comunes a crear y editar una entrada.
     */
    protected function entryRules(): array
    {
        return [
            'rating' => 'nullable|numeric|min:0.5|max:10',
            'review_text' => 'nullable|string|max:5000',
            'private_notes' => 'nullable|string|max:5000',
            'watched_date' => 'nullable|date|before_or_equal:today',
            'is_rewatch' => 'nullable|boolean',
            'contains_spoilers' => 'nullable|boolean',
            'status' => 'required|in:watched,watching,plan_to_watch,dropped',
        ];
    }

    /**
     * Campos de la entrada ya normalizados.
     *
     * Regla de negocio: "quiero verla" no es un visionado, así que no lleva
     * fecha, ni nota, ni flag de re-visionado por más que el form los mande.
     * "Viéndola" y "abandonada" sí los conservan: viste una parte, y tanto la
     * fecha como la calificación siguen significando algo.
     */
    protected function entryAttributes(Request $request, array $validated): array
    {
        $planned = $validated['status'] === 'plan_to_watch';

        return [
            'rating' => $planned ? null : ($validated['rating'] ?? null),
            'review_text' => $validated['review_text'] ?? null,
            'private_notes' => $validated['private_notes'] ?? null,
            // Toda entrada de algo que se vio queda fechada: sin fecha se cae
            // del orden del diario y de los filtros por año.
            'watched_date' => $planned
                ? null
                : ($validated['watched_date'] ?? now()->toDateString()),
            'is_rewatch' => ! $planned && $request->boolean('is_rewatch'),
            'contains_spoilers' => $request->boolean('contains_spoilers'),
            'status' => $validated['status'],
        ];
    }

    /**
     * Si se marcó como vista, ya no tiene sentido tenerla en "pendientes".
     */
    protected function clearFromWatchlist(Review $review): void
    {
        if ($review->status !== 'watched') {
            return;
        }

        Watchlist::where('user_id', $review->user_id)
            ->where('media_item_id', $review->media_item_id)
            ->delete();
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
                    'authRequired' => [
                        'message' => 'Inicia sesión para calificar y escribir reseñas.',
                    ],
                ]),
            ]);
    }

    /**
     * Respuesta compartida por crear y editar.
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

        $entries = Review::entriesFor($userId, $mediaItem->id)->get();

        $html = view('reviews.partials.log-button-state', [
            'entries' => $entries,
            'type' => $mediaItem->media_type,
            'tmdbId' => $mediaItem->tmdb_id,
            'oob' => true,
        ])->render();

        // Bloque "Tu nota" del hero de la ficha: muestra la entrada más
        // reciente, que es la que vale como "tu nota actual".
        $html .= view('media.partials.hero-score-mine', [
            'review' => $entries->first(),
            'type' => $mediaItem->media_type,
            'tmdbId' => $mediaItem->tmdb_id,
            'oob' => true,
        ])->render();

        // El promedio de la comunidad cambia con cada nota nueva. Una fila por
        // usuario: si no, quien vio algo 5 veces pesaría 5 veces en el promedio.
        $ratings = Review::latestPerUser($mediaItem->id, fn ($q) => $q->whereNotNull('rating'));

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

        // Contadores de Notas/Reseñas/Watchlist del hero del home.
        $html .= view('partials.hero-stats-oob', [
            'stats' => $this->getHeroStats($userId),
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
