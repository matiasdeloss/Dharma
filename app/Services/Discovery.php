<?php

namespace App\Services;

/**
 * Descubrimiento sobre TMDB `/discover`: "lo mejor de Netflix", "lo nuevo en
 * tus plataformas", y el browse con filtros de Explorar.
 *
 * Todo sale de la misma consulta con distintos parámetros; acá viven los
 * criterios (umbral de votos, orden, monetización) para que las pantallas no
 * los repitan.
 */
class Discovery
{
    /**
     * Plataformas grandes para las filas de Explorar cuando el usuario no
     * eligió las suyas (ids de TMDB). El nombre y el logo reales se toman de
     * `TmdbService::getWatchProviders`; esto es solo el orden y el fallback.
     */
    public const BIG_PLATFORMS = [
        8 => 'Netflix',
        119 => 'Amazon Prime Video',
        337 => 'Disney Plus',
        1899 => 'Max',
        350 => 'Apple TV+',
        531 => 'Paramount Plus',
    ];

    /** Ofertas que cuentan como "la puedo ver hoy" (sin alquiler ni compra). */
    private const STREAMING = 'flatrate|free|ads';

    public const SORTS = ['popular', 'valoradas', 'recientes'];

    public function __construct(protected TmdbService $tmdb)
    {
    }

    /**
     * Lo mejor valorado disponible en una o más plataformas.
     *
     * `vote_count.gte` es clave: sin eso TMDB devuelve películas con tres
     * votos y un 10. Con 300 todavía se colaban títulos chicos con nota
     * inflada por encima de clásicos; 1000 deja lo que de verdad es conocido.
     */
    public function bestOn(string $type, array $providerIds, string $region, int $page = 1): array
    {
        return $this->tmdb->discover($type, $this->onPlatforms($providerIds, $region) + [
            'sort_by' => 'vote_average.desc',
            'vote_count.gte' => 1000,
        ], $page);
    }

    /**
     * Lo más reciente disponible en las plataformas (ya estrenado).
     */
    public function newOn(string $type, array $providerIds, string $region, int $page = 1): array
    {
        $dateField = $type === 'tv' ? 'first_air_date' : 'primary_release_date';

        return $this->tmdb->discover($type, $this->onPlatforms($providerIds, $region) + [
            'sort_by' => "{$dateField}.desc",
            "{$dateField}.lte" => now()->toDateString(),
            'vote_count.gte' => 30,
        ], $page);
    }

    /**
     * Lo más popular ahora en las plataformas.
     */
    public function popularOn(string $type, array $providerIds, string $region, int $page = 1): array
    {
        return $this->tmdb->discover($type, $this->onPlatforms($providerIds, $region) + [
            'sort_by' => 'popularity.desc',
        ], $page);
    }

    /**
     * Browse de Explorar con filtros del usuario.
     *
     * @param  array{genre?:int|null, year?:int|null, providers?:int[], sort?:string}  $filters
     */
    public function browse(string $type, array $filters, string $region, int $page = 1): array
    {
        $dateField = $type === 'tv' ? 'first_air_date' : 'primary_release_date';

        $params = match ($filters['sort'] ?? 'popular') {
            'valoradas' => ['sort_by' => 'vote_average.desc', 'vote_count.gte' => 200],
            'recientes' => ['sort_by' => "{$dateField}.desc", "{$dateField}.lte" => now()->toDateString(), 'vote_count.gte' => 20],
            default => ['sort_by' => 'popularity.desc'],
        };

        if (! empty($filters['genre'])) {
            $params['with_genres'] = (int) $filters['genre'];
        }

        if (! empty($filters['year'])) {
            $params[$type === 'tv' ? 'first_air_date_year' : 'primary_release_year'] = (int) $filters['year'];
        }

        if (! empty($filters['providers'])) {
            $params += $this->onPlatforms($filters['providers'], $region);
        }

        return $this->tmdb->discover($type, $params, $page);
    }

    /**
     * Parámetros de discover para "disponible por suscripción en estas
     * plataformas, en esta región". El `|` entre ids es OR.
     */
    protected function onPlatforms(array $providerIds, string $region): array
    {
        return [
            'with_watch_providers' => implode('|', array_map('intval', $providerIds)),
            'watch_region' => $region,
            'with_watch_monetization_types' => self::STREAMING,
        ];
    }
}
