{{--
    Carrusel horizontal de cards (una fila del home o de Explorar).

    Props:
      $title, $subtitle          Encabezado de la sección.
      $items                     Resultados de TMDB (con `in_watchlist` ya cruzado).
      $type                      'movie' | 'tv' | null (si null, cada item trae su media_type).
      $ranked                    true → numera las cards (#1, #2, ...).
      $emptyText                 Mensaje si no hay items.
      $moreUrl, $moreText        Link opcional "Ver todo" a la derecha del título.
      $logo                      URL opcional de un logo (plataforma) al lado del título.

    El JS (modules/sliders.js) engancha por `.media-slider-container`.
--}}
<div class="media-slider-container mb-5">
    <div class="media-slider-header">
        <div class="d-flex align-items-center gap-2">
            @if(!empty($logo))
                <img src="{{ $logo }}" alt="" class="section-logo" loading="lazy">
            @endif
            <h3 class="section-title mb-0">{{ $title }}</h3>
            @if(!empty($moreUrl))
                <a href="{{ $moreUrl }}" class="section-more">{{ $moreText ?? 'Ver todo' }} <i class="bi bi-arrow-right"></i></a>
            @endif
        </div>
        @if(!empty($subtitle))
            <span class="section-subtitle">{{ $subtitle }}</span>
        @endif
    </div>
    <div class="media-slider-wrapper position-relative">
        <button type="button" class="slider-nav-arrow slider-nav-prev" aria-label="Anterior" title="Anterior">
            <i class="bi bi-chevron-left"></i>
        </button>

        <div class="media-slider-track">
            @forelse($items as $item)
                @include('media.partials.movie-card', array_filter([
                    'item' => $item,
                    'type' => $type ?? null,
                    'colClass' => 'media-slider-col',
                    'rank' => ($ranked ?? false) ? $loop->iteration : null,
                ], fn ($v) => $v !== null))
            @empty
                <p class="text-secondary">{{ $emptyText ?? 'No hay títulos disponibles en este momento.' }}</p>
            @endforelse
        </div>

        <button type="button" class="slider-nav-arrow slider-nav-next" aria-label="Siguiente" title="Siguiente">
            <i class="bi bi-chevron-right"></i>
        </button>
    </div>
</div>
