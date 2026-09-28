<?php

namespace App\Http\Controllers;

use App\Services\Discovery;
use App\Services\Recommender;
use App\Services\TmdbService;
use App\Traits\AttachesUserStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Explorar: "¿qué veo?".
 *
 * Sin filtros muestra filas curadas por plataforma (las del usuario primero,
 * después las grandes). Con algún filtro (género, año, plataforma, orden)
 * pasa a una grilla paginada sobre TMDB discover.
 */
class ExploreController extends Controller
{
    use AttachesUserStatus;

    /** Cuántas filas por plataforma como máximo en el modo curado. */
    private const MAX_PLATFORM_ROWS = 6;

    public function __construct(protected TmdbService $tmdb, protected Discovery $discovery, protected Recommender $recommender)
    {
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $userId = $user?->id;
        $region = $user?->region ?? 'AR';
        $myProviders = $user?->providerIds() ?? [];

        $type = $request->input('tipo') === 'tv' ? 'tv' : 'movie';
        $genre = (int) $request->input('genero') ?: null;
        $year = (int) $request->input('anio') ?: null;
        $sort = in_array($request->input('orden'), Discovery::SORTS, true) ? $request->input('orden') : 'popular';
        $platform = $request->input('plataforma'); // id numérico, 'mias' o vacío
        $page = max(1, (int) $request->input('page', 1));

        // Catálogo de plataformas de la región, para nombres/logos y para el
        // select. Se indexa por id.
        $providers = collect($this->tmdb->getWatchProviders($region))->keyBy('provider_id');

        $providerFilter = match (true) {
            $platform === 'mias' && $myProviders !== [] => $myProviders,
            is_numeric($platform) && $providers->has((int) $platform) => [(int) $platform],
            default => [],
        };

        $filtering = $genre || $year || $providerFilter !== [] || $sort !== 'popular';

        $shared = [
            'type' => $type,
            'genre' => $genre,
            'year' => $year,
            'sort' => $sort,
            'platform' => $providerFilter === [] ? null : $platform,
            'genres' => $this->tmdb->getGenres($type),
            'years' => range((int) date('Y'), 1950),
            'providerOptions' => $this->providerOptions($providers, $myProviders),
            'hasMyProviders' => $myProviders !== [],
            'filtering' => $filtering,
        ];

        if ($filtering) {
            $response = $this->discovery->browse($type, [
                'genre' => $genre,
                'year' => $year,
                'providers' => $providerFilter,
                'sort' => $sort,
            ], $region, $page);

            return view('explore.index', $shared + [
                'results' => $this->attachUserStatus($response['results'] ?? [], $userId, $type),
                'page' => $page,
                'totalPages' => $response['total_pages'] ?? 1,
                'totalResults' => $response['total_results'] ?? 0,
            ]);
        }

        // "Para vos" solo en el modo curado y con sesion: sale del diario.
        $forYou = $user ? $this->recommender->forUser($user) : ['seeds' => [], 'items' => []];
        $forYou['items'] = $this->attachUserStatus($forYou['items'], $userId);

        return view('explore.index', $shared + [
            'forYou' => $forYou,
            'sections' => $this->curatedSections($type, $region, $myProviders, $providers, $userId),
        ]);
    }

    /**
     * Filas del modo curado: "en tus plataformas" (si eligió) y "lo mejor de
     * X" por plataforma, las suyas primero y después las grandes.
     *
     * @return array<int, array{key:string, title:string, subtitle:string, items:array, logo:?string, more:?string}>
     */
    protected function curatedSections(string $type, string $region, array $myProviders, $providers, ?int $userId): array
    {
        $sections = [];

        if ($myProviders !== []) {
            $sections[] = [
                'key' => 'best-mine',
                'title' => 'Lo mejor en tus plataformas',
                'subtitle' => 'Lo mejor valorado que podés ver hoy con lo que tenés',
                'items' => $this->discovery->bestOn($type, $myProviders, $region)['results'] ?? [],
                'logo' => null,
                'more' => route('explore.index', ['tipo' => $type, 'plataforma' => 'mias', 'orden' => 'valoradas']),
            ];
            $sections[] = [
                'key' => 'new-mine',
                'title' => 'Lo nuevo en tus plataformas',
                'subtitle' => 'Estrenos recientes disponibles en tus servicios',
                'items' => $this->discovery->newOn($type, $myProviders, $region)['results'] ?? [],
                'logo' => null,
                'more' => route('explore.index', ['tipo' => $type, 'plataforma' => 'mias', 'orden' => 'recientes']),
            ];
        }

        // Las del usuario primero, después las grandes que no tenga.
        $platformIds = array_values(array_unique(array_merge($myProviders, array_keys(Discovery::BIG_PLATFORMS))));

        foreach (array_slice($platformIds, 0, self::MAX_PLATFORM_ROWS) as $id) {
            $name = $providers[$id]['provider_name'] ?? Discovery::BIG_PLATFORMS[$id] ?? "Plataforma {$id}";
            $logo = $providers[$id]['logo_path'] ?? null;

            $items = $this->discovery->bestOn($type, [$id], $region)['results'] ?? [];

            // Una plataforma sin catálogo en la región no merece una fila vacía.
            if ($items === []) {
                continue;
            }

            $sections[] = [
                'key' => "best-{$id}",
                'title' => "Lo mejor de {$name}",
                'subtitle' => in_array($id, $myProviders, true) ? 'Tu plataforma' : null,
                'items' => $items,
                'logo' => $logo ? $this->tmdb->imageUrl($logo, 'w92') : null,
                'more' => route('explore.index', ['tipo' => $type, 'plataforma' => $id, 'orden' => 'valoradas']),
            ];
        }

        foreach ($sections as &$section) {
            $section['items'] = $this->attachUserStatus($section['items'], $userId, $type);
        }

        return $sections;
    }

    /**
     * Opciones del select de plataforma: "Mis plataformas", las del usuario y
     * después las grandes; nombres reales de TMDB cuando los hay.
     *
     * @return array<string|int, string>
     */
    protected function providerOptions($providers, array $myProviders): array
    {
        $options = [];

        if ($myProviders !== []) {
            $options['mias'] = 'Mis plataformas';
        }

        foreach (array_unique(array_merge($myProviders, array_keys(Discovery::BIG_PLATFORMS))) as $id) {
            $options[$id] = $providers[$id]['provider_name'] ?? Discovery::BIG_PLATFORMS[$id] ?? "Plataforma {$id}";
        }

        return $options;
    }
}
