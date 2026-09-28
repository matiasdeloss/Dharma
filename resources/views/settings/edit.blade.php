@extends('layouts.app')

@section('title', 'Plataformas y región')

@section('content')
<x-page-header
    title="Plataformas y región"
    subtitle="Elegí dónde ves películas y series. Dharma lo usa para marcarte qué podés ver hoy y para recomendarte cosas que tengas a mano."
    icon="bi-tv"
/>

<div class="container pb-5">
    @if($bienvenida)
        {{-- Primer paso despues de registrarse. Salteable: el usuario puede
             volver a esta pantalla desde el menu de su cuenta. --}}
        <div class="settings-welcome mb-4">
            <div>
                <strong class="text-white">¡Bienvenido a Dharma!</strong>
                <span class="text-secondary">Un solo paso antes de empezar: marcá tus plataformas. Podés cambiarlas cuando quieras desde el menú de tu cuenta.</span>
            </div>
            <a href="{{ route('home') }}" class="btn btn-cine-ghost btn-pill-compact text-nowrap">Saltar por ahora</a>
        </div>
    @endif

    <form action="{{ route('settings.update') }}" method="POST" class="settings-form">
        @csrf
        @method('PUT')

        {{-- Region: cambiarla recarga la lista de plataformas (sin guardar) --}}
        <div class="settings-block">
            <div class="settings-block-head">
                <h2 class="settings-block-title">Región</h2>
                <p class="settings-block-help">La disponibilidad cambia según el país. Al cambiarla se actualiza la lista de plataformas.</p>
            </div>
            <label for="settings-region" class="visually-hidden">Región</label>
            <select
                id="settings-region"
                name="region"
                class="dharma-select settings-region"
                onchange="location.href = '{{ route('settings.edit') }}?region=' + encodeURIComponent(this.value){{ $bienvenida ? " + '&bienvenida=1'" : '' }}"
            >
                @foreach($regions as $iso => $name)
                    <option value="{{ $iso }}" @selected($region === $iso)>{{ $name }}</option>
                @endforeach
            </select>
            @error('region')
                <div class="text-danger small mt-2">{{ $message }}</div>
            @enderror
        </div>

        {{-- Plataformas: chips con logo, checkbox oculto --}}
        <div class="settings-block">
            <div class="settings-block-head">
                <h2 class="settings-block-title">Mis plataformas</h2>
                <p class="settings-block-help">Marcá las que tenés contratadas. Podés dejarlo vacío.</p>
            </div>

            @if(count($providers) === 0)
                <p class="text-secondary mb-0">TMDB no tiene plataformas cargadas para esta región.</p>
            @else
                <div class="provider-grid" id="provider-grid">
                    @foreach($providers as $provider)
                        <label class="provider-chip">
                            <input
                                type="checkbox"
                                name="providers[]"
                                value="{{ $provider['provider_id'] }}"
                                class="provider-chip-input"
                                @checked(in_array($provider['provider_id'], $selected, true))
                            >
                            @if($provider['logo_url'])
                                <img src="{{ $provider['logo_url'] }}" alt="" class="provider-chip-logo" loading="lazy">
                            @else
                                <span class="provider-chip-logo provider-chip-logo-empty"><i class="bi bi-tv"></i></span>
                            @endif
                            <span class="provider-chip-name">{{ $provider['provider_name'] }}</span>
                            <i class="bi bi-check-circle-fill provider-chip-check" aria-hidden="true"></i>
                        </label>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="settings-actions">
            <button type="submit" class="btn btn-cine-primary px-4 py-2">
                <i class="bi bi-check-lg me-2"></i> Guardar
            </button>
            @if($bienvenida)
                <a href="{{ route('home') }}" class="btn btn-cine-ghost px-4 py-2">Saltar</a>
            @endif
        </div>
    </form>
</div>
@endsection
