<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class TmdbService
{
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
        return !empty($this->apiKey) || !empty($this->readToken);
    }

    /**
     * Build HTTP client with auth headers/params
     */
    protected function client()
    {
        $request = Http::baseUrl($this->baseUrl)
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
        if (!$this->isConfigured()) {
            return $this->getMockData($endpoint, $params);
        }

        $params['language'] = $params['language'] ?? $this->language;

        // If using API Key (v3 auth) and no bearer token
        if (!$this->readToken && $this->apiKey) {
            $params['api_key'] = $this->apiKey;
        }

        try {
            $cacheKey = 'tmdb_' . md5($endpoint . serialize($params));

            // Dynamic caching: Details are static (24h), trending/popular (12h), search (4h)
            $ttl = match (true) {
                str_starts_with($endpoint, '/movie/') || str_starts_with($endpoint, '/tv/') => now()->addHours(24),
                str_contains($endpoint, 'trending') || str_contains($endpoint, 'popular') || str_contains($endpoint, 'top_rated') => now()->addHours(12),
                str_contains($endpoint, 'search') => now()->addHours(4),
                default => now()->addHours(8),
            };

            return Cache::remember($cacheKey, $ttl, function () use ($endpoint, $params) {
                $response = $this->client()->get($endpoint, $params);

                if ($response->successful()) {
                    return $response->json();
                }

                Log::warning("TMDB API Error [{$response->status()}]: " . $response->body());
                return [];
            });
        } catch (\Exception $e) {
            Log::error("TMDB API Exception: " . $e->getMessage());
            return [];
        }
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
     * Get backdrops and posters for a specific TV show
     */
    public function getTvImages(int $tvId): array
    {
        return $this->get("/tv/{$tvId}/images", [
            'include_image_language' => 'en,null,es',
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
        return $this->get("/movie/{$id}", [
            'append_to_response' => 'credits,videos,recommendations,similar,watch/providers,external_ids',
        ]);
    }

    /**
     * Get TV Show Details (with credits, videos, recommendations, and streaming providers in a SINGLE API call)
     */
    public function getTvDetails(int $id): array
    {
        return $this->get("/tv/{$id}", [
            'append_to_response' => 'credits,videos,recommendations,similar,watch/providers,external_ids',
        ]);
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
            'has_providers' => !empty($regionData['flatrate']) || !empty($regionData['rent']) || !empty($regionData['buy']),
            'region' => $currentRegion,
            'flatrate' => $regionData['flatrate'] ?? [],
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
        if (!$path) {
            return asset('images/no-poster.svg');
        }
        return "{$this->imageBaseUrl}/{$size}{$path}";
    }

    /**
     * Helper to get full Backdrop Image URL
     */
    public function backdropUrl(?string $path, string $size = 'original'): string
    {
        if (!$path) {
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
        if (str_contains($endpoint, 'trending') || str_contains($endpoint, 'popular') || str_contains($endpoint, 'search')) {
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
                ]
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
                        ['name' => 'Matthew McConaughey', 'character' => 'Cooper', 'profile_path' => '/8qBuzIMEnYVM9Kn44Q8X5WCE210.jpg'],
                        ['name' => 'Anne Hathaway', 'character' => 'Brand', 'profile_path' => '/tLel4cuA046JbcmGRfHQF2x0ezz.jpg'],
                        ['name' => 'Jessica Chastain', 'character' => 'Murph (adulta)', 'profile_path' => '/lodMzLKSbeeygPbaKz6b26dKnkj.jpg'],
                        ['name' => 'Michael Caine', 'character' => 'Profesor Brand', 'profile_path' => '/bVZRMlp17949k5W6vLwG8l82WzN.jpg'],
                    ],
                    'crew' => [
                        ['name' => 'Christopher Nolan', 'job' => 'Director'],
                    ]
                ],
                'videos' => [
                    'results' => [
                        [
                            'key' => 'zSWdZVtXT7E',
                            'site' => 'YouTube',
                            'type' => 'Trailer',
                            'name' => 'Official Trailer'
                        ]
                    ]
                ],
                'recommendations' => [
                    'results' => []
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
                        ]
                    ]
                ]
            ];
        }

        return [];
    }
}
