<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Región y plataformas de streaming del usuario (/ajustes).
 * Corre contra los datos mock de TmdbService (sin clave en phpunit.xml).
 */
class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_los_ajustes_piden_sesion(): void
    {
        $this->get(route('settings.edit'))->assertRedirect(route('login'));
    }

    public function test_la_pantalla_lista_las_plataformas_de_la_region(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('settings.edit'))
            ->assertOk()
            ->assertSee('Netflix')
            ->assertSee('Disney Plus')
            ->assertSee('Argentina');
    }

    public function test_guardar_reemplaza_las_plataformas_y_la_region(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->put(route('settings.update'), ['region' => 'ES', 'providers' => [8, 337]])
            ->assertRedirect(route('settings.edit'));

        $this->assertSame('ES', $user->fresh()->region);
        $this->assertEqualsCanonicalizing([8, 337], $user->fresh()->providerIds());
        $this->assertDatabaseHas('user_providers', ['user_id' => $user->id, 'provider_id' => 8, 'name' => 'Netflix']);

        // Guardar de nuevo con otro set lo reemplaza entero.
        $this->put(route('settings.update'), ['region' => 'ES', 'providers' => [119]]);

        $this->assertSame([119], $user->fresh()->providerIds());
    }

    public function test_se_ignoran_plataformas_que_tmdb_no_ofrece_y_se_puede_dejar_vacio(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $this->put(route('settings.update'), ['region' => 'AR', 'providers' => [8, 999999]]);
        $this->assertSame([8], $user->fresh()->providerIds());

        $this->put(route('settings.update'), ['region' => 'AR']);
        $this->assertSame([], $user->fresh()->providerIds());
    }

    public function test_una_region_desconocida_se_rechaza(): void
    {
        $this->actingAs(User::factory()->create());

        $this->put(route('settings.update'), ['region' => 'ZZ'])
            ->assertSessionHasErrors('region');
    }
}
