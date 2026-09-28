@extends('layouts.app')

@section('title', 'Tu diario en números')

@section('content')
@php
    $monthNames = ['ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];
    $monthLabels = collect($byMonth)->keys()->mapWithKeys(function ($ym) use ($monthNames) {
        [$y, $m] = explode('-', $ym);
        return [$ym => $monthNames[(int) $m - 1] . ($m === '01' ? " '" . substr($y, 2) : '')];
    })->all();
    $decadeLabels = collect($byDecade)->keys()->mapWithKeys(fn ($d) => [$d => "{$d}s"])->all();
    $period = $year ? "en {$year}" : 'en todo tu historial';
@endphp

<x-page-header
    title="Tu diario en números"
    subtitle="Qué calificás, cuándo mirás y de qué décadas y géneros son las cosas que ves."
    icon="bi-bar-chart-line"
    :backdrop="$headerBackdrop"
>
    <x-slot:aside>
        <div class="stat-tile-group">
            <div class="stat-tile">
                <div class="stat-tile-value">{{ $totals['logged'] }}</div>
                <span class="stat-tile-label">Registros</span>
            </div>
            <div class="stat-tile">
                <div class="stat-tile-value text-accent">{{ $totals['avg_rating'] > 0 ? number_format($totals['avg_rating'], 1) : '—' }}</div>
                <span class="stat-tile-label">Promedio</span>
            </div>
            <div class="stat-tile">
                <div class="stat-tile-value">{{ $totals['movies'] }}</div>
                <span class="stat-tile-label">Películas</span>
            </div>
            <div class="stat-tile">
                <div class="stat-tile-value text-purple">{{ $totals['tv'] }}</div>
                <span class="stat-tile-label">Series</span>
            </div>
        </div>
    </x-slot:aside>
</x-page-header>

<div class="container pb-5">
    <div class="filter-bar mb-4">
        <form action="{{ route('reviews.stats') }}" method="GET" class="d-flex flex-wrap align-items-center justify-content-between gap-3">
            <div class="d-flex align-items-center gap-2">
                <label for="stats-year" class="visually-hidden">Período</label>
                <select id="stats-year" name="year" class="dharma-select" onchange="this.form.submit()">
                    <option value="">Todo el historial</option>
                    @foreach($years as $y)
                        <option value="{{ $y }}" @selected((string) $year === (string) $y)>{{ $y }}</option>
                    @endforeach
                </select>
            </div>
            <a href="{{ route('reviews.index', array_filter(['year' => $year])) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-journal-text me-1"></i> Ver el diario
            </a>
        </form>
    </div>

    @if($totals['logged'] === 0)
        <div class="empty-state">
            <i class="bi bi-bar-chart empty-state-icon"></i>
            <h4 class="empty-state-title">Todavía no hay nada que contar {{ $period }}</h4>
            <p class="empty-state-text">Calificá lo que vas viendo y acá aparecen tus patrones: notas, meses, géneros y décadas.</p>
            <a href="{{ route('explore.index') }}" class="btn btn-cine-primary px-4 py-2"><i class="bi bi-compass me-1"></i> Explorar</a>
        </div>
    @else
        @if($genresMissing)
            <div class="alert alert-dark border-warning text-light-emphasis d-flex align-items-center gap-3 mb-4 p-3 rounded-3" role="alert">
                <i class="bi bi-info-circle-fill text-warning fs-4"></i>
                <div class="small">
                    Algunos títulos todavía no tienen género cargado, así que el gráfico de géneros está incompleto.
                    Se completa solo la próxima vez que se toquen, o de una con <code>php artisan dharma:completar-media</code>.
                </div>
            </div>
        @endif

        <div class="stats-grid">
            <section class="stats-card">
                <h2 class="stats-card-title">Tus notas</h2>
                <p class="stats-card-help">Cuántos títulos calificaste con cada nota {{ $period }}.</p>
                @include('charts.bars', ['data' => $histogram, 'title' => 'Cantidad de títulos por nota', 'unit' => 'títulos'])
            </section>

            <section class="stats-card">
                <h2 class="stats-card-title">Actividad por mes</h2>
                <p class="stats-card-help">{{ $year ? "Registros por mes de {$year}." : 'Registros en los últimos 12 meses.' }}</p>
                @include('charts.bars', ['data' => $byMonth, 'labels' => $monthLabels, 'title' => 'Registros por mes', 'unit' => 'registros'])
            </section>

            <section class="stats-card">
                <h2 class="stats-card-title">Géneros más vistos</h2>
                <p class="stats-card-help">Un título cuenta en cada uno de sus géneros.</p>
                @if($byGenre->isEmpty())
                    <p class="text-secondary small mb-0">Sin datos de género todavía.</p>
                @else
                    @include('charts.hbars', ['data' => $byGenre, 'title' => 'Géneros más vistos'])
                @endif
            </section>

            <section class="stats-card">
                <h2 class="stats-card-title">Por década de estreno</h2>
                <p class="stats-card-help">De qué época es lo que mirás.</p>
                @if($byDecade->isEmpty())
                    <p class="text-secondary small mb-0">Sin fechas de estreno todavía.</p>
                @else
                    @include('charts.bars', ['data' => $byDecade, 'labels' => $decadeLabels, 'title' => 'Títulos por década de estreno', 'unit' => 'títulos'])
                @endif
            </section>

            @if($top->isNotEmpty())
                <section class="stats-card stats-card-wide">
                    <h2 class="stats-card-title">Lo mejor que viste {{ $period }}</h2>
                    <ol class="stats-top">
                        @foreach($top as $entry)
                            <li class="stats-top-item">
                                <span class="stats-top-rank">{{ $loop->iteration }}</span>
                                <a href="{{ route('media.show', ['type' => $entry->mediaItem->media_type, 'id' => $entry->mediaItem->tmdb_id]) }}" class="stats-top-title">{{ $entry->mediaItem->title }}</a>
                                <span class="stats-top-rating">{{ number_format($entry->rating, 1) }}<span class="stats-top-scale">/10</span></span>
                            </li>
                        @endforeach
                    </ol>
                </section>
            @endif
        </div>
    @endif
</div>
@endsection
