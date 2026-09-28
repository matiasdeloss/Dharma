---
name: dharma-frontend
description: Convenciones de frontend del proyecto Dharma (Laravel + Blade + SCSS + HTMX) acumuladas a fuerza de errores reales en esta sesión — paleta de colores, layout de cards/carruseles, animaciones, estilizado de formularios nativos, y cómo verificar cambios visuales en este entorno. Usar SIEMPRE que se toque CSS/SCSS, vistas Blade, el carrusel de películas (media-slider), cards de película, modales, o cualquier archivo bajo resources/scss o resources/views en este proyecto — incluso si el pedido es chico ("cambiá este color", "agrandá esto"), porque varias de estas reglas evitan bugs que no son obvios a simple vista.
---

# Frontend de Dharma

Este skill existe porque varias de estas reglas se aprendieron rompiendo algo primero. No son preferencias de estilo — cada una evita un bug específico que ya pasó en este proyecto. Explico el por qué de cada una para que puedas juzgar los casos límite, no solo seguirlas de memoria.

## 1. Paleta de colores: reusar, no inventar

Los colores viven en dos lugares:
- `resources/scss/base/_variables.scss` — variables SCSS (`$color-accent-gold`, `$color-accent-sage`, `$color-accent-purple`, `$color-accent-info`, `$color-accent-danger`, etc.)
- `resources/scss/base/_base.scss` — las mismas expuestas como CSS custom properties en `:root` (`--dharma-accent`, `--dharma-cream`, `--dharma-sage`, `--dharma-gold`, `--dharma-text-main`, `--dharma-text-secondary`, etc.)

Antes de escribir un color nuevo (hex o rgba suelto), buscá si ya existe una variable que sirva. El sitio tiene un lenguaje visual consistente (dorado = acento principal, crema = texto cálido secundario, oscuro translúcido = superficies de controles) y un color inventado ad-hoc rompe esa consistencia aunque individualmente se vea bien. Si de verdad hace falta un tono nuevo, agregalo como variable en `_variables.scss`/`_base.scss`, no como literal disperso en un archivo de componente.

Cuando el usuario pida "otro color" para algo, después de aplicarlo explicá brevemente qué variable usaste y por qué — en esta sesión terminamos iterando varias veces sobre el color de un badge porque las primeras propuestas no explicaban el razonamiento, solo tiraban un hex.

## 2. Cards de carrusel: el ancho de columna nunca se toca para cambiar el tamaño visual

`resources/scss/components/_sliders.scss` calcula el ancho de `.media-slider-col` para que exactamente N cards (6/5/4/3/2 según breakpoint) llenen el 100% del carril — ver la función `slider-card-width()`. Esa matemática determina cuántas cards se ven, y si no cierra exacto, se asoma un pedacito de la próxima card en el borde.

**Si necesitás que las cards se vean más chicas o más grandes, nunca toques ese cálculo de ancho.** Usá `margin` real en `.movie-card` (ver `_cards.scss`) — el margin no le resta espacio a la columna en el layout, así que la matemática de "exactas N cards" sigue cerrando sola.

**Tampoco uses `transform: scale()` para achicar la card.** Ya lo probamos: el navegador renderiza el texto a tamaño completo y después reescala el resultado como si fuera una foto, y el texto chico (título, año, botón) queda visiblemente borroso. `margin` es layout real — el texto se dibuja directo a su tamaño final, sin reescalado, así que queda nítido.

Nota aparte sobre el `overflow-x: auto` del carril (`.media-slider-track`): el padding/margin negativo en ese contenedor **solo** protege los bordes absolutos de *todo* el scroll (antes de la primera card, después de la última), no cada "página" que se revela al clickear la flecha. Si el problema es que la sombra del hover se corta contra el borde del scroll en el medio de la navegación, la solución no es padding en el track — es achicar el alcance de esa sombra específica, o aceptar que es una limitación inherente de los carruseles horizontales (que la mayoría de los sitios de streaming también tiene).

## 3. Animación del carrusel: rAF manual, no `scrollTo({behavior:'smooth'})`

El slider mueve `scrollLeft` a mano con `requestAnimationFrame` + una función de easing (`easeInOutCubic`), no con el scroll suave nativo del navegador. La razón: `scrollTo({behavior:'smooth'})` delega la decisión de animar al navegador/sistema operativo, y configuraciones de accesibilidad del SO (como "Efectos de animación" en Windows) pueden forzarlo a saltar instantáneo sin que el código tenga forma de detectarlo. rAF manual está 100% bajo nuestro control.

**Nunca combines `scroll-snap-type` con la animación manual por rAF.** Ya causó un bug real: el navegador intenta "corregir" el scroll a mitad del gesto porque cree que el usuario está scrolleando, y la animación queda rota/saltando. El paso ya se calcula exacto por card en JS (`getScrollStep()` en `resources/js/modules/sliders.js`), así que el snap de CSS no aporta nada — si en algún momento parece necesario, es señal de que hay que revisar el cálculo del paso, no agregar snap.

## 4. Backdrop atenuado sobre fotogramas de película

Patrón usado en `.hero-home-banner` y `.rate-hero` (`_hero.scss`, `_modals.scss`) para poner texto legible sobre una imagen de fondo variable (backdrop de película, que puede ser clara u oscura):

```scss
.algo {
    position: relative;
    isolation: isolate;

    &::before {
        content: '';
        position: absolute;
        inset: 0;
        z-index: -2;
        background-image: var(--mi-imagen-de-fondo, none);
        background-size: cover;
        background-position: center;
        filter: saturate(0.8) brightness(0.5..0.6);
    }

    &::after {
        content: '';
        position: absolute;
        inset: 0;
        z-index: -1;
        background: linear-gradient(...) , radial-gradient(...); // viñeta + degradé
    }
}
```

