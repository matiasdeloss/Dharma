@extends('layouts.app')

@section('title', 'Mi Diario de Cine y Notas')

@section('content')
<x-page-header
    title="Mi Diario de Cine"
    subtitle="Tu historial de películas y series vistas, calificaciones sobre 10 y notas personales privadas."
    icon="bi-journal-bookmark"
    :backdrop="$headerBackdrop"
>
    <x-slot:aside>
        @include('reviews.partials.diary-stats', ['stats' => $stats])
    </x-slot:aside>
</x-page-header>

<div class="container pb-5">
    <!-- Filtros -->
    <div class="filter-bar mb-4">
        <form action="{{ route('reviews.index') }}" method="GET" class="row g-2 align-items-center">
            {{-- Busca en títulos, reseñas y notas privadas (ReviewController::matchesSearch). Se manda con Enter. --}}
            <div class="col-12 col-lg">
                <label for="filter-search" class="visually-hidden">Buscar en tu diario</label>
                <div class="dharma-search">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input
                        type="search"
                        id="filter-search"
                        name="buscar"
                        value="{{ $filterSearch }}"
                        class="modal-form-control dharma-search-input w-100"
                        placeholder="Buscar en tu diario: títulos, reseñas, notas..."
                        autocomplete="off"
                    >
                </div>
            </div>
            <div class="col-sm-6 col-lg-auto">
                <label for="filter-rating" class="visually-hidden">Filtrar por calificación</label>
                <select id="filter-rating" name="rating" class="dharma-select w-100" onchange="this.form.submit()">
                    <option value="">Todas las calificaciones</option>
                    <option value="9.0" {{ $filterRating == '9.0' ? 'selected' : '' }}>9+ · Obras maestras</option>
                    <option value="8.0" {{ $filterRating == '8.0' ? 'selected' : '' }}>8+ · Muy buenas</option>
                    <option value="7.0" {{ $filterRating == '7.0' ? 'selected' : '' }}>7+ · Buenas</option>
                    <option value="5.0" {{ $filterRating == '5.0' ? 'selected' : '' }}>5+ · Pasables</option>
                </select>
            </div>
            <div class="col-sm-6 col-lg-auto">
                <label for="filter-year" class="visually-hidden">Filtrar por año</label>
                <select id="filter-year" name="year" class="dharma-select w-100" onchange="this.form.submit()">
                    <option value="">Todos los años</option>
                    @foreach($years as $y)
                        <option value="{{ $y }}" {{ (string) $filterYear === (string) $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-12 col-lg-auto d-flex align-items-center justify-content-lg-end gap-3">
                <span class="text-secondary small text-nowrap">
                    {{ $reviews->total() }} {{ $reviews->total() === 1 ? 'registro' : 'registros' }}
                </span>
                @if($filterRating || $filterYear || $filterSearch)
                    <a href="{{ route('reviews.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-circle me-1"></i> Limpiar
                    </a>
                @endif
                <a href="{{ route('reviews.stats', array_filter(['year' => $filterYear])) }}" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-bar-chart-line me-1"></i> Estadísticas
                </a>
            </div>
        </form>
    </div>

    @if($reviews->count() > 0)
        {{-- #diary-list es el destino del fragmento out-of-band de una entrada
             recién creada: al guardar un re-visionado desde el diario, la fila
             nueva se antepone acá sin recargar. --}}
        <div id="diary-list" class="d-flex flex-column gap-3 mb-5">
            @foreach($reviews as $entry)
                @include('reviews.partials.diary-entry', ['entry' => $entry])
            @endforeach
        </div>

        <div class="d-flex justify-content-center">
            {{ $reviews->withQueryString()->links() }}
        </div>
    @else
        <div class="empty-state">
            @if($filterSearch)
                <i class="bi bi-search empty-state-icon"></i>
                <h4 class="empty-state-title">Nada en tu diario coincide con «{{ $filterSearch }}»</h4>
                <p class="empty-state-text">
                    La búsqueda mira títulos, reseñas y notas privadas. Probá con otra palabra{{ $filterRating || $filterYear ? ' o sin los filtros' : '' }}.
                </p>
                <a href="{{ route('reviews.index') }}" class="btn btn-cine-secondary px-4 py-2">
                    <i class="bi bi-x-circle me-1"></i> Limpiar búsqueda
                </a>
            @elseif($filterRating || $filterYear)
                <i class="bi bi-funnel empty-state-icon"></i>
                <h4 class="empty-state-title">Ningún registro coincide con estos filtros</h4>
                <p class="empty-state-text">
                    Probá aflojando la calificación mínima o cambiando el año.
                </p>
                <a href="{{ route('reviews.index') }}" class="btn btn-cine-secondary px-4 py-2">
                    <i class="bi bi-x-circle me-1"></i> Limpiar filtros
                </a>
            @else
                <i class="bi bi-journal-x empty-state-icon"></i>
                <h4 class="empty-state-title">Tu diario todavía está en blanco</h4>
                <p class="empty-state-text">
                    Buscá cualquier película o serie que hayas visto, calificala del 1 al 10 y guardá tus notas.
                    Todo lo que registres va a aparecer acá.
                </p>
                <a href="{{ route('home') }}" class="btn btn-cine-primary px-4 py-2">
                    <i class="bi bi-compass me-1"></i> Explorar títulos
                </a>
            @endif
        </div>
    @endif
</div>
@endsection
