@extends('layouts.app')

@section('title', 'Resultados de Búsqueda: ' . ($query ?: 'Explorar'))

@section('content')
<div class="container">
    <!-- Search Header -->
    <div class="mb-4">
        <h2 class="fw-bold text-white mb-2">
            @if($query)
                Resultados para: <span class="text-success">"{{ $query }}"</span>
            @else
                Buscar Películas o Series
            @endif
        </h2>
        <p class="text-secondary small">
            Explora títulos de la base de datos de películas y series de TMDB.
        </p>
    </div>

    <!-- Search Form Input -->
    <div class="card bg-dark border-secondary p-3 mb-5">
        <form action="{{ route('media.search') }}" method="GET" class="row g-2">
            <div class="col-md-10">
                <input type="text" name="q" value="{{ $query }}" class="form-control form-control-lg bg-dark text-white border-secondary" placeholder="Escribe el nombre de una película o serie..." autofocus>
            </div>
            <div class="col-md-2">
                <button type="submit" class="btn btn-cine-primary btn-lg w-100">
                    <i class="bi bi-search me-1"></i> Buscar
                </button>
            </div>
        </form>
    </div>

    <!-- Results Grid -->
    @if(count($results) > 0)
        <div class="row">
            @foreach($results as $item)
                @include('media.partials.movie-card', ['item' => $item])
            @endforeach
        </div>
    @elseif($query)
        <div class="text-center py-5">
            <i class="bi bi-film fs-1 text-secondary mb-3 d-block"></i>
            <h4 class="text-white">No se encontraron resultados para "{{ $query }}"</h4>
            <p class="text-secondary">Intenta buscar con otro título o revisa la ortografía.</p>
        </div>
    @else
        <div class="text-center py-5">
            <i class="bi bi-search fs-1 text-secondary mb-3 d-block"></i>
            <h4 class="text-white">Comienza a buscar</h4>
            <p class="text-secondary">Escribe el título de una película o serie para registrar tu reseña o notas.</p>
        </div>
    @endif
</div>
@endsection
