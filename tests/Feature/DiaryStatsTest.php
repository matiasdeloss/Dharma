<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Tu diario en números" (/diary/stats).
 */
class DiaryStatsTest extends TestCase
{
    use RefreshDatabase;

    private function log(User $user, string $title, float $rating, string $watched, string $released, array $genres, string $type = 'movie'): void
    {
        $media = MediaItem::create([
            'tmdb_id' => random_int(1, 999999),
            'media_type' => $type,
            'title' => $title,
            'release_date' => $released,
            'genres' => array_map(fn ($g) => ['id' => crc32($g), 'name' => $g], $genres),
        ]);

        Review::create(['user_id' => $user->id, 'media_item_id' => $media->id, 'rating' => $rating, 'watched_date' => $watched]);
    }

    public function test_pide_sesion(): void
    {
        $this->get(route('reviews.stats'))->assertRedirect(route('login'));
    }

    public function test_sin_registros_muestra_el_empty_state(): void
    {
        $this->actingAs(User::factory()->create());

        $this->get(route('reviews.stats'))
            ->assertOk()
            ->assertSee('Todavía no hay nada que contar')
            ->assertDontSee('Géneros más vistos');
    }

    public function test_agrega_notas_generos_y_decadas(): void
    {
        $user = User::factory()->create();
        $this->log($user, 'Alien', 9, '2026-03-10', '1979-05-25', ['Terror', 'Ciencia ficción']);
        $this->log($user, 'Heat', 8.5, '2026-03-22', '1995-12-15', ['Crimen', 'Drama']);
        $this->log($user, 'Perdidos', 7.4, '2025-11-02', '2004-09-22', ['Drama', 'Misterio'], 'tv');

        $html = $this->actingAs($user)->get(route('reviews.stats'))
            ->assertOk()
            ->assertSee('Tu diario en números')
            ->assertSee('Géneros más vistos')
            ->assertSee('1970s')
            ->assertSee('2000s')
            ->assertSee('Alien')
            ->getContent();

        // Drama aparece en dos títulos: es el máximo del ranking de géneros.
        $this->assertMatchesRegularExpression('/Drama<\/span>\s*<span class="chart-hbar-track">\s*<span class="chart-hbar-fill is-max"/', $html);

        // 8.5 redondea a 9: dos títulos en la nota 9 (Alien y Heat).
        $this->assertStringContainsString('<title>9: 2 títulos</title>', $html);
    }

    public function test_el_filtro_por_anio_acota_todo(): void
    {
        $user = User::factory()->create();
        $this->log($user, 'Alien', 9, '2026-03-10', '1979-05-25', ['Terror']);
        $this->log($user, 'Perdidos', 7.4, '2025-11-02', '2004-09-22', ['Misterio'], 'tv');

        $this->actingAs($user)->get(route('reviews.stats', ['year' => 2025]))
            ->assertOk()
            ->assertSee('Perdidos')
            ->assertDontSee('Alien')
            ->assertDontSee('1970s');

        // Un año que no está en el diario cae a "todo el historial".
        $this->actingAs($user)->get(route('reviews.stats', ['year' => 1999]))
            ->assertOk()
            ->assertSee('Alien')
            ->assertSee('Perdidos');
    }
}
