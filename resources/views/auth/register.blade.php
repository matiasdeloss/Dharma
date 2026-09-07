@extends('layouts.app')

@section('title', 'Crear Cuenta - Dharma')

@section('content')
<div class="auth-split-wrapper">
    <!-- Left 50%: Seamless Clean Form Panel (No Box) -->
    <div class="auth-split-form-col">
        <div class="auth-split-form-inner">
            <!-- Title & Subtitle -->
            <h1 class="auth-title">Únete a <span class="text-accent">Dharma</span></h1>
            <p class="auth-subtitle">Crea tu cuenta y empieza tu diario de cine personal.</p>

            <!-- Registration Form -->
            <form action="{{ route('register') }}" method="POST" autocomplete="on">
                @csrf

                <!-- Name -->
                <div class="auth-input-wrapper">
                    <label for="name" class="auth-label">Nombre de Usuario</label>
                    <div class="auth-input-field">
                        <input 
                            type="text" 
                            name="name" 
                            id="name" 
                            class="form-control @error('name') is-invalid @enderror" 
                            placeholder="Tu nombre de usuario" 
                            value="{{ old('name') }}" 
                            required 
                            autofocus
                        >
                        <i class="bi bi-person auth-field-icon"></i>
                    </div>
                    @error('name')
                        <div class="auth-error">
                            <i class="bi bi-exclamation-circle me-1"></i>{{ $message }}
                        </div>
                    @enderror
                </div>

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
                        >
                        <i class="bi bi-envelope auth-field-icon"></i>
                    </div>
                    @error('email')
                        <div class="auth-error">
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
                            placeholder="Mínimo 8 caracteres" 
                            required
                        >
                        <i class="bi bi-lock auth-field-icon"></i>
                        <button type="button" class="auth-password-toggle" data-toggle-password="password" aria-label="Mostrar contraseña">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                    @error('password')
                        <div class="auth-error">
                            <i class="bi bi-exclamation-circle me-1"></i>{{ $message }}
                        </div>
                    @enderror
                </div>

                <!-- Confirm Password -->
                <div class="auth-input-wrapper">
                    <label for="password_confirmation" class="auth-label">Confirmar Contraseña</label>
                    <div class="auth-input-field">
                        <input 
                            type="password" 
                            name="password_confirmation" 
                            id="password_confirmation" 
                            class="form-control" 
                            placeholder="Repite tu contraseña" 
                            required
                        >
                        <i class="bi bi-shield-check auth-field-icon"></i>
                        <button type="button" class="auth-password-toggle" data-toggle-password="password_confirmation" aria-label="Mostrar contraseña">
                            <i class="bi bi-eye"></i>
                        </button>
                    </div>
                </div>

                <!-- Submit Button (Centered & Non-Full Width) -->
                <div class="text-center mt-4">
                    <button type="submit" class="btn btn-cine-primary btn-auth-submit">
                        <i class="bi bi-person-plus-fill me-2"></i> Crear Mi Cuenta
                    </button>
                </div>
            </form>

            <!-- Footer Link -->
            <div class="auth-footer-seamless">
                <span>¿Ya tienes una cuenta en Dharma?</span>
                <a href="{{ route('login') }}">Inicia sesión &rarr;</a>
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
