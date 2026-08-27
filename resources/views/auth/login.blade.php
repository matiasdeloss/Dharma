@extends('layouts.app')

@section('title', 'Iniciar Sesión - Dharma')

@section('content')
<div class="auth-split-wrapper">
    <!-- Left 50%: Seamless Clean Form Panel (No Box) -->
    <div class="auth-split-form-col">
        <div class="auth-split-form-inner">
            <!-- Title & Subtitle -->
            <h1 class="auth-title">Bienvenido a <span class="text-accent">Dharma</span></h1>
            <p class="auth-subtitle">Ingresa a tu diario personal de cine, reseñas y notas.</p>

            <!-- Login Form -->
            <form action="{{ route('login') }}" method="POST" autocomplete="on">
                @csrf

                <!-- Email Address -->
                <div class="auth-input-wrapper">
                    <label for="email" class="auth-label">Correo Electrónico</label>
                    <div class="auth-input-field">
                        <input 
                            type="email" 
                            name="email" 
                            id="email" 
                            class="form-control @error('email') is-invalid @enderror" 
                            placeholder="tu@email.com" 
                            value="{{ old('email') }}" 
                            required 
                            autofocus
                        >
                        <i class="bi bi-envelope auth-field-icon"></i>
                    </div>
                    @error('email')
                        <div class="text-danger small mt-1 ps-2">
                            <i class="bi bi-exclamation-circle me-1"></i>{{ $message }}
                        </div>
                    @enderror
                </div>

                <!-- Password -->
                <div class="auth-input-wrapper">
                    <label for="password" class="auth-label">Contraseña</label>
                    <div class="auth-input-field">
                        <input 
                            type="password" 
                            name="password" 
                            id="password" 
                            class="form-control @error('password') is-invalid @enderror" 
                            placeholder="••••••••" 
                            required
                        >
                        <i class="bi bi-lock auth-field-icon"></i>
                        <button type="button" class="auth-password-toggle" data-toggle-password="password" aria-label="Mostrar contraseña">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    @error('password')
                        <div class="text-danger small mt-1 ps-2">
                            <i class="bi bi-exclamation-circle me-1"></i>{{ $message }}
                        </div>
                    @enderror
                </div>

                <!-- Remember Me -->
                <div class="d-flex justify-content-between align-items-center mb-4 ps-1">
                    <div class="form-check">
                        <input class="form-check-input bg-dark border-secondary" type="checkbox" name="remember" id="remember" {{ old('remember') ? 'checked' : '' }}>
                        <label class="form-check-label auth-check-label" for="remember">
                            Recordarme en este equipo
                        </label>
                    </div>
                </div>

                <!-- Submit Button (Centered & Non-Full Width) -->
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-cine-primary btn-auth-submit">
                        <i class="bi bi-box-arrow-in-right me-2"></i> Iniciar Sesión
                    </button>
                </div>
            </form>

            <!-- Footer Link -->
            <div class="auth-footer-seamless">
                <span>¿Aún no tienes una cuenta?</span>
                <a href="{{ route('register') }}">Crea tu cuenta gratis &rarr;</a>
            </div>
        </div>
    </div>

    <!-- Right 50%: Giant Fullscreen Cinema Still Panel (100% Crisp) -->
    <div class="auth-split-media-col">
        @if(isset($backdrop['url']))
            <div class="split-cinema-still" style="background-image: url('{{ $backdrop['url'] }}');" aria-hidden="true"></div>
        @endif

        <!-- Soft Seam Gradient -->
        <div class="split-seam-gradient" aria-hidden="true"></div>

        <!-- Optional Photo Credit -->
        @if(!empty($backdrop['title']))
            <div class="split-photo-credit">
                <i class="bi bi-camera-reels text-accent"></i>
                <span>{{ $backdrop['title'] }}</span>
            </div>
        @endif
    </div>
</div>
@endsection
