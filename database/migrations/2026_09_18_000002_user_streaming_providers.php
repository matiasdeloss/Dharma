<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plataformas de streaming y región del usuario.
 *
 * `provider_id` es el id de TMDB (8 = Netflix, 337 = Disney+, ...). Nombre y
 * logo se guardan como copia para no depender de la API al pintar un badge;
 * si TMDB los cambia, se refrescan al volver a guardar los ajustes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('region', 2)->default('AR')->after('password');
        });

        Schema::create('user_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('provider_id');
            $table->string('name');
            $table->string('logo_path')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'provider_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_providers');

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('region');
        });
    }
};
