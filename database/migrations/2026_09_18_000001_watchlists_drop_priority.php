<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fuera la prioridad de la watchlist.
 *
 * Decisión de producto: la lista es "para ver", sin alta/media/baja. El orden
 * vuelve a ser por fecha de agregado y la nota por ítem se queda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('watchlists', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }

    public function down(): void
    {
        Schema::table('watchlists', function (Blueprint $table) {
            $table->string('priority')->default('medium');
        });
    }
};
