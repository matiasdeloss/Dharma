@extends('layouts.app')

@section('title', 'Explorar: qué ver hoy')

@section('content')
<x-page-header
    title="Explorar"
    subtitle="Qué ver: lo mejor de cada plataforma, filtrado por género, año y dónde lo tenés disponible."
    icon="bi-compass"
/>

<div class="container pb-5">
    {{-- Filtros. Cualquier cambio manda el form (GET) y recarga. --}}
    <form action="{{ route('explore.index') }}" method="GET" class="filter-bar explore-filters mb-4">
        <div class="explore-type" role="group" aria-label="Tipo">
            <a href="{{ route('explore.index', array_filter(['tipo' => 'movie', 'genero' => null, 'anio' => $year, 'plataforma' => $platform, 'orden' => $sort !== 'popular' ? $sort : null])) }}" class="explore-type-btn {{ $type === 'movie' ? 'is-on' : '' }}">Películas</a>
            <a href="{{ route('explore.index', array_filter(['tipo' => 'tv', 'genero' => null, 'anio' => $year, 'plataforma' => $platform, 'orden' => $sort !== 'popular' ? $sort : null])) }}" class="explore-type-btn {{ $type === 'tv' ? 'is-on' : '' }}">Series</a>
        </div>
        <input type="hidden" name="tipo" value="{{ $type }}">

        <label for="f-genero" class="visually-hidden">Género</label>
        <select id="f-genero" name="genero" class="dharma-select" onchange="this.form.submit()">
            <option value="">Todos los géneros</option>
            @foreach($genres as $g)
                <option value="{{ $g['id'] }}" @selected($genre === (int) $g['id'])>{{ $g['name'] }}</option>
            @endforeach
        </select>

        <label for="f-anio" class="visually-hidden">Año</label>
        <select id="f-anio" name="anio" class="dharma-select" onchange="this.form.submit()">
            <option value="">Cualquier año</option>
            @foreach($years as $y)
                <option value="{{ $y }}" @selected($year === $y)>{{ $y }}</option>
            @endforeach
        </select>

        <label for="f-plataforma" class="visually-hidden">Plataforma</label>
        <select id="f-plataforma" name="plataforma" class="dharma-select" onchange="this.form.submit()">
            <option value="">Cualquier plataforma</option>
            @foreach($providerOptions as $value => $label)
                <option value="{{ $value }}" @selected((string) $platform === (string) $value)>{{ $label }}</option>
            @endforeach
        </select>

        <label for="f-orden" class="visually-hidden">Orden</label>
        <select id="f-orden" name="orden" class="dharma-select" onchange="this.form.submit()">
            <option value="popular" @selected($sort === 'popular')>Más populares</option>
            <option value="valoradas" @selected($sort === 'valoradas')>Mejor valoradas</option>
            <option value="recientes" @selected($sort === 'recientes')>Más recientes</option>
        </select>

        @if($filtering)
            <a href="{{ route('explore.index', ['tipo' => $type]) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-x-circle me-1"></i> Limpiar
            </a>
        @endif
    </form>

    @if(!$hasMyProviders)
        <p class="explore-hint">
            <i class="bi bi-tv text-accent me-1"></i>
            <a href="{{ auth()->check() ? route('settings.edit') : route('login') }}">Elegí tus plataformas</a>
            y acá te mostramos primero lo que podés ver hoy.
        </p>
    @endif

    @if($filtering)
        {{-- Modo grilla: resultados paginados de discover --}}
        @if(count($results) > 0)
            <div class="search-meta">
                <span><strong>{{ number_format(min($totalResults, 10000)) }}</strong> {{ $totalResults === 1 ? 'título' : 'títulos' }}</span>
                @if($totalPages > 1)
                    <span class="search-meta-sep">&bull;</span>
                    <span>Página <strong>{{ $page }}</strong> de <strong>{{ min($totalPages, 500) }}</strong></span>
                @endif
            </div>

            <div class="row">
                @foreach($results as $item)
                    @include('media.partials.movie-card', ['item' => $item, 'type' => $type])
                @endforeach
            </div>

            @include('media.partials.tmdb-pagination', [
                'page' => $page,
                'totalPages' => $totalPages,
                'route' => 'explore.index',
                'params' => array_filter(['tipo' => $type, 'genero' => $genre, 'anio' => $year, 'plataforma' => $platform, 'orden' => $sort]),
            ])
        @else
            <div class="empty-state">
                <i class="bi bi-film empty-state-icon"></i>
                <h4 class="empty-state-title">Nada con esos filtros</h4>
                <p class="empty-state-text">Probá con otro género o año, o sacá el filtro de plataforma.</p>
                <a href="{{ route('explore.index', ['tipo' => $type]) }}" class="btn btn-cine-secondary px-4 py-2">Limpiar filtros</a>
            </div>
        @endif
    @else
        {{-- Para vos: a partir de las mejores notas del diario --}}
        @if(!empty($forYou['items']))
            @include('media.partials.slider', [
                'title' => 'Para vos',
                'subtitle' => 'Porque te gustaron ' . collect($forYou['seeds'])->join(', ', ' y '),
                'items' => $forYou['items'],
                'type' => null,
            ])
        @elseif(auth()->check())
            <p class="explore-hint">
                <i class="bi bi-stars text-accent me-1"></i>
                Calificá algunas películas o series en tu diario y acá aparece una fila <strong>Para vos</strong>, armada a partir de tus mejores notas.
            </p>
        @endif

        {{-- Modo curado: una fila por plataforma --}}
        @forelse($sections as $section)
            @include('media.partials.slider', [
                'title' => $section['title'],
                'subtitle' => $section['subtitle'],
                'items' => $section['items'],
                'type' => $type,
                'logo' => $section['logo'],
                'moreUrl' => $section['more'],
            ])
        @empty
            <div class="empty-state">
                <i class="bi bi-tv empty-state-icon"></i>
                <h4 class="empty-state-title">Sin catálogo para tu región</h4>
                <p class="empty-state-text">TMDB no tiene datos de streaming para esta región todavía. Podés cambiarla en <a href="{{ route('settings.edit') }}">Ajustes</a>.</p>
            </div>
        @endforelse
    @endif
</div>
@endsection
