<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Listas del usuario ("Mi top de Nolan", "Maratón de Halloween").
 *
 * `media_lists` es la lista (privada, de su dueño) y `media_list_items` los
 * títulos en orden. `position` va de 1 a N sin huecos: al quitar un título
 * los de abajo suben un puesto (ver MediaListController).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_lists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->text('description')->nullable();
            // Numerada: muestra el puesto de cada título (#1, #2...), para rankings.
            $table->boolean('is_ranked')->default(false);
            $table->timestamps();
        });

        Schema::create('media_list_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_list_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_item_id')->constrained('media_items')->cascadeOnDelete();
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->unique(['media_list_id', 'media_item_id']);
            $table->index(['media_list_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('media_list_items');
        Schema::dropIfExists('media_lists');
    }
};
