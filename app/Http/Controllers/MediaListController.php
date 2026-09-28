<?php

namespace App\Http\Controllers;

use App\Models\MediaItem;
use App\Models\MediaList;
use App\Models\MediaListItem;
use App\Services\MediaCatalog;
use App\Services\TmdbService;
use App\Traits\AttachesUserStatus;
use App\Traits\HasLocalBackdrop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Listas del usuario: colecciones propias ("Mi top de Nolan", "Maratón de
 * Halloween"), numeradas o no, en el orden en que el usuario las arrastre.
 *
 * Son privadas: solo las ve su dueño. Los títulos se suman desde el modal
 * "Agregar a lista" de la ficha y del diario (`picker`), que igual que los
 * modales de calificar vive fuera del grupo `auth` para devolverle al
 * invitado el modal de iniciar sesión.
 */
class MediaListController extends Controller
{
    use AttachesUserStatus;
    use HasLocalBackdrop;

    public function __construct(protected TmdbService $tmdb, protected MediaCatalog $catalog)
    {
    }

    /**
     * Mis listas, la que se tocó último primero.
     */
    public function index()
    {
        $userId = Auth::id();

        $lists = MediaList::where('user_id', $userId)
            ->withCount('items')
            // Los primeros cinco pósters de cada una, para el mazo de la card.
            ->with(['items' => fn ($query) => $query->orderBy('position')->limit(5)->with('mediaItem')])
            ->latest('updated_at')
            ->get();

        return view('lists.index', [
            'lists' => $lists,
            'totalTitles' => $lists->sum('items_count'),
            'headerBackdrop' => $this->backdropFrom(
                MediaItem::whereHas('listItems.mediaList', fn ($query) => $query->where('user_id', $userId))
            ),
        ]);
    }

    /**
     * Una lista con sus títulos en orden.
     */
    public function show(MediaList $list)
    {
        $this->authorizeOwner($list);

        $items = $this->cardsFor($list);

        return view('lists.show', [
            'list' => $list,
            'items' => $items,
            'counts' => $this->countsFor($items),
            'headerBackdrop' => $this->backdropFrom(
                MediaItem::whereHas('listItems', fn ($query) => $query->where('media_list_id', $list->id))
            ),
        ]);
    }

    /**
     * Modal para crear una lista (desde Mis listas).
     */
    public function create()
    {
        return view('lists.partials.form-modal', ['list' => null, 'still' => $this->modalStill(null)]);
    }

    /**
     * Modal para editar nombre, descripción y si va numerada.
     */
    public function edit(MediaList $list)
    {
        $this->authorizeOwner($list);

        return view('lists.partials.form-modal', ['list' => $list, 'still' => $this->modalStill($list)]);
    }

    /**
     * Crea una lista. Desde Mis listas lleva a la lista nueva; desde el modal
     * "Agregar a lista" (viene con `tmdb_id`) ya sale con ese título adentro y
     * el modal se vuelve a pintar con la lista nueva marcada.
     */
    public function store(Request $request)
    {
        $validated = $request->validate($this->listRules() + [
            'tmdb_id' => 'nullable|integer',
            'media_type' => 'required_with:tmdb_id|in:movie,tv',
        ] + $this->mediaFallbackRules(), $this->messages());

        $list = MediaList::create([
            'user_id' => Auth::id(),
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_ranked' => $request->boolean('is_ranked'),
        ]);

        if (! empty($validated['tmdb_id'])) {
            $this->addTitle($list, $this->mediaItemFrom($validated));

            return response(view('lists.partials.picker-modal', $this->pickerContext($validated['media_type'], (int) $validated['tmdb_id'])))
                ->withHeaders($this->toast("Creaste «{$list->name}» con este título."));
        }

        session()->flash('success', "Lista «{$list->name}» creada. Sumale títulos desde la ficha de cualquier película o serie.");

        return $request->header('HX-Request')
            ? response('', 200, ['HX-Redirect' => route('lists.show', $list)])
            : redirect()->route('lists.show', $list);
    }

    public function update(Request $request, MediaList $list)
    {
        $this->authorizeOwner($list);

        $validated = $request->validate($this->listRules(), $this->messages());

        $list->update([
            'name' => $validated['name'],
            'description' => $validated['description'] ?? null,
            'is_ranked' => $request->boolean('is_ranked'),
        ]);

        session()->flash('success', 'Lista actualizada.');

        // Recargar y no reconstruir por fragmentos: el nombre, la descripción
        // y los puestos numerados cambian a la vez.
        return $request->header('HX-Request')
            ? response('', 200, ['HX-Refresh' => 'true'])
            : redirect()->route('lists.show', $list);
    }

    public function destroy(Request $request, MediaList $list)
    {
        $this->authorizeOwner($list);

        $name = $list->name;
        $list->delete();

        session()->flash('info', "Se eliminó la lista «{$name}».");

        return $request->header('HX-Request')
            ? response('', 200, ['HX-Redirect' => route('lists.index')])
            : redirect()->route('lists.index');
    }

    /**
     * Modal "Agregar a lista": las listas del usuario, cada una con su botón
     * para sumar o sacar este título.
     */
    public function picker(string $type, int $tmdbId)
    {
        if (! Auth::check()) {
            return response(view('reviews.partials.auth-required-modal'))->withHeaders([
                'HX-Trigger' => json_encode([
                    'authRequired' => ['message' => 'Inicia sesión para armar tus listas.'],
                ]),
            ]);
        }

        return view('lists.partials.picker-modal', $this->pickerContext($type, $tmdbId));
    }

    /**
     * Suma o saca un título de la lista (el botón de cada fila del modal).
     */
    public function toggleItem(Request $request, MediaList $list)
    {
        $this->authorizeOwner($list);

        $validated = $request->validate([
            'tmdb_id' => 'required|integer',
            'media_type' => 'required|in:movie,tv',
        ] + $this->mediaFallbackRules());

        $mediaItem = $this->mediaItemFrom($validated);
        $entry = $list->items()->where('media_item_id', $mediaItem->id)->first();

        if ($entry) {
            $this->removeEntry($list, $entry);
        } else {
            $this->addTitle($list, $mediaItem);
        }

        $list->loadCount('items');

        return response(view('lists.partials.picker-row', [
            'list' => $list,
            'inList' => ! $entry,
            'media' => $this->mediaFieldsFrom($validated),
            'type' => $validated['media_type'],
        ]))->withHeaders($entry
            ? $this->toast("Quitada de «{$list->name}».", 'info')
            : $this->toast("Agregada a «{$list->name}»."));
    }

    /**
     * Quita un título desde la página de la lista. Devuelve la grilla entera
     * (los puestos de abajo cambian) y el contador del encabezado.
     */
    public function removeItem(MediaList $list, MediaListItem $item)
    {
        $this->authorizeOwner($list);
        abort_unless($item->media_list_id === $list->id, 404);

        $title = $item->mediaItem?->title;
        $this->removeEntry($list, $item);

        $items = $this->cardsFor($list);

        $html = view('lists.partials.items-grid', ['list' => $list, 'items' => $items])->render()
            .view('lists.partials.list-stats', ['counts' => $this->countsFor($items), 'oob' => true])->render();

        return response($html)->withHeaders(
            $this->toast($title ? "Quitaste «{$title}» de la lista." : 'Quitaste el título de la lista.', 'info')
        );
    }

    /**
     * Guarda el orden después de arrastrar (`items[]` con los ids de
     * media_list_items en el orden nuevo).
     */
    public function reorder(Request $request, MediaList $list)
    {
        $this->authorizeOwner($list);

        $validated = $request->validate([
            'items' => 'required|array',
            'items.*' => 'integer',
        ]);

        $ids = array_map('intval', $validated['items']);
        $current = $list->items()->pluck('id')->all();

        // Tiene que ser exactamente el mismo conjunto: ni ids de otra lista ni
        // faltantes (si la lista cambió en otra pestaña, mejor no adivinar).
        if (count($ids) !== count(array_unique($ids)) || array_diff($ids, $current) !== [] || array_diff($current, $ids) !== []) {
            throw ValidationException::withMessages([
                'items' => 'La lista cambió mientras la ordenabas. Recargá la página y probá de nuevo.',
            ]);
        }

        DB::transaction(function () use ($ids) {
            foreach ($ids as $index => $id) {
                MediaListItem::whereKey($id)->update(['position' => $index + 1]);
            }
        });

        $list->touch();

        return response()->noContent();
    }

    // =====================================================================
    // Internos
    // =====================================================================

    protected function authorizeOwner(MediaList $list): void
    {
        if ($list->user_id !== Auth::id()) {
            abort(403);
        }
    }

    /**
     * Póster para la columna de la derecha del modal de crear/editar: uno de
     * la lista, o de lo que el usuario vio si todavía no existe.
     */
    protected function modalStill(?MediaList $list): string
    {
        $query = $list
            ? MediaItem::whereHas('listItems', fn ($q) => $q->where('media_list_id', $list->id))
            : MediaItem::whereHas('reviews', fn ($q) => $q->where('user_id', Auth::id()));

        return $query->whereNotNull('poster_path')->inRandomOrder()->first()?->poster_url
            ?? $this->getLocalBackdrop()['url'];
    }

    protected function listRules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'description' => 'nullable|string|max:1000',
            'is_ranked' => 'nullable|boolean',
        ];
    }

    /**
     * Lo que manda el form sobre el título: solo de respaldo si TMDB no
     * responde (ver MediaCatalog::firstOrCreate).
     */
    protected function mediaFallbackRules(): array
    {
        return [
            'title' => 'nullable|string|max:255',
            'poster_path' => 'nullable|string|max:255',
            'release_date' => 'nullable|date',
            'vote_average' => 'nullable|numeric',
        ];
    }

    protected function messages(): array
    {
        return [
            'name.required' => 'Ponele un nombre a la lista.',
            'name.max' => 'El nombre puede tener hasta 100 caracteres.',
            'description.max' => 'La descripción puede tener hasta 1000 caracteres.',
        ];
    }

    /**
     * Lo que necesita el modal "Agregar a lista": la ficha del título, las
     * listas del usuario y en cuáles ya está.
     */
    protected function pickerContext(string $type, int $tmdbId): array
    {
        $details = $type === 'tv'
            ? $this->tmdb->getTvDetails($tmdbId)
            : $this->tmdb->getMovieDetails($tmdbId);

        $lists = MediaList::where('user_id', Auth::id())
            ->withCount('items')
            ->latest('updated_at')
            ->get();

        $mediaItemId = MediaItem::where('tmdb_id', $tmdbId)->where('media_type', $type)->value('id');

        return [
            'media' => ['id' => $tmdbId] + $details,
            'type' => $type,
            'lists' => $lists,
            'inLists' => $mediaItemId
                ? MediaListItem::where('media_item_id', $mediaItemId)->whereIn('media_list_id', $lists->pluck('id'))->pluck('media_list_id')->all()
                : [],
        ];
    }

    /**
     * Los títulos de la lista con el formato de TMDB que espera
     * media/partials/movie-card, en orden y marcados con lo que el usuario ya
     * hizo con cada uno (watchlist, nota).
     */
    protected function cardsFor(MediaList $list): array
    {
        $cards = $list->items()
            ->orderBy('position')
            ->with('mediaItem')
            ->get()
            ->filter(fn (MediaListItem $entry) => $entry->mediaItem !== null)
            ->map(fn (MediaListItem $entry) => [
                'entry_id' => $entry->id,
                'id' => $entry->mediaItem->tmdb_id,
                'media_type' => $entry->mediaItem->media_type,
                'title' => $entry->mediaItem->title,
                'poster_path' => $entry->mediaItem->poster_path,
                'release_date' => $entry->mediaItem->release_date?->toDateString(),
                'vote_average' => $entry->mediaItem->vote_average,
            ])
            ->values()
            ->all();

        return $this->attachUserStatus($cards, Auth::id());
    }

    /**
     * Composición de la lista para el encabezado.
     *
     * @return array{total: int, movies: int, series: int}
     */
    protected function countsFor(array $items): array
    {
        $types = array_count_values(array_column($items, 'media_type'));

        return [
            'total' => count($items),
            'movies' => $types['movie'] ?? 0,
            'series' => $types['tv'] ?? 0,
        ];
    }

    protected function mediaItemFrom(array $validated): MediaItem
    {
        return $this->catalog->firstOrCreate($validated['media_type'], (int) $validated['tmdb_id'], [
            'title' => $validated['title'] ?? null,
            'poster_path' => $validated['poster_path'] ?? null,
            'release_date' => $validated['release_date'] ?? null,
            'vote_average' => $validated['vote_average'] ?? null,
        ]);
    }

    /**
     * Los campos del título en el formato de TMDB, para volver a pintar los
     * hidden de la fila del modal.
     */
    protected function mediaFieldsFrom(array $validated): array
    {
        return [
            'id' => (int) $validated['tmdb_id'],
            'title' => $validated['title'] ?? null,
            'poster_path' => $validated['poster_path'] ?? null,
            'release_date' => $validated['release_date'] ?? null,
            'vote_average' => $validated['vote_average'] ?? null,
        ];
    }

    /**
     * Al final de la lista. `firstOrCreate` por si llega dos veces el mismo
     * pedido (doble clic): el índice único no deja duplicarlo.
     */
    protected function addTitle(MediaList $list, MediaItem $mediaItem): void
    {
        $list->items()->firstOrCreate(
            ['media_item_id' => $mediaItem->id],
            ['position' => (int) $list->items()->max('position') + 1],
        );

        $list->touch();
    }

    /**
     * Sin huecos: los que estaban debajo suben un puesto.
     */
    protected function removeEntry(MediaList $list, MediaListItem $entry): void
    {
        DB::transaction(function () use ($list, $entry) {
            $entry->delete();
            $list->items()->where('position', '>', $entry->position)->decrement('position');
        });

        $list->touch();
    }

    protected function toast(string $message, string $type = 'success'): array
    {
        return [
            'HX-Trigger' => json_encode([
                'listUpdated' => ['title' => 'Listas', 'message' => $message, 'type' => $type],
            ]),
        ];
    }
}
