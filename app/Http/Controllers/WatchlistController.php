<?php

namespace App\Http\Controllers;

use App\Models\MediaItem;
use App\Models\Watchlist;
use App\Services\MediaCatalog;
use App\Traits\HasLocalBackdrop;
use App\Traits\HasUserStats;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Auth;

class WatchlistController extends Controller
{
    use HasLocalBackdrop;
    use HasUserStats;

    public function __construct(protected MediaCatalog $catalog)
    {
    }

    /**
     * Display user's watchlist
     */
    public function index(Request $request)
    {
        $user = Auth::user();

        if (! $user) {
            return redirect()->route('home')->with('info', 'Inicia sesión para ver tu lista de seguimiento.');
        }

        $region = $user->region;
        $myProviders = $user->providerIds();
        $onlyAvailable = $request->boolean('disponible') && $myProviders !== [];

        // Se carga la lista entera (es chica: la watchlist de UNA persona) para
        // poder filtrar por disponibilidad en PHP y paginar despues. Lo
        // agregado mas recientemente primero.
        $items = Watchlist::with('mediaItem')
            ->where('user_id', $user->id)
            ->latest()
            ->get()
            ->filter(fn ($item) => $item->mediaItem !== null);

        // Disponibilidad vieja o nunca pedida: se refresca de a pocos por
        // visita, asi la pantalla no espera N llamadas a TMDB.
        $this->catalog->refreshStaleAvailability($items->pluck('mediaItem'), $region);

        foreach ($items as $item) {
            $item->availableOn = $item->mediaItem->availableOn($myProviders, $region);
        }

        $availableCount = $items->filter(fn ($item) => $item->availableOn !== [])->count();

        if ($onlyAvailable) {
            $items = $items->filter(fn ($item) => $item->availableOn !== []);
        }

        $page = Paginator::resolveCurrentPage();
        $perPage = 16;
        $watchlist = new LengthAwarePaginator(
            $items->forPage($page, $perPage)->values(),
            $items->count(),
            $perPage,
            $page,
            ['path' => Paginator::resolveCurrentPath(), 'query' => $request->query()],
        );

        // Composicion de la lista, para la banda de encabezado. Se cuenta sobre
        // media_items (no sobre la pagina actual) para que el total no cambie
        // al pasar de pagina.
        $inWatchlist = MediaItem::whereHas('watchlists', fn ($q) => $q->where('user_id', $user->id));

        $stats = [
            'total' => (clone $inWatchlist)->count(),
            'movies' => (clone $inWatchlist)->where('media_type', 'movie')->count(),
            'series' => (clone $inWatchlist)->where('media_type', 'tv')->count(),
            'available' => $availableCount,
        ];

        return view('watchlist.index', [
            'watchlist' => $watchlist,
            'stats' => $stats,
            'onlyAvailable' => $onlyAvailable,
            'hasProviders' => $myProviders !== [],
            'headerBackdrop' => $this->backdropFrom(clone $inWatchlist),
        ]);
    }

    /**
     * Actualiza la nota de un item de la watchlist.
     */
    public function update(Request $request, Watchlist $watchlist)
    {
        if ($watchlist->user_id !== Auth::id()) {
            abort(403);
        }

        $validated = $request->validate([
            'notes' => 'nullable|string|max:2000',
        ], [
            'notes.max' => 'La nota puede tener hasta 2000 caracteres.',
        ]);

        $watchlist->update($validated);

        if ($request->header('HX-Request')) {
            return response(
                view('watchlist.partials.item-meta', ['item' => $watchlist])->render()
            )->withHeaders([
                'HX-Trigger' => json_encode([
                    'watchlistUpdated' => [
                        'message' => 'Nota guardada.',
                        'type' => 'success',
                        'title' => 'Watchlist',
                    ],
                ]),
            ]);
        }

        return back()->with('success', 'Nota guardada.');
    }

    /**
     * Un título al azar de la watchlist, para el modal "Elegir al azar". Si la
     * lista está filtrada por "Solo lo que puedo ver hoy", sale de ahí.
     * `excepto` es el que se acaba de mostrar: "Elegir otro" no lo repite.
     */
    public function random(Request $request)
    {
        $user = Auth::user();
        $myProviders = $user->providerIds();
        $onlyAvailable = $request->boolean('disponible') && $myProviders !== [];

        $items = Watchlist::with('mediaItem')
            ->where('user_id', $user->id)
            ->get()
            ->filter(fn ($item) => $item->mediaItem !== null)
            ->each(fn ($item) => $item->availableOn = $item->mediaItem->availableOn($myProviders, $user->region));

        if ($onlyAvailable) {
            $items = $items->filter(fn ($item) => $item->availableOn !== []);
        }

        if ($items->count() > 1) {
            $items = $items->reject(fn ($item) => $item->id === $request->integer('excepto'));
        }

        return view('watchlist.partials.random-pick', [
            'item' => $items->isEmpty() ? null : $items->random(),
            'onlyAvailable' => $onlyAvailable,
            'canReroll' => $items->count() > 1,
        ]);
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

        // La card solo tiene título, poster y fecha; géneros, runtime y
        // backdrop los completa MediaCatalog desde TMDB.
        $mediaItem = $this->catalog->firstOrCreate(
            $validated['media_type'],
            (int) $validated['tmdb_id'],
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

            // Al guardar se anota en que plataformas esta hoy, para que la
            // lista pueda decir "la podes ver en Netflix" sin pedir nada mas.
            $this->catalog->syncAvailability($mediaItem, Auth::user()->region);
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

            // Fragmento out-of-band: si la banda del diario está en pantalla,
            // su contador de Watchlist se actualiza solo con esta misma respuesta.
            $html .= view('reviews.partials.diary-stats', [
                'stats' => $this->getDiaryStats($userId),
                'oob' => true,
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
