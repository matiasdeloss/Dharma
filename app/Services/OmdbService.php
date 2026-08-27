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
            // Cache ratings for 7 days - ratings don't fluctuate quickly, saving 99% of API quota
            return Cache::remember($cacheKey, now()->addDays(7), function () use ($imdbId) {
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
     * Normalize OMDb response into a clean structure
     */
    protected function normalizeRatings(array $data): array
    {
        $ratings = [
            'imdb' => $data['imdbRating'] ?? null,
            'imdb_votes' => $data['imdbVotes'] ?? null,
            'rotten_tomatoes' => null,
            'metacritic' => $data['Metascore'] ?? null,
            'awards' => (!empty($data['Awards']) && $data['Awards'] !== 'N/A') ? $data['Awards'] : null,
            'rated' => (!empty($data['Rated']) && $data['Rated'] !== 'N/A') ? $data['Rated'] : null,
        ];

        if (!empty($data['Ratings']) && is_array($data['Ratings'])) {
            foreach ($data['Ratings'] as $rating) {
                $source = $rating['Source'] ?? '';
                $val = $rating['Value'] ?? '';

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
