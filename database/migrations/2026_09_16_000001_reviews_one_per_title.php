<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vuelta a UNA entrada por usuario y título, y fuera el estado y el re-visionado.
 *
 * Decisión de producto: calificar es dar por vista. No hay "viéndola", "quiero
 * verla" ni "abandonada", y tampoco re-visionados. El diario es una fila por
 * película, con su nota, su fecha y su reseña.
 *
 * Como la migración anterior permitió N filas por título, primero se deja una
 * sola por par (la más nueva) y recién después se pone el índice único.
 */
return new class extends Migration
{
    private const OLD_INDEX = 'reviews_user_id_media_item_id_index';

    private const UNIQUE = 'reviews_user_id_media_item_id_unique';

    public function up(): void
    {
        // 1. Deduplicar: de cada (user, media) sobrevive la fila con mayor id.
        $duplicates = DB::table('reviews')
            ->select('user_id', 'media_item_id', DB::raw('max(id) as keep_id'))
            ->groupBy('user_id', 'media_item_id')
            ->havingRaw('count(*) > 1')
            ->get();

        foreach ($duplicates as $dup) {
            DB::table('reviews')
                ->where('user_id', $dup->user_id)
                ->where('media_item_id', $dup->media_item_id)
                ->where('id', '!=', $dup->keep_id)
                ->delete();
        }

        // 2. Columnas que ya no significan nada.
        Schema::table('reviews', function (Blueprint $table) {
            $table->dropColumn(['is_rewatch', 'status']);
        });

        // 3. Índice único real (el original nunca lo tuvo: el candado vivía en
        //    el controller). Antes se saca el no-único que dejó la migración
        //    anterior, que quedaría redundante.
        $existing = $this->indexNames();

        Schema::table('reviews', function (Blueprint $table) use ($existing) {
            if (in_array(self::OLD_INDEX, $existing, true)) {
                $table->dropIndex(self::OLD_INDEX);
            }

            if (! in_array(self::UNIQUE, $existing, true)) {
                $table->unique(['user_id', 'media_item_id']);
            }
        });
    }

    public function down(): void
    {
        Schema::table('reviews', function (Blueprint $table) {
            if (in_array(self::UNIQUE, $this->indexNames(), true)) {
                $table->dropUnique(self::UNIQUE);
            }

            $table->boolean('is_rewatch')->default(false);
            $table->string('status')->default('watched');
        });
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
