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
        <div class="stat-tile-group">
            <div class="stat-tile">
                <div class="stat-tile-value">{{ $stats['total_logged'] }}</div>
                <span class="stat-tile-label">Registros</span>
            </div>
            <div class="stat-tile">
                <div class="stat-tile-value">{{ $stats['total_hours'] }}<span class="fs-6">h</span></div>
                <span class="stat-tile-label">Tiempo</span>
            </div>
            <div class="stat-tile">
                <div class="stat-tile-value text-accent">
                    {{ $stats['avg_rating'] > 0 ? number_format($stats['avg_rating'], 1) : '—' }}
                </div>
                <span class="stat-tile-label">Promedio</span>
            </div>
            <div class="stat-tile">
                <div class="stat-tile-value text-purple">{{ $stats['total_rewatches'] }}</div>
                <span class="stat-tile-label">Re-vistas</span>
            </div>
        </div>
    </x-slot:aside>
</x-page-header>

<div class="container pb-5">
    <!-- Filtros -->
    <div class="filter-bar mb-4">
        <form action="{{ route('reviews.index') }}" method="GET" class="row g-2 align-items-center">
            <div class="col-md-3 col-sm-6">
                <label for="filter-rating" class="visually-hidden">Filtrar por calificación</label>
                <select id="filter-rating" name="rating" class="dharma-select w-100" onchange="this.form.submit()">
                    <option value="">Todas las calificaciones</option>
                    <option value="9.0" {{ $filterRating == '9.0' ? 'selected' : '' }}>9+ · Obras maestras</option>
                    <option value="8.0" {{ $filterRating == '8.0' ? 'selected' : '' }}>8+ · Muy buenas</option>
                    <option value="7.0" {{ $filterRating == '7.0' ? 'selected' : '' }}>7+ · Buenas</option>
                    <option value="5.0" {{ $filterRating == '5.0' ? 'selected' : '' }}>5+ · Pasables</option>
                </select>
            </div>
            <div class="col-md-3 col-sm-6">
                <label for="filter-status" class="visually-hidden">Filtrar por estado</label>
                <select id="filter-status" name="status" class="dharma-select w-100" onchange="this.form.submit()">
                    <option value="">Todos los estados</option>
                    <option value="watched" {{ $filterStatus == 'watched' ? 'selected' : '' }}>Vistas</option>
                    <option value="watching" {{ $filterStatus == 'watching' ? 'selected' : '' }}>En progreso</option>
                    <option value="plan_to_watch" {{ $filterStatus == 'plan_to_watch' ? 'selected' : '' }}>Por ver</option>
                    <option value="dropped" {{ $filterStatus == 'dropped' ? 'selected' : '' }}>Abandonadas</option>
                </select>
            </div>
            <div class="col-md-2 col-sm-6">
                <label for="filter-year" class="visually-hidden">Filtrar por año</label>
                <select id="filter-year" name="year" class="dharma-select w-100" onchange="this.form.submit()">
                    <option value="">Todos los años</option>
                    @foreach($years as $y)
                        <option value="{{ $y }}" {{ (string) $filterYear === (string) $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-center justify-content-md-end gap-3">
                <span class="text-secondary small">
                    {{ $reviews->total() }} {{ $reviews->total() === 1 ? 'registro' : 'registros' }}
                </span>
                @if($filterRating || $filterStatus || $filterYear)
                    <a href="{{ route('reviews.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-circle me-1"></i> Limpiar
                    </a>
                @endif
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
            @if($filterRating || $filterStatus || $filterYear)
                <i class="bi bi-funnel empty-state-icon"></i>
                <h4 class="empty-state-title">Ningún registro coincide con estos filtros</h4>
                <p class="empty-state-text">
                    Probá aflojando la calificación mínima, o cambiando el estado o el año.
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
