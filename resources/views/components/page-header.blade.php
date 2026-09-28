{{--
    Banda de encabezado de pagina.

    Usa el mismo patron de "fotograma atenuado + contenido legible encima" que
    el hero del home, en version compacta, para que las vistas internas
    (diario, watchlist) no arranquen con un titulo suelto sobre fondo plano.

    El backdrop es opcional: sin el, la banda queda como superficie solida.

    Uso:
        <x-page-header title="Mi Diario" subtitle="..." icon="bi-journal-bookmark" :backdrop="$headerBackdrop">
            <x-slot:aside> ...tiles, acciones... </x-slot:aside>
        </x-page-header>
--}}
@props([
    'title',
    'subtitle' => null,
    'icon' => null,
    'backdrop' => null,
])

<div class="page-header-band" @if($backdrop) style="--page-header-backdrop: url('{{ $backdrop }}');" @endif>
    <div class="container">
        <div class="page-header-inner">
            <div class="page-header-copy">
                <h1 class="page-header-title">
                    @if($icon)
                        <i class="bi {{ $icon }} page-header-icon"></i>
                    @endif
                    {{ $title }}
                </h1>

                @if($subtitle)
                    <p class="page-header-subtitle">{{ $subtitle }}</p>
                @endif
            </div>

            @isset($aside)
                <div class="page-header-aside">
                    {{ $aside }}
                </div>
            @endisset
        </div>
    </div>
</div>
