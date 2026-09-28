<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OmdbService
{
    protected ?string $apiKey;
    protected string $baseUrl;

    public function __construct()
    {
        $this->apiKey = config('services.omdb.api_key');
        $this->baseUrl = config('services.omdb.base_url', 'https://www.omdbapi.com/');
    }

    /**
     * Check if OMDb API key is set
     */
    public function isConfigured(): bool
    {
        return !empty($this->apiKey);
    }

    /**
     * Get multi-platform ratings (IMDb, Rotten Tomatoes, Metacritic) by IMDb ID
     */
    public function getRatings(?string $imdbId): ?array
    {
        if (!$imdbId) {
            return null;
        }

        $cacheKey = "omdb_ratings_{$imdbId}";

        // If not configured, provide mock ratings for preview
        if (!$this->isConfigured()) {
            return $this->getMockRatings($imdbId);
        }

        try {
            // TTL segun lo completa que venga la respuesta (ver cacheTtlFor):
            // 7 dias si ya estan las tres notas, mucho menos si faltan. Un
            // estreno reciente aparece en OMDb con todo en N/A y recien a los
            // dias le cargan Rotten Tomatoes / Metacritic; con 7 dias fijos
            // quedaba clavado en "N/D" aunque la API ya tuviera las notas.
            return Cache::remember($cacheKey, fn (?array $ratings) => $this->cacheTtlFor($ratings), function () use ($imdbId) {
                $response = Http::timeout(6)
                    ->get($this->baseUrl, [
                        'i' => $imdbId,
                        'apikey' => $this->apiKey,
                    ]);

                if ($response->successful()) {
                    $data = $response->json();

                    if (($data['Response'] ?? 'False') === 'True') {
                        return $this->normalizeRatings($data);
                    }
                }

                Log::warning("OMDb API error for {$imdbId}: " . $response->body());
                return null;
            });
        } catch (\Exception $e) {
            Log::error("OMDb Service Exception: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Cuanto tiempo guardar una respuesta de OMDb.
     *
     * - null (error de API): no se cachea (Cache::remember ignora null).
     * - Completa: 7 dias, las notas no cambian rapido y ahorra cuota.
     * - Incompleta: 12 horas, para que un estreno que todavia no tiene
     *   Rotten Tomatoes / Metacritic las levante en cuanto OMDb las cargue.
     *   Para series OMDb casi nunca trae RT ni Metacritic, asi que ahi solo
     *   se considera incompleta si falta la de IMDb.
     */
    protected function cacheTtlFor(?array $ratings): \DateTimeInterface
    {
        if ($ratings === null) {
            return now();
        }

        $missing = empty($ratings['imdb']);

        if (($ratings['type'] ?? 'movie') !== 'series') {
            $missing = $missing || empty($ratings['rotten_tomatoes']) || empty($ratings['metacritic']);
        }

        return $missing ? now()->addHours(12) : now()->addDays(7);
    }

    /**
     * Normalize OMDb response into a clean structure.
     * OMDb devuelve "N/A" en vez de omitir el campo; aca se convierte a null
     * para que las vistas y el TTL de cache no tengan que conocer ese detalle.
     */
    protected function normalizeRatings(array $data): array
    {
        $clean = fn ($value) => (isset($value) && $value !== '' && $value !== 'N/A') ? $value : null;

        $ratings = [
            'type' => $data['Type'] ?? 'movie',
            'imdb' => $clean($data['imdbRating'] ?? null),
            'imdb_votes' => $clean($data['imdbVotes'] ?? null),
            'rotten_tomatoes' => null,
            'metacritic' => $clean($data['Metascore'] ?? null),
            'awards' => $clean($data['Awards'] ?? null),
            'rated' => $clean($data['Rated'] ?? null),
        ];

        if (!empty($data['Ratings']) && is_array($data['Ratings'])) {
            foreach ($data['Ratings'] as $rating) {
                $source = $rating['Source'] ?? '';
                $val = $clean($rating['Value'] ?? null);

                if ($val === null) {
                    continue;
                }

                if (str_contains($source, 'Rotten Tomatoes')) {
                    $ratings['rotten_tomatoes'] = $val;
                } elseif (str_contains($source, 'Metacritic') && !$ratings['metacritic']) {
                    $ratings['metacritic'] = str_replace('/100', '', $val);
                }
            }
        }

        return $ratings;
    }

    /**
     * Mock ratings when OMDb key is not yet placed in .env
     */
    protected function getMockRatings(string $imdbId): array
    {
        return [
            'type' => 'movie',
            'imdb' => '8.7',
            'imdb_votes' => '2,100,000',
            'rotten_tomatoes' => '73%',
            'metacritic' => '74',
            'awards' => 'Won 1 Oscar. 44 wins & 148 nominations total',
            'rated' => 'PG-13',
            'is_mock' => true,
        ];
    }
}
