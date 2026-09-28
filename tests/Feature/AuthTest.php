<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_page_renders_successfully(): void
    {
        $response = $this->get(route('login'));

        $response->assertStatus(200);
        $response->assertSeeText('Bienvenido a');
        $response->assertSeeText('Dharma');
        $response->assertSee('Iniciar Sesión');
    }

    public function test_user_can_login_with_valid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'tarantino@dharma.tv',
            'password' => Hash::make('secret123'),
        ]);

        $response = $this->post(route('login'), [
            'email' => 'tarantino@dharma.tv',
            'password' => 'secret123',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_user_cannot_login_with_invalid_credentials(): void
    {
        $user = User::factory()->create([
            'email' => 'nolan@dharma.tv',
            'password' => Hash::make('correctpassword'),
        ]);

        $response = $this->from(route('login'))->post(route('login'), [
            'email' => 'nolan@dharma.tv',
            'password' => 'wrongpassword',
        ]);

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_login_se_frena_despues_de_cinco_intentos_fallidos(): void
    {
        // Reloj quieto para que la espera del mensaje sea exacta.
        $this->freezeTime();

        $user = User::factory()->create([
            'email' => 'villeneuve@dharma.tv',
            'password' => Hash::make('correctpassword'),
        ]);

        foreach (range(1, 5) as $intento) {
            $this->from(route('login'))->post(route('login'), [
                'email' => 'villeneuve@dharma.tv',
                'password' => 'wrongpassword',
            ])->assertSessionHasErrors('email');
        }

        // Ni con la contraseña correcta: primero hay que esperar.
        $this->from(route('login'))->post(route('login'), [
            'email' => 'villeneuve@dharma.tv',
            'password' => 'correctpassword',
        ])->assertSessionHasErrors(['email' => 'Demasiados intentos fallidos. Vuelve a intentarlo en 60 segundos.']);
        $this->assertGuest();

        // Pasado el minuto vuelve a dejar entrar.
        $this->travel(61)->seconds();

        $this->post(route('login'), [
            'email' => 'villeneuve@dharma.tv',
            'password' => 'correctpassword',
        ])->assertRedirect(route('home'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_registro_se_frena_despues_de_cinco_cuentas_por_hora_desde_la_misma_ip(): void
    {
        foreach (range(1, 5) as $i) {
            $this->post(route('register'), [
                'name' => "Cinéfilo {$i}",
                'email' => "cinefilo{$i}@dharma.tv",
                'password' => 'password123',
                'password_confirmation' => 'password123',
            ])->assertSessionHasNoErrors();

            auth()->logout();
        }

        $this->from(route('register'))->post(route('register'), [
            'name' => 'Cinéfilo 6',
            'email' => 'cinefilo6@dharma.tv',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseMissing('users', ['email' => 'cinefilo6@dharma.tv']);
        $this->assertGuest();
    }

    public function test_register_page_renders_successfully(): void
    {
        $response = $this->get(route('register'));

        $response->assertStatus(200);
        $response->assertSeeText('Únete a');
        $response->assertSeeText('Dharma');
        $response->assertSee('Crear Mi Cuenta');
    }

    public function test_user_can_register_and_is_authenticated(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Scorsese Fan',
            'email' => 'marty@dharma.tv',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        // El alta manda a elegir plataformas, no al inicio.
        $response->assertRedirect(route('settings.edit', ['bienvenida' => 1]));

        $this->assertDatabaseHas('users', [
            'name' => 'Scorsese Fan',
            'email' => 'marty@dharma.tv',
        ]);

        $this->assertAuthenticated();
    }

    public function test_user_cannot_register_with_duplicate_email(): void
    {
        User::factory()->create([
            'email' => 'existing@dharma.tv',
        ]);

        $response = $this->from(route('register'))->post(route('register'), [
            'name' => 'Otro Usuario',
            'email' => 'existing@dharma.tv',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);

        $response->assertRedirect(route('register'));
        $response->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_authenticated_user_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('logout'));

        $response->assertRedirect(route('home'));
        $this->assertGuest();
    }

    public function test_guest_is_redirected_to_login_when_accessing_diary(): void
    {
        $response = $this->get(route('reviews.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_guest_is_redirected_to_login_when_accessing_watchlist(): void
    {
        $response = $this->get(route('watchlist.index'));

        $response->assertRedirect(route('login'));
    }

    public function test_guest_toggling_watchlist_triggers_auth_required_header(): void
    {
        $response = $this->post(route('watchlist.toggle'), [
            'tmdb_id' => 157336,
            'media_type' => 'movie',
            'title' => 'Interstellar',
            'style' => 'ribbon',
        ]);

        $response->assertStatus(200);
        $response->assertHeader('HX-Trigger');
        $this->assertStringContainsString('authRequired', $response->headers->get('HX-Trigger'));
        $this->assertGuest();
    }
}
