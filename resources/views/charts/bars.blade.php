{{--
    Barras verticales de una sola serie, SVG inline (sin librería).

    Props:
      $data        array|Collection  etiqueta => valor (en orden de eje X)
      $title       string            para aria-label y la tabla oculta
      $labels      array|null        etiqueta => texto a mostrar en el eje (opcional)
      $unit        string            'registro'/'registros' para el tooltip (opcional)

    Reglas (skill dataviz): una sola tonalidad (dorado), marcas finas con tope
    redondeado ancladas a la base, grilla recesiva de 3 líneas, número directo
    solo en el máximo (el resto en el tooltip), texto con tokens de texto y no
    con el color de la serie, y una tabla oculta para lectores de pantalla.
--}}
@php
    $data = collect($data);
    $n = max(1, $data->count());
    $max = max(1, (int) $data->max());
    $unit = $unit ?? 'registros';

    // Geometría en unidades del viewBox.
    $w = 600; $h = 220;
    $padL = 28; $padR = 8; $padT = 18; $padB = 30;
    $plotW = $w - $padL - $padR; $plotH = $h - $padT - $padB;
    $slot = $plotW / $n;
    $barW = min(28, $slot * 0.62);
    $r = min(4, $barW / 2);
    $base = $padT + $plotH;

    // Ticks "lindos": 3 líneas de grilla sobre un máximo redondeado.
    $niceMax = $max <= 5 ? $max : (int) (ceil($max / 5) * 5);
    $ticks = [round($niceMax / 3), round($niceMax * 2 / 3), $niceMax];
    $y = fn (int|float $v) => $base - ($v / $niceMax) * $plotH;
    $maxKey = $data->search($data->max());
@endphp

<figure class="chart chart-bars">
    <svg viewBox="0 0 {{ $w }} {{ $h }}" role="img" aria-label="{{ $title }}" preserveAspectRatio="xMidYMid meet">
        {{-- Grilla recesiva --}}
        @foreach($ticks as $t)
            <line x1="{{ $padL }}" x2="{{ $w - $padR }}" y1="{{ $y($t) }}" y2="{{ $y($t) }}" class="chart-grid" />
            <text x="{{ $padL - 6 }}" y="{{ $y($t) + 3 }}" class="chart-tick" text-anchor="end">{{ $t }}</text>
        @endforeach
        <line x1="{{ $padL }}" x2="{{ $w - $padR }}" y1="{{ $base }}" y2="{{ $base }}" class="chart-axis" />

        @foreach($data as $label => $value)
            @php
                $i = $loop->index;
                $x = $padL + $slot * $i + ($slot - $barW) / 2;
                $top = $value > 0 ? $y($value) : $base;
                $bh = $base - $top;
                $rr = min($r, $bh);
                $path = $bh > 0
                    ? "M{$x},{$base} L{$x}," . ($top + $rr) . " Q{$x},{$top} " . ($x + $rr) . ",{$top} L" . ($x + $barW - $rr) . ",{$top} Q" . ($x + $barW) . ",{$top} " . ($x + $barW) . "," . ($top + $rr) . " L" . ($x + $barW) . ",{$base} Z"
                    : null;
                $text = $labels[$label] ?? $label;
            @endphp
            <g class="chart-bar-group">
                {{-- Zona de hover más grande que la barra --}}
                <rect x="{{ $padL + $slot * $i }}" y="{{ $padT }}" width="{{ $slot }}" height="{{ $plotH }}" class="chart-hit">
                    <title>{{ $text }}: {{ $value }} {{ $value === 1 ? rtrim($unit, 's') : $unit }}</title>
                </rect>
                @if($path)
                    <path d="{{ $path }}" class="chart-bar {{ $label === $maxKey ? 'is-max' : '' }}" />
                @endif
                @if($label === $maxKey && $value > 0)
                    <text x="{{ $x + $barW / 2 }}" y="{{ $top - 5 }}" class="chart-value" text-anchor="middle">{{ $value }}</text>
                @endif
                <text x="{{ $padL + $slot * $i + $slot / 2 }}" y="{{ $h - 10 }}" class="chart-label" text-anchor="middle">{{ $text }}</text>
            </g>
        @endforeach
    </svg>

    <table class="visually-hidden">
        <caption>{{ $title }}</caption>
        <thead><tr><th scope="col">Categoría</th><th scope="col">Cantidad</th></tr></thead>
        <tbody>
            @foreach($data as $label => $value)
                <tr><th scope="row">{{ $labels[$label] ?? $label }}</th><td>{{ $value }}</td></tr>
            @endforeach
        </tbody>
    </table>
</figure>
