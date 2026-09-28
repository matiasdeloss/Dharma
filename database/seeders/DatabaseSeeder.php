<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\MediaItem;
use App\Models\Review;
use App\Models\Watchlist;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'cinefilo@example.com'],
            [
                'name' => 'Cinéfago Pro',
                'password' => Hash::make('password'),
            ]
        );

        $interstellar = MediaItem::firstOrCreate(
            ['tmdb_id' => 157336, 'media_type' => 'movie'],
            [
                'title' => 'Interstellar',
                'original_title' => 'Interstellar',
                'release_date' => '2014-11-05',
                'poster_path' => '/gEU2QniE6E77NI6lCU6MxlNBvIx.jpg',
                'backdrop_path' => '/xJHokMbljvjADYdit5fK5VQsXEG.jpg',
                'overview' => 'Un grupo de científicos y exploradores viaja a través de un agujero de gusano en el espacio para intentar asegurar la supervivencia de la humanidad.',
                'genres' => [['id' => 12, 'name' => 'Aventura'], ['id' => 18, 'name' => 'Drama'], ['id' => 878, 'name' => 'Ciencia ficción']],
                'runtime' => 169,
                'vote_average' => 8.4,
            ]
        );

        $inception = MediaItem::firstOrCreate(
            ['tmdb_id' => 27205, 'media_type' => 'movie'],
            [
                'title' => 'Inception',
                'original_title' => 'Inception',
                'release_date' => '2010-07-15',
                'poster_path' => '/9gk7adHYeDvHkCSEqAvQNLV5Uge.jpg',
                'backdrop_path' => '/8ZTVqvKDQ8emSGUEMjsS4yHAwrp.jpg',
                'overview' => 'Dom Cobb es un ladrón capaz de adentrarse en los sueños de la gente para hacerse con sus secretos durante el sueño profundo.',
                'genres' => [['id' => 28, 'name' => 'Acción'], ['id' => 878, 'name' => 'Ciencia ficción'], ['id' => 12, 'name' => 'Aventura']],
                'runtime' => 148,
                'vote_average' => 8.4,
            ]
        );

        Review::firstOrCreate(
            [
                'user_id' => $user->id,
                'media_item_id' => $interstellar->id,
            ],
            [
                'rating' => 10,
                'review_text' => 'Una obra maestra absoluta de la ciencia ficción. La banda sonora de Hans Zimmer te transporta emocionalmente y el final siempre me hace llorar.',
                'private_notes' => 'Vista en IMAX por 3ra vez. La escena de las olas en el planeta de Miller sigue siendo insuperable.',
                'watched_date' => now()->subDays(3)->toDateString(),
                'contains_spoilers' => false,
            ]
        );

        Watchlist::firstOrCreate(
            [
                'user_id' => $user->id,
                'media_item_id' => $inception->id,
            ],
            [
                'notes' => 'Rever antes del próximo maratón de Nolan.',
            ]
        );
    }
}
