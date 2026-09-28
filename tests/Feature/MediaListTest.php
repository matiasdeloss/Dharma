<?php

namespace Tests\Feature;

use App\Models\MediaItem;
use App\Models\MediaList;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Listas propias: crear, sumar y sacar títulos desde el modal "Agregar a
 * lista", ordenar arrastrando, y que nadie vea ni toque las listas de otro.
 */
class MediaListTest extends TestCase
{
    use RefreshDatabase;

    private const HX = ['HX-Request' => 'true'];

    private function media(int $tmdbId, string $title): MediaItem
    {
        return MediaItem::create(['tmdb_id' => $tmdbId, 'media_type' => 'movie', 'title' => $title, 'genres' => []]);
    }

    /**
     * @param  MediaItem[]  $titles  en el orden en que van
     */
    private function listWith(User $user, array $titles, array $attributes = []): MediaList
    {
        $list = MediaList::create($attributes + ['user_id' => $user->id, 'name' => 'Mi top']);

        foreach (array_values($titles) as $index => $media) {
            $list->items()->create(['media_item_id' => $media->id, 'position' => $index + 1]);
        }

        return $list;
    }

    private function toggle(MediaList $list, int $tmdbId)
    {
        return $this->withHeaders(self::HX)->post(route('lists.items.toggle', $list), [
            'tmdb_id' => $tmdbId,
            'media_type' => 'movie',
            'title' => 'Lo que diga el form',
        ]);
    }

    public function test_crear_una_lista_lleva_a_la_lista_nueva(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->withHeaders(self::HX)->post(route('lists.store'), [
            'name' => 'Mi top de Nolan',
            'description' => 'De mejor a peor.',
            'is_ranked' => '1',
        ]);

        $list = MediaList::firstOrFail();
        $response->assertHeader('HX-Redirect', route('lists.show', $list));
        $this->assertSame($user->id, $list->user_id);
        $this->assertTrue($list->is_ranked);

        $this->get(route('lists.index'))->assertOk()->assertSee('Mi top de Nolan');
    }

    public function test_el_nombre_es_obligatorio(): void
    {
        $this->actingAs(User::factory()->create());

        $response = $this->withHeaders(self::HX)->post(route('lists.store'), ['name' => '']);

        $response->assertStatus(422);
        $trigger = json_decode($response->headers->get('HX-Trigger'), true);
        $this->assertSame('Ponele un nombre a la lista.', $trigger['formInvalid']['message'] ?? null);
        $this->assertDatabaseCount('media_lists', 0);
    }

    public function test_el_boton_de_cada_lista_suma_y_saca_el_titulo(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $interstellar = $this->media(157336, 'Interstellar');
        $list = $this->listWith($user, [$this->media(27205, 'Inception')]);

        $this->toggle($list, 157336)
            ->assertOk()
            ->assertHeader('HX-Trigger')
            ->assertSee('aria-pressed="true"', false);

        $entry = $list->items()->where('media_item_id', $interstellar->id)->firstOrFail();
        $this->assertSame(2, $entry->position, 'Se suma al final.');

        $this->toggle($list, 157336)->assertOk()->assertSee('aria-pressed="false"', false);
        $this->assertSame(1, $list->items()->count());
    }

    public function test_crear_una_lista_desde_el_modal_ya_incluye_el_titulo(): void
    {
        $user = User::factory()->create();
        $interstellar = $this->media(157336, 'Interstellar');

        $this->actingAs($user)->withHeaders(self::HX)->post(route('lists.store'), [
            'name' => 'Maratón espacial',
            'tmdb_id' => 157336,
            'media_type' => 'movie',
            'title' => 'Interstellar',
        ])->assertOk()->assertSee('Maratón espacial')->assertSee('aria-pressed="true"', false);

        $this->assertSame([$interstellar->id], MediaList::firstOrFail()->items()->pluck('media_item_id')->all());
    }

    public function test_el_modal_marca_las_listas_donde_ya_esta_y_al_invitado_le_pide_sesion(): void
    {
        $user = User::factory()->create();
        $interstellar = $this->media(157336, 'Interstellar');
        $this->listWith($user, [$interstellar], ['name' => 'Favoritas']);
        MediaList::create(['user_id' => $user->id, 'name' => 'Para ver con amigos']);

        $html = $this->actingAs($user)->withHeaders(self::HX)
            ->get(route('lists.picker', ['type' => 'movie', 'id' => 157336]))
            ->assertOk()
            ->assertSee('Favoritas')
            ->assertSee('Para ver con amigos')
            ->getContent();

        $this->assertSame(1, substr_count($html, 'aria-pressed="true"'));

        auth()->logout();

        $this->withHeaders(self::HX)->get(route('lists.picker', ['type' => 'movie', 'id' => 157336]))
            ->assertOk()
            ->assertHeader('HX-Trigger')
            ->assertSee('auth-modal-body', false);
    }

