@php
    $actorImage = !empty($actor['profile_path'])
        ? ($imageBase ?? config('services.tmdb.image_base_url', 'https://image.tmdb.org/t/p')) . '/w185' . $actor['profile_path']
        : asset('images/no-poster.svg');
@endphp

<div class="cast-card">
    <img src="{{ $actorImage }}" alt="{{ $actor['name'] }}" class="cast-avatar" loading="lazy">
    <div class="cast-card-body">
        <span class="cast-name text-truncate">{{ $actor['name'] }}</span>
        @if(!empty($actor['character']))
            <span class="cast-role text-truncate" title="{{ $actor['character'] }}">{{ $actor['character'] }}</span>
        @elseif(!empty($actor['roles'][0]['character']))
            <span class="cast-role text-truncate">{{ $actor['roles'][0]['character'] }}</span>
        @endif
    </div>
</div>
