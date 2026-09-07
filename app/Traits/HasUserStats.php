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
        // Sin usuario no hay nada que contar. El hero solo muestra estos numeros
        // dentro de @auth, asi que devolver ceros ademas ahorra tres COUNT por
        // visita de invitado.
        if (! $userId) {
            return [
                'total_notes' => 0,
                'total_reviews' => 0,
                'total_watchlist' => 0,
            ];
        }

        return [
            'total_notes' => Review::where('user_id', $userId)
                ->whereNotNull('private_notes')
                ->where('private_notes', '!=', '')
                ->count(),

            // Antes esto terminaba en `?: Review::where(...)->count()`: con cero
            // resenas escritas caia a contar TODOS los registros del usuario, y
            // el numero que mostraba el hero no significaba nada.
            'total_reviews' => Review::where('user_id', $userId)
                ->whereNotNull('review_text')
                ->where('review_text', '!=', '')
                ->count(),

            'total_watchlist' => Watchlist::where('user_id', $userId)->count(),
        ];
    }
}
