<?php

namespace App\Traits;

use App\Models\Review;
use App\Models\Watchlist;

trait HasUserStats
{
    /**
     * Contadores del encabezado del diario (reviews/partials/diary-stats).
     *
     * Los usan ReviewController::index para pintar la banda y, como fragmento
     * out-of-band, ReviewController::store y WatchlistController::toggle para
     * mantenerla al dia si el diario esta en pantalla al guardar.
     */
    protected function getDiaryStats(int $userId): array
    {
        $mine = Review::where('user_id', $userId);

        return [
            'total_logged' => (clone $mine)->count(),
            'avg_rating' => round((clone $mine)->whereNotNull('rating')->avg('rating') ?? 0, 1),

            'total_notes' => (clone $mine)
                ->whereNotNull('private_notes')
                ->where('private_notes', '!=', '')
                ->count(),

            // Antes esto terminaba en `?: Review::where(...)->count()`: con cero
            // resenas escritas caia a contar TODOS los registros del usuario, y
            // el numero que mostraba no significaba nada.
            'total_reviews' => (clone $mine)
                ->whereNotNull('review_text')
                ->where('review_text', '!=', '')
                ->count(),

            'total_watchlist' => Watchlist::where('user_id', $userId)->count(),
        ];
    }
}
