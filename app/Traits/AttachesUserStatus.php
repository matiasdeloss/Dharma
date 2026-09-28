<?php

namespace App\Traits;

use App\Models\Review;
use App\Models\Watchlist;

/**
 * Marca cada resultado de TMDB (cards del home, Explorar y búsqueda) con lo
 * que el usuario ya hizo con ese título: `in_watchlist` y `my_rating`.
 * Una consulta a la watchlist y una al diario por request, sin importar
 * cuántas filas de cards haya.
 */
trait AttachesUserStatus
{
    protected function attachUserStatus(array $items, ?int $userId, string $defaultType = 'movie'): array
    {
        if (empty($items)) {
            return $items;
        }

        ['watchlist' => $watchlist, 'ratings' => $ratings] = $this->userStatusLookups($userId);

        foreach ($items as &$item) {
            $id = $item['id'] ?? $item['tmdb_id'] ?? 0;
            $type = $item['media_type'] ?? $defaultType;
            $key = "{$type}_{$id}";

            $item['in_watchlist'] = isset($watchlist[$key]);
            $item['my_rating'] = $ratings[$key] ?? null;
        }

        return $items;
    }

    /**
     * Las dos consultas se hacen una vez por request. Se memoizan en el
     * Request y no en el controller: el router reutiliza la instancia del
     * controller entre requests (tests, Octane) y una propiedad quedaría
     * pegada al usuario anterior.
     *
     * @return array{watchlist: array<string, true>, ratings: array<string, float>}
     */
    private function userStatusLookups(?int $userId): array
    {
        $request = request();
        $cacheKey = 'dharma.user_status.'.($userId ?? 'guest');

        if ($request->attributes->has($cacheKey)) {
            return $request->attributes->get($cacheKey);
        }

        $lookups = ['watchlist' => [], 'ratings' => []];

        if (! $userId) {
            $request->attributes->set($cacheKey, $lookups);

            return $lookups;
        }

        $inWatchlist = Watchlist::where('user_id', $userId)
            ->join('media_items', 'watchlists.media_item_id', '=', 'media_items.id')
            ->get(['media_items.tmdb_id', 'media_items.media_type']);

        foreach ($inWatchlist as $row) {
            $lookups['watchlist']["{$row->media_type}_{$row->tmdb_id}"] = true;
        }

        $rated = Review::where('user_id', $userId)
            ->whereNotNull('rating')
            ->join('media_items', 'reviews.media_item_id', '=', 'media_items.id')
            ->get(['media_items.tmdb_id', 'media_items.media_type', 'reviews.rating']);

        foreach ($rated as $row) {
            $lookups['ratings']["{$row->media_type}_{$row->tmdb_id}"] = (float) $row->rating;
        }

        $request->attributes->set($cacheKey, $lookups);

        return $lookups;
    }
}
