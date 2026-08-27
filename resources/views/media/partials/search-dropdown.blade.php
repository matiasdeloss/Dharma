@if(count($results) > 0)
    <div class="list-group bg-dark border border-secondary shadow-lg rounded-3 overflow-hidden">
        @foreach($results as $item)
            @php
                $title = $item['title'] ?? $item['name'] ?? 'Sin título';
                $type = $item['media_type'] ?? 'movie';
                $year = isset($item['release_date']) ? substr($item['release_date'], 0, 4) : (isset($item['first_air_date']) ? substr($item['first_air_date'], 0, 4) : null);
                $poster = !empty($item['poster_path']) 
                    ? config('services.tmdb.image_base_url', 'https://image.tmdb.org/t/p') . '/w92' . $item['poster_path']
                    : asset('images/no-poster.svg');
            @endphp
            <a href="{{ route('media.show', ['type' => $type, 'id' => $item['id']]) }}" class="list-group-item list-group-item-action bg-dark text-white border-secondary-subtle p-2 d-flex align-items-center gap-3 search-item">
                <img src="{{ $poster }}" alt="{{ $title }}" class="search-item-thumb rounded">
                <div class="flex-grow-1 min-w-0">
                    <div class="fw-bold text-truncate">{{ $title }}</div>
                    <div class="small text-secondary d-flex align-items-center gap-2">
                        <span class="badge bg-secondary-subtle text-secondary border border-secondary-subtle">
                            {{ $type === 'tv' ? 'Serie' : 'Película' }}
                        </span>
                        @if($year)
                            <span>{{ $year }}</span>
                        @endif
                        @if(isset($item['vote_average']) && $item['vote_average'] > 0)
                            <span class="text-warning"><i class="bi bi-star-fill me-1"></i>{{ number_format($item['vote_average'], 1) }}</span>
                        @endif
                    </div>
                </div>
            </a>
        @endforeach
        <a href="{{ route('media.search', ['q' => $query]) }}" class="list-group-item list-group-item-action bg-dark-subtle text-center text-success py-2 fw-semibold small">
            Ver todos los resultados para "{{ $query }}" <i class="bi bi-arrow-right ms-1"></i>
        </a>
    </div>
@else
    <div class="p-3 text-center text-secondary bg-dark border border-secondary rounded-3 shadow">
        <i class="bi bi-search me-1"></i> No se encontraron resultados para "{{ $query }}"
    </div>
@endif
