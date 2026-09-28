<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Un título dentro de una lista, con su puesto (1 a N, sin huecos).
 */
class MediaListItem extends Model
{
    protected $fillable = [
        'media_list_id',
        'media_item_id',
        'position',
    ];

    protected $casts = [
        'position' => 'integer',
    ];

    public function mediaList(): BelongsTo
    {
        return $this->belongsTo(MediaList::class);
    }

    public function mediaItem(): BelongsTo
    {
        return $this->belongsTo(MediaItem::class);
    }
}
