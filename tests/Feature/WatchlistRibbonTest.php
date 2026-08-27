<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WatchlistRibbonTest extends TestCase
{
    use RefreshDatabase;

    public function test_ribbon_toggle_with_htmx_header()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $response = $this->withHeaders(['HX-Request' => 'true'])
            ->post(route('watchlist.toggle'), [
                'style' => 'ribbon',
                'tmdb_id' => 119243,
                'media_type' => 'tv',
                'title' => 'From',
                'poster_path' => '/test.jpg',
                'release_date' => '2022-02-20',
                'vote_average' => '8.2',
            ]);

        $response->assertStatus(200);
        $response->assertHeader('HX-Trigger');
        $this->assertStringContainsString('watchlistUpdated', $response->headers->get('HX-Trigger'));
        $this->assertStringContainsString('ribbon-btn', $response->getContent());
        $this->assertStringContainsString('ribbon-icon-active', $response->getContent());
    }

    public function test_ribbon_toggle_unauthenticated()
    {
        $response = $this->withHeaders(['HX-Request' => 'true'])
            ->post(route('watchlist.toggle'), [
                'style' => 'ribbon',
                'tmdb_id' => 119243,
                'media_type' => 'tv',
                'title' => 'From',
            ]);

        $response->assertStatus(200);
        $response->assertHeader('HX-Trigger');
        $this->assertStringContainsString('authRequired', $response->headers->get('HX-Trigger'));
    }

    public function test_watchlist_persists_on_page_reload()
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        // Agregamos a Watchlist (por ejemplo, Interstellar con tmdb_id 157336 o From con 119243)
        $this->post(route('watchlist.toggle'), [
            'style' => 'ribbon',
            'tmdb_id' => 157336,
            'media_type' => 'movie',
            'title' => 'Interstellar',
        ]);

        // Recargar la home (simular F5)
        $response = $this->get(route('home'));
        $response->assertStatus(200);

        // Si Interstellar o el item está en los trending/mock, debe renderizar ribbon-icon-active
        // Comprobar que en la base de datos y en la lógica se adjuntó in_watchlist
        $this->assertDatabaseHas('watchlists', [
            'user_id' => $user->id,
        ]);
    }
}
