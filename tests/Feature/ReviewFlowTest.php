<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * El diario: una entrada por usuario y título, alimentada por dos modales.
 *
 *   - calificar (`form=rating`): nota en estrellas (1–10) + fecha
 *   - reseñar   (`form=review`): texto público, spoilers, nota privada
 *
 * Los dos guardan en la misma fila y cada uno toca SOLO sus campos.
 */
class ReviewFlowTest extends TestCase
{
    use RefreshDatabase;

    private function media(array $overrides = []): array
    {
        return array_merge([
            'tmdb_id' => 27205,
            'media_type' => 'movie',
            'title' => 'Inception',
        ], $overrides);
    }

    private function rate(array $overrides = []): array
    {
        return array_merge($this->media(), ['form' => 'rating', 'rating' => 8], $overrides);
    }

    private function write(array $overrides = []): array
    {
        return array_merge($this->media(), ['form' => 'review'], $overrides);
    }

    // =====================================================================
    // Calificar
    // =====================================================================

    public function test_al_crear_el_titulo_se_completan_los_generos_desde_tmdb(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('reviews.store'), $this->rate());

        $item = MediaItem::where('tmdb_id', 27205)->first();

        // El form no manda generos ni runtime: los completa MediaCatalog.
        $this->assertNotEmpty($item->genres);
        $this->assertSame('Drama', $item->genres[1]['name']);
        $this->assertSame(169, $item->runtime);
    }

    public function test_los_datos_del_titulo_salen_de_tmdb_y_no_del_form(): void
    {
        // TMDB "de verdad" (clave de prueba + Http::fake): el mock devuelve
        // Interstellar para cualquier id y no serviría para distinguir.
        config(['services.tmdb.api_key' => 'test-key', 'services.tmdb.read_token' => null]);
        Http::fake(['api.themoviedb.org/3/movie/27205*' => Http::response([
            'id' => 27205,
            'title' => 'El origen',
            'poster_path' => '/origen.jpg',
            'genres' => [['id' => 28, 'name' => 'Acción']],
        ])]);

        $this->actingAs(User::factory()->create());

        // Un form armado a mano no puede "bautizar" el título para todos.
        $this->post(route('reviews.store'), $this->rate(['title' => 'Título inventado', 'poster_path' => '/trucho.jpg']));

        $item = MediaItem::where('tmdb_id', 27205)->firstOrFail();
        $this->assertSame('El origen', $item->title);
        $this->assertSame('/origen.jpg', $item->poster_path);
    }

    public function test_si_tmdb_no_responde_el_titulo_se_crea_con_lo_que_trajo_el_form(): void
    {
        config(['services.tmdb.api_key' => 'test-key', 'services.tmdb.read_token' => null]);
        Http::fake(['api.themoviedb.org/*' => Http::response(['status_message' => 'Internal error'], 500)]);

        $this->actingAs(User::factory()->create());
        $this->post(route('reviews.store'), $this->rate());

        $this->assertSame('Inception', MediaItem::where('tmdb_id', 27205)->firstOrFail()->title);
    }

    public function test_calificar_crea_la_entrada_con_nota_y_fecha(): void
    {
        $this->actingAs($user = User::factory()->create());

        $this->post(route('reviews.store'), $this->rate(['rating' => 9, 'watched_date' => '2026-05-05']))
            ->assertSessionHas('success');

        $entry = Review::where('user_id', $user->id)->firstOrFail();
        $this->assertSame(9.0, $entry->rating);
        $this->assertSame('2026-05-05', $entry->watched_date->toDateString());
        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_calificar_dos_veces_edita_la_misma_entrada_en_vez_de_duplicarla(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('reviews.store'), $this->rate(['rating' => 6]));
        $this->post(route('reviews.store'), $this->rate(['rating' => 9]));

        $this->assertDatabaseCount('reviews', 1);
        $this->assertSame(9.0, Review::firstOrFail()->rating);
    }

    public function test_la_nota_es_un_entero_del_1_al_10(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('reviews.store'), $this->rate(['rating' => 8.5]))
            ->assertSessionHasErrors('rating');
        $this->post(route('reviews.store'), $this->rate(['rating' => 11]))
            ->assertSessionHasErrors('rating');
        $this->post(route('reviews.store'), $this->rate(['rating' => 0]))
            ->assertSessionHasErrors('rating');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_sin_fecha_queda_fechada_hoy(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('reviews.store'), $this->rate());

        $this->assertSame(now()->toDateString(), Review::firstOrFail()->watched_date->toDateString());
    }

    public function test_no_se_puede_registrar_una_fecha_futura(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('reviews.store'), $this->rate(['watched_date' => now()->addWeek()->toDateString()]))
            ->assertSessionHasErrors('watched_date');

        $this->assertDatabaseCount('reviews', 0);
    }

    public function test_por_htmx_un_error_de_validacion_vuelve_como_toast(): void
    {
        $this->actingAs(User::factory()->create());

        // Sin HTMX sigue el redirect de siempre. Por HTMX ese redirect se
        // perdía y el modal quedaba abierto sin decir nada.
        $response = $this->withHeaders(['HX-Request' => 'true'])
            ->post(route('reviews.store'), $this->rate(['rating' => '']));

        $response->assertStatus(422);
        $trigger = json_decode($response->headers->get('HX-Trigger'), true);
        $this->assertSame('Elegí una nota con las estrellas.', $trigger['formInvalid']['message'] ?? null);
        $this->assertDatabaseCount('reviews', 0);
    }

    // =====================================================================
    // Reseñar
    // =====================================================================

    public function test_resenar_sin_nota_previa_crea_la_entrada(): void
    {
        $this->actingAs($user = User::factory()->create());

        $this->post(route('reviews.store'), $this->write(['review_text' => 'Enorme.']))
            ->assertSessionHas('success');

        $entry = Review::where('user_id', $user->id)->firstOrFail();
        $this->assertSame('Enorme.', $entry->review_text);
        $this->assertNull($entry->rating);
        $this->assertNotNull($entry->watched_date);
    }

    public function test_resenar_no_toca_la_nota_y_calificar_no_toca_la_resena(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('reviews.store'), $this->rate(['rating' => 9]));
        $this->post(route('reviews.store'), $this->write([
            'review_text' => 'Texto público.',
            'private_notes' => 'Solo mío.',
            'contains_spoilers' => 1,
        ]));

        $entry = Review::firstOrFail();
        $this->assertSame(9.0, $entry->rating, 'reseñar borró la nota');
        $this->assertSame('Texto público.', $entry->review_text);
        $this->assertSame('Solo mío.', $entry->private_notes);
        $this->assertTrue($entry->contains_spoilers);

        // Y al revés: volver a calificar deja la reseña como estaba.
        $this->post(route('reviews.store'), $this->rate(['rating' => 7]));

        $entry->refresh();
        $this->assertSame(7.0, $entry->rating);
        $this->assertSame('Texto público.', $entry->review_text, 'calificar borró la reseña');
        $this->assertSame('Solo mío.', $entry->private_notes);
        $this->assertDatabaseCount('reviews', 1);
    }

    public function test_el_form_tiene_que_decir_que_modal_esta_guardando(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('reviews.store'), array_merge($this->media(), ['rating' => 8]))
            ->assertSessionHasErrors('form');
    }

    // =====================================================================
    // Modales
    // =====================================================================

    public function test_los_dos_modales_abren_y_precargan_la_entrada(): void
    {
        $this->actingAs(User::factory()->create());
        // El mock de TMDB devuelve Interstellar para cualquier ficha.
        $this->post(route('reviews.store'), $this->rate(['tmdb_id' => 157336, 'rating' => 9]));
        $this->post(route('reviews.store'), $this->write(['tmdb_id' => 157336, 'review_text' => 'Mi reseña.']));

        $rate = $this->withHeaders(['HX-Request' => 'true'])
            ->get(route('reviews.rate', ['type' => 'movie', 'id' => 157336]));
        $rate->assertStatus(200);
        $rate->assertSee('name="form" value="rating"', false);
        $rate->assertSee('star-rater-star', false);
        $rate->assertSee('name="rating" value="9"', false);
        $rate->assertSee('Guardar nota');
        $rate->assertDontSee('name="status"', false);
        $rate->assertDontSee('is_rewatch', false);

        $write = $this->withHeaders(['HX-Request' => 'true'])
            ->get(route('reviews.write', ['type' => 'movie', 'id' => 157336]));
        $write->assertStatus(200);
        $write->assertSee('name="form" value="review"', false);
        $write->assertSee('Mi reseña.');
        $write->assertSee('Guardar cambios');
        $write->assertDontSee('star-rater-star', false);
    }

    public function test_calificar_arranca_deshabilitado_hasta_que_hay_nota(): void
    {
        $this->actingAs(User::factory()->create());

        $this->withHeaders(['HX-Request' => 'true'])
            ->get(route('reviews.rate', ['type' => 'movie', 'id' => 157336]))
            ->assertSee('data-star-submit disabled', false);

        $this->post(route('reviews.store'), $this->rate(['tmdb_id' => 157336, 'rating' => 9]));

        $this->withHeaders(['HX-Request' => 'true'])
            ->get(route('reviews.rate', ['type' => 'movie', 'id' => 157336]))
            ->assertSee('data-star-submit', false)
            ->assertDontSee('data-star-submit disabled', false);
    }

    public function test_sin_sesion_los_modales_devuelven_el_de_iniciar_sesion(): void
    {
        foreach (['reviews.rate', 'reviews.write'] as $route) {
            $response = $this->withHeaders(['HX-Request' => 'true'])
                ->get(route($route, ['type' => 'movie', 'id' => 157336]));

            $response->assertStatus(200);
            $response->assertHeader('HX-Trigger');
            $response->assertSee('auth-modal-body', false);
        }
    }

    // =====================================================================
    // Respuesta HTMX y borrado
    // =====================================================================

    public function test_guardar_devuelve_todos_los_fragmentos_que_mantienen_la_pantalla_al_dia(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->withHeaders(['HX-Request' => 'true'])
            ->post(route('reviews.store'), $this->rate(['rating' => 8]));

        $response->assertStatus(200);
        $response->assertHeader('HX-Trigger');
        $response->assertSee('id="hero-score-mine"', false);
        $response->assertSee('id="hero-score-dharma"', false);
        $response->assertSee('id="log-action-container"', false);
        $response->assertSee('id="diary-stats"', false);
        $this->assertGreaterThanOrEqual(4, substr_count($response->getContent(), 'hx-swap-oob='));
    }

    public function test_borrar_desde_el_modal_pide_refresco_y_elimina_el_registro(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post(route('reviews.store'), $this->rate());

        $review = Review::firstOrFail();

        $response = $this->withHeaders(['HX-Request' => 'true'])
            ->delete(route('reviews.destroy', $review));

        $response->assertStatus(200);
        $response->assertHeader('HX-Refresh', 'true');
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    public function test_no_se_puede_borrar_la_resena_de_otro_usuario(): void
    {
        $this->actingAs(User::factory()->create())->post(route('reviews.store'), $this->rate());
        $review = Review::firstOrFail();

        $this->actingAs(User::factory()->create())
            ->delete(route('reviews.destroy', $review))
            ->assertForbidden();

        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    }

    // =====================================================================
    // Ficha
    // =====================================================================

    public function test_la_ficha_muestra_tu_nota_y_sin_el_boton_de_calificar(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post(route('reviews.store'), $this->rate(['tmdb_id' => 157336, 'rating' => 9]));

        $response = $this->get(route('media.show', ['type' => 'movie', 'id' => 157336]));

        $response->assertStatus(200);
        $response->assertSee('id="hero-score-mine"', false);
        $response->assertSee('9.0', false);
        // Con la nota puesta, la barra de acciones (#log-action-container) queda
        // vacía: la edición vive en el hero. Se mira SOLO ese contenedor porque
        // las cards del riel de relacionados también dicen "Calificar".
        preg_match('/<div id="log-action-container"[^>]*>(.*?)<\/div>/s', $response->getContent(), $m);
        $this->assertSame('', trim($m[1] ?? 'no encontrado'), 'La barra de acciones debería estar vacía.');
        $response->assertSee(route('reviews.rate', ['type' => 'movie', 'id' => 157336]), false);
    }
}
