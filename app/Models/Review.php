<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        'is_rewatch',
        'contains_spoilers',
        'status',
    ];

    protected $casts = [
        'watched_date' => 'date',
        'rating' => 'float',
        'is_rewatch' => 'boolean',
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
     * Get equivalent 5-star score (e.g. 10 -> 5.0, 8 -> 4.0)
     */
    public function getStarRatingAttribute(): ?float
    {
        return $this->rating !== null ? round($this->rating / 2, 1) : null;
    }

    /**
     * Friendly rating label
     */
    public function getRatingLabelAttribute(): ?string
    {
        if ($this->rating === null) return null;

        return match (true) {
            $this->rating >= 9.5 => 'Obra Maestra',
            $this->rating >= 8.5 => 'Excelente',
            $this->rating >= 7.5 => 'Muy Buena',
            $this->rating >= 6.5 => 'Buena',
            $this->rating >= 5.5 => 'Interesante / Pasable',
            $this->rating >= 4.5 => 'Regular',
            $this->rating >= 3.5 => 'Floja / Mediocre',
            $this->rating >= 2.0 => 'Mala',
            default => 'Pésima',
        };
    }
}
