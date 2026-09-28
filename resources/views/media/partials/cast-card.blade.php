@php
    $actorImage = !empty($actor['profile_path'])
        ? ($imageBase ?? config('services.tmdb.image_base_url', 'https://image.tmdb.org/t/p')) . '/w185' . $actor['profile_path']
        : asset('images/no-poster.svg');
    $role = $actor['character'] ?? ($actor['roles'][0]['character'] ?? null);
@endphp

{{-- Al tocar, se carga la persona por HTMX en el panel lateral #personPanel
     (offcanvas del layout). Sin id (mock viejo) queda como texto. --}}
@if(!empty($actor['id']))
    <button
        type="button"
        class="cast-card cast-card-btn"
        hx-get="{{ route('person.show', $actor['id']) }}"
        hx-target="#personPanelContent"
        hx-indicator="#personPanelContent"
        data-bs-toggle="offcanvas"
        data-bs-target="#personPanel"
        title="Ver más de {{ $actor['name'] }}"
    >
@else
    <div class="cast-card">
@endif
    <img src="{{ $actorImage }}" alt="{{ $actor['name'] }}" class="cast-avatar" loading="lazy">
    <div class="cast-card-body">
        <span class="cast-name text-truncate">{{ $actor['name'] }}</span>
        @if($role)
            <span class="cast-role text-truncate" title="{{ $role }}">{{ $role }}</span>
        @endif
    </div>
    @if(!empty($actor['id']))
        <i class="bi bi-chevron-right cast-card-chevron" aria-hidden="true"></i>
    </button>
@else
    </div>
@endif
