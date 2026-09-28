<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Models\User;
use App\Models\Watchlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Elegir al azar" en la watchlist: sortea un título propio, "Elegir otro" no
 * repite el que estaba, y con el filtro puesto sale de lo que se puede ver hoy.
 */
class WatchlistRandomTest extends TestCase
{
    use RefreshDatabase;

    private function save(User $user, int $tmdbId, string $title, ?array $availability = null): Watchlist
    {
        $media = MediaItem::create([
            'tmdb_id' => $tmdbId,
            'media_type' => 'movie',
            'title' => $title,
            'genres' => [],
            'availability' => $availability,
        ]);

        return Watchlist::create(['user_id' => $user->id, 'media_item_id' => $media->id]);
    }

    public function test_sortea_un_titulo_de_mi_watchlist(): void
    {
        $user = User::factory()->create();
        $this->save($user, 1, 'Alfa');
        $this->save(User::factory()->create(), 2, 'De otra persona');

        $this->actingAs($user)->get(route('watchlist.random'))
            ->assertOk()
            ->assertSee('Alfa')
            ->assertDontSee('De otra persona')
            // Con un solo título no hay otro para sortear.
            ->assertDontSee('Elegir otro');
    }

    public function test_elegir_otro_no_repite_el_que_estaba(): void
    {
        $user = User::factory()->create();
        $alfa = $this->save($user, 1, 'Alfa');
        $this->save($user, 2, 'Beta');

        foreach (range(1, 5) as $intento) {
            $this->actingAs($user)->get(route('watchlist.random', ['excepto' => $alfa->id]))
                ->assertOk()
                ->assertSee('Beta')
                ->assertDontSee('Alfa');
        }
    }

    public function test_con_el_filtro_sale_de_lo_que_puedo_ver_hoy(): void
    {
        $user = User::factory()->create();
        $user->providers()->create(['provider_id' => 8, 'name' => 'Netflix']);
        $snapshot = fn (array $flatrate) => ['AR' => ['synced_at' => now()->toIso8601String(), 'flatrate' => $flatrate, 'rent' => []]];

        $this->save($user, 1, 'Está en Netflix', $snapshot([['id' => 8, 'name' => 'Netflix', 'logo_path' => null]]));
        $this->save($user, 2, 'No está en ningún lado', $snapshot([]));

        foreach (range(1, 5) as $intento) {
            $this->actingAs($user)->get(route('watchlist.random', ['disponible' => 1]))
                ->assertOk()
                ->assertSee('Está en Netflix')
                ->assertDontSee('No está en ningún lado');
        }
    }

    public function test_con_la_watchlist_vacia_avisa(): void
    {
        $this->actingAs(User::factory()->create())->get(route('watchlist.random'))
            ->assertOk()
            ->assertSee('No hay nada para sortear');
    }

    public function test_la_watchlist_ofrece_elegir_al_azar(): void
    {
        $user = User::factory()->create();
        $this->save($user, 1, 'Alfa');

        $this->actingAs($user)->get(route('watchlist.index'))
            ->assertOk()
            ->assertSee('Elegir al azar');
    }
}
