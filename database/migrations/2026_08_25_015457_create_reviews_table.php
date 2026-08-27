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
        Schema::create('reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('media_item_id')->constrained('media_items')->cascadeOnDelete();
            $table->decimal('rating', 3, 1)->nullable(); // e.g., 1.0 to 10.0 (corresponds to 0.5 - 5.0 stars)
            $table->text('review_text')->nullable(); // Public review
            $table->text('private_notes')->nullable(); // Private personal notes
            $table->date('watched_date')->nullable();
            $table->boolean('is_rewatch')->default(false);
            $table->boolean('contains_spoilers')->default(false);
            $table->enum('status', ['watched', 'watching', 'plan_to_watch', 'dropped'])->default('watched');
            $table->timestamps();

            $table->index(['user_id', 'watched_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('reviews');
    }
};
