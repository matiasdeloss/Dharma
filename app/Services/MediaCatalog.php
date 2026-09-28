<?php

namespace App\Services;

use App\Models\MediaItem;

/**
 * Alta y completado de `media_items` a partir de TMDB.
 *
 * Los formularios (calificar, watchlist) mandan lo que la card o la ficha
 * tenían a mano: título, poster, fecha. Lo que no viaja en el form (géneros,
 * runtime, backdrop, sinopsis) se completa desde la ficha de TMDB al crear el
 * registro, en una sola llamada cacheada 24 h. Sin esto `genres` quedaba
 * siempre null y no había forma de filtrar ni de armar estadísticas por
 * género.
 */
class MediaCatalog
{
    /** Columnas que se completan desde TMDB cuando el registro no las tiene. */
    private const COMPLETABLE = ['original_title', 'release_date', 'poster_path', 'backdrop_path', 'overview', 'genres', 'runtime', 'vote_average'];

    public function __construct(protected TmdbService $tmdb)
    {
    }

    /**
     * Busca o crea el título. Los datos salen de TMDB; `$fallback` (lo que
     * trajo el form) solo cubre lo que TMDB no devolvió, por ejemplo si la
     * API no respondió.
     *
     * Antes era al revés y el form mandaba: cualquier usuario logueado podía
     * "bautizar" un título que todavía no estaba en la base con el nombre o
     * el póster que quisiera, y como `media_items` es compartido, lo veían
     * todos.
     */
    public function firstOrCreate(string $type, int $tmdbId, array $fallback = []): MediaItem
    {
        $item = MediaItem::where('tmdb_id', $tmdbId)->where('media_type', $type)->first();

        if ($item) {
            // Registros anteriores a este servicio: se completan la primera
            // vez que alguien los vuelve a tocar.
            return $item->genres === null ? $this->complete($item) : $item;
        }

        $attributes = array_merge(
            array_filter($fallback, fn ($value) => $value !== null && $value !== ''),
            $this->fromTmdb($type, $tmdbId),
            ['tmdb_id' => $tmdbId, 'media_type' => $type],
        );

        $attributes['title'] ??= 'Sin título';

        return MediaItem::create($attributes);
    }

    /**
     * Rellena desde TMDB las columnas que el registro tenga en null.
     */
    public function complete(MediaItem $item): MediaItem
    {
        $details = $this->fromTmdb($item->media_type, $item->tmdb_id);

        foreach (self::COMPLETABLE as $column) {
            if ($item->{$column} === null && isset($details[$column])) {
                $item->{$column} = $details[$column];
            }
        }

        // Si la ficha vino pero sin géneros, se marca como completado igual
        // para no volver a pedirla en cada toque. Si la API no respondió,
        // queda en null y se reintenta la próxima vez.
        if ($details !== []) {
            $item->genres ??= [];
        }

        if ($item->isDirty()) {
            $item->save();
        }

        return $item;
    }

    /**
     * Guarda en el título qué plataformas lo ofrecen en una región (snapshot
     * de `watch/providers` de TMDB). Si ya se tiene la ficha a mano (la
     * pantalla de detalle la acaba de pedir) se pasa en `$details` y no
     * cuesta ninguna llamada extra.
     */
    public function syncAvailability(MediaItem $item, string $region, ?array $details = null): MediaItem
    {
        $details ??= $item->media_type === 'tv'
            ? $this->tmdb->getTvDetails($item->tmdb_id)
            : $this->tmdb->getMovieDetails($item->tmdb_id);

        // Sin ficha no se pisa lo que hubiera: mejor un snapshot viejo que
        // uno vacío por un error de red.
        if (empty($details)) {
            return $item;
        }

        $regionData = $details['watch/providers']['results'][$region]
            ?? $details['watch_providers']['results'][$region]
            ?? [];

        $snapshot = [
            'synced_at' => now()->toIso8601String(),
            'link' => $regionData['link'] ?? null,
        ];

        foreach (['flatrate', 'ads', 'free', 'rent', 'buy'] as $kind) {
            $snapshot[$kind] = array_values(array_map(fn ($p) => [
                'id' => (int) ($p['provider_id'] ?? 0),
                'name' => $p['provider_name'] ?? '',
                'logo_path' => $p['logo_path'] ?? null,
            ], $regionData[$kind] ?? []));
        }

        $availability = $item->availability ?? [];
        $availability[$region] = $snapshot;
        $item->availability = $availability;
        $item->save();

        return $item;
    }

    /**
     * Refresca la disponibilidad de los títulos que la tengan vieja o nunca
     * sincronizada, de a pocos por pedido para no demorar la pantalla
     * (cada uno puede ser una llamada a TMDB).
     *
     * @param  iterable<MediaItem>  $items
     */
    public function refreshStaleAvailability(iterable $items, string $region, int $limit = 10): void
    {
        foreach ($items as $item) {
            if ($limit <= 0) {
                return;
            }

            if ($item->isAvailabilityStale($region)) {
                $this->syncAvailability($item, $region);
                $limit--;
            }
        }
    }

    /**
     * Ficha de TMDB traducida a columnas de `media_items`. Vacío si la API
     * no responde: el alta sigue con lo que trajo el form.
     */
    protected function fromTmdb(string $type, int $tmdbId): array
    {
        $details = $type === 'tv'
            ? $this->tmdb->getTvDetails($tmdbId)
            : $this->tmdb->getMovieDetails($tmdbId);

        if (empty($details)) {
            return [];
        }

        $genres = array_values(array_map(
            fn ($genre) => ['id' => $genre['id'] ?? null, 'name' => $genre['name'] ?? ''],
            $details['genres'] ?? [],
        ));

        return array_filter([
            'title' => $details['title'] ?? $details['name'] ?? null,
            'original_title' => $details['original_title'] ?? $details['original_name'] ?? null,
            'release_date' => $details['release_date'] ?? $details['first_air_date'] ?? null,
            'poster_path' => $details['poster_path'] ?? null,
            'backdrop_path' => $details['backdrop_path'] ?? null,
            'overview' => $details['overview'] ?? null,
            'genres' => $genres,
            // En series `runtime` no existe: TMDB da la duración por episodio.
            'runtime' => $details['runtime'] ?? ($details['episode_run_time'][0] ?? null),
            'vote_average' => $details['vote_average'] ?? null,
        ], fn ($value) => $value !== null && $value !== '');
    }
}
