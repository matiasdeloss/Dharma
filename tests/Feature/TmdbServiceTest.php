<?php

namespace Tests\Feature;

use App\Services\TmdbService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Caché de TMDB con la API "de verdad" (clave de prueba + Http::fake): que los
 * errores no queden guardados y que las fichas pedidas en tanda compartan la
 * caché con las pedidas de a una.
 */
class TmdbServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['services.tmdb.api_key' => 'test-key', 'services.tmdb.read_token' => null]);
    }

    public function test_un_error_de_tmdb_no_queda_cacheado(): void
    {
        Http::fake(['api.themoviedb.org/*' => Http::sequence()
            ->push(['status_message' => 'Too many requests'], 429)
            ->push(['id' => 155, 'title' => 'El caballero oscuro'])]);

        $tmdb = app(TmdbService::class);

        $this->assertSame([], $tmdb->getMovieDetails(155));
        // Antes el [] quedaba 24 h en caché y la ficha daba 404 todo ese tiempo.
        $this->assertSame('El caballero oscuro', $tmdb->getMovieDetails(155)['title'] ?? null);
        Http::assertSentCount(2);
    }

    public function test_las_fichas_en_tanda_quedan_en_la_misma_cache_que_las_sueltas(): void
    {
        Http::fake([
            'api.themoviedb.org/3/movie/155*' => Http::response(['id' => 155, 'title' => 'El caballero oscuro']),
            'api.themoviedb.org/3/tv/1396*' => Http::response(['id' => 1396, 'name' => 'Breaking Bad']),
        ]);

        $tmdb = app(TmdbService::class);
        $batch = $tmdb->detailsMany([['movie', 155], ['tv', 1396]]);

        $this->assertSame(['movie_155', 'tv_1396'], array_keys($batch));
        $this->assertSame('Breaking Bad', $batch['tv_1396']['name']);

        // Ya están en caché: pedirlas sueltas no vuelve a la red.
        $this->assertSame('El caballero oscuro', $tmdb->getMovieDetails(155)['title']);
        $this->assertSame('Breaking Bad', $tmdb->getTvDetails(1396)['name']);
        Http::assertSentCount(2);
    }

    public function test_en_una_tanda_lo_que_falla_vuelve_vacio_y_se_reintenta_despues(): void
    {
        Http::fake([
            'api.themoviedb.org/3/movie/155*' => Http::response(['id' => 155, 'title' => 'El caballero oscuro']),
            'api.themoviedb.org/3/movie/27205*' => Http::sequence()
                ->push(['status_message' => 'Internal error'], 500)
                ->push(['id' => 27205, 'title' => 'El origen']),
        ]);

        $tmdb = app(TmdbService::class);
        $batch = $tmdb->detailsMany([['movie', 155], ['movie', 27205]]);

        $this->assertSame('El caballero oscuro', $batch['movie_155']['title']);
        $this->assertSame([], $batch['movie_27205']);

        $this->assertSame('El origen', $tmdb->getMovieDetails(27205)['title'] ?? null);
        Http::assertSentCount(3);
    }
}
