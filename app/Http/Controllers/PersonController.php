<?php

namespace App\Http\Controllers;

use App\Services\TmdbService;
use App\Traits\AttachesUserStatus;
use Illuminate\Support\Facades\Auth;

/**
 * Panel lateral de una persona del reparto/equipo: quién es y en qué más
 * estuvo. Es un fragmento HTMX que se carga dentro del offcanvas
 * `#personPanel` del layout; no hay página propia.
 */
class PersonController extends Controller
{
    use AttachesUserStatus;

    /** Cuántos títulos se listan (los más populares, sin repetir). */
    private const MAX_CREDITS = 30;

    /**
     * Géneros de TV que no son "actuaciones": talk shows, noticias y reality.
     * Sin este filtro, la lista de cualquier actor famoso arranca con Jimmy
     * Fallon y Stephen Colbert porque esos programas tienen popularidad alta.
     */
    private const NOT_A_ROLE_GENRES = [10767, 10763, 10764];

    public function __construct(protected TmdbService $tmdb)
    {
    }

    public function show(int $id)
    {
        $person = $this->tmdb->getPerson($id);

        if (empty($person)) {
            return response(view('media.partials.person-panel-empty'), 404);
        }

        $isActor = ($person['known_for_department'] ?? 'Acting') === 'Acting';

        // Actores: sus papeles. Directores, guionistas, etc.: sus créditos de
        // equipo, agrupando los trabajos de un mismo título ("Director, Guion").
        $source = $isActor
            ? ($person['combined_credits']['cast'] ?? [])
            : ($person['combined_credits']['crew'] ?? []);

        $byTitle = [];
        foreach ($source as $credit) {
            $type = $credit['media_type'] ?? 'movie';
            if (! in_array($type, ['movie', 'tv'], true) || empty($credit['id'])) {
                continue;
            }

            if (array_intersect($credit['genre_ids'] ?? [], self::NOT_A_ROLE_GENRES) !== []) {
                continue;
            }

            $key = "{$type}_{$credit['id']}";
            $role = $isActor ? ($credit['character'] ?? null) : ($credit['job'] ?? null);

            // Apariciones como él/ella mismo (premios, especiales) tampoco.
            if ($isActor && preg_match('/^(self|himself|herself|él mismo|ella misma)/i', (string) $role)) {
                continue;
            }

            if (isset($byTitle[$key])) {
                if ($role && ! in_array($role, $byTitle[$key]['roles'], true)) {
                    $byTitle[$key]['roles'][] = $role;
                }
                continue;
            }

            $byTitle[$key] = $credit + ['roles' => array_filter([$role])];
        }

        $credits = collect($byTitle)
            // Popularidad primero: lo conocido arriba, sin fecha o sin poster abajo.
            ->sortByDesc(fn ($c) => [(float) ($c['popularity'] ?? 0), ! empty($c['poster_path'])])
            ->take(self::MAX_CREDITS)
            ->values()
            ->all();

        return view('media.partials.person-panel', [
            'person' => $person,
            'isActor' => $isActor,
            'credits' => $this->attachUserStatus($credits, Auth::id()),
            'totalCredits' => count($byTitle),
        ]);
    }
}
