<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TmdbService
{
    /** Lo que se pide junto con cada ficha, en la misma llamada. */
    private const DETAILS_APPEND = 'credits,videos,recommendations,similar,watch/providers,external_ids';

    /** Pedidos simultáneos como máximo en getMany(). */
    private const POOL_CONCURRENCY = 10;

    protected string $baseUrl;

    protected ?string $apiKey;

    protected ?string $readToken;

    protected string $imageBaseUrl;

    protected string $language;

    public function __construct()
    {
        $this->baseUrl = config('services.tmdb.base_url', 'https://api.themoviedb.org/3');
        $this->apiKey = config('services.tmdb.api_key');
        $this->readToken = config('services.tmdb.read_token');
        $this->imageBaseUrl = config('services.tmdb.image_base_url', 'https://image.tmdb.org/t/p');
        $this->language = config('services.tmdb.language', 'es-ES');
    }

    /**
     * Check if TMDB API is properly configured
     */
    public function isConfigured(): bool
    {
        return ! empty($this->apiKey) || ! empty($this->readToken);
    }

    /**
     * Build HTTP client with auth headers/params
     */
    protected function client(): PendingRequest
    {
        return $this->configure(Http::acceptJson());
    }

    /**
     * Base, timeout y auth de TMDB sobre un pedido suelto o de un pool.
     */
    protected function configure(PendingRequest $request): PendingRequest
    {
        $request->baseUrl($this->baseUrl)
            ->timeout(10)
            ->acceptJson();

        if ($this->readToken) {
            $request->withToken($this->readToken);
        }

        return $request;
    }

    /**
     * Perform a GET request to TMDB
     */
    public function get(string $endpoint, array $params = []): array
    {
        if (! $this->isConfigured()) {
            return $this->getMockData($endpoint, $params);
        }

        [$query, $cacheKey, $ttl] = $this->prepareRequest($endpoint, $params);

        try {
            return $this->fromCache($cacheKey)
                ?? $this->cacheSuccessful($cacheKey, $ttl, $endpoint, $this->client()->get($endpoint, $query));
        } catch (\Exception $e) {
            Log::error('TMDB API Exception: '.$e->getMessage());

            return [];
        }
    }

    /**
     * Varios GET a la vez: lo que ya está en caché sale de ahí y el resto se
     * pide en paralelo. Usa las mismas claves de caché que get(), así que un
     * get() posterior con ese endpoint y esos params no vuelve a la red.
     *
     * Existe por "Para vos" en Explorar: necesita la ficha de cada semilla y
     * de cada recomendado, y de a una (con caché frío) eran casi 40 llamadas
     * en fila y la página pasaba los 30 s de PHP.
     *
     * @param  array<array-key, array{0: string, 1?: array}>  $requests  clave => [endpoint, params]
     * @return array<array-key, array> misma clave y mismo orden => respuesta (`[]` si falló)
     */
    public function getMany(array $requests): array
    {
        if (! $this->isConfigured()) {
            return array_map(fn (array $request) => $this->getMockData($request[0], $request[1] ?? []), $requests);
        }

        $results = array_fill_keys(array_keys($requests), []);
        $pending = [];

        try {
            foreach ($requests as $name => $request) {
                [$query, $cacheKey, $ttl] = $this->prepareRequest($request[0], $request[1] ?? []);

                if (($cached = $this->fromCache($cacheKey)) !== null) {
                    $results[$name] = $cached;
                } else {
                    $pending[$name] = [$request[0], $query, $cacheKey, $ttl];
                }
            }

            if ($pending === []) {
                return $results;
            }

            $responses = Http::pool(function (Pool $pool) use ($pending) {
                foreach ($pending as $name => [$endpoint, $query]) {
                    $this->configure($pool->as((string) $name))->get($endpoint, $query);
                }
            }, self::POOL_CONCURRENCY);

            foreach ($pending as $name => [$endpoint, , $cacheKey, $ttl]) {
                $results[$name] = $this->cacheSuccessful($cacheKey, $ttl, $endpoint, $responses[$name] ?? null);
            }
        } catch (\Exception $e) {
            Log::error('TMDB API Exception: '.$e->getMessage());
        }

        return $results;
    }

    /**
     * Query final (idioma y api_key), clave de caché y TTL de un pedido.
     *
     * @return array{0: array, 1: string, 2: \DateTimeInterface}
     */
    protected function prepareRequest(string $endpoint, array $params): array
    {
        $params['language'] = $params['language'] ?? $this->language;

        // If using API Key (v3 auth) and no bearer token
        if (! $this->readToken && $this->apiKey) {
            $params['api_key'] = $this->apiKey;
        }

        // Dynamic caching: Details are static (24h), trending/popular (12h), search (4h)
        $ttl = match (true) {
            str_starts_with($endpoint, '/movie/') || str_starts_with($endpoint, '/tv/') || str_starts_with($endpoint, '/person/') => now()->addHours(24),
            str_contains($endpoint, 'trending') || str_contains($endpoint, 'popular') || str_contains($endpoint, 'top_rated') => now()->addHours(12),
            str_contains($endpoint, 'search') => now()->addHours(4),
            str_contains($endpoint, '/genre/') || str_contains($endpoint, '/watch/providers/') => now()->addDays(7),
            default => now()->addHours(8),
        };

        return [$params, 'tmdb_'.md5($endpoint.serialize($params)), $ttl];
    }

    /**
     * Lo cacheado para esa clave, o null si no hay nada útil. Un `[]` cuenta
     * como vacío: así se curan solos los errores que quedaron guardados antes
     * de que cacheSuccessful() dejara de guardarlos.
     */
    protected function fromCache(string $cacheKey): ?array
    {
        $cached = Cache::get($cacheKey);

        return is_array($cached) && $cached !== [] ? $cached : null;
    }

    /**
     * Guarda en caché una respuesta buena y la devuelve. Los errores NO se
     * guardan: un 429 o un 500 momentáneo de TMDB dejaba la ficha en 404 (o
     * las filas del home vacías) hasta que venciera el TTL.
     */
    protected function cacheSuccessful(string $cacheKey, \DateTimeInterface $ttl, string $endpoint, mixed $response): array
    {
        $data = $response instanceof Response && $response->successful() ? $response->json() : null;

        if (is_array($data) && $data !== []) {
            Cache::put($cacheKey, $data, $ttl);

            return $data;
        }

        Log::warning("TMDB API Error {$endpoint}: ".match (true) {
            $response instanceof Response => "[{$response->status()}] ".$response->body(),
            $response instanceof \Throwable => $response->getMessage(),
            default => 'sin respuesta',
        });

        return [];
    }

    /**
     * Get Trending Movies & Shows (day / week)
     */
    public function getTrending(string $mediaType = 'all', string $timeWindow = 'week', int $page = 1): array
    {
        return $this->get("/trending/{$mediaType}/{$timeWindow}", ['page' => $page]);
    }

    /**
     * Get Popular Movies
     */
    public function getPopularMovies(int $page = 1): array
    {
        return $this->get('/movie/popular', ['page' => $page]);
    }

    /**
     * Get Popular TV Shows
     */
    public function getPopularTv(int $page = 1): array
    {
        return $this->get('/tv/popular', ['page' => $page]);
    }

    /**
     * Get Top Rated Movies
     */
    public function getTopRatedMovies(int $page = 1): array
    {
        return $this->get('/movie/top_rated', ['page' => $page]);
    }

    /**
     * Search Multi (Movies, TV Shows)
     */
    public function searchMulti(string $query, int $page = 1): array
    {
        if (trim($query) === '') {
            return ['results' => []];
        }

        return $this->get('/search/multi', [
            'query' => $query,
            'page' => $page,
            'include_adult' => false,
        ]);
    }

    /**
     * Search Movies specifically
     */
    public function searchMovies(string $query, int $page = 1): array
    {
        return $this->get('/search/movie', [
            'query' => $query,
            'page' => $page,
            'include_adult' => false,
        ]);
    }

    /**
     * Search TV Shows specifically
     */
    public function searchTv(string $query, int $page = 1): array
    {
        return $this->get('/search/tv', [
            'query' => $query,
            'page' => $page,
            'include_adult' => false,
        ]);
    }

    /**
     * Get Movie Details (with credits, videos, recommendations, and streaming providers in a SINGLE API call)
     */
    public function getMovieDetails(int $id): array
    {
        return $this->get(...$this->detailsRequest('movie', $id));
    }

    /**
     * Get TV Show Details (with credits, videos, recommendations, and streaming providers in a SINGLE API call)
     */
    public function getTvDetails(int $id): array
    {
        return $this->get(...$this->detailsRequest('tv', $id));
    }

    /**
     * Fichas de varios títulos a la vez (ver getMany), por "tipo_id".
     *
     * @param  iterable<array{0: string, 1: int}>  $titles  [tipo, id de TMDB]
     * @return array<string, array>
     */
    public function detailsMany(iterable $titles): array
    {
        $requests = [];

        foreach ($titles as [$type, $id]) {
            $type = $type === 'tv' ? 'tv' : 'movie';
            $requests["{$type}_{$id}"] = $this->detailsRequest($type, (int) $id);
        }

        return $this->getMany($requests);
    }

    /**
     * Endpoint y params de una ficha. Lo comparten getMovieDetails,
     * getTvDetails y detailsMany para caer siempre en la misma entrada de caché.
     */
    protected function detailsRequest(string $type, int $id): array
    {
        return ["/{$type}/{$id}", ['append_to_response' => self::DETAILS_APPEND]];
    }

    /**
     * Persona (actor, director...) con todos sus créditos de película y serie
     * en una sola llamada.
     */
    public function getPerson(int $id): array
    {
        return $this->get("/person/{$id}", [
            'append_to_response' => 'combined_credits',
        ]);
    }

    /**
     * TMDB discover. Los resultados no traen `media_type`; se agrega para que
     * las cards y el cruce con la watchlist funcionen igual que con trending.
     */
    public function discover(string $type, array $params = [], int $page = 1): array
    {
        $type = $type === 'tv' ? 'tv' : 'movie';

        $response = $this->get("/discover/{$type}", $params + ['page' => $page, 'include_adult' => 'false']);

        foreach ($response['results'] ?? [] as &$item) {
            $item['media_type'] = $type;
        }

        return $response;
    }

    /**
     * Géneros de película o serie, `[{id, name}]` en el idioma configurado.
     */
    public function getGenres(string $type): array
    {
        $type = $type === 'tv' ? 'tv' : 'movie';

        return $this->get("/genre/{$type}/list")['genres'] ?? [];
    }

    /**
     * Plataformas de streaming disponibles en una región, ordenadas como TMDB
     * las prioriza para esa región (Netflix, Prime, Disney+... primero). Se
     * unen las listas de película y serie porque difieren un poco.
     *
     * @return array<int, array{provider_id:int, provider_name:string, logo_path:?string}>
     */
    public function getWatchProviders(string $region = 'AR'): array
    {
        $byId = [];

        foreach (['movie', 'tv'] as $type) {
            $results = $this->get("/watch/providers/{$type}", ['watch_region' => $region])['results'] ?? [];

            foreach ($results as $provider) {
                $id = $provider['provider_id'] ?? null;

                if ($id === null || isset($byId[$id])) {
                    continue;
                }

                $byId[$id] = [
                    'provider_id' => (int) $id,
                    'provider_name' => $provider['provider_name'] ?? '',
                    'logo_path' => $provider['logo_path'] ?? null,
                    'priority' => $provider['display_priorities'][$region] ?? ($provider['display_priority'] ?? 999),
                ];
            }
        }

        usort($byId, fn ($a, $b) => $a['priority'] <=> $b['priority']);

        return array_values($byId);
    }

    /**
     * Regiones con datos de streaming, `[iso => nombre]` ordenadas por nombre.
     *
     * @return array<string, string>
     */
    public function getWatchRegions(): array
    {
        $regions = [];

        foreach ($this->get('/watch/providers/regions')['results'] ?? [] as $region) {
            if (! empty($region['iso_3166_1'])) {
                $regions[$region['iso_3166_1']] = $region['native_name'] ?? $region['english_name'] ?? $region['iso_3166_1'];
            }
        }

        asort($regions);

        return $regions;
    }

    /**
     * Extract normalized Streaming Providers for Argentina (AR) with fallback to US/global
     */
    public function extractWatchProviders(array $mediaDetails, string $preferredRegion = 'AR'): array
    {
        $allProviders = $mediaDetails['watch/providers']['results']
            ?? $mediaDetails['watch_providers']['results']
            ?? [];

        if (empty($allProviders)) {
            return [
                'has_providers' => false,
                'region' => $preferredRegion,
                'flatrate' => [],
                'ads' => [],
                'free' => [],
                'rent' => [],
                'buy' => [],
                'link' => null,
                'available_regions' => [],
            ];
        }

        // Available regions in the results
        $availableRegions = array_keys($allProviders);

        // Pick requested region (default AR), fallback to US, or first available
        $currentRegion = in_array($preferredRegion, $availableRegions)
            ? $preferredRegion
            : (in_array('US', $availableRegions) ? 'US' : reset($availableRegions));

        $regionData = $allProviders[$currentRegion] ?? [];

        return [
            'has_providers' => ! empty($regionData['flatrate']) || ! empty($regionData['ads']) || ! empty($regionData['free']) || ! empty($regionData['rent']) || ! empty($regionData['buy']),
            'region' => $currentRegion,
            'flatrate' => $regionData['flatrate'] ?? [],
            'ads' => $regionData['ads'] ?? [],
            'free' => $regionData['free'] ?? [],
            'rent' => $regionData['rent'] ?? [],
            'buy' => $regionData['buy'] ?? [],
            'link' => $regionData['link'] ?? null,
            'available_regions' => $availableRegions,
        ];
    }

    /**
     * Helper to get full Poster Image URL
     */
    public function imageUrl(?string $path, string $size = 'w500'): string
    {
        if (! $path) {
            return asset('images/no-poster.svg');
        }

        return "{$this->imageBaseUrl}/{$size}{$path}";
    }

    /**
     * Helper to get full Backdrop Image URL
     */
    public function backdropUrl(?string $path, string $size = 'original'): string
    {
        if (! $path) {
            return '';
        }

        return "{$this->imageBaseUrl}/{$size}{$path}";
    }

    /**
     * Fallback mock data when API key is not yet set
     */
    protected function getMockData(string $endpoint, array $params): array
    {
        // Provide rich mock data so the UI renders beautifully out-of-the-box before key configuration
        if (str_starts_with($endpoint, '/person/')) {
            return [
                'id' => 10297,
                'name' => 'Matthew McConaughey',
                'known_for_department' => 'Acting',
                'birthday' => '1969-11-04',
                'deathday' => null,
                'place_of_birth' => 'Uvalde, Texas, USA',
                'profile_path' => '/8qBuzIMEnYVM9Kn44Q8X5WCE210.jpg',
                'biography' => 'Actor y productor estadounidense, ganador del Óscar por Dallas Buyers Club.',
                'combined_credits' => [
                    'cast' => [
                        ['id' => 157336, 'media_type' => 'movie', 'title' => 'Interstellar', 'character' => 'Cooper', 'release_date' => '2014-11-05', 'poster_path' => '/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg', 'popularity' => 140.0],
                        ['id' => 27205, 'media_type' => 'movie', 'title' => 'Inception', 'character' => 'Cobb (mock)', 'release_date' => '2010-07-15', 'poster_path' => '/9gk7adHYeDvHkCSEqAvQNLV5Uge.jpg', 'popularity' => 90.0],
                        ['id' => 46648, 'media_type' => 'tv', 'name' => 'True Detective', 'character' => 'Rust Cohle', 'first_air_date' => '2014-01-12', 'poster_path' => '/cuV2O5ZyDLHSOWzg3nLVojx4JMs.jpg', 'popularity' => 60.0],
                    ],
                    'crew' => [],
                ],
            ];
        }

        if (str_contains($endpoint, '/genre/')) {
            return [
                'genres' => [
                    ['id' => 28, 'name' => 'Acción'],
                    ['id' => 12, 'name' => 'Aventura'],
                    ['id' => 35, 'name' => 'Comedia'],
                    ['id' => 18, 'name' => 'Drama'],
                    ['id' => 878, 'name' => 'Ciencia ficción'],
                ],
            ];
        }

        if (str_contains($endpoint, 'trending') || str_contains($endpoint, 'popular') || str_contains($endpoint, 'search') || str_contains($endpoint, 'discover')) {
            return [
                'page' => 1,
                'total_pages' => 1,
                'total_results' => 4,
                'results' => [
                    [
                        'id' => 157336,
                        'title' => 'Interstellar',
                        'name' => 'Interstellar',
                        'media_type' => 'movie',
                        'overview' => 'Un grupo de científicos y exploradores viaja a través de un agujero de gusano en el espacio para intentar asegurar la supervivencia de la humanidad.',
                        'poster_path' => '/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg',
                        'backdrop_path' => '/xJHokMbljvjADYdit5fK5VQsXEG.jpg',
                        'release_date' => '2014-11-05',
                        'first_air_date' => '2014-11-05',
                        'vote_average' => 8.4,
                        'vote_count' => 34000,
                    ],
                    [
                        'id' => 27205,
                        'title' => 'Inception (El Origen)',
                        'name' => 'Inception',
                        'media_type' => 'movie',
                        'overview' => 'Dom Cobb es un ladrón capaz de adentrarse en los sueños de la gente para hacerse con sus secretos durante el sueño profundo.',
                        'poster_path' => '/9gk7adHYeDvHkCSEqAvQNLV5Uge.jpg',
                        'backdrop_path' => '/8ZTVqvKDQ8emSGUEMjsS4yHAwrp.jpg',
                        'release_date' => '2010-07-15',
                        'first_air_date' => '2010-07-15',
                        'vote_average' => 8.4,
                        'vote_count' => 36000,
                    ],
                    [
                        'id' => 1396,
                        'title' => 'Breaking Bad',
                        'name' => 'Breaking Bad',
                        'media_type' => 'tv',
                        'overview' => 'Walter White, un profesor de química diagnosticado con cáncer inoperable, decide comenzar a fabricar metanfetamina junto a un exalumno para asegurar el futuro de su familia.',
                        'poster_path' => '/ztkUQFLlC19CCMYHW9o1zWhJRNq.jpg',
                        'backdrop_path' => '/tsRy63Mu5cu8etL1X7ZLyf7UP1M.jpg',
                        'release_date' => '2008-01-20',
                        'first_air_date' => '2008-01-20',
                        'vote_average' => 8.9,
                        'vote_count' => 14000,
                    ],
                    [
                        'id' => 693134,
                        'title' => 'Dune: Parte Dos',
                        'name' => 'Dune: Part Two',
                        'media_type' => 'movie',
                        'overview' => 'Paul Atreides se une a Chani y a los Fremen mientras busca venganza contra los conspiradores que destruyeron a su familia.',
                        'poster_path' => '/czembW0Rk1Ke7desVsc39ObpEIL.jpg',
                        'backdrop_path' => '/xOMo8BRK7PfcJv9JCnx7s520QIq.jpg',
                        'release_date' => '2024-02-27',
                        'first_air_date' => '2024-02-27',
                        'vote_average' => 8.2,
                        'vote_count' => 5200,
                    ],
                ],
            ];
        }

        if (str_contains($endpoint, '/movie/') || str_contains($endpoint, '/tv/')) {
            return [
                'id' => 157336,
                'title' => 'Interstellar',
                'original_title' => 'Interstellar',
                'name' => 'Interstellar',
                'overview' => 'Un grupo de científicos y exploradores emprende un viaje espacial a través de un agujero de gusano en busca de un nuevo hogar para la humanidad ante la escasez de recursos en la Tierra.',
                'poster_path' => '/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg',
                'backdrop_path' => '/xJHokMbljvjADYdit5fK5VQsXEG.jpg',
                'release_date' => '2014-11-05',
                'runtime' => 169,
                'vote_average' => 8.4,
                'vote_count' => 34500,
                'tagline' => 'El fin de la Tierra no será el fin de la humanidad.',
                'genres' => [
                    ['id' => 12, 'name' => 'Aventura'],
                    ['id' => 18, 'name' => 'Drama'],
                    ['id' => 878, 'name' => 'Ciencia ficción'],
                ],
                'credits' => [
                    'cast' => [
                        ['id' => 10297, 'name' => 'Matthew McConaughey', 'character' => 'Cooper', 'profile_path' => '/8qBuzIMEnYVM9Kn44Q8X5WCE210.jpg'],
                        ['id' => 1813, 'name' => 'Anne Hathaway', 'character' => 'Brand', 'profile_path' => '/tLel4cuA046JbcmGRfHQF2x0ezz.jpg'],
                        ['name' => 'Jessica Chastain', 'character' => 'Murph (adulta)', 'profile_path' => '/lodMzLKSbeeygPbaKz6b26dKnkj.jpg'],
                        ['name' => 'Michael Caine', 'character' => 'Profesor Brand', 'profile_path' => '/bVZRMlp17949k5W6vLwG8l82WzN.jpg'],
                    ],
                    'crew' => [
                        ['name' => 'Christopher Nolan', 'job' => 'Director'],
                    ],
                ],
                'videos' => [
                    'results' => [
                        [
                            'key' => 'zSWdZVtXT7E',
                            'site' => 'YouTube',
                            'type' => 'Trailer',
                            'name' => 'Official Trailer',
                        ],
                    ],
                ],
                'recommendations' => [
                    'results' => [
                        ['id' => 27205, 'title' => 'Inception', 'media_type' => 'movie', 'poster_path' => '/9gk7adHYeDvHkCSEqAvQNLV5Uge.jpg', 'release_date' => '2010-07-15', 'vote_average' => 8.4, 'vote_count' => 36000],
                        ['id' => 155, 'title' => 'El caballero de la noche', 'media_type' => 'movie', 'poster_path' => '/qJ2tW6WMUDux911r6m7haRef0WH.jpg', 'release_date' => '2008-07-16', 'vote_average' => 8.5, 'vote_count' => 33000],
                    ],
                ],
                'watch/providers' => [
                    'results' => [
                        'AR' => [
                            'link' => 'https://www.themoviedb.org/movie/157336-interstellar/watch?locale=AR',
                            'flatrate' => [
                                ['provider_id' => 119, 'provider_name' => 'Amazon Prime Video', 'logo_path' => '/emthp39XA2GUNqUwRWhZ97JqGJZ.jpg'],
                                ['provider_id' => 1899, 'provider_name' => 'Max', 'logo_path' => '/j7D0vmuVPO1UGtG5t5YZpqudcVo.jpg'],
                            ],
                            'rent' => [
                                ['provider_id' => 2, 'provider_name' => 'Apple TV', 'logo_path' => '/peURlLlr8jggOwK53fJ5wdQl05y.jpg'],
                                ['provider_id' => 3, 'provider_name' => 'Google Play Movies', 'logo_path' => '/tbEdFQDwx5LEVr8Wp6QO9vdpNsM.jpg'],
                            ],
                        ],
                    ],
                ],
            ];
        }

        if (str_contains($endpoint, '/watch/providers/regions')) {
            return [
                'results' => [
                    ['iso_3166_1' => 'AR', 'english_name' => 'Argentina', 'native_name' => 'Argentina'],
                    ['iso_3166_1' => 'CL', 'english_name' => 'Chile', 'native_name' => 'Chile'],
                    ['iso_3166_1' => 'ES', 'english_name' => 'Spain', 'native_name' => 'España'],
                    ['iso_3166_1' => 'MX', 'english_name' => 'Mexico', 'native_name' => 'México'],
                    ['iso_3166_1' => 'US', 'english_name' => 'United States of America', 'native_name' => 'Estados Unidos'],
                    ['iso_3166_1' => 'UY', 'english_name' => 'Uruguay', 'native_name' => 'Uruguay'],
                ],
            ];
        }

        if (str_contains($endpoint, '/watch/providers/')) {
            return [
                'results' => [
                    ['provider_id' => 8, 'provider_name' => 'Netflix', 'logo_path' => '/pbpMk2JmcoNnQwx5JGpXngfoWtp.jpg', 'display_priority' => 0],
                    ['provider_id' => 119, 'provider_name' => 'Amazon Prime Video', 'logo_path' => '/dQeAar5H991VYporEjUspolDarG.jpg', 'display_priority' => 1],
                    ['provider_id' => 337, 'provider_name' => 'Disney Plus', 'logo_path' => '/97yvRBw1GzX7fXprcF80er19ot.jpg', 'display_priority' => 2],
                    ['provider_id' => 1899, 'provider_name' => 'Max', 'logo_path' => '/jbe4gVSfRlbPTdESXhEKpornsfu.jpg', 'display_priority' => 3],
                    ['provider_id' => 350, 'provider_name' => 'Apple TV+', 'logo_path' => '/2E03IAZsX4ZaUqM7tXlctEPMGWS.jpg', 'display_priority' => 4],
                    ['provider_id' => 531, 'provider_name' => 'Paramount Plus', 'logo_path' => '/h5DcR0J2EESLitnhR8xLG1QymTE.jpg', 'display_priority' => 5],
                ],
            ];
        }

        return [];
    }
}
