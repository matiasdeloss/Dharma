<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MediaItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'tmdb_id',
        'media_type',
        'title',
        'original_title',
        'release_date',
        'poster_path',
        'backdrop_path',
        'overview',
        'genres',
        'runtime',
        'vote_average',
    ];

    protected $casts = [
        'release_date' => 'date',
        'genres' => 'array',
        'vote_average' => 'float',
        'tmdb_id' => 'integer',
        'runtime' => 'integer',
    ];

    /**
     * Get the reviews for this media item.
     */
    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * Get watchlist entries for this media item.
     */
    public function watchlists(): HasMany
    {
        return $this->hasMany(Watchlist::class);
    }

    /**
     * Helper to get full poster URL
     */
    public function getPosterUrlAttribute(): string
    {
        if ($this->poster_path) {
            $base = config('services.tmdb.image_base_url', 'https://image.tmdb.org/t/p');
            return "{$base}/w500{$this->poster_path}";
        }
        return asset('images/no-poster.svg');
    }

    /**
     * Helper to get full backdrop URL
     */
    public function getBackdropUrlAttribute(): ?string
    {
        if ($this->backdrop_path) {
            $base = config('services.tmdb.image_base_url', 'https://image.tmdb.org/t/p');
            return "{$base}/original{$this->backdrop_path}";
        }
        return null;
    }

    /**
     * Helper to get release year
     */
    public function getReleaseYearAttribute(): ?string
    {
        return $this->release_date ? $this->release_date->format('Y') : null;
    }
}
