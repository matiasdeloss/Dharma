<?php

namespace App\Http\Controllers;

use App\Models\MediaItem;
use App\Models\Review;
use App\Models\Watchlist;
use App\Services\OmdbService;
use App\Services\TmdbService;
use App\Traits\HasUserStats;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MediaController extends Controller
{
    use HasUserStats;

    // Imagen fija para el fondo del hero del home (no rota al azar, a diferencia
    // del backdrop de /login y /register).
    protected const HERO_BACKDROP_FILE = 'MV5BMzdkNTdhMzItYjVhOC00M2RmLThmOTAtNmZlNDJkOTc2ODk2XkEyXkFqcGc@._V1_FMjpg_UX1280_.jpg';

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

        $userId = Auth::id();

        $stats = $this->getHeroStats(Auth::id());

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
            'heroBackdrop' => [
                'url' => asset('images/auth/'.self::HERO_BACKDROP_FILE),
                'title' => '',
            ],
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

        $userId = Auth::id();
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
        $userId = Auth::id();

        // Un título puede tener N entradas del mismo usuario (re-visionados):
        // la ficha trabaja con la lista completa y elige cuál mostrar.
        $userEntries = collect();
        $userReview = null;   // la entrada más reciente: "Tu reseña"
        $userScore = null;    // la entrada calificada más reciente: "Tu nota"
        $inWatchlist = false;

        if ($userId && $mediaItem) {
            $userEntries = Review::entriesFor($userId, $mediaItem->id)->get();

            $userReview = $userEntries->first();

            // Un re-visionado sin nota no borra la nota que ya habías puesto.
            $userScore = $userEntries->first(fn ($entry) => $entry->rating !== null)
                ?? $userReview;

            $inWatchlist = Watchlist::where('user_id', $userId)
                ->where('media_item_id', $mediaItem->id)
                ->exists();
        }

        // Reseñas de la comunidad: una sola por usuario (la última que tenga
        // texto). Sin deduplicar, quien registra tres visionados aparecía tres
        // veces seguidas en la lista.
        $communityReviews = $mediaItem
            ? Review::latestPerUser($mediaItem->id, fn ($q) => $q->whereNotNull('review_text')->where('review_text', '!=', ''))
                ->with('user')
                ->latest()
                ->take(10)
                ->get()
            : collect();

        // Streaming Watch Providers (JustWatch / TMDB)
        $watchProviders = $this->tmdb->extractWatchProviders($details, 'AR');

        // External IDs and Multi-Source Critic Ratings (IMDb, Rotten Tomatoes, Metacritic)
        $imdbId = $details['imdb_id'] ?? $details['external_ids']['imdb_id'] ?? null;
        $omdbRatings = $this->omdb->getRatings($imdbId);

        // Dharma Community Rating. También una fila por usuario: si no, quien
        // vio algo cinco veces pesaba cinco veces en el promedio "de todos".
        $dharmaAvg = null;
        $dharmaCount = 0;

        if ($mediaItem) {
            $dharmaRatings = Review::latestPerUser($mediaItem->id, fn ($q) => $q->whereNotNull('rating'));

            $dharmaAvg = (clone $dharmaRatings)->avg('rating');
            $dharmaCount = (clone $dharmaRatings)->count();
        }

        // Títulos relacionados: recomendaciones con fallback a similares. Ambos
        // ya venían en el append_to_response de TmdbService y no se usaban.
        //
        // Se arma acá y no en la vista porque necesita consultar la watchlist
        // del usuario: en Blade no hay forma de marcarle el estado a cada card.
        $relatedPool = ! empty($details['recommendations']['results'])
            ? $details['recommendations']['results']
            : ($details['similar']['results'] ?? []);

        $related = [];
        $relatedSeen = [];

        foreach ($relatedPool as $item) {
            $itemId = $item['id'] ?? null;
            $itemType = $item['media_type'] ?? $type; // 'similar' no trae media_type

            if (! $itemId || isset($relatedSeen[$itemId]) || empty($item['poster_path'])) {
                continue;
            }

            if (! in_array($itemType, ['movie', 'tv'], true)) {
                continue;
            }

            $item['media_type'] = $itemType;
            $relatedSeen[$itemId] = true;
            $related[] = $item;

            if (count($related) >= 18) {
                break;
            }
        }

        $related = $this->attachWatchlistStatus($related, $userId);

        return view('media.show', [
            'media' => $details,
            'type' => $type,
            'related' => $related,
            'mediaItem' => $mediaItem,
            'userReview' => $userReview,
            'userScore' => $userScore,
            'userEntries' => $userEntries,
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
