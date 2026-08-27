<?php

namespace App\Http\Controllers;

use App\Models\MediaItem;
use App\Models\Review;
use App\Models\User;
use App\Models\Watchlist;
use App\Services\TmdbService;
use App\Services\OmdbService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MediaController extends Controller
{
    protected TmdbService $tmdb;
    protected OmdbService $omdb;

    public function __construct(TmdbService $tmdb, OmdbService $omdb)
    {
        $this->tmdb = $tmdb;
        $this->omdb = $omdb;
    }

    /**
     * Home page: Trending, Popular, Recent Reviews, Stats
     */
    public function index()
    {
        $trending = $this->tmdb->getTrending('movie', 'week');
        $popularTv = $this->tmdb->getPopularTv();
        $topRated = $this->tmdb->getTopRatedMovies();

        // Recent reviews logged by users
        $recentReviews = Review::with(['user', 'mediaItem'])
            ->latest()
            ->take(6)
            ->get();

        $userId = Auth::id() ?? User::first()?->id;

        if (Auth::check()) {
            $stats = [
                'total_reviews' => Review::where('user_id', Auth::id())->where(function($q) {
                    $q->whereNotNull('review_text')->where('review_text', '!=', '');
                })->count() ?: Review::where('user_id', Auth::id())->count(),
                'total_notes' => Review::where('user_id', Auth::id())->whereNotNull('private_notes')->where('private_notes', '!=', '')->count(),
                'total_watchlist' => Watchlist::where('user_id', Auth::id())->count(),
            ];
        } else {
            $stats = [
                'total_reviews' => Review::where(function($q) {
                    $q->whereNotNull('review_text')->where('review_text', '!=', '');
                })->count() ?: Review::count(),
                'total_notes' => Review::whereNotNull('private_notes')->where('private_notes', '!=', '')->count(),
                'total_watchlist' => Watchlist::count(),
            ];
        }

        $user = Auth::user();

        // 1. Selecciones Populares (Tendencias globales combinadas)
        $popularPicksRaw = $this->tmdb->getTrending('all', 'week');
        $popularPicks = $this->attachWatchlistStatus(array_slice($popularPicksRaw['results'] ?? [], 0, 15), $userId);

        // 2. Mejores 10 Películas de la Semana (Ranking de popularidad)
        $trendingMoviesRaw = $this->tmdb->getTrending('movie', 'week');
        $topMovies = $this->attachWatchlistStatus(array_slice($trendingMoviesRaw['results'] ?? [], 0, 10), $userId, 'movie');

        // 3. Mejores 10 Series de la Semana (Ranking de popularidad)
        $trendingTvRaw = $this->tmdb->getTrending('tv', 'week');
        $topTv = $this->attachWatchlistStatus(array_slice($trendingTvRaw['results'] ?? [], 0, 10), $userId, 'tv');

        return view('home', [
            'popularPicks' => $popularPicks,
            'topMovies' => $topMovies,
            'topTv' => $topTv,
            'trending' => $topMovies, // Alias de compatibilidad
            'popularTv' => $topTv, // Alias de compatibilidad
            'recentReviews' => $recentReviews,
            'stats' => $stats,
            'user' => $user,
            'isConfigured' => $this->tmdb->isConfigured(),
        ]);
    }

    /**
     * Search movies and TV shows (supports HTMX live search and full search page)
     */
    public function search(Request $request)
    {
        $query = $request->input('q', '');
        $page = (int) $request->input('page', 1);

        if (trim($query) === '') {
            if ($request->header('HX-Request')) {
                return response('');
            }
            return view('media.search', ['results' => [], 'query' => '']);
        }

        $response = $this->tmdb->searchMulti($query, $page);
        $results = array_filter($response['results'] ?? [], function ($item) {
            return in_array($item['media_type'] ?? '', ['movie', 'tv']);
        });

        $userId = Auth::id() ?? User::first()?->id;
        $results = $this->attachWatchlistStatus($results, $userId);

        // If request comes from HTMX live search input
        if ($request->header('HX-Request') && $request->input('dropdown') == '1') {
            return view('media.partials.search-dropdown', [
                'results' => array_slice($results, 0, 6),
                'query' => $query,
            ]);
        }

        return view('media.search', [
            'results' => $results,
            'query' => $query,
            'page' => $page,
            'totalPages' => $response['total_pages'] ?? 1,
        ]);
    }

    /**
     * Show Media Detail Page (Movie or TV Show)
     */
    public function show(string $type, int $tmdbId)
    {
        $details = $type === 'tv'
            ? $this->tmdb->getTvDetails($tmdbId)
            : $this->tmdb->getMovieDetails($tmdbId);

        if (empty($details)) {
            abort(404, 'Película o serie no encontrada.');
        }

        // Check if exists in local database
        $mediaItem = MediaItem::where('tmdb_id', $tmdbId)
            ->where('media_type', $type)
            ->first();

        // Get currently logged-in user (or default demo user if unauthenticated for testing)
        $userId = Auth::id() ?? User::first()?->id;

        $userReview = null;
        $inWatchlist = false;

        if ($userId && $mediaItem) {
            $userReview = Review::where('user_id', $userId)
                ->where('media_item_id', $mediaItem->id)
                ->first();

            $inWatchlist = Watchlist::where('user_id', $userId)
                ->where('media_item_id', $mediaItem->id)
                ->exists();
        }

        // Community reviews for this media item
        $communityReviews = $mediaItem
            ? $mediaItem->reviews()->with('user')->latest()->take(10)->get()
            : collect();

        // Streaming Watch Providers (JustWatch / TMDB)
        $watchProviders = $this->tmdb->extractWatchProviders($details, 'AR');

        // External IDs and Multi-Source Critic Ratings (IMDb, Rotten Tomatoes, Metacritic)
        $imdbId = $details['imdb_id'] ?? $details['external_ids']['imdb_id'] ?? null;
        $omdbRatings = $this->omdb->getRatings($imdbId);

        // Dharma Community Rating
        $dharmaAvg = $mediaItem ? $mediaItem->reviews()->whereNotNull('rating')->avg('rating') : null;
        $dharmaCount = $mediaItem ? $mediaItem->reviews()->whereNotNull('rating')->count() : 0;

        return view('media.show', [
            'media' => $details,
            'type' => $type,
            'mediaItem' => $mediaItem,
            'userReview' => $userReview,
            'inWatchlist' => $inWatchlist,
            'communityReviews' => $communityReviews,
            'watchProviders' => $watchProviders,
            'imdbId' => $imdbId,
            'omdbRatings' => $omdbRatings,
            'dharmaAvg' => $dharmaAvg,
            'dharmaCount' => $dharmaCount,
        ]);
    }

    /**
     * Attach user's in_watchlist status to a list of media items
     */
    protected function attachWatchlistStatus(array $items, ?int $userId, string $defaultType = 'movie'): array
    {
        if (empty($items)) {
            return $items;
        }

        $watchlistLookup = [];
        $watchlistIds = [];

        if ($userId) {
            $userWatchlist = Watchlist::where('user_id', $userId)
                ->join('media_items', 'watchlists.media_item_id', '=', 'media_items.id')
                ->select('media_items.tmdb_id', 'media_items.media_type')
                ->get();

            foreach ($userWatchlist as $w) {
                $watchlistLookup["{$w->media_type}_{$w->tmdb_id}"] = true;
                $watchlistIds[$w->tmdb_id] = true;
            }
        }

        foreach ($items as &$item) {
            $id = $item['id'] ?? $item['tmdb_id'] ?? 0;
            $type = $item['media_type'] ?? $defaultType;
            $item['in_watchlist'] = isset($watchlistLookup["{$type}_{$id}"]) || isset($watchlistIds[$id]);
        }

        return $items;
    }
}
