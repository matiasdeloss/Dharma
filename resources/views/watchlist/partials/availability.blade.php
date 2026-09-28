{{--
    Plataformas del usuario en las que el título se ve hoy (por suscripción o
    gratis). Vacío → no se pinta nada; el "no disponible" no aporta en una
    grilla y ensuciaría cada card.

    Props: $providers (array de {id, name, logo_path}, ver MediaItem::availableOn)
--}}
@if(!empty($providers))
    <div class="wl-availability" title="Disponible en {{ collect($providers)->pluck('name')->join(', ', ' y ') }}">
        <span class="wl-availability-label">Ver en</span>
        @foreach($providers as $provider)
            @if($provider['logo_path'])
                <img src="https://image.tmdb.org/t/p/w92{{ $provider['logo_path'] }}" alt="{{ $provider['name'] }}" class="wl-availability-logo" loading="lazy">
            @else
                <span class="wl-availability-label">{{ $provider['name'] }}</span>
            @endif
        @endforeach
    </div>
@endif
