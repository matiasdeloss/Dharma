<?php

namespace Tests\Feature;

use App\Models\Review;
use App\Models\User;
use App\Models\Watchlist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * El seeder tiene que correr sobre una base recién migrada: es lo primero que
 * prueba quien clona el repo (`migrate --seed`).
 */
class DatabaseSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_el_seeder_corre_sobre_una_base_nueva(): void
    {
        $this->seed();

        $user = User::where('email', 'cinefilo@example.com')->firstOrFail();

        $this->assertSame(10.0, Review::where('user_id', $user->id)->firstOrFail()->rating);
        $this->assertSame(1, Watchlist::where('user_id', $user->id)->count());
    }
}
