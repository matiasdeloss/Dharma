<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Models\Review;
use App\Models\User;
use App\Models\Watchlist;
use App\Services\Recommender;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * "Para vos": semilla relativa (las mejores notas del usuario), agregación
 * ponderada y exclusión de lo ya visto/guardado.
 *
 * Con los datos mock, la ficha de cualquier título recomienda Inception
 * (27205) y El caballero de la noche (155), y se ve en AR por Amazon Prime
 * Video (119) y Max (1899).
 */
class RecommenderTest extends TestCase
{
    use RefreshDatabase;

    private function rate(User $user, int $tmdbId, string $title, float $rating): MediaItem
    {
        $media = MediaItem::create(['tmdb_id' => $tmdbId, 'media_type' => 'movie', 'title' => $title, 'genres' => []]);
        Review::create(['user_id' => $user->id, 'media_item_id' => $media->id, 'rating' => $rating, 'watched_date' => now()]);

        return $media;
    }

    public function test_sin_notas_no_hay_recomendaciones(): void
    {
        $user = User::factory()->create();

        $this->assertSame(['seeds' => [], 'items' => []], app(Recommender::class)->forUser($user));
    }

    public function test_recomienda_a_partir_de_las_mejores_notas_aunque_sean_bajas(): void
    {
        $user = User::factory()->create();
        // Un usuario exigente: su mejor nota es un 7. Igual tiene favoritas.
        $this->rate($user, 157336, 'Interstellar', 7);
        $this->rate($user, 550, 'El club de la pelea', 6.5);
        $this->rate($user, 680, 'Pulp Fiction', 4); // no es semilla: debajo del piso

        $result = app(Recommender::class)->forUser($user);

        $this->assertSame(['Interstellar', 'El club de la pelea'], $result['seeds']);
        $this->assertSame([155, 27205], array_column($result['items'], 'id'), 'A igual peso gana la mejor valorada en TMDB.');
        $this->assertSame('movie', $result['items'][0]['media_type']);
    }

    public function test_no_recomienda_lo_que_ya_esta_en_el_diario_o_la_watchlist(): void
    {
        $user = User::factory()->create();
        $this->rate($user, 157336, 'Interstellar', 9);

        $inception = MediaItem::create(['tmdb_id' => 27205, 'media_type' => 'movie', 'title' => 'Inception', 'genres' => []]);
        Watchlist::create(['user_id' => $user->id, 'media_item_id' => $inception->id]);

        $result = app(Recommender::class)->forUser($user);

        $this->assertSame([155], array_column($result['items'], 'id'));
    }

    public function test_marca_en_que_plataformas_del_usuario_se_ve(): void
    {
        $user = User::factory()->create();
        $user->providers()->create(['provider_id' => 1899, 'name' => 'Max']);
        $this->rate($user, 157336, 'Interstellar', 8);

        $result = app(Recommender::class)->forUser($user);

        $this->assertSame([1899], array_column($result['items'][0]['my_providers'], 'id'));
    }

    public function test_si_tmdb_falla_la_fila_vacia_no_queda_guardada_seis_horas(): void
    {
        // Este usa la API "de verdad" (clave de prueba + Http::fake), no el mock.
        config(['services.tmdb.api_key' => 'test-key', 'services.tmdb.read_token' => null]);

        Http::fake(['api.themoviedb.org/3/movie/157336*' => Http::sequence()
            ->push(['status_message' => 'Internal error'], 500)
            ->push(['id' => 157336, 'recommendations' => ['results' => [
                ['id' => 155, 'media_type' => 'movie', 'title' => 'El caballero oscuro', 'vote_average' => 8.5, 'vote_count' => 30000],
            ]]])]);

        $user = User::factory()->create();
        $this->rate($user, 157336, 'Interstellar', 9);

        $this->assertSame([], app(Recommender::class)->forUser($user)['items']);

        // Pasado el TTL corto se recalcula, ya con TMDB respondiendo.
        $this->travel(11)->minutes();

        $this->assertSame([155], array_column(app(Recommender::class)->forUser($user)['items'], 'id'));
    }

    public function test_la_fila_aparece_en_explorar_pero_no_en_inicio(): void
    {
        $user = User::factory()->create();
        $this->rate($user, 157336, 'Interstellar', 8);

        $this->actingAs($user)->get(route('explore.index'))
            ->assertOk()
            ->assertSee('Para vos')
            ->assertSee('Porque te gustaron Interstellar');

        // Inicio es tendencias y comunidad; lo personal vive en Explorar.
        $this->actingAs($user)->get(route('home'))
            ->assertOk()
            ->assertDontSee('Porque te gustaron Interstellar');
    }
}
