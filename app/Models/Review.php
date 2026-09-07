<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Query\Builder;

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
     * Entradas de un usuario sobre un título, de la más reciente a la más vieja.
     *
     * Desde que existen los re-visionados hay N filas por (user, media): esta
     * es la consulta canónica para "mis registros de este título". Las que no
     * tienen fecha (por ver / viéndola) van al final, no al principio.
     */
    public function scopeEntriesFor($query, int $userId, int $mediaItemId)
    {
        return $query
            ->where('user_id', $userId)
            ->where('media_item_id', $mediaItemId)
            ->orderByRaw('watched_date is null')
            ->orderByDesc('watched_date')
            ->orderByDesc('id');
    }

    /**
     * Una sola fila por usuario: la última que cumpla la condición.
     *
     * Con re-visionados, promediar o listar "todas las filas" del título
     * cuenta al mismo usuario tantas veces como lo haya visto. Esto deja la
     * entrada más nueva (MAX(id)) de cada uno.
     *
     * @param  callable(Builder): void  $filter
     */
    public function scopeLatestPerUser($query, int $mediaItemId, callable $filter)
    {
        $filter($query->where('media_item_id', $mediaItemId));

        return $query->whereIn('id', function ($sub) use ($mediaItemId, $filter) {
            $sub->selectRaw('max(id)')
                ->from('reviews')
                ->where('media_item_id', $mediaItemId)
                ->groupBy('user_id');

            $filter($sub);
        });
    }

    /**
     * Etiqueta en español del estado de visionado.
     */
    public function getStatusLabelAttribute(): string
    {
        return match ($this->status) {
            'watching' => 'Viéndola',
            'plan_to_watch' => 'Quiero verla',
            'dropped' => 'Abandonada',
            default => 'Vista',
        };
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
        if ($this->rating === null) {
            return null;
        }

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
