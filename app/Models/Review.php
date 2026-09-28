<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una entrada del diario: una fila por usuario y título.
 *
 * Calificar es dar por vista. No hay estados de visionado ni re-visionados: la
 * nota, la fecha y la reseña son de "la vez que la viste", y si la ves de
 * nuevo se edita la misma entrada.
 */
class Review extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'media_item_id',
        'rating',
        'review_text',
        'private_notes',
        'watched_date',
        'contains_spoilers',
    ];

    protected $casts = [
        'watched_date' => 'date',
        'rating' => 'float',
        'contains_spoilers' => 'boolean',
    ];

    /**
     * User who authored the review / log
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Associated Media Item
     */
    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class);
    }

    /**
     * La entrada de un usuario sobre un título (hay como mucho una).
     */
    public function scopeOf($query, int $userId, int $mediaItemId)
    {
        return $query
            ->where('user_id', $userId)
            ->where('media_item_id', $mediaItemId);
    }

    /**
     * Get equivalent 5-star score (e.g. 10 -> 5.0, 8 -> 4.0)
     */
    public function getStarRatingAttribute(): ?float
    {
        return $this->rating !== null ? round($this->rating / 2, 1) : null;
    }
}
