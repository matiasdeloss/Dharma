<?php

namespace App\Http\Controllers;

use App\Models\MediaItem;
use App\Models\Watchlist;
use App\Traits\HasLocalBackdrop;
use App\Traits\HasUserStats;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WatchlistController extends Controller
{
    use HasLocalBackdrop;
    use HasUserStats;

    /** Valores validos de `watchlists.priority`, en orden de urgencia. */
    private const PRIORITIES = ['high', 'medium', 'low'];

    /**
     * Display user's watchlist
     */
    public function index(Request $request)
    {
        $userId = Auth::id();

        if (! $userId) {
            return redirect()->route('home')->with('info', 'Inicia sesión para ver tu lista de seguimiento.');
        }

        $query = Watchlist::with('mediaItem')->where('user_id', $userId);

        $filterPriority = $request->input('priority');
        if (in_array($filterPriority, self::PRIORITIES, true)) {
            $query->where('priority', $filterPriority);
        }

        // Alta primero, despues media, despues baja; y dentro de cada grupo lo
        // agregado mas recientemente. `latest()` solo no alcanza: sin este orden
        // la prioridad seria un dato decorativo.
        $watchlist = $query
            ->orderByRaw("CASE priority WHEN 'high' THEN 0 WHEN 'medium' THEN 1 ELSE 2 END")
            ->latest()
            ->paginate(16)
            ->withQueryString();

        // Composicion de la lista, para la banda de encabezado. Se cuenta sobre
        // media_items (no sobre la pagina actual) para que el total no cambie
        // al pasar de pagina.
        $inWatchlist = MediaItem::whereHas('watchlists', fn ($q) => $q->where('user_id', $userId));

        $stats = [
            'total' => (clone $inWatchlist)->count(),
            'movies' => (clone $inWatchlist)->where('media_type', 'movie')->count(),
            'series' => (clone $inWatchlist)->where('media_type', 'tv')->count(),
        ];

        // Horas estimadas para terminar la lista. Muchos titulos de TMDB vienen
        // sin runtime, asi que es una cota inferior; se muestra como "aprox".
        $stats['hours'] = round(((clone $inWatchlist)->sum('runtime') ?? 0) / 60, 1);

        return view('watchlist.index', [
            'watchlist' => $watchlist,
            'stats' => $stats,
            'filterPriority' => $filterPriority,
            'headerBackdrop' => $this->backdropFrom(clone $inWatchlist),
        ]);
    }

    /**
     * Actualiza prioridad y nota de un item de la watchlist.
     */
    public function update(Request $request, Watchlist $watchlist)
    {
        if ($watchlist->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'priority' => 'required|in:'.implode(',', self::PRIORITIES),
            'notes' => 'nullable|string|max:2000',
        ]);

        $watchlist->update($validated);

        if ($request->header('HX-Request')) {
            return response(
                view('watchlist.partials.item-meta', ['item' => $watchlist])->render()
            )->withHeaders([
                'HX-Trigger' => json_encode([
                    'watchlistUpdated' => [
                        'message' => 'Prioridad y nota guardadas.',
                        'type' => 'success',
                        'title' => 'Watchlist',
                    ],
                ]),
            ]);
        }

        return back()->with('success', 'Prioridad y nota guardadas.');
    }

    /**
     * Toggle item in watchlist
     */
    public function toggle(Request $request)
    {
        $validated = $request->validate([
            'tmdb_id' => 'required|integer',
            'media_type' => 'required|in:movie,tv',
            'title' => 'required|string',
            'poster_path' => 'nullable|string',
            'release_date' => 'nullable|date',
            'vote_average' => 'nullable|numeric',
        ]);

        $userId = Auth::id();

        if (! $userId) {
            $viewName = $request->input('style') === 'ribbon'
                ? 'watchlist.partials.ribbon-button'
                : 'watchlist.partials.toggle-button';

            return response(view($viewName, [
                'inWatchlist' => false,
                'tmdbId' => $validated['tmdb_id'],
                'mediaType' => $validated['media_type'],
                'title' => $validated['title'],
                'posterPath' => $validated['poster_path'] ?? '',
                'releaseDate' => $validated['release_date'] ?? '',
                'voteAverage' => $validated['vote_average'] ?? '',
            ]))->withHeaders([
                'HX-Trigger' => json_encode([
                    'authRequired' => [
                        'message' => 'Inicia sesión para guardar películas en tu Watchlist.',
                    ],
                ]),
            ]);
        }

        $mediaItem = MediaItem::firstOrCreate(
            [
                'tmdb_id' => $validated['tmdb_id'],
                'media_type' => $validated['media_type'],
            ],
            [
                'title' => $validated['title'],
                'poster_path' => $validated['poster_path'] ?? null,
                'release_date' => $validated['release_date'] ?? null,
                'vote_average' => $validated['vote_average'] ?? null,
            ]
        );

        $exists = Watchlist::where('user_id', $userId)
            ->where('media_item_id', $mediaItem->id)
            ->first();

        $inWatchlist = false;

        if ($exists) {
            $exists->delete();
            $inWatchlist = false;
            $message = 'Eliminada de tu Watchlist';
            $type = 'info';
        } else {
            Watchlist::create([
                'user_id' => $userId,
                'media_item_id' => $mediaItem->id,
            ]);
            $inWatchlist = true;
            $message = '¡Agregada a tu Watchlist!';
            $type = 'success';
        }

        if ($request->header('HX-Request')) {
            $viewName = $request->input('style') === 'ribbon'
                ? 'watchlist.partials.ribbon-button'
                : 'watchlist.partials.toggle-button';

            $html = view($viewName, [
                'inWatchlist' => $inWatchlist,
                'tmdbId' => $validated['tmdb_id'],
                'mediaType' => $validated['media_type'],
                'title' => $validated['title'],
                'posterPath' => $validated['poster_path'] ?? '',
                'releaseDate' => $validated['release_date'] ?? '',
                'voteAverage' => $validated['vote_average'] ?? '',
            ])->render();

            // Fragmento out-of-band: si el hero del home está en pantalla, su
            // contador de Watchlist se actualiza solo con esta misma respuesta.
            $html .= view('partials.hero-stats-oob', [
                'stats' => $this->getHeroStats($userId),
            ])->render();

            return response($html)->withHeaders([
                'HX-Trigger' => json_encode([
                    'watchlistUpdated' => [
                        'message' => $message,
                        'type' => $type,
                        'title' => 'Watchlist',
                    ],
                ]),
            ]);
        }

        return back()->with('success', $message);
    }
}
