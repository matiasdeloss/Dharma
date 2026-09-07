<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Un título puede tener N entradas por usuario (re-visionados).
 *
 * El candado de "una reseña por título" no vivía en el esquema sino en el
 * `Review::updateOrCreate([user_id, media_item_id])` del controller: la tabla
 * nunca llegó a tener el índice único. Igual se contempla acá por si alguna
 * base creada a mano lo tiene, porque mientras exista una entrada nueva del
 * mismo título revienta con violación de unicidad.
 *
 * A cambio se deja un índice NO único sobre el mismo par, que es la consulta
 * más caliente de la ficha ("mis entradas de este título").
 */
return new class extends Migration
{
    private const UNIQUE = 'reviews_user_id_media_item_id_unique';

    private const INDEX = 'reviews_user_id_media_item_id_index';

    public function up(): void
    {
        $existing = $this->indexNames();

        Schema::table('reviews', function (Blueprint $table) use ($existing) {
            if (in_array(self::UNIQUE, $existing, true)) {
                $table->dropUnique(self::UNIQUE);
            }

            if (! in_array(self::INDEX, $existing, true)) {
                $table->index(['user_id', 'media_item_id']);
            }
        });
    }

    public function down(): void
    {
        // No se restaura el índice único a propósito: si ya hay re-visionados
        // guardados, volver a ponerlo haría fallar el rollback. Solo se saca
        // el índice que agregó esta migración.
        if (in_array(self::INDEX, $this->indexNames(), true)) {
            Schema::table('reviews', function (Blueprint $table) {
                $table->dropIndex(self::INDEX);
            });
        }
    }

    /**
     * @return array<int, string>
     */
    private function indexNames(): array
    {
        return array_map(
            fn (array $index) => $index['name'],
            Schema::getIndexes('reviews')
        );
    }
};
