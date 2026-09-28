{{--
    Contenido del panel lateral de una persona (offcanvas #personPanel).
    Lo carga PersonController::show por HTMX al tocar una card del reparto.

    Props: $person (array TMDB con combined_credits), $isActor (bool),
           $credits (array, ya con in_watchlist / my_rating), $totalCredits (int)
--}}
@php
    $imageBase = config('services.tmdb.image_base_url', 'https://image.tmdb.org/t/p');
    $photo = !empty($person['profile_path']) ? $imageBase . '/w185' . $person['profile_path'] : null;

    $departments = [
        'Acting' => 'Actúa',
        'Directing' => 'Dirige',
        'Writing' => 'Escribe',
        'Production' => 'Produce',
        'Sound' => 'Música / sonido',
        'Camera' => 'Fotografía',
        'Editing' => 'Montaje',
        'Art' => 'Arte',
        'Visual Effects' => 'Efectos visuales',
    ];
    $department = $departments[$person['known_for_department'] ?? ''] ?? ($person['known_for_department'] ?? null);

    $birth = !empty($person['birthday']) ? \Carbon\Carbon::parse($person['birthday']) : null;
    $death = !empty($person['deathday']) ? \Carbon\Carbon::parse($person['deathday']) : null;
    // (int): desde Carbon 3 diffInYears devuelve la fracción (54.9046...).
    $age = $birth ? (int) $birth->diffInYears($death ?? now()) : null;

    $bio = trim((string) ($person['biography'] ?? ''));
@endphp

<div class="person-head">
    @if($photo)
        <img src="{{ $photo }}" alt="{{ $person['name'] }}" class="person-photo" loading="lazy">
    @else
        <span class="person-photo person-photo-empty"><i class="bi bi-person"></i></span>
    @endif
    <div class="person-head-body">
        <h2 class="person-name">{{ $person['name'] }}</h2>
        <div class="person-meta">
            @if($department)
                <span>{{ $department }}</span>
            @endif
            @if($birth)
                <span>· {{ $birth->translatedFormat('Y') }}{{ $death ? '–' . $death->translatedFormat('Y') : '' }} ({{ $age }} años)</span>
            @endif
            @if(!empty($person['place_of_birth']))
                <span class="person-meta-place">{{ $person['place_of_birth'] }}</span>
            @endif
        </div>
    </div>
</div>

@if($bio !== '')
    <p class="person-bio">{{ \Illuminate\Support\Str::limit($bio, 420) }}</p>
@endif

<div class="person-credits-head">
    <span class="person-credits-title">{{ $isActor ? 'También actúa en' : 'También trabajó en' }}</span>
    <span class="person-credits-count">{{ $totalCredits }} {{ $totalCredits === 1 ? 'título' : 'títulos' }}{{ $totalCredits > count($credits) ? ', los ' . count($credits) . ' más conocidos' : '' }}</span>
</div>

@if(count($credits) === 0)
    <p class="text-secondary small">TMDB no tiene otros títulos cargados.</p>
@else
    <ul class="person-credits">
        @foreach($credits as $credit)
            @php
                $type = $credit['media_type'];
                $title = $credit['title'] ?? $credit['name'] ?? 'Sin título';
                $date = $credit['release_date'] ?? $credit['first_air_date'] ?? null;
                $year = $date ? substr($date, 0, 4) : null;
                $poster = !empty($credit['poster_path']) ? $imageBase . '/w92' . $credit['poster_path'] : asset('images/no-poster.svg');
            @endphp
            <li>
                <a href="{{ route('media.show', ['type' => $type, 'id' => $credit['id']]) }}" class="person-credit">
                    <img src="{{ $poster }}" alt="" class="person-credit-poster" loading="lazy">
                    <span class="person-credit-body">
                        <span class="person-credit-title">{{ $title }}</span>
                        <span class="person-credit-meta">
                            @if($year){{ $year }} · @endif{{ $type === 'tv' ? 'Serie' : 'Película' }}
                            @if(!empty($credit['roles']))
                                · {{ implode(', ', $credit['roles']) }}
                            @endif
                        </span>
                    </span>
                    @if($credit['my_rating'] !== null)
                        <span class="person-credit-mine" title="Tu nota">{{ number_format($credit['my_rating'], 1) }}</span>
                    @elseif($credit['in_watchlist'])
                        <i class="bi bi-bookmark-fill person-credit-wl" title="En tu watchlist"></i>
                    @endif
                </a>
            </li>
        @endforeach
    </ul>
@endif
