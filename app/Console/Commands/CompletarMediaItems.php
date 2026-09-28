<?php

namespace App\Console\Commands;

use App\Models\MediaItem;
use App\Services\MediaCatalog;
use Illuminate\Console\Command;

/**
 * Completa desde TMDB los títulos que quedaron sin géneros (los creados antes
 * de MediaCatalog). Una llamada por título, cacheada; se puede correr las
 * veces que haga falta.
 */
class CompletarMediaItems extends Command
{
    protected $signature = 'dharma:completar-media';

    protected $description = 'Completa géneros, runtime y backdrop de los títulos que no los tienen';

    public function handle(MediaCatalog $catalog): int
    {
        $pendientes = MediaItem::whereNull('genres')->get();

        if ($pendientes->isEmpty()) {
            $this->info('Todos los títulos ya están completos.');

            return self::SUCCESS;
        }

        $this->withProgressBar($pendientes, fn (MediaItem $item) => $catalog->complete($item));
        $this->newLine();

        $sinDatos = MediaItem::whereNull('genres')->count();
        $this->info(sprintf('%d títulos completados%s.', $pendientes->count() - $sinDatos, $sinDatos ? ", {$sinDatos} sin respuesta de TMDB" : ''));

        return self::SUCCESS;
    }
}
