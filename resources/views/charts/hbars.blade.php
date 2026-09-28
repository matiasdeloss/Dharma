{{--
    Barras horizontales de una sola serie (ranking: géneros más vistos).
    HTML + CSS, sin SVG: cada fila es etiqueta + barra + valor, así el texto
    se lee a cualquier ancho sin escalar.

    Props:
      $data   array|Collection  etiqueta => valor, ya ordenado desc
      $title  string
--}}
@php
    $data = collect($data);
    $max = max(1, (int) $data->max());
@endphp

<div class="chart chart-hbars" role="img" aria-label="{{ $title }}">
    @foreach($data as $label => $value)
        <div class="chart-hbar-row" title="{{ $label }}: {{ $value }}">
            <span class="chart-hbar-label">{{ $label }}</span>
            <span class="chart-hbar-track">
                <span class="chart-hbar-fill {{ $loop->first ? 'is-max' : '' }}" style="width: {{ round($value / $max * 100, 1) }}%"></span>
            </span>
            <span class="chart-hbar-value">{{ $value }}</span>
        </div>
    @endforeach
</div>
