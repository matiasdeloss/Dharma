@extends('layouts.app')

@section('title', $query ? 'Resultados: ' . $query : 'Buscar películas y series')

@section('content')
@php
    // La rama de búsqueda vacía del controller no manda estas dos, así que se
    // resuelven acá en vez de asumirlas.
    $currentPage = (int) ($page ?? 1);
    $lastPage = min((int) ($totalPages ?? 1), 500);
    $count = count($results);
@endphp

<div class="container pb-5">
    <div class="search-header">
        <span class="search-eyebrow">Buscar</span>

        <h1 class="search-title">
            @if($query)
                Resultados para <span class="search-term">&ldquo;{{ $query }}&rdquo;</span>
            @else
                ¿Qué viste últimamente?
            @endif
        </h1>

        <form action="{{ route('media.search') }}" method="GET" class="search-field-form" role="search">
            <div class="search-field-wrapper">
                <i class="bi bi-search search-field-icon" aria-hidden="true"></i>
                <label for="search-q" class="visually-hidden">Buscar películas o series</label>
                <input
                    type="text"
                    name="q"
                    id="search-q"
                    value="{{ $query }}"
                    class="search-field"
                    placeholder="Escribí el nombre de una película o serie…"
                    autocomplete="off"
                    autofocus
                >
            </div>
            <button type="submit" class="btn btn-cine-primary search-submit">
                Buscar
            </button>
        </form>
    </div>

    @if($count > 0)
        <div class="search-meta">
            <span><strong>{{ $count }}</strong> {{ $count === 1 ? 'título' : 'títulos' }} en esta página</span>
            @if($lastPage > 1)
                <span class="search-meta-sep">&bull;</span>
                <span>Página <strong>{{ $currentPage }}</strong> de <strong>{{ $lastPage }}</strong></span>
            @endif
        </div>

        <div class="row">
            @foreach($results as $item)
                @include('media.partials.movie-card', ['item' => $item])
            @endforeach
        </div>

        @include('media.partials.tmdb-pagination', [
            'page' => $currentPage,
            'totalPages' => $lastPage,
            'query' => $query,
        ])
    @elseif($query)
        <div class="empty-state">
            <i class="bi bi-film empty-state-icon"></i>
            <h4 class="empty-state-title">Sin resultados para &ldquo;{{ $query }}&rdquo;</h4>
            <p class="empty-state-text">
                Probá con el título original, o revisá la ortografía. La búsqueda cubre
                películas y series de TMDB.
            </p>
            <a href="{{ route('home') }}" class="btn btn-cine-secondary px-4 py-2">
                <i class="bi bi-compass me-1"></i> Volver a explorar
            </a>
        </div>
    @else
        <div class="empty-state">
            <i class="bi bi-search empty-state-icon"></i>
            <h4 class="empty-state-title">Buscá lo que quieras registrar</h4>
            <p class="empty-state-text">
                Escribí el título de una película o serie para calificarla, escribir tu
                reseña o guardarla en la watchlist.
            </p>
        </div>
    @endif
</div>
@endsection
