<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Disponibilidad en streaming por región, copiada de TMDB (JustWatch).
 *
 * Forma: {"AR": {"synced_at": "...", "link": "...", "flatrate": [{id, name, logo_path}], "ads": [], "free": [], "rent": [], "buy": []}}
 *
 * Va como JSON en el propio título (y no en una tabla aparte) porque se lee
 * siempre junto con él y se filtra en PHP sobre listas chicas (la watchlist
 * de un usuario). Ver MediaCatalog::syncAvailability.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('media_items', function (Blueprint $table) {
            $table->json('availability')->nullable()->after('genres');
        });
    }

    public function down(): void
    {
        Schema::table('media_items', function (Blueprint $table) {
            $table->dropColumn('availability');
        });
    }
};
