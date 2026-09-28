@extends('layouts.app')

@section('title', 'Mis listas')

@section('content')
<x-page-header
    title="Mis listas"
    subtitle="Tus colecciones: rankings, maratones, lo que quieras agrupar."
    icon="bi-collection"
    :backdrop="$headerBackdrop"
>
    <x-slot:aside>
        <div class="stat-tile-group">
            <div class="stat-tile">
                <div class="stat-tile-value">{{ $lists->count() }}</div>
                <span class="stat-tile-label">{{ $lists->count() === 1 ? 'Lista' : 'Listas' }}</span>
            </div>
            <div class="stat-tile">
                <div class="stat-tile-value">{{ $totalTitles }}</div>
                <span class="stat-tile-label">Títulos</span>
            </div>
        </div>
    </x-slot:aside>
</x-page-header>

<div class="container pb-5">
    @if($lists->isNotEmpty())
        <div class="filter-bar mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
            <span class="text-secondary small">
                {{ $lists->count() }} {{ $lists->count() === 1 ? 'lista' : 'listas' }}
            </span>
            <button
                type="button"
                class="btn-cine-primary btn-pill-compact"
                hx-get="{{ route('lists.create') }}"
                hx-target="#logModalContent"
                data-bs-toggle="modal"
                data-bs-target="#logModal"
            >
                <i class="bi bi-plus-lg"></i> Nueva lista
            </button>
        </div>

        <div class="row g-4">
            @foreach($lists as $list)
                <div class="col-md-6 col-lg-4">
                    @include('lists.partials.list-card', ['list' => $list])
                </div>
            @endforeach
        </div>
    @else
        <div class="empty-state">
            <i class="bi bi-collection empty-state-icon"></i>
            <h4 class="empty-state-title">Todavía no armaste ninguna lista</h4>
            <p class="empty-state-text">
                Rankings, maratones, pendientes para ver con amigos: agrupá películas y series como quieras.
                Creá una acá o desde la ficha de cualquier título, con «Agregar a lista».
            </p>
            <button
                type="button"
                class="btn btn-cine-primary px-4 py-2"
                hx-get="{{ route('lists.create') }}"
                hx-target="#logModalContent"
                data-bs-toggle="modal"
                data-bs-target="#logModal"
            >
                <i class="bi bi-plus-lg me-1"></i> Crear mi primera lista
            </button>
        </div>
    @endif
</div>
@endsection
