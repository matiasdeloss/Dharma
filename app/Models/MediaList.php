<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Una lista del usuario: títulos en el orden que él elige. Privada (solo la
 * ve su dueño). `is_ranked` la muestra numerada, como un ranking.
 *
 * Se llama MediaList y no List porque `list` es palabra reservada de PHP.
 */
class MediaList extends Model
{
    protected $fillable = [
        'user_id',
        'name',
        'description',
        'is_ranked',
    ];

    protected $casts = [
        'is_ranked' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Los títulos de la lista. Sin orden en la relación a propósito: se usa
     * también para agregados (`max('position')`), y un ORDER BY junto a un
     * agregado falla en MySQL/Postgres. Al listarlos, `orderBy('position')`.
     */
    public function items(): HasMany
    {
        return $this->hasMany(MediaListItem::class);
    }
}