La imagen va en un custom property (`--mi-imagen-de-fondo`) seteado inline desde Blade (`style="--rate-backdrop: url('...')"`), no hardcodeada en el SCSS — así el componente es reutilizable con cualquier imagen. Reusá este patrón para cualquier caja nueva que necesite "fotograma de película de fondo + contenido legible encima", en vez de reinventar el atenuado cada vez.

## 5. Inputs/selects/range nativos: siempre re-estilizados

Bootstrap deja los controles de formulario con su estilo dark-theme genérico. En este proyecto los reemplazamos con una superficie propia + foco dorado — ver `.modal-form-control` en `_modals.scss` (superficie oscura, borde sutil, ring dorado en `:focus`) y `.rate-slider` (range input estilizado a mano vía `::-webkit-slider-thumb`/`::-moz-range-thumb`, sin la manija/thumb circular default).

Si agregás un `<select>`, `<input>`, `<textarea>` o `<input type="range">` nuevo en una vista de este proyecto, aplicale la clase de estilo existente correspondiente en vez de dejarlo con el `form-control`/`form-select` default de Bootstrap — el contraste entre un control sin estilizar y el resto de la UI se nota de inmediato.

Cuando pidan estilizar la manija (thumb) de un range: **no le agregues gradientes, brillos ni "volumen" a menos que te lo pidan explícitamente.** En esta sesión, agregar un brillo sutil "porque queda mejor" fue rechazado — el usuario había pedido específicamente sacarle las estrías y no agregar textura nueva. Cuando piden algo "sin estilizar" o "plano", es literal: color sólido, sin degradé.

## 6. Cómo verificar un cambio visual en este entorno (importante)

El navegador de preview de esta sesión corre sandboxeado y **no puede conectarse al servidor de `vite dev`** (bloqueo de red del entorno hacia `localhost:5173`). Esto significa:

- `getComputedStyle(...)` sobre ese navegador para temas de **apariencia visual** (colores, tamaños, transforms) va a devolver valores por default/vacíos, no los estilos reales — es una falsa negativa, no un bug del proyecto. No lo uses para verificar CSS.
- Sí sirve para verificar **estructura HTML y lógica PHP/Blade**: que el texto correcto se renderice, que no haya errores de Laravel, que un formulario mande los campos esperados, etc. — eso no depende del CSS externo.

**Para verificar CSS/SCSS real:** correr `npm run build` (compila a `public/build/assets/app-*.css`, independiente del modo hot) y hacer `grep` sobre el CSS compilado para confirmar que la regla que escribiste quedó como esperabas. Esto es rápido, confiable, y no interfiere con el `vite dev` que el usuario tiene corriendo aparte (nunca toques `public/hot` — si existe, es porque el usuario tiene `npm run dev` corriendo en otra terminal; borrarlo le rompe el HMR).

Como el usuario tiene HMR activo, los cambios en SCSS/JS/Blade ya se reflejan solos en su navegador real — no hace falta pedirle que refresque, solo confirmar que el build compila limpio y el CSS/HTML generado es el correcto.

## 7. Cambios chicos y verificables, no reescrituras grandes

El patrón de trabajo real en este proyecto es iterativo: el usuario prueba en su navegador, pide un ajuste puntual, se prueba, se ajusta de nuevo. Preferí ediciones quirúrgicas (`Edit` sobre un selector específico) por sobre reescribir archivos SCSS/Blade enteros, y verificá cada cambio con:

1. `npm run build` (sin errores)
2. `grep` sobre el CSS compilado confirmando la regla puntual que cambiaste
3. Si tocaste un controller/lógica PHP: `php -l` sobre el archivo y `php artisan test` (21 tests en este proyecto — deberían seguir pasando)

Si el usuario dice que algo "no cambió" o "sigue igual", el primer sospechoso no es "hay que probar en el navegador" — es revisar si el selector CSS que escribiste realmente matchea (errores comunes que ya pasaron acá: anidar mal en SCSS y generar un selector descendiente que nunca matchea, o pisar una regla con menor especificidad de la esperada).

## 8. Texto nítido en cards: nada de capas GPU ni `scale` sobre contenedores con texto

Ya pasó: `.movie-card` tenía `transform: translateZ(0)` + `will-change: transform` y un `scale(1.02)` en hover, y el usuario notó "las letras se sienten borrosas". Promover a capa GPU y escalar rasteriza el texto como bitmap. El hover de una card es `translateY(...)` a secas; el zoom va **solo en la imagen** (`.poster-wrapper img`), que sí puede escalar.

## 9. Meta chica (año, separadores, acciones fantasma): gris neutro, no taupe

`--dharma-text-secondary` es taupe (`#948979`) y sobre superficies oscuras al lado del dorado se lee como "amarillo apagado" — el usuario lo rechazó explícitamente para el año y el botón "Calificar" de las cards. Para meta fría usar `--dharma-text-neutral` (`#9aa3ad`, definido en `_variables.scss`/`_base.scss`). El taupe queda para textos largos donde su calidez suma.

Además, en las cards: la nota de TMDB va blanca y con peso (es lo primero que se lee de la meta), la estrella se alinea con `line-height: 1` + `vertical-align: 0` (los íconos de Bootstrap traen `vertical-align: -.125em` y se hunden), y "Calificar" es fantasma y solo aparece en hover con puntero (`@media (hover: hover)`), mientras que la nota propia se ve siempre.
