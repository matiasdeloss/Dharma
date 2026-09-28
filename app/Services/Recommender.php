<?php

namespace App\Services;

use App\Models\MediaItem;
use App\Models\Review;
use App\Models\User;
use Illuminate\Support\Facades\Cache;

/**
 * "Para vos": recomendaciones a partir de lo que el usuario calificó mejor.
 *
 * No hay recomendador propio. Se toman las `recommendations` de TMDB de los
 * títulos mejor calificados del diario y se agregan ponderadas por la nota:
 * un título que TMDB sugiere desde varias de tus favoritas sube, y el que
 * sale de tu 9 pesa más que el que sale de tu 6.5.
 *
 * La semilla es RELATIVA al usuario (sus mejores notas, no "8 o más"): quien
 * califica exigente y nunca pasa de 7.5 también tiene favoritas.
 */
class Recommender
{
    /** Nota mínima para que un título cuente como semilla (no recomendar desde algo que no gustó). */
    private const MIN_SEED_RATING = 6;

    /** Cuántas semillas: al menos 10, o el 30% del diario, hasta 20. */
    private const MIN_SEEDS = 10;

    private const MAX_SEEDS = 20;

    private const SEED_SHARE = 0.3;

    /**
     * Piso de calidad del candidato. Que dos favoritas apunten al mismo
     * título es buena señal, pero no alcanza para recomendar algo con 6.0
     * en TMDB o con cuatro votos.
     */
    private const MIN_CANDIDATE_RATING = 6.5;

    private const MIN_CANDIDATE_VOTES = 50;

    public function __construct(protected TmdbService $tmdb)
    {
    }

    /**
     * @return array{seeds: string[], items: array}  `seeds` son los títulos
     *         de las 3 semillas con más nota (para el subtítulo "Porque te
     *         gustaron..."); `items` son resultados tipo TMDB con
     *         `media_type` y `my_providers` (plataformas del usuario donde
     *         se ve hoy).
     */
    public function forUser(User $user, int $limit = 18): array
    {
        $seeds = $this->seeds($user);

        if ($seeds->isEmpty()) {
            return ['seeds' => [], 'items' => []];
        }

        $excluded = $this->excludedKeys($user);
        $providerIds = $user->providerIds();

        // La clave cambia si cambian las semillas, lo excluido o las
        // plataformas: cualquier movimiento del diario/watchlist invalida.
        $signature = md5(json_encode([
            $seeds->map(fn ($r) => [$r->mediaItem->media_type, $r->mediaItem->tmdb_id, (float) $r->rating])->all(),
            array_keys($excluded),
            $providerIds,
            $user->region,
            $limit,
        ]));

        // Vacío puede ser un error momentáneo de TMDB: se guarda 10 minutos y
        // no 6 horas, para no dejar la fila vacía todo ese tiempo.
        $ttl = fn (array $result) => $result['items'] === [] ? now()->addMinutes(10) : now()->addHours(6);

        return Cache::remember("recs_{$user->id}_{$signature}", $ttl, function () use ($seeds, $excluded, $providerIds, $user, $limit) {
            $scores = [];
            $items = [];

            // Las `recommendations` vienen en la ficha de cada semilla. Se
            // piden todas juntas y en paralelo: de a una, con caché frío,
            // Explorar pasaba los 30 s de PHP.
            $seedDetails = $this->tmdb->detailsMany(
                $seeds->map(fn ($review) => [$review->mediaItem->media_type, $review->mediaItem->tmdb_id])
            );

            foreach ($seeds as $review) {
                $media = $review->mediaItem;
                $weight = ((float) $review->rating) / 10;
                $recommendations = $seedDetails["{$media->media_type}_{$media->tmdb_id}"]['recommendations']['results'] ?? [];

                foreach ($recommendations as $candidate) {
                    $type = $candidate['media_type'] ?? $media->media_type;
                    $key = "{$type}_{$candidate['id']}";

                    if (isset($excluded[$key])
                        || ($candidate['vote_average'] ?? 0) < self::MIN_CANDIDATE_RATING
                        || ($candidate['vote_count'] ?? 0) < self::MIN_CANDIDATE_VOTES) {
                        continue;
                    }

                    $candidate['media_type'] = $type;
                    $scores[$key] = ($scores[$key] ?? 0) + $weight;
                    $items[$key] ??= $candidate;
                }
            }

            // La nota de TMDB modula (no manda): un 8.5 sugerido por una sola
            // favorita puede pasar a un 6.8 sugerido por dos.
            foreach ($scores as $key => $weight) {
                $scores[$key] = $weight * (0.5 + (($items[$key]['vote_average'] ?? 0) / 20));
            }

            arsort($scores);
            $pickedKeys = array_slice(array_keys($scores), 0, $limit);

            // Dónde se ve cada elegido sale de su propia ficha: también en una
            // sola tanda, solo para los que se muestran y solo si el usuario
            // eligió plataformas.
            $pickedDetails = $providerIds === [] ? [] : $this->tmdb->detailsMany(
                array_map(fn ($key) => [$items[$key]['media_type'], $items[$key]['id']], $pickedKeys)
            );

            $picked = [];
            foreach ($pickedKeys as $key) {
                $item = $items[$key];
                $item['my_providers'] = $this->myProvidersIn($pickedDetails[$key] ?? [], $providerIds, $user->region);
                $picked[] = $item;
            }

            return [
                'seeds' => $seeds->take(3)->map(fn ($r) => $r->mediaItem->title)->all(),
                'items' => $picked,
            ];
        });
    }

