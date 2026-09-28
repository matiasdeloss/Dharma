<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'region'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Región por defecto para streaming (mismo default que la columna, para
     * que un usuario recién creado en memoria ya la tenga).
     */
    protected $attributes = [
        'region' => 'AR',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * User's reviews and movie notes
     */
    public function reviews(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Review::class);
    }

    /**
     * User's watchlist entries
     */
    public function watchlists(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(Watchlist::class);
    }

    /**
     * Listas propias ("Mi top de Nolan", "Maratón de Halloween").
     */
    public function mediaLists(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(MediaList::class);
    }

    /**
     * Plataformas de streaming elegidas en Ajustes.
     */
    public function providers(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(UserProvider::class);
    }

    /**
     * Ids de TMDB de las plataformas del usuario, para cruzar con
     * `watch/providers` de una ficha o con `with_watch_providers` de discover.
     *
     * @return int[]
     */
    public function providerIds(): array
    {
        return $this->providers->pluck('provider_id')->all();
    }

    /**
     * Get user avatar URL
     */
    public function getAvatarUrlAttribute(): string
    {
        return 'https://ui-avatars.com/api/?name=' . urlencode($this->name) . '&background=1f252b&color=f1f5f9&bold=true&rounded=true';
    }
}
