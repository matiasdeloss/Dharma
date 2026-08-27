<?php

namespace App\Http\Controllers;

use App\Models\MediaItem;
use App\Models\Review;
use App\Models\User;
use App\Models\Watchlist;
use App\Services\TmdbService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ReviewController extends Controller
{
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
        $userId = Auth::id() ?? User::first()?->id;

        if (!$userId) {
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

        $reviews = $query->orderBy('watched_date', 'desc')->paginate(12);

        // Statistics
        $totalHours = Review::where('user_id', $userId)
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
            'filterRating' => $request->rating,
            'filterStatus' => $request->status,
        ]);
    }

    /**
     * Return Quick Log Modal view via HTMX
     */
    public function createModal(string $type, int $tmdbId)
    {
        if (!Auth::check()) {
            return response(view('reviews.partials.auth-required-modal'))
                ->withHeaders([
                    'HX-Trigger' => json_encode([
                        'authRequired' => [
                            'message' => 'Inicia sesión para calificar y escribir reseñas.'
                        ]
                    ])
                ]);
        }

        $details = $type === 'tv'
            ? $this->tmdb->getTvDetails($tmdbId)
            : $this->tmdb->getMovieDetails($tmdbId);

        $userId = Auth::id();

        $mediaItem = MediaItem::where('tmdb_id', $tmdbId)
            ->where('media_type', $type)
            ->first();

        $review = null;
        if ($mediaItem) {
            $review = Review::where('user_id', $userId)
                ->where('media_item_id', $mediaItem->id)
                ->first();
        }

        return view('reviews.partials.log-modal', [
            'media' => $details,
            'type' => $type,
            'review' => $review,
        ]);
    }

    /**
     * Store or update a review / log entry
     */
    public function store(Request $request)
    {
        if (!Auth::check()) {
            if ($request->header('HX-Request')) {
                return response('')
                    ->withHeaders([
                        'HX-Trigger' => json_encode([
                            'authRequired' => [
                                'message' => 'Inicia sesión para calificar y guardar notas.'
                            ]
                        ])
                    ]);
            }
            return redirect()->route('login')->with('info', 'Inicia sesión para calificar y guardar notas.');
        }

        $validated = $request->validate([
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
            'rating' => 'nullable|numeric|min:0|max:10',
            'review_text' => 'nullable|string',
            'private_notes' => 'nullable|string',
            'watched_date' => 'nullable|date',
            'is_rewatch' => 'nullable|boolean',
            'contains_spoilers' => 'nullable|boolean',
            'status' => 'required|in:watched,watching,plan_to_watch,dropped',
        ]);

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

        // Update or create review
        $review = Review::updateOrCreate(
            [
                'user_id' => $userId,
                'media_item_id' => $mediaItem->id,
            ],
            [
                'rating' => $validated['rating'] ?? null,
                'review_text' => $validated['review_text'] ?? null,
                'private_notes' => $validated['private_notes'] ?? null,
                'watched_date' => $validated['watched_date'] ?? now()->toDateString(),
                'is_rewatch' => $request->boolean('is_rewatch'),
                'contains_spoilers' => $request->boolean('contains_spoilers'),
                'status' => $validated['status'],
            ]
        );

        // If watched, remove from watchlist if present
        if ($validated['status'] === 'watched') {
            Watchlist::where('user_id', $userId)->where('media_item_id', $mediaItem->id)->delete();
        }

        if ($request->header('HX-Request')) {
            return response(view('reviews.partials.log-button-state', [
                'review' => $review,
                'type' => $validated['media_type'],
                'tmdbId' => $validated['tmdb_id'],
            ]))->withHeaders([
                'HX-Trigger' => json_encode([
                    'reviewSaved' => [
                        'title' => 'Mi Diario',
                        'message' => '¡Reseña y nota guardadas exitosamente!',
                        'type' => 'success',
                    ]
                ])
            ]);
        }

        return back()->with('success', '¡Reseña y nota guardadas exitosamente!');
    }

    /**
     * Delete review
     */
    public function destroy(Review $review)
    {
        $userId = Auth::id() ?? User::first()?->id;

        if ($review->user_id !== $userId) {
            abort(403);
        }

        $review->delete();

        if (request()->header('HX-Request')) {
            return response('');
        }

        return back()->with('success', 'Registro eliminado correctamente.');
    }
}