    /**
     * Las mejores notas del diario, como semilla.
     */
    protected function seeds(User $user)
    {
        $rated = Review::where('user_id', $user->id)
            ->whereNotNull('rating')
            ->where('rating', '>=', self::MIN_SEED_RATING)
            ->with('mediaItem')
            ->orderByDesc('rating')
            ->orderByDesc('watched_date')
            ->get()
            ->filter(fn ($r) => $r->mediaItem !== null)
            ->values();

        $count = min(self::MAX_SEEDS, max(self::MIN_SEEDS, (int) ceil($rated->count() * self::SEED_SHARE)));

        return $rated->take($count);
    }

    /**
     * Todo lo que ya está en el diario o la watchlist: no se recomienda lo
     * que ya viste ni lo que ya guardaste.
     *
     * @return array<string, true>  "movie_27205" => true
     */
    protected function excludedKeys(User $user): array
    {
        $keys = [];

        $reviewed = MediaItem::whereHas('reviews', fn ($q) => $q->where('user_id', $user->id))
            ->orWhereHas('watchlists', fn ($q) => $q->where('user_id', $user->id))
            ->get(['tmdb_id', 'media_type']);

        foreach ($reviewed as $item) {
            $keys["{$item->media_type}_{$item->tmdb_id}"] = true;
        }

        return $keys;
    }

    /**
     * Plataformas del usuario donde un título se ve hoy por suscripción,
     * según su ficha de TMDB.
     *
     * @return array<int, array{id:int, name:string, logo_path:?string}>
     */
    protected function myProvidersIn(array $details, array $providerIds, string $region): array
    {
        $regionData = $details['watch/providers']['results'][$region] ?? [];
        $matches = [];

        foreach (MediaItem::STREAMING_KINDS as $kind) {
            foreach ($regionData[$kind] ?? [] as $provider) {
                $id = (int) ($provider['provider_id'] ?? 0);

                if (in_array($id, $providerIds, true) && ! isset($matches[$id])) {
                    $matches[$id] = ['id' => $id, 'name' => $provider['provider_name'] ?? '', 'logo_path' => $provider['logo_path'] ?? null];
                }
            }
        }

        return array_values($matches);
    }
}