    public function test_arrastrar_guarda_el_orden_nuevo(): void
    {
        $user = User::factory()->create();
        $alfa = $this->media(1, 'Alfa');
        $beta = $this->media(2, 'Beta');
        $gamma = $this->media(3, 'Gamma');
        $list = $this->listWith($user, [$alfa, $beta, $gamma], ['is_ranked' => true]);
        $ids = $list->items()->pluck('id', 'media_item_id');

        $this->actingAs($user)->withHeaders(self::HX)
            ->post(route('lists.reorder', $list), ['items' => [$ids[$gamma->id], $ids[$alfa->id], $ids[$beta->id]]])
            ->assertNoContent();

        $this->assertSame([$gamma->id, $alfa->id, $beta->id], $list->items()->orderBy('position')->pluck('media_item_id')->all());

        // La página las muestra en ese orden y numeradas.
        $html = $this->get(route('lists.show', $list))->assertOk()->getContent();
        $this->assertLessThan(strpos($html, 'title="Alfa"'), strpos($html, 'title="Gamma"'));
        $this->assertStringContainsString('badge-rank-number">#1</span>', $html);
    }

    public function test_reordenar_rechaza_titulos_que_no_son_de_la_lista(): void
    {
        $user = User::factory()->create();
        $list = $this->listWith($user, [$this->media(1, 'Alfa'), $this->media(2, 'Beta')]);
        $otra = $this->listWith($user, [$this->media(3, 'Gamma')], ['name' => 'Otra']);
        $propios = $list->items()->orderBy('position')->pluck('id')->all();

        $this->actingAs($user)->withHeaders(self::HX)
            ->post(route('lists.reorder', $list), ['items' => [$propios[1], $otra->items()->value('id')]])
            ->assertStatus(422);

        $this->assertSame($propios, $list->items()->orderBy('position')->pluck('id')->all());
    }

    public function test_quitar_un_titulo_sube_a_los_de_abajo(): void
    {
        $user = User::factory()->create();
        $beta = $this->media(2, 'Beta');
        $gamma = $this->media(3, 'Gamma');
        $list = $this->listWith($user, [$this->media(1, 'Alfa'), $beta, $gamma]);
        $primero = $list->items()->where('position', 1)->firstOrFail();

        $this->actingAs($user)->withHeaders(self::HX)
            ->delete(route('lists.items.destroy', [$list, $primero]))
            ->assertOk()
            ->assertSee('id="list-items"', false)
            ->assertSee('id="list-stats"', false);

        $this->assertSame([1, 2], $list->items()->orderBy('position')->pluck('position')->all());
        $this->assertSame([$beta->id, $gamma->id], $list->items()->orderBy('position')->pluck('media_item_id')->all());
    }

    public function test_nadie_ve_ni_toca_la_lista_de_otro(): void
    {
        $list = $this->listWith(User::factory()->create(), [$this->media(1, 'Alfa')]);
        $entry = $list->items()->firstOrFail();

        $this->actingAs(User::factory()->create());

        $this->get(route('lists.show', $list))->assertForbidden();
        $this->get(route('lists.edit', $list))->assertForbidden();
        $this->withHeaders(self::HX)->put(route('lists.update', $list), ['name' => 'Ajena'])->assertForbidden();
        $this->withHeaders(self::HX)->post(route('lists.items.toggle', $list), ['tmdb_id' => 1, 'media_type' => 'movie'])->assertForbidden();
        $this->withHeaders(self::HX)->post(route('lists.reorder', $list), ['items' => [$entry->id]])->assertForbidden();
        $this->withHeaders(self::HX)->delete(route('lists.items.destroy', [$list, $entry]))->assertForbidden();
        $this->withHeaders(self::HX)->delete(route('lists.destroy', $list))->assertForbidden();

        $this->assertSame('Mi top', $list->fresh()->name);
        $this->assertSame(1, $list->items()->count());
        $this->get(route('lists.index'))->assertOk()->assertDontSee('Mi top');
    }

    public function test_eliminar_la_lista_borra_sus_titulos_pero_no_los_de_la_base(): void
    {
        $user = User::factory()->create();
        $alfa = $this->media(1, 'Alfa');
        $list = $this->listWith($user, [$alfa]);

        $this->actingAs($user)->withHeaders(self::HX)
            ->delete(route('lists.destroy', $list))
            ->assertHeader('HX-Redirect', route('lists.index'));

        $this->assertDatabaseCount('media_lists', 0);
        $this->assertDatabaseCount('media_list_items', 0);
        $this->assertModelExists($alfa);
    }

    public function test_la_ficha_ofrece_agregar_a_lista(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('media.show', ['type' => 'movie', 'id' => 157336]))
            ->assertOk()
            ->assertSee(route('lists.picker', ['type' => 'movie', 'id' => 157336]), false);
    }
}
