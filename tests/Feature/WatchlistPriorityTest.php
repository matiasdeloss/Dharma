<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Models\User;
use App\Models\Watchlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Prioridad y nota de la watchlist.
 *
 * Las columnas `priority` y `notes` existían desde la primera migración, las
 * poblaba el seeder y no tenían ninguna interfaz ni orden que las usara.
 */
class WatchlistPriorityTest extends TestCase
{
    use RefreshDatabase;

    private function itemFor(User $user, string $priority = 'medium', string $title = 'Título'): Watchlist
    {
        $media = MediaItem::create([
            'tmdb_id' => random_int(1, 999999),
            'media_type' => 'movie',
            'title' => $title,
        ]);

        return Watchlist::create([
            'user_id' => $user->id,
            'media_item_id' => $media->id,
            'priority' => $priority,
        ]);
    }

    public function test_guardar_prioridad_y_nota_persiste_y_devuelve_el_fragmento(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $item = $this->itemFor($user);

        $response = $this->withHeaders(['HX-Request' => 'true'])
            ->patch(route('watchlist.update', $item), [
                'priority' => 'high',
                'notes' => 'Me la recomendó Sofía.',
            ]);

        $response->assertStatus(200);
        $response->assertHeader('HX-Trigger');
        $response->assertSee('watchlist-meta-'.$item->id, false);
        $response->assertSee('Alta', false);

        $this->assertDatabaseHas('watchlists', [
            'id' => $item->id,
            'priority' => 'high',
            'notes' => 'Me la recomendó Sofía.',
        ]);
    }

    public function test_no_se_puede_editar_un_item_de_otro_usuario(): void
    {
        $owner = User::factory()->create();
        $item = $this->itemFor($owner, 'low');

        $this->actingAs(User::factory()->create())
            ->patch(route('watchlist.update', $item), ['priority' => 'high'])
            ->assertForbidden();

        $this->assertDatabaseHas('watchlists', ['id' => $item->id, 'priority' => 'low']);
    }

    public function test_una_prioridad_invalida_se_rechaza(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $item = $this->itemFor($user);

        $this->patch(route('watchlist.update', $item), ['priority' => 'urgentisimo'])
            ->assertSessionHasErrors('priority');
    }

    public function test_la_watchlist_lista_primero_lo_de_prioridad_alta(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->itemFor($user, 'low', 'La de menos urgencia');
        $this->itemFor($user, 'high', 'La urgente');
        $this->itemFor($user, 'medium', 'La del medio');

        $content = $this->get(route('watchlist.index'))->assertOk()->getContent();

        $this->assertLessThan(
            strpos($content, 'La del medio'),
            strpos($content, 'La urgente'),
            'La de prioridad alta debería listarse antes que la media.'
        );
        $this->assertLessThan(
            strpos($content, 'La de menos urgencia'),
            strpos($content, 'La del medio'),
            'La media debería listarse antes que la baja.'
        );
    }
}
