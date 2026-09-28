<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Models\Review;
use App\Models\User;
use App\Models\Watchlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

        $base = [
            'tmdb_id' => 157336,
            'media_type' => 'movie',
            'title' => 'Interstellar',
            'release_date' => '2014-11-05',
        ];

        // Dos modales, una fila: primero la nota, despues la reseña.
        $this->post(route('reviews.store'), $base + [
            'form' => 'rating',
            'rating' => 9,
            'watched_date' => '2026-08-20',
        ])->assertSessionHas('success');

        $response = $this->post(route('reviews.store'), $base + [
            'form' => 'review',
            'review_text' => 'Una de las mejores películas que he visto jamás.',
            'private_notes' => 'Nota personal: Ver de nuevo con auriculares.',
            'contains_spoilers' => 0,
        ]);

        $response->assertSessionHas('success');

        $this->assertDatabaseHas('media_items', [
            'tmdb_id' => 157336,
            'title' => 'Interstellar',
        ]);

        $this->assertDatabaseHas('reviews', [
            'user_id' => $this->user->id,
            'rating' => 9,
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
        ]);

        $response = $this->get(route('reviews.index'));
        $response->assertStatus(200);
        $response->assertSee('Mi Diario de Cine');
        $response->assertSee('Interstellar');
        $response->assertSee('Nota secreta.');
    }

    public function test_la_card_muestra_mi_nota_en_vez_de_calificar(): void
    {
        $user = User::factory()->create();

        // Sin nota: la card ofrece "Calificar" (solo texto, sin icono).
        $this->actingAs($user)->get(route('home'))
            ->assertSee('Calificar')
            ->assertDontSee('badge-rate-mine')
            ->assertDontSee('bi-star text-success');

        $media = MediaItem::create(['tmdb_id' => 157336, 'media_type' => 'movie', 'title' => 'Interstellar', 'genres' => []]);
        Review::create(['user_id' => $user->id, 'media_item_id' => $media->id, 'rating' => 8.5, 'watched_date' => now()]);

        // Con nota: la card muestra 8.5/10 en su lugar.
        $this->actingAs($user)->get(route('home'))
            ->assertSee('<span class="badge-rate-mine">8.5</span>', false);
    }

    public function test_la_web_oficial_solo_se_enlaza_si_es_http(): void
    {
        // TMDB "de verdad" (clave de prueba + Http::fake): la web oficial es
        // un dato que edita cualquiera en TMDB.
        config(['services.tmdb.api_key' => 'test-key', 'services.tmdb.read_token' => null]);
        Http::fake([
            'api.themoviedb.org/3/movie/101*' => Http::response(['id' => 101, 'title' => 'Trucha', 'overview' => '', 'homepage' => 'javascript:alert(1)']),
            'api.themoviedb.org/3/movie/202*' => Http::response(['id' => 202, 'title' => 'Legal', 'overview' => '', 'homepage' => 'https://example.com/pelicula']),
        ]);

        $this->get(route('media.show', ['type' => 'movie', 'id' => 101]))
            ->assertOk()
            ->assertDontSee('javascript:alert', false);

        $this->get(route('media.show', ['type' => 'movie', 'id' => 202]))
            ->assertOk()
            ->assertSee('href="https://example.com/pelicula"', false);
    }
}
