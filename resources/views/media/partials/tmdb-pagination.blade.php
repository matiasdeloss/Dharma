{{--
    Paginación para listados que vienen de TMDB.

    Laravel no puede paginarlos solo: los resultados llegan ya paginados por la
    API, así que no hay un LengthAwarePaginator del que sacar los links. Usa las
    clases `.pagination` de Bootstrap a propósito, para heredar el estilo Dharma
    que ya está definido en pages/_library.scss.

    Espera: $page, $totalPages, $query
--}}
@php
    // TMDB no devuelve nada más allá de la página 500, aunque `total_pages`
    // diga un número mayor. Sin este tope se ofrecen páginas vacías.
    $lastPage = min((int) ($totalPages ?? 1), 500);
    $current = max(1, min((int) ($page ?? 1), $lastPage));

    // Ventana de 2 a cada lado. Cerca de los bordes se corre para mantener
    // siempre la misma cantidad de botones y que no salte el ancho.
    $window = 2;
    $start = max(1, $current - $window);
    $end = min($lastPage, $current + $window);

    if (($end - $start) < ($window * 2)) {
        $start = max(1, $end - ($window * 2));
        $end = min($lastPage, $start + ($window * 2));
    }

    $linkTo = fn (int $n) => route('media.search', ['q' => $query, 'page' => $n]);
@endphp

@if($lastPage > 1)
    <nav aria-label="Paginación de resultados" class="d-flex justify-content-center mt-4">
        <ul class="pagination mb-0 flex-wrap">
            <li class="page-item {{ $current <= 1 ? 'disabled' : '' }}">
                <a class="page-link" href="{{ $current <= 1 ? '#' : $linkTo($current - 1) }}"
                   @if($current <= 1) tabindex="-1" aria-disabled="true" @endif>
                    <i class="bi bi-chevron-left"></i><span class="d-none d-sm-inline ms-1">Anterior</span>
                </a>
            </li>

            @if($start > 1)
                <li class="page-item"><a class="page-link" href="{{ $linkTo(1) }}">1</a></li>
                @if($start > 2)
                    <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                @endif
            @endif

            @for($i = $start; $i <= $end; $i++)
                <li class="page-item {{ $i === $current ? 'active' : '' }}">
                    <a class="page-link" href="{{ $linkTo($i) }}"
                       @if($i === $current) aria-current="page" @endif>{{ $i }}</a>
                </li>
            @endfor

            @if($end < $lastPage)
                @if($end < $lastPage - 1)
                    <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                @endif
                <li class="page-item"><a class="page-link" href="{{ $linkTo($lastPage) }}">{{ $lastPage }}</a></li>
            @endif

            <li class="page-item {{ $current >= $lastPage ? 'disabled' : '' }}">
                <a class="page-link" href="{{ $current >= $lastPage ? '#' : $linkTo($current + 1) }}"
                   @if($current >= $lastPage) tabindex="-1" aria-disabled="true" @endif>
                    <span class="d-none d-sm-inline me-1">Siguiente</span><i class="bi bi-chevron-right"></i>
                </a>
            </li>
        </ul>
    </nav>
@endif
