<?php

namespace App\Http\Controllers;

use App\Models\MediaItem;
use App\Models\Review;
use App\Traits\HasLocalBackdrop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * "Tu diario en números": patrones del propio diario (notas, meses, géneros,
 * décadas). Todo se agrega en memoria con Collections: el diario de una
 * persona son decenas o cientos de filas, y así no hay SQL de fechas atado
 * al driver (SQLite en tests, MySQL en producción).
 */
class DiaryStatsController extends Controller
{
    use HasLocalBackdrop;

    public function index(Request $request)
    {
        $userId = Auth::id();

        $years = Review::where('user_id', $userId)
            ->whereNotNull('watched_date')
            ->pluck('watched_date')
            ->map(fn ($d) => $d->format('Y'))
            ->unique()
            ->sortDesc()
            ->values();

        // Solo se acepta un año que exista en el diario; cualquier otro = todo.
        $year = (int) $request->input('year') ?: null;
        if ($year && ! $years->contains((string) $year)) {
            $year = null;
        }

        $entries = Review::where('user_id', $userId)
            ->when($year, fn ($q) => $q->whereYear('watched_date', $year))
            ->with('mediaItem:id,tmdb_id,media_type,genres,release_date,title')
            ->get()
            ->filter(fn ($e) => $e->mediaItem !== null);

        $rated = $entries->whereNotNull('rating');

        $totals = [
            'logged' => $entries->count(),
            'avg_rating' => round($rated->avg('rating') ?? 0, 1),
            'movies' => $entries->filter(fn ($e) => $e->mediaItem->media_type === 'movie')->count(),
            'tv' => $entries->filter(fn ($e) => $e->mediaItem->media_type === 'tv')->count(),
        ];

        // Histograma 1..10 (la nota se redondea al entero; 7.5 cuenta como 8).
        $histogram = array_fill_keys(range(1, 10), 0);
        foreach ($rated as $e) {
            $bucket = max(1, min(10, (int) round($e->rating)));
            $histogram[$bucket]++;
        }

        // Actividad por mes: los últimos 12 meses (o los 12 del año elegido),
        // con los meses vacíos en cero para que la serie no tenga huecos.
        $start = $year ? now()->setDate($year, 1, 1)->startOfMonth() : now()->subMonths(11)->startOfMonth();
        $byMonth = [];
        for ($i = 0; $i < 12; $i++) {
            $byMonth[$start->copy()->addMonths($i)->format('Y-m')] = 0;
        }
        foreach ($entries->whereNotNull('watched_date') as $e) {
            $key = $e->watched_date->format('Y-m');
            if (array_key_exists($key, $byMonth)) {
                $byMonth[$key]++;
            }
        }

        $byGenre = $entries
            ->flatMap(fn ($e) => collect($e->mediaItem->genres ?? [])->map(fn ($g) => is_array($g) ? ($g['name'] ?? null) : $g))
            ->filter()
            ->countBy()
            ->sortDesc()
            ->take(10);

        $byDecade = $entries
            ->filter(fn ($e) => $e->mediaItem->release_date)
            ->groupBy(fn ($e) => (int) floor((int) $e->mediaItem->release_date->format('Y') / 10) * 10)
            ->map->count()
            ->sortKeys();

        // Lo mejor calificado del período, para cerrar con nombres y no solo barras.
        $top = $rated->sortByDesc('rating')->take(5)->values();

        return view('reviews.stats', [
            'totals' => $totals,
            'histogram' => $histogram,
            'byMonth' => $byMonth,
            'byGenre' => $byGenre,
            'byDecade' => $byDecade,
            'top' => $top,
            'years' => $years,
            'year' => $year,
            'genresMissing' => MediaItem::whereNull('genres')
                ->whereHas('reviews', fn ($q) => $q->where('user_id', $userId))
                ->exists(),
            'headerBackdrop' => $this->backdropFrom(
                MediaItem::whereHas('reviews', fn ($q) => $q->where('user_id', $userId))
            ),
        ]);
    }
}
