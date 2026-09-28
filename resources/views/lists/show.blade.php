@extends('layouts.app')

@section('title', $list->name)

@section('content')
<x-page-header
    :title="$list->name"
    :subtitle="$list->description"
    icon="bi-collection"
    :backdrop="$headerBackdrop"
>
    <x-slot:aside>
        @include('lists.partials.list-stats', ['counts' => $counts])
    </x-slot:aside>
</x-page-header>

<div class="container pb-5">
    <div class="filter-bar mb-4 d-flex flex-wrap align-items-center justify-content-between gap-3">
        <div class="d-flex flex-wrap align-items-center gap-3">
            <a href="{{ route('lists.index') }}" class="text-secondary small text-decoration-none">
                <i class="bi bi-arrow-left me-1"></i> Mis listas
            </a>
            @if($list->is_ranked)
                <span class="diary-chip"><i class="bi bi-list-ol"></i> Numerada</span>
            @endif
        </div>

        <div class="d-flex align-items-center gap-3">
            @if(count($items) > 1)
                <span class="list-drag-hint d-none d-md-inline">
                    <i class="bi bi-arrows-move me-1"></i> Arrastrá los títulos para ordenarlos
                </span>
            @endif
            <button
                type="button"
                class="btn-cine-secondary btn-pill-compact"
                hx-get="{{ route('lists.edit', $list) }}"
                hx-target="#logModalContent"
                data-bs-toggle="modal"
                data-bs-target="#logModal"
            >
                <i class="bi bi-pencil"></i> Editar
            </button>
        </div>
    </div>

    @include('lists.partials.items-grid', ['list' => $list, 'items' => $items])
</div>
@endsection
