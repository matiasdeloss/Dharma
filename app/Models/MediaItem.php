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
        'availability',
        'runtime',
        'vote_average',
    ];

    protected $casts = [
        'release_date' => 'date',
        'genres' => 'array',
        'availability' => 'array',
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
     * En qué listas de usuarios está este título.
     */
    public function listItems(): HasMany
    {
        return $this->hasMany(MediaListItem::class);
    }

    // ---------------------------------------------------------------------
    // Disponibilidad en streaming (ver MediaCatalog::syncAvailability)
    // ---------------------------------------------------------------------

    /** Tipos de oferta que cuentan como "la puedo ver hoy" (no alquiler/compra). */
    public const STREAMING_KINDS = ['flatrate', 'ads', 'free'];

    /**
     * Snapshot de disponibilidad para una región, o null si nunca se sincronizó.
     */
    public function availabilityIn(string $region): ?array
    {
        return $this->availability[$region] ?? null;
    }

    /**
     * Hace falta volver a pedirle a TMDB la disponibilidad de esta región.
     */
    public function isAvailabilityStale(string $region, int $hours = 72): bool
    {
        $syncedAt = $this->availabilityIn($region)['synced_at'] ?? null;

        return $syncedAt === null || now()->subHours($hours)->greaterThan($syncedAt);
    }

    /**
     * Plataformas del usuario en las que este título se puede ver por
     * suscripción (o gratis) en la región. Lista de {id, name, logo_path}.
     */
    public function availableOn(array $providerIds, string $region): array
    {
        if ($providerIds === [] || ! ($snapshot = $this->availabilityIn($region))) {
            return [];
        }

        $matches = [];

        foreach (self::STREAMING_KINDS as $kind) {
            foreach ($snapshot[$kind] ?? [] as $provider) {
                if (in_array($provider['id'], $providerIds, true) && ! isset($matches[$provider['id']])) {
                    $matches[$provider['id']] = $provider;
                }
            }
        }

        return array_values($matches);
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
