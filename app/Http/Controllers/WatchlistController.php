<?php

namespace App\Http\Controllers;

use App\Models\MediaItem;
use App\Models\User;
use App\Models\Watchlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WatchlistController extends Controller
{
    /**
     * Display user's watchlist
     */
    public function index()
    {
        $userId = Auth::id() ?? User::first()?->id;

        if (!$userId) {
            return redirect()->route('home')->with('info', 'Inicia sesión para ver tu lista de seguimiento.');
        }

        $watchlist = Watchlist::with('mediaItem')
            ->where('user_id', $userId)
            ->latest()
            ->paginate(16);

        return view('watchlist.index', [
            'watchlist' => $watchlist,
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

        if (!$userId) {
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
                        'message' => 'Inicia sesión para guardar películas en tu Watchlist.'
                    ]
                ])
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

            return response(view($viewName, [
                'inWatchlist' => $inWatchlist,
                'tmdbId' => $validated['tmdb_id'],
                'mediaType' => $validated['media_type'],
                'title' => $validated['title'],
                'posterPath' => $validated['poster_path'] ?? '',
                'releaseDate' => $validated['release_date'] ?? '',
                'voteAverage' => $validated['vote_average'] ?? '',
            ]))->withHeaders([
                'HX-Trigger' => json_encode([
                    'watchlistUpdated' => [
                        'message' => $message,
                        'type' => $type,
                        'title' => 'Watchlist',
                    ]
                ])
            ]);
        }

        return back()->with('success', $message);
    }
}
