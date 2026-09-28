<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Panel lateral de una persona del reparto (/personas/{id}, fragmento HTMX).
 */
class PersonPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_la_ficha_ofrece_abrir_el_panel_desde_el_reparto(): void
    {
        $this->get(route('media.show', ['type' => 'movie', 'id' => 157336]))
            ->assertOk()
            ->assertSee('hx-get="'.route('person.show', 10297).'"', false)
            ->assertSee('id="personPanel"', false);
    }

    public function test_el_panel_lista_sus_otros_titulos_mas_populares_primero(): void
    {
        $html = $this->get(route('person.show', 10297))
            ->assertOk()
            ->assertSee('Matthew McConaughey')
            ->assertSee('También actúa en')
            ->assertSee('Rust Cohle')
            ->getContent();

        $this->assertLessThan(strpos($html, 'True Detective'), strpos($html, 'Interstellar'));
    }

    public function test_marca_lo_que_ya_califique(): void
    {
        $user = User::factory()->create();
        $media = MediaItem::create(['tmdb_id' => 157336, 'media_type' => 'movie', 'title' => 'Interstellar', 'genres' => []]);
        Review::create(['user_id' => $user->id, 'media_item_id' => $media->id, 'rating' => 9, 'watched_date' => now()]);

        $this->actingAs($user)->get(route('person.show', 10297))
            ->assertOk()
            ->assertSee('person-credit-mine', false)
            ->assertSee('9.0');
    }
}
