<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Explorar (/explorar): filas curadas por plataforma sin filtros, grilla
 * paginada con filtros. Corre contra los datos mock de TmdbService.
 */
class ExploreTest extends TestCase
{
    use RefreshDatabase;

    public function test_sin_filtros_muestra_filas_por_plataforma_y_es_publico(): void
    {
        $this->get(route('explore.index'))
            ->assertOk()
            ->assertSee('Explorar')
            ->assertSee('Lo mejor de Netflix')
            ->assertSee('Lo mejor de Disney Plus')
            ->assertDontSee('Lo mejor en tus plataformas')
            ->assertSee('Elegí tus plataformas');
    }

    public function test_con_plataformas_elegidas_sus_filas_van_primero(): void
    {
        $user = User::factory()->create();
        $user->providers()->create(['provider_id' => 1899, 'name' => 'Max']);

        $html = $this->actingAs($user)->get(route('explore.index'))
            ->assertOk()
            ->assertSee('Lo mejor en tus plataformas')
            ->assertSee('Lo nuevo en tus plataformas')
            ->assertDontSee('Elegí tus plataformas')
            ->getContent();

        $this->assertLessThan(
            strpos($html, 'Lo mejor de Netflix'),
            strpos($html, 'Lo mejor de Max'),
            'La plataforma del usuario debería listarse antes que las grandes.'
        );
    }

    public function test_con_filtros_pasa_a_grilla_con_paginacion(): void
    {
        $this->get(route('explore.index', ['tipo' => 'tv', 'genero' => 18, 'orden' => 'valoradas']))
            ->assertOk()
            ->assertDontSee('Lo mejor de Netflix')
            ->assertSee('Limpiar')
            ->assertSee('Interstellar');
    }

    public function test_los_valores_invalidos_caen_al_default(): void
    {
        $this->get(route('explore.index', ['tipo' => 'anime', 'orden' => 'alfabetico', 'plataforma' => 'mias']))
            ->assertOk()
            // Sin filtro válido sigue en modo curado, con Películas activo.
            ->assertSee('Lo mejor de Netflix')
            ->assertDontSee('Limpiar');
    }
}
