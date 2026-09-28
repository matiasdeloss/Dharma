<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Models\User;
use App\Models\Watchlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Nota personal por ítem de la watchlist. (La prioridad alta/media/baja que
 * la acompañaba se eliminó: la lista se ordena por fecha de agregado.)
 */
class WatchlistNoteTest extends TestCase
{
    use RefreshDatabase;

    private function itemFor(User $user, ?string $notes = null): Watchlist
    {
        $media = MediaItem::create([
            'tmdb_id' => random_int(1, 999999),
            'media_type' => 'movie',
            'title' => 'Título',
        ]);

        return Watchlist::create([
            'user_id' => $user->id,
            'media_item_id' => $media->id,
            'notes' => $notes,
        ]);
    }

    public function test_guardar_la_nota_persiste_y_devuelve_el_fragmento(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $item = $this->itemFor($user);

        $response = $this->withHeaders(['HX-Request' => 'true'])
            ->patch(route('watchlist.update', $item), [
                'notes' => 'Me la recomendó Sofía.',
            ]);

        $response->assertStatus(200);
        $response->assertHeader('HX-Trigger');
        $response->assertSee('watchlist-meta-'.$item->id, false);
        $response->assertSee('Me la recomendó Sofía.', false);

        $this->assertDatabaseHas('watchlists', [
            'id' => $item->id,
            'notes' => 'Me la recomendó Sofía.',
        ]);
    }

    public function test_no_se_puede_editar_un_item_de_otro_usuario(): void
    {
        $owner = User::factory()->create();
        $item = $this->itemFor($owner, 'Mía');

        $this->actingAs(User::factory()->create())
            ->patch(route('watchlist.update', $item), ['notes' => 'Ajena'])
            ->assertForbidden();

        $this->assertDatabaseHas('watchlists', ['id' => $item->id, 'notes' => 'Mía']);
    }

    public function test_la_watchlist_no_ofrece_prioridades(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $this->itemFor($user);

        $this->get(route('watchlist.index'))
            ->assertOk()
            ->assertDontSee('prioridad')
            ->assertDontSee('Prioridad');
    }
}
