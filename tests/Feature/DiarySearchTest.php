<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Models\Review;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Buscar en el diario: títulos, reseñas y notas privadas, sin distinguir
 * mayúsculas ni tildes, y combinable con los filtros de nota y año.
 */
class DiarySearchTest extends TestCase
{
    use RefreshDatabase;

    private function entry(User $user, int $tmdbId, string $title, array $attributes = []): Review
    {
        $media = MediaItem::create(['tmdb_id' => $tmdbId, 'media_type' => 'movie', 'title' => $title, 'genres' => []]);

        return Review::create($attributes + [
            'user_id' => $user->id,
            'media_item_id' => $media->id,
            'rating' => 8,
            'watched_date' => now(),
        ]);
    }

    public function test_busca_por_titulo_sin_importar_tildes_ni_mayusculas(): void
    {
        $user = User::factory()->create();
        $this->entry($user, 1, 'Parásitos');
        $this->entry($user, 2, 'Interstellar');

        $this->actingAs($user)->get(route('reviews.index', ['buscar' => 'PARASITOS']))
            ->assertOk()
            ->assertSee('Parásitos')
            ->assertDontSee('Interstellar');
    }

    public function test_busca_en_resenas_y_notas_privadas(): void
    {
        $user = User::factory()->create();
        $this->entry($user, 1, 'Parásitos', ['private_notes' => 'La escena del sótano me voló la cabeza.']);
        $this->entry($user, 2, 'Interstellar', ['review_text' => 'La banda sonora de Zimmer.']);

        $this->actingAs($user)->get(route('reviews.index', ['buscar' => 'sotano']))
            ->assertSee('Parásitos')
            ->assertDontSee('Interstellar');

        $this->actingAs($user)->get(route('reviews.index', ['buscar' => 'zimmer']))
            ->assertSee('Interstellar')
            ->assertDontSee('Parásitos');
    }

    public function test_solo_busca_en_mi_diario_y_se_combina_con_los_filtros(): void
    {
        $user = User::factory()->create();
        $this->entry($user, 1, 'Parásitos', ['rating' => 6]);
        $this->entry(User::factory()->create(), 2, 'Parásitos 2');

        $this->actingAs($user)->get(route('reviews.index', ['buscar' => 'parasitos']))
            ->assertSee('Parásitos')
            ->assertDontSee('Parásitos 2');

        $this->actingAs($user)->get(route('reviews.index', ['buscar' => 'parasitos', 'rating' => '8.0']))
            ->assertOk()
            ->assertSee('Nada en tu diario coincide');
    }
}
