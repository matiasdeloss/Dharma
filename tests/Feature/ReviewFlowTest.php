<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Guardar y borrar una reseña desde el modal.
 *
 * El caso que motivó estos tests: al guardar, la nota del hero de la ficha
 * quedaba desactualizada hasta recargar la página, porque la respuesta solo
 * traía el botón y los contadores. Ahora son todos fragmentos out-of-band.
 */
class ReviewFlowTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $overrides = []): array
    {
        return array_merge([
            'tmdb_id' => 27205,
            'media_type' => 'movie',
            'title' => 'Inception',
            'status' => 'watched',
            'rating' => 8.5,
        ], $overrides);
    }

    public function test_guardar_devuelve_todos_los_fragmentos_que_mantienen_la_pantalla_al_dia(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->withHeaders(['HX-Request' => 'true'])
            ->post(route('reviews.store'), $this->payload());

        $response->assertStatus(200);
        $response->assertHeader('HX-Trigger');

        // Las cuatro zonas que tienen que quedar sincronizadas sin recargar.
        $response->assertSee('id="hero-score-mine"', false);
        $response->assertSee('id="hero-score-dharma"', false);
        $response->assertSee('id="log-action-container"', false);
        $response->assertSee('id="hero-stat-notes"', false);

        // Y todas marcadas como out-of-band: si alguna dejara de estarlo,
        // htmx no la aplicaría y volvería el bug de la nota vieja en pantalla.
        $this->assertGreaterThanOrEqual(
            4,
            substr_count($response->getContent(), 'hx-swap-oob="true"'),
            'Faltan fragmentos out-of-band en la respuesta de guardado.'
        );

        // La nota recién puesta viaja en la respuesta.
        $response->assertSee('8.5', false);
    }

    public function test_borrar_desde_el_modal_pide_refresco_y_elimina_el_registro(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post(route('reviews.store'), $this->payload());

        $review = Review::firstOrFail();

        $response = $this->withHeaders(['HX-Request' => 'true'])
            ->delete(route('reviews.destroy', $review));

        $response->assertStatus(200);
        $response->assertHeader('HX-Refresh', 'true');
        $this->assertDatabaseMissing('reviews', ['id' => $review->id]);
    }

    public function test_no_se_puede_borrar_la_resena_de_otro_usuario(): void
    {
        $owner = User::factory()->create();
        $this->actingAs($owner)->post(route('reviews.store'), $this->payload());

        $review = Review::firstOrFail();

        $this->actingAs(User::factory()->create())
            ->delete(route('reviews.destroy', $review))
            ->assertForbidden();

        $this->assertDatabaseHas('reviews', ['id' => $review->id]);
    }

    // =====================================================================
    // Diario real: N entradas por título
    // =====================================================================

    /**
     * El bug de fondo que motivó la feature: `updateOrCreate` sobre
     * (user_id, media_item_id) hacía que volver a registrar una película
     * pisara la entrada anterior. Un diario tiene una fila por visionado.
     */
    public function test_registrar_el_mismo_titulo_dos_veces_crea_dos_entradas(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('reviews.store'), $this->payload([
            'watched_date' => '2026-01-10',
            'rating' => 8.5,
        ]));

        $this->post(route('reviews.store'), $this->payload([
            'watched_date' => '2026-09-01',
            'rating' => 9.5,
            'is_rewatch' => 1,
        ]));

        // Dos entradas, un solo título local.
        $this->assertDatabaseCount('reviews', 2);
        $this->assertDatabaseCount('media_items', 1);

        $this->assertDatabaseHas('reviews', ['rating' => 8.5, 'is_rewatch' => false]);
        $this->assertDatabaseHas('reviews', ['rating' => 9.5, 'is_rewatch' => true]);
    }

    /**
     * El estado y el re-visionado iban hardcodeados en `<input type="hidden">`
     * (`watched` y `0`): los filtros del diario nunca matcheaban nada y la
     * estadística de re-vistas siempre daba cero.
     */
    public function test_el_estado_y_el_re_visionado_llegan_del_formulario(): void
    {
        $this->actingAs($user = User::factory()->create());

        $this->post(route('reviews.store'), $this->payload([
            'status' => 'watching',
            'is_rewatch' => 1,
            'watched_date' => '2026-09-02',
        ]));

        $entry = Review::firstOrFail();

        $this->assertSame('watching', $entry->status);
        $this->assertTrue($entry->is_rewatch);
        $this->assertSame('2026-09-02', $entry->watched_date->toDateString());
        $this->assertSame($user->id, $entry->user_id);
    }

    /**
     * La fecha también era un hidden fijo en "hoy": no se podía registrar algo
     * visto hace dos semanas.
     */
    public function test_la_fecha_de_visionado_se_puede_poner_en_el_pasado(): void
    {
        $this->actingAs($user = User::factory()->create());

        $this->post(route('reviews.store'), $this->payload(['watched_date' => '2025-12-24']));

        $this->assertSame(
            '2025-12-24',
            Review::where('user_id', $user->id)->firstOrFail()->watched_date->toDateString()
        );
    }

    public function test_no_se_puede_registrar_una_fecha_futura(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('reviews.store'), $this->payload([
            'watched_date' => now()->addWeek()->toDateString(),
        ]))->assertSessionHasErrors('watched_date');

        $this->assertDatabaseCount('reviews', 0);
    }

    /**
     * "Quiero verla" no es un visionado: no lleva fecha, ni nota, ni flag de
     * re-visionado, aunque el formulario los mande.
     */
    public function test_quiero_verla_se_guarda_sin_fecha_ni_nota(): void
    {
        $this->actingAs($user = User::factory()->create());

        $this->post(route('reviews.store'), $this->payload([
            'status' => 'plan_to_watch',
            'rating' => 9.0,
            'watched_date' => '2026-09-01',
            'is_rewatch' => 1,
        ]));

        $this->assertDatabaseHas('reviews', [
            'user_id' => $user->id,
            'status' => 'plan_to_watch',
            'rating' => null,
            'watched_date' => null,
            'is_rewatch' => false,
        ]);
    }

    public function test_editar_una_entrada_la_modifica_en_lugar_de_duplicarla(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post(route('reviews.store'), $this->payload(['watched_date' => '2026-08-01']));

        $review = Review::firstOrFail();

        $response = $this->withHeaders(['HX-Request' => 'true'])
            ->patch(route('reviews.update', $review), [
                'status' => 'watched',
                'rating' => 6.0,
                'watched_date' => '2026-08-02',
                'review_text' => 'Bajé la nota en la segunda pasada.',
            ]);

        $response->assertStatus(200);
        $response->assertHeader('HX-Trigger');

        $this->assertDatabaseCount('reviews', 1);

        $review->refresh();
        $this->assertSame(6.0, $review->rating);
        $this->assertSame('2026-08-02', $review->watched_date->toDateString());
    }

    public function test_no_se_puede_editar_la_entrada_de_otro_usuario(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('reviews.store'), $this->payload());

        $review = Review::firstOrFail();

        $this->actingAs(User::factory()->create())
            ->patch(route('reviews.update', $review), [
                'status' => 'watched',
                'rating' => 1.0,
            ])
            ->assertForbidden();

        $this->assertDatabaseHas('reviews', ['id' => $review->id, 'rating' => 8.5]);
    }

    public function test_el_modal_de_edicion_apunta_a_esa_entrada(): void
    {
        $this->actingAs(User::factory()->create());
        $this->post(route('reviews.store'), $this->payload(['watched_date' => '2026-08-15']));

        $review = Review::firstOrFail();

        $response = $this->withHeaders(['HX-Request' => 'true'])
            ->get(route('reviews.edit', $review));

        $response->assertStatus(200);
        $response->assertSee(route('reviews.update', $review), false);
        $response->assertSee('name="_method" value="PATCH"', false);
        $response->assertSee('Guardar cambios');
    }

    public function test_el_modal_de_alta_avisa_que_ya_hay_registros_previos(): void
    {
        $this->actingAs(User::factory()->create());
        // El mock de TMDB devuelve Interstellar para cualquier ficha.
        $this->post(route('reviews.store'), $this->payload(['tmdb_id' => 157336]));

        $response = $this->withHeaders(['HX-Request' => 'true'])
            ->get(route('reviews.modal', ['type' => 'movie', 'id' => 157336]));

        $response->assertStatus(200);
        $response->assertSee(route('reviews.store'), false);
        $response->assertSee('re-visionado', false);
        $response->assertSee('Registrar de nuevo');
    }

    /**
     * El promedio "de la comunidad" cuenta una vez por usuario: si no, quien
     * registra cinco visionados pesa cinco veces en la nota del título.
     */
    public function test_el_promedio_de_dharma_cuenta_una_sola_vez_por_usuario(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('reviews.store'), $this->payload(['watched_date' => '2026-01-05']));

        $response = $this->withHeaders(['HX-Request' => 'true'])
            ->post(route('reviews.store'), $this->payload([
                'watched_date' => '2026-09-01',
                'is_rewatch' => 1,
            ]));

        $this->assertDatabaseCount('reviews', 2);
        $response->assertSee('1 calificación', false);
    }

    /**
     * La ficha con varias entradas del mismo título: el botón pasa a ser un
     * desplegable con "registrar de nuevo" y una fila por entrada, cada una
     * con su enlace de edición por id.
     */
    public function test_la_ficha_lista_todas_mis_entradas_del_titulo(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('reviews.store'), $this->payload([
            'tmdb_id' => 157336,
            'title' => 'Interstellar',
            'watched_date' => '2025-05-05',
            'rating' => 8.0,
        ]));
        $this->post(route('reviews.store'), $this->payload([
            'tmdb_id' => 157336,
            'title' => 'Interstellar',
            'watched_date' => '2026-05-05',
            'rating' => 9.0,
            'is_rewatch' => 1,
        ]));

        $response = $this->get(route('media.show', ['type' => 'movie', 'id' => 157336]));

        $response->assertStatus(200);
        $response->assertSee('2 registros');
        $response->assertSee('Registrar de nuevo');

        // "Tu nota" es la de la entrada calificada más reciente.
        $response->assertSee('9.0/10', false);

        foreach (Review::all() as $entry) {
            $response->assertSee(route('reviews.edit', $entry), false);
        }
    }

    public function test_el_diario_ofrece_filtro_por_ano_y_lo_aplica(): void
    {
        $this->actingAs(User::factory()->create());

        $this->post(route('reviews.store'), $this->payload([
            'watched_date' => '2025-03-04',
            'review_text' => 'La vi el año pasado.',
        ]));
        $this->post(route('reviews.store'), $this->payload([
            'watched_date' => '2026-03-04',
            'review_text' => 'Y la repetí este año.',
            'is_rewatch' => 1,
        ]));

        $this->get(route('reviews.index'))
            ->assertSee('La vi el año pasado.')
            ->assertSee('Y la repetí este año.');

        $this->get(route('reviews.index', ['year' => 2026]))
            ->assertSee('Y la repetí este año.')
            ->assertDontSee('La vi el año pasado.');
    }
}
