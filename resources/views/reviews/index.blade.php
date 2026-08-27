@extends('layouts.app')

@section('title', 'Mi Diario de Cine y Notas')

@section('content')
<div class="container">
    <!-- Header & Stats Overview -->
    <div class="row align-items-center mb-5 g-4">
        <div class="col-md-6">
            <h1 class="display-6 fw-extrabold text-white mb-2">
                <i class="bi bi-journal-bookmark text-success me-2"></i>Mi Diario de Cine
            </h1>
            <p class="text-secondary mb-0">
                Tu historial de películas y series vistas, calificaciones sobre 10 y notas personales privadas.
            </p>
        </div>
        <div class="col-md-6">
            <div class="row g-2 text-center">
                <div class="col-6 col-sm-3">
                    <div class="p-3 bg-dark border border-secondary rounded-3">
                        <div class="h3 fw-bold text-success mb-0">{{ $stats['total_logged'] }}</div>
                        <span class="small text-secondary">Registros</span>
                    </div>
                </div>
                <div class="col-6 col-sm-3">
                    <div class="p-3 bg-dark border border-secondary rounded-3">
                        <div class="h3 fw-bold text-info mb-0">{{ $stats['total_hours'] }}h</div>
                        <span class="small text-secondary">Tiempo</span>
                    </div>
                </div>
                <div class="col-6 col-sm-3">
                    <div class="p-3 bg-dark border border-secondary rounded-3">
                        <div class="h3 fw-bold text-warning mb-0">
                            {{ $stats['avg_rating'] > 0 ? number_format($stats['avg_rating'], 1) : '-' }}
                        </div>
                        <span class="small text-secondary">Promedio / 10</span>
                    </div>
                </div>
                <div class="col-6 col-sm-3">
                    <div class="p-3 bg-dark border border-secondary rounded-3">
                        <div class="h3 fw-bold text-light mb-0">{{ $stats['total_rewatches'] }}</div>
                        <span class="small text-secondary">Re-vistas</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filters Bar -->
    <div class="card bg-dark border-secondary p-3 mb-4">
        <form action="{{ route('reviews.index') }}" method="GET" class="row g-2 align-items-center">
            <div class="col-md-4 col-sm-6">
                <select name="rating" class="form-select form-select-sm bg-dark text-white border-secondary" onchange="this.form.submit()">
                    <option value="">Todas las calificaciones (1 al 10)</option>
                    <option value="9.0" {{ $filterRating == '9.0' ? 'selected' : '' }}>🌟 9+ Obras Maestras (9 - 10 / ★★★★★)</option>
                    <option value="8.0" {{ $filterRating == '8.0' ? 'selected' : '' }}>👍 8+ Muy Buenas (8+ / ★★★★☆)</option>
                    <option value="7.0" {{ $filterRating == '7.0' ? 'selected' : '' }}>👌 7+ Buenas (7+ / ★★★½☆)</option>
                    <option value="5.0" {{ $filterRating == '5.0' ? 'selected' : '' }}>⚖️ 5+ Pasables (5+ / ★★½☆☆)</option>
                </select>
            </div>
            <div class="col-md-4 col-sm-6">
                <select name="status" class="form-select form-select-sm bg-dark text-white border-secondary" onchange="this.form.submit()">
                    <option value="">Todos los estados</option>
                    <option value="watched" {{ $filterStatus == 'watched' ? 'selected' : '' }}>✓ Vistas</option>
                    <option value="watching" {{ $filterStatus == 'watching' ? 'selected' : '' }}>En progreso</option>
                    <option value="plan_to_watch" {{ $filterStatus == 'plan_to_watch' ? 'selected' : '' }}>Por ver</option>
                </select>
            </div>
            <div class="col-md-4 text-md-end">
                @if($filterRating || $filterStatus)
                    <a href="{{ route('reviews.index') }}" class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-x-circle me-1"></i> Limpiar filtros
                    </a>
                @endif
            </div>
        </form>
    </div>

    <!-- Entries List -->
    @if($reviews->count() > 0)
        <div class="d-flex flex-column gap-3 mb-5">
            @foreach($reviews as $entry)
                <div class="card bg-dark border-secondary p-3 rounded-3 hover-card">
                    <div class="row g-3 align-items-start">
                        <!-- Poster Thumbnail -->
                        <div class="col-auto">
                            <a href="{{ route('media.show', ['type' => $entry->mediaItem->media_type, 'id' => $entry->mediaItem->tmdb_id]) }}">
                                <img src="{{ $entry->mediaItem->poster_url }}" alt="{{ $entry->mediaItem->title }}" class="review-thumb rounded shadow-sm">
                            </a>
                        </div>

                        <!-- Details & Content -->
                        <div class="col min-w-0">
                            <div class="d-flex flex-wrap justify-content-between align-items-start gap-2 mb-2">
                                <div>
                                    <h5 class="fw-bold text-white mb-1">
                                        <a href="{{ route('media.show', ['type' => $entry->mediaItem->media_type, 'id' => $entry->mediaItem->tmdb_id]) }}" class="text-white text-decoration-none">
                                            {{ $entry->mediaItem->title }}
                                        </a>
                                        @if($entry->mediaItem->release_year)
                                            <span class="text-secondary small">({{ $entry->mediaItem->release_year }})</span>
                                        @endif
                                    </h5>
                                    <div class="small text-secondary d-flex flex-wrap align-items-center gap-2">
                                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                                            {{ $entry->mediaItem->media_type === 'tv' ? 'Serie' : 'Película' }}
                                        </span>
                                        @if($entry->watched_date)
                                            <span><i class="bi bi-calendar-event me-1"></i>{{ $entry->watched_date->format('d M, Y') }}</span>
                                        @endif
                                        @if($entry->is_rewatch)
                                            <span class="badge bg-dark-subtle text-info border border-info-subtle">
                                                <i class="bi bi-arrow-repeat me-1"></i>Re-visionado
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <!-- Rating & Action Menu -->
                                <div class="d-flex align-items-center gap-3">
                                    @if($entry->rating !== null)
                                        <div class="text-end">
                                            <div class="text-warning fw-bold fs-5">
                                                {{ number_format($entry->rating, 1) }}<span class="fs-6 text-secondary">/10</span>
                                            </div>
                                            <div class="small text-secondary">
                                                <span class="text-warning">{{ number_format($entry->star_rating, 1) }} ★</span>
                                                @if($entry->rating_label)
                                                    <span class="d-none d-sm-inline">({{ $entry->rating_label }})</span>
                                                @endif
                                            </div>
                                        </div>
                                    @endif

                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-outline-secondary border-0" type="button" data-bs-toggle="dropdown">
                                            <i class="bi bi-three-dots-vertical"></i>
                                        </button>
                                        <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end">
                                            <li>
                                                <button 
                                                    class="dropdown-item" 
                                                    hx-get="{{ route('reviews.modal', ['type' => $entry->mediaItem->media_type, 'id' => $entry->mediaItem->tmdb_id]) }}"
                                                    hx-target="#logModalContent"
                                                    data-bs-toggle="modal"
                                                    data-bs-target="#logModal"
                                                >
                                                    <i class="bi bi-pencil-square me-2 text-warning"></i> Editar reseña y notas
                                                </button>
                                            </li>
                                            <li><hr class="dropdown-divider border-secondary"></li>
                                            <li>
                                                <form action="{{ route('reviews.destroy', $entry) }}" method="POST" onsubmit="return confirm('¿Seguro que deseas eliminar este registro?');">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" class="dropdown-item text-danger">
                                                        <i class="bi bi-trash3 me-2"></i> Eliminar
                                                    </button>
                                                </form>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <!-- Review Text -->
                            @if($entry->review_text)
                                <div class="mb-2 text-light-emphasis small">
                                    <p class="mb-0">{{ $entry->review_text }}</p>
                                </div>
                            @endif

                            <!-- Private Notes -->
                            @if($entry->private_notes)
                                <div class="p-2 rounded bg-dark-subtle border border-warning-subtle text-secondary small mt-2">
                                    <div class="text-warning fw-semibold mb-1 d-flex align-items-center gap-1">
                                        <i class="bi bi-lock-fill"></i> Nota Privada:
                                    </div>
                                    <div>{{ $entry->private_notes }}</div>
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <!-- Pagination -->
        <div class="d-flex justify-content-center">
            {{ $reviews->withQueryString()->links() }}
        </div>
    @else
        <div class="text-center py-5 bg-dark border border-secondary rounded-4">
            <i class="bi bi-journal-x fs-1 text-secondary mb-3 d-block"></i>
            <h4 class="text-white">Aún no tienes películas o series registradas</h4>
            <p class="text-secondary mb-4">Busca cualquier película que hayas visto para comenzar a construir tu diario cinematográfico.</p>
            <a href="{{ route('home') }}" class="btn btn-cine-primary px-4 py-2">
                <i class="bi bi-compass me-1"></i> Explorar Películas
            </a>
        </div>
    @endif
</div>
@endsection
