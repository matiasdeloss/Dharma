<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Models\Review;
use App\Models\User;
use App\Models\Watchlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MediaAppTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create([
            'name' => 'Cinéfago Tester',
            'email' => 'tester@example.com',
        ]);
    }

    public function test_home_page_loads_successfully()
    {
        $response = $this->get(route('home'));
        $response->assertStatus(200);
        $response->assertSee('Dharma');
        $response->assertSee('Explorar');
    }

    public function test_live_search_htmx_dropdown_returns_results()
    {
        $response = $this->withHeaders(['HX-Request' => 'true'])
            ->get(route('media.search', ['q' => 'Interstellar', 'dropdown' => '1']));

        $response->assertStatus(200);
        $response->assertSee('Interstellar');
    }

    public function test_media_detail_page_loads()
    {
        $response = $this->get(route('media.show', ['type' => 'movie', 'id' => 157336]));
        $response->assertStatus(200);
        $response->assertSee('Interstellar');
        $response->assertSee('Sinopsis');
        $response->assertSee('Reparto Principal');
        $response->assertSee('Disponible en:');
        $response->assertSee('TMDB');
        $response->assertSee('IMDb');
    }

    public function test_storing_a_review_and_personal_notes()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('reviews.store'), [
            'tmdb_id' => 157336,
            'media_type' => 'movie',
            'title' => 'Interstellar',
            'release_date' => '2014-11-05',
            'rating' => 9.5,
            'review_text' => 'Una de las mejores películas que he visto jamás.',
            'private_notes' => 'Nota personal: Ver de nuevo con auriculares.',
            'watched_date' => '2026-08-20',
            'status' => 'watched',
            'is_rewatch' => 0,
            'contains_spoilers' => 0,
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('media_items', [
            'tmdb_id' => 157336,
            'title' => 'Interstellar',
        ]);

        $this->assertDatabaseHas('reviews', [
            'user_id' => $this->user->id,
            'rating' => 9.5,
            'review_text' => 'Una de las mejores películas que he visto jamás.',
            'private_notes' => 'Nota personal: Ver de nuevo con auriculares.',
        ]);
    }

    public function test_watchlist_toggle()
    {
        $this->actingAs($this->user);

        $response = $this->post(route('watchlist.toggle'), [
            'tmdb_id' => 27205,
            'media_type' => 'movie',
            'title' => 'Inception',
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('watchlists', [
            'user_id' => $this->user->id,
        ]);

        // Toggle again to remove
        $response2 = $this->post(route('watchlist.toggle'), [
            'tmdb_id' => 27205,
            'media_type' => 'movie',
            'title' => 'Inception',
        ]);

        $response2->assertSessionHas('success');
        $this->assertDatabaseCount('watchlists', 0);
    }

    public function test_diary_index_page_displays_entries()
    {
        $this->actingAs($this->user);

        $media = MediaItem::create([
            'tmdb_id' => 157336,
            'media_type' => 'movie',
            'title' => 'Interstellar',
            'release_date' => '2014-11-05',
        ]);

        Review::create([
            'user_id' => $this->user->id,
            'media_item_id' => $media->id,
            'rating' => 10.0,
            'review_text' => 'Excelente película.',
            'private_notes' => 'Nota secreta.',
            'watched_date' => '2026-08-20',
            'status' => 'watched',
        ]);

        $response = $this->get(route('reviews.index'));
        $response->assertStatus(200);
        $response->assertSee('Mi Diario de Cine');
        $response->assertSee('Interstellar');
        $response->assertSee('Nota secreta.');
    }
}
