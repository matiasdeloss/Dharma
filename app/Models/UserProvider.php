<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Una plataforma de streaming elegida por el usuario (id de TMDB + copia del
 * nombre y logo).
 */
class UserProvider extends Model
{
    protected $fillable = [
        'user_id',
        'provider_id',
        'name',
        'logo_path',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
