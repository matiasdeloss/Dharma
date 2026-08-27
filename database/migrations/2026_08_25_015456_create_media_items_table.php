<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('media_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tmdb_id')->index();
            $table->enum('media_type', ['movie', 'tv'])->default('movie');
            $table->string('title');
            $table->string('original_title')->nullable();
            $table->date('release_date')->nullable();
            $table->string('poster_path')->nullable();
            $table->string('backdrop_path')->nullable();
            $table->text('overview')->nullable();
            $table->json('genres')->nullable();
            $table->integer('runtime')->nullable();
            $table->decimal('vote_average', 3, 1)->nullable();
            $table->timestamps();

            $table->unique(['tmdb_id', 'media_type']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('media_items');
    }
};
