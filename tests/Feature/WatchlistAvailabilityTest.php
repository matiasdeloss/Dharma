<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Models\User;
use App\Models\Watchlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Puedo verla hoy": cruce entre la disponibilidad del título (snapshot de
 * TMDB por región) y las plataformas del usuario.
 *
 * Con los datos mock, cualquier ficha se ve en AR por suscripción en Amazon
 * Prime Video (119) y Max (1899), y en alquiler en Apple TV (2).
 */
class WatchlistAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function userWith(array $providerIds, string $region = 'AR'): User
    {
        $user = User::factory()->create(['region' => $region]);

        foreach ($providerIds as $id) {
            $user->providers()->create(['provider_id' => $id, 'name' => "Proveedor {$id}"]);
        }

        return $user;
    }

    private function toggle(User $user, int $tmdbId, string $title): void
    {
        $this->actingAs($user)->withHeaders(['HX-Request' => 'true'])->post(route('watchlist.toggle'), [
            'tmdb_id' => $tmdbId,
            'media_type' => 'movie',
            'title' => $title,
        ]);
    }

    public function test_al_agregar_a_la_watchlist_se_guarda_la_disponibilidad_de_la_region(): void
    {
        $user = $this->userWith([119]);
        $this->toggle($user, 27205, 'Inception');

        $item = MediaItem::where('tmdb_id', 27205)->first();
        $snapshot = $item->availabilityIn('AR');

        $this->assertNotNull($snapshot);
        $this->assertSame([119, 1899], array_column($snapshot['flatrate'], 'id'));
        $this->assertSame([2, 3], array_column($snapshot['rent'], 'id'));
        $this->assertNull($item->availabilityIn('ES'));
    }

    public function test_la_card_muestra_solo_las_plataformas_del_usuario_por_suscripcion(): void
    {
        // Tiene Max (1899) y Apple TV (2): Max cuenta, Apple TV es alquiler y no.
        $user = $this->userWith([1899, 2]);
        $this->toggle($user, 27205, 'Inception');

        $item = MediaItem::where('tmdb_id', 27205)->first();

        $this->assertSame([1899], array_column($item->availableOn([1899, 2], 'AR'), 'id'));

        $this->actingAs($user)->get(route('watchlist.index'))
            ->assertOk()
            ->assertSee('Ver en')
            ->assertSee('Puedo ver hoy');
    }

    public function test_el_filtro_deja_solo_lo_disponible(): void
    {
        $user = $this->userWith([119]);
        $this->toggle($user, 27205, 'Inception');
        // El título lo pone TMDB (en el mock, Interstellar para cualquier
        // ficha): la card se reconoce por su link, no por el nombre.
        $disponible = route('media.show', ['type' => 'movie', 'id' => 27205]);

        // Un título sin disponibilidad sincronizada para AR (creado a mano).
        $sinDatos = MediaItem::create(['tmdb_id' => 1, 'media_type' => 'movie', 'title' => 'Sin plataformas', 'genres' => [], 'availability' => ['AR' => ['synced_at' => now()->toIso8601String(), 'flatrate' => [], 'rent' => []]]]);
        Watchlist::create(['user_id' => $user->id, 'media_item_id' => $sinDatos->id]);

        $this->actingAs($user)->get(route('watchlist.index'))
            ->assertOk()
            ->assertSee($disponible)
            ->assertSee('Sin plataformas');

        $this->actingAs($user)->get(route('watchlist.index', ['disponible' => 1]))
            ->assertOk()
            ->assertSee($disponible)
            ->assertDontSee('Sin plataformas');
    }

    public function test_sin_plataformas_elegidas_no_hay_filtro_y_se_invita_a_ajustes(): void
    {
        $user = $this->userWith([]);
        $this->toggle($user, 27205, 'Inception');

        $this->actingAs($user)->get(route('watchlist.index'))
            ->assertOk()
            ->assertDontSee('Solo lo que puedo ver hoy')
            ->assertDontSee('Ver en')
            ->assertSee(route('settings.edit'));
    }
}
