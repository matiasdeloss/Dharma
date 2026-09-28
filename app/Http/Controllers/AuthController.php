<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\TmdbService;
use App\Traits\HasLocalBackdrop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    use HasLocalBackdrop;

    /** Intentos de login fallidos por minuto para un mismo correo e IP. */
    private const MAX_LOGIN_ATTEMPTS = 5;

    /** Cuentas nuevas por hora desde una misma IP. */
    private const MAX_REGISTRATIONS_PER_HOUR = 5;

    protected TmdbService $tmdb;

    public function __construct(TmdbService $tmdb)
    {
        $this->tmdb = $tmdb;
    }

    /**
     * Show the login form
     */
    public function showLoginForm()
    {
        return view('auth.login', [
            'backdrop' => $this->getLocalBackdrop(),
        ]);
    }

    /**
     * Handle an incoming authentication request
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Por favor ingresa un correo electrónico válido.',
            'password.required' => 'La contraseña es obligatoria.',
        ]);

        // Freno contra fuerza bruta: cinco intentos fallidos por minuto para el
        // mismo correo desde la misma IP (el criterio de Laravel Breeze). El
        // aviso va en el form y no como la página 429 del middleware.
        $throttleKey = Str::transliterate(Str::lower($credentials['email']).'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_LOGIN_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()
                ->withInput($request->only('email', 'remember'))
                ->withErrors([
                    'email' => "Demasiados intentos fallidos. Vuelve a intentarlo en {$seconds} ".($seconds === 1 ? 'segundo.' : 'segundos.'),
                ]);
        }

        $remember = $request->boolean('remember');

        if (Auth::attempt($credentials, $remember)) {
            RateLimiter::clear($throttleKey);
            $request->session()->regenerate();

            return redirect()->intended(route('home'))
                ->with('success', '¡Bienvenido de nuevo a Dharma!');
        }

        RateLimiter::hit($throttleKey);

        return back()
            ->withInput($request->only('email', 'remember'))
            ->withErrors([
                'email' => 'Las credenciales proporcionadas no coinciden con nuestros registros.',
            ]);
    }

    /**
     * Show the registration form
     */
    public function showRegisterForm()
    {
        return view('auth.register', [
            'backdrop' => $this->getLocalBackdrop(),
        ]);
    }

    /**
     * Handle an incoming registration request
     */
    public function register(Request $request)
    {
        // Freno contra altas en masa: pocas cuentas por hora desde una misma
        // IP. Solo cuentan las cuentas creadas, no los errores de tipeo.
        $throttleKey = 'register|'.$request->ip();

        if (RateLimiter::tooManyAttempts($throttleKey, self::MAX_REGISTRATIONS_PER_HOUR)) {
            $minutes = (int) ceil(RateLimiter::availableIn($throttleKey) / 60);

            return back()
                ->withInput($request->only('name', 'email'))
                ->withErrors([
                    'email' => "Se crearon demasiadas cuentas desde esta conexión. Vuelve a intentarlo en {$minutes} ".($minutes === 1 ? 'minuto.' : 'minutos.'),
                ]);
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ], [
            'name.required' => 'Tu nombre de cinéfilo es obligatorio.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Ingresa un correo electrónico válido.',
            'email.unique' => 'Este correo electrónico ya está registrado.',
            'password.required' => 'La contraseña es obligatoria.',
            'password.min' => 'La contraseña debe tener al menos 8 caracteres.',
            'password.confirmed' => 'Las contraseñas no coinciden.',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);

        RateLimiter::hit($throttleKey, 3600);

        Auth::login($user);
        $request->session()->regenerate();

        // Primer paso despues de crear la cuenta: elegir plataformas. Es
        // salteable desde la misma pantalla.
        return redirect()->route('settings.edit', ['bienvenida' => 1])
            ->with('success', '¡Cuenta creada con éxito! Bienvenido a Dharma.');
    }

    /**
     * Destroy an authenticated session
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')
            ->with('info', 'Has cerrado sesión correctamente.');
    }
}
