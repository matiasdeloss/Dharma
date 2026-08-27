<?php

namespace App\Traits;

use App\Models\Review;
use App\Models\Watchlist;

trait HasUserStats
{
    /**
     * Notas, reseñas y watchlist para el bloque de estadísticas del hero.
     * Sin usuario (demo/guest) devuelve agregados globales en vez de por usuario.
     */
    protected function getHeroStats(?int $userId): array
    {
        if ($userId) {
            return [
                'total_notes' => Review::where('user_id', $userId)
                    ->whereNotNull('private_notes')->where('private_notes', '!=', '')->count(),
                'total_reviews' => Review::where('user_id', $userId)->where(function ($q) {
                    $q->whereNotNull('review_text')->where('review_text', '!=', '');
                })->count() ?: Review::where('user_id', $userId)->count(),
                'total_watchlist' => Watchlist::where('user_id', $userId)->count(),
            ];
        }

        return [
            'total_notes' => Review::whereNotNull('private_notes')->where('private_notes', '!=', '')->count(),
            'total_reviews' => Review::where(function ($q) {
                $q->whereNotNull('review_text')->where('review_text', '!=', '');
            })->count() ?: Review::count(),
            'total_watchlist' => Watchlist::count(),
        ];
    }
}
