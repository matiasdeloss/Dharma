# Tasks

Pendientes de Dharma. Los briefs completos de las features grandes están en
`.claude/research/` — cada tarea que tiene uno lo referencia.

## Active











- [ ] **Backfill de `media_items.genres`** - sub-tarea de las estadísticas, destraba browse por género
  - La columna existe, está en `$fillable` y casteada a `array`, pero `firstOrCreate` en `ReviewController::store` y `WatchlistController::toggle` nunca la pasa → siempre `null`
  - Los géneros ya vienen en la ficha que se pide a TMDB, no hace falta llamada nueva

- [ ] **Página de estadísticas del diario ("Tu diario en números")**
  - Brief: `.claude/research/2026-09-06-estadisticas-diario.md`
  - Histograma de ratings, por género, por década, por mes, horas totales
  - Depende del backfill de géneros


## Waiting On

- [ ] **Decidir si el navbar transparente se extiende a más vistas** - hoy está en home, diario y watchlist

- [ ] **Decidir sobre el Ken Burns del hero** - zoom lento de 34s sobre el fotograma
  - Se mata borrando la línea `animation:` en `.hero-home-banner::before`

## Someday

- [ ] **Listas / colecciones curadas** (rankeadas, privadas/públicas)
  - Feature estrella de Letterboxd y sirve con un solo usuario ("mi top 100", organizar maratones)
  - Esfuerzo alto: 2 tablas nuevas + CRUD + rutas + vistas + reordenamiento

- [ ] **Browse / discover por género y año**
  - Métodos nuevos en `TmdbService` (`/discover/movie`, `/discover/tv`, `/genre/*/list`) + página
  - Sinergiza con el backfill de géneros

- [ ] **Calendario de estrenos de la watchlist** (estilo Trakt)
  - "Qué se viene de lo que quiero ver"

- [ ] **Likes / favoritas (4 en el perfil)**
  - Vale poco estando solo — el corazón brilla en lo social. Además necesita una página de perfil que hoy no existe

**Decidido NO construir por ahora** (razones en `.claude/research/2026-09-06-roadmap-features.md` §4):
- Tracking de episodios y temporadas — esfuerzo enorme y el producto es coherente tratando cada serie como una unidad
- Todo lo social (perfiles públicos, follow, feed de actividad, comentarios) — un dev solo no tiene masa crítica; el bloque "Reseñas de la Comunidad" ya alcanza como stub

## Done

- [x] ~~**Listas, "Elegir al azar" y buscar en el diario**~~ (2026-09-24)
  - **Listas propias** (`/listas`): privadas, numeradas o no, con descripción. Se suman títulos desde **"Agregar a lista"** en la ficha y en el menú de cada entrada del diario: un modal con un botón por lista y una fila para crear una nueva que ya incluye el título
  - Se **ordenan arrastrando** las cards (SortableJS, dependencia nueva, MIT). Al soltar, htmx manda `items[]` en el orden nuevo; ojo: `hx-include` con `find ...` trae solo el PRIMER input
  - Tablas `media_lists` y `media_list_items` (puesto 1..N sin huecos). Link "Listas" en el navbar solo con sesión; entre 992 y 1199px el nombre del usuario se esconde (queda el avatar) para que entre
  - **"Elegir al azar"** en la watchlist: sortea un título (de lo disponible hoy si el filtro está puesto); "Elegir otro" no repite el anterior
  - **Buscar en el diario**: títulos, reseñas y notas privadas, sin tildes ni mayúsculas. Parámetro `buscar` y no `q`, que es el del buscador del navbar
  - De paso: el navbar de Explorar, Estadísticas y Ajustes quedaba "a medio camino" (dos listas de páginas que no coincidían en el layout; ahora es una sola, `$fullBleed`), la edad de los actores salía con decimales (`diffInYears` de Carbon 3), y la watchlist con el filtro puesto y nada disponible decía "Tu watchlist está vacía"
  - 96/96 tests, 19 nuevos

- [x] ~~**Seguridad (de la auditoría)**~~ (2026-09-24)
  - **Login y registro con freno**: 5 intentos fallidos por minuto para el mismo correo + IP (criterio de Breeze) y 5 cuentas por hora por IP. El aviso sale en el form, en español, y no como la página 429
  - **TMDB manda sobre el form** al dar de alta un título: antes cualquiera podía "bautizar" un título nuevo con el nombre o el póster que quisiera, y `media_items` es compartido. El form queda solo de respaldo si TMDB no responde
  - **Toasts sin `innerHTML`** para título y mensaje: hoy eran textos fijos, pero era una puerta a XSS el día que un toast mostrara un título
  - **Web oficial solo si es http(s)**: viene de TMDB, que edita cualquiera; un `javascript:` ya no llega a un href
  - 77/77 tests, 5 nuevos

- [x] ~~**Auditoría antes de subir a git: 7 bugs arreglados**~~ (2026-09-24)
  - **Seeder roto en base nueva**: mandaba `is_rewatch` y `status`, que ya no existen, y una nota 9.5 (pasó a 10). Test nuevo que corre el seeder
  - **Sticky roto en todo el sitio**: `overflow-x: hidden` en `html, body` volvía al body contenedor de scroll. Ahora es `clip`: el navbar y la ficha técnica quedan fijos
  - **Explorar daba 500 la primera vez** (38 llamadas a TMDB en fila, 28 s): `TmdbService::getMany`/`detailsMany` piden en paralelo con `Http::pool`. En frío baja a ~9 s. Si "Para vos" sale vacío se guarda 10 min y no 6 h
  - **Errores de TMDB cacheados**: un 429/500 dejaba la ficha en 404 durante 24 h. Ya no se guardan, y los `[]` que quedaron guardados se ignoran
  - **Calificar sin estrellas no hacía nada**: el botón arranca deshabilitado, y cualquier error de validación por HTMX vuelve como 422 + toast (`formInvalid`), con mensajes en español
  - **Logos gigantes en "Para vos"**: `.poster-wrapper img` les ganaba por orden. Las reglas del póster ahora son `> a > img`
  - **"Mis Notas" en dos líneas a 1366px**: `nowrap` en los links. De paso, entre 992 y 1199px el buscador cede espacio (los botones de la derecha ya quedaban cortados)
  - 72/72 tests, 7 nuevos

- [x] ~~**Flujo de calificar/reseñar rediseñado: dos modales, sin estados ni re-visionados**~~ (2026-09-16)
  - **Modal de calificar**: solo el número arriba y cinco estrellas clickeables abajo (mitad izquierda = media estrella; 1 a 10 en la escala). Más la fecha. Y el botón de eliminar
  - **Modal de reseña**, aparte: texto público, spoilers y nota privada. Se abre desde "Tu reseña" en la ficha, desde "Escribir tu reseña" en la sección de reseñas, y desde el menú de cada entrada del diario
  - Los dos escriben en la **misma fila** y cada uno toca solo sus campos: calificar no borra la reseña, reseñar no toca la nota. Cubierto por test
  - **Fuera los estados** (viéndola / quiero verla / abandonada): calificar es dar por vista. Fuera el **re-visionado** del sistema entero. Vuelve a ser **una entrada por título** con índice único de verdad — el original nunca lo tuvo, el candado vivía en el controller
  - Migración: deduplica si hubiera re-visionados guardados (no había), borra `is_rewatch` y `status`, pone el único. Backup de la base antes de correrla
  - La nota pasó a **entero del 1 al 10** (cinco estrellas con medias). Las notas viejas con .5 quedan como están; al reeditarlas se redondean
  - El diario perdió el filtro de estado y el tile "Re-vistas" pasó a "Reseñas" (escritas)
  - `log-modal` y el slider desaparecen; `star-rater.js` nuevo. 39/39 tests, reescritos para el modelo nuevo

- [x] ~~**Diario real: re-visionados + estados + fecha editable**~~ (2026-09-16)
  - Hecho por `dharma-backend`; lo cortó el límite de sesión mientras agregaba tests extra, pero lo que dejó estaba completo y en verde: migración que quita el índice único, rutas `reviews.edit`/`reviews.update`, modal con selector de estado, fecha editable y aviso de re-visionado. 12 tests nuevos
  - Verificado por mí: 40/40, migración aplicada, las cinco pantallas renderizan 200
- [x] ~~**Ficha: botones al sistema Dharma**~~ (2026-09-16)
  - Fuera el botón verde de "Tu nota" y también "Registrar de nuevo" + el desplegable de registros (pedido directo): una vez registrada, la barra de acciones no muestra nada. La nota vive en el hero con su Editar
  - "Ver trailer" pasó de `btn-outline-danger` a `.btn-cine-ghost`; el botón de eliminar del modal a `.btn-cine-danger` (alias borrado)
  - El riel de relacionados ya no muestra lo que está en tu watchlist: se juntan 36, se filtran, se muestran 18
- [x] ~~**El trailer seguía sonando al cerrar el modal**~~ (2026-09-16)
  - Ocultar un modal no pausa un iframe de YouTube. Ahora el `src` se pone al abrir y se saca al cerrar — es lo único fiable sin cargar la API de YouTube. De paso la ficha ya no carga YouTube en cada visita

- [x] ~~**Sistema de botones Dharma estandarizado**~~ (2026-09-07)
  - Había dos variantes (`primary`, `secondary`) y todo lo demás caía en botones de Bootstrap, cada uno con su rojo, celeste o verde ajenos a la paleta
  - Ahora son cinco, cubriendo la escala de intención: `primary` (acción principal), `secondary`, `ghost` (terciaria), `danger` (destructiva, siempre contorno) y `active` (estado "ya hecho", azul de watchlist). Más `.btn-pill-compact` y `.btn-pill-lg`
  - Todas comparten una base `%btn-cine-base`, así que el alto, el radio y la animación no se pueden desincronizar
  - Aplicado ya en "En tu Watchlist", que usaba `btn-outline-info`

- [x] ~~**Watchlist: prioridad y notas**~~ (2026-09-07)
  - Chip de prioridad en cada card, con color de la paleta (rojo alta, dorado media, salvia baja) y un menú para cambiarla y escribir la nota. Se guarda por HTMX y solo se reemplaza ese pedacito
  - La lista ahora **ordena por prioridad** y se puede filtrar. Sin eso la prioridad hubiera sido un dato decorativo
  - `data-bs-auto-close="outside"` en el menú: sin eso Bootstrap lo cierra al tocar el select y no se llega a guardar
  - 4 tests nuevos, incluido que no se pueda editar el ítem de otro usuario y que una prioridad inválida se rechace
- [x] ~~**Vistas de auth revisadas**~~ (2026-09-07)
  - Estaban bien: ya tienen su propio sistema `auth-*`. Solo dos cosas fuera de paleta: el checkbox de "recordarme" con `bg-dark border-secondary` de Bootstrap, y los 5 mensajes de error con `text-danger` (#dc3545) en vez del rojo del proyecto

- [x] ~~**Tanda de bugs y limpieza**~~ (2026-09-07)
  - **Privacidad:** fuera el fallback `Auth::id() ?? User::first()?->id` en los 6 lugares. Verificado: un invitado ya no ve tu watchlist marcada, y vos sí
  - **Contador roto:** `total_reviews` terminaba en `?: Review::count()`, así que con cero reseñas escritas mostraba el total de registros. Y los invitados ya no disparan tres COUNT inútiles
  - **Spoilers:** `contains_spoilers` se guardaba y se ignoraba. Ahora el texto de reseñas ajenas va desenfocado con botón para revelar. Con `user-select: none`, si no se lee igual arrastrando el cursor por encima
  - **El diario ya se actualiza sin recargar:** la entrada se extrajo a un partial con id propio y viaja como fragmento out-of-band
  - **Fechas en español:** `format()` → `translatedFormat()`. `APP_LOCALE=es` ya estaba puesto, solo se usaba el método que ignora el locale
  - **Muertos:** `welcome.blade.php`, el partial `watch-providers` y `TmdbService::getTvImages()`, los tres sin una sola referencia

- [x] ~~**Guardar una reseña ahora actualiza la nota sin recargar**~~ (2026-09-07)
  - El bug: la respuesta traía el botón y los contadores, pero no el bloque “Tu nota” del hero, que es nuevo
  - Debajo había algo peor: el formulario apuntaba a `hx-target="#log-action-container"`, que solo existe en la ficha. Desde el diario htmx abortaba el swap entero
  - Ahora la respuesta son solo fragmentos out-of-band (`hx-swap="none"`): cada uno se aplica si su destino está en la página, y htmx ignora los que no. La misma respuesta sirve para ficha, diario y home
  - Partials nuevos `hero-score-mine` y `hero-score-dharma` — el promedio de la comunidad también cambia con cada nota
  - Tests nuevos en `ReviewFlowTest`: 24/24
- [x] ~~**Botón de calificar a la derecha de tu nota + borrar desde el modal**~~ (2026-09-07)
  - El botón dice “Editar” en cuanto existe un registro, aunque no tenga nota puesta
  - Borrar responde con `HX-Refresh` en vez de fragmentos: al borrar cambian a la vez el botón, la nota del hero, los contadores y la fila del diario; recargar es más confiable que reconstruir cada pieza, y es una acción puntual
  - Cubierto por tests, incluido que no se pueda borrar la reseña de otro usuario
- [x] ~~**Calificaciones de vuelta al hero, sin cards**~~ (2026-09-07)
  - Ocupan la tercera columna del hero, que había quedado vacía. Orden: tu nota, debajo la de Dharma, debajo la crítica externa
  - Sin cards: son líneas separadas por filetes finos, como una ficha técnica. Apiladas en una columna angosta las cajas se leían como una pila de recuadros compitiendo entre sí
  - La tira de críticos pasó de grilla de tiles a filas compactas (nombre a la izquierda, cifra a la derecha) para entrar en la columna
  - Tu reseña y tu nota privada se movieron a la sección de reseñas, destacadas arriba de las de la comunidad: no entraban en la columna
  - El dorado sigue reservado a tu nota

- [x] ~~**Los 4 agentes quedaron registrados**~~ (2026-09-07) - tras reiniciar la sesión; `dharma-frontend` ya hizo la ficha
- [x] ~~**Ficha de película: sección de información rehecha**~~ (2026-09-07)
  - Hecho por el agente `dharma-frontend`. El hero quedó intacto (verificado: cero borrados que toquen sus selectores)
  - Calificaciones movidas del panel lateral a debajo del hero: Dharma y "Tu nota" arriba en cards iguales, la crítica (TMDB/IMDb/RT/Metacritic) en tiles más chicos debajo. El dorado lo lleva solo "Tu nota" — es un diario personal
  - Reparto de 6 a 12 + colapsable con el resto; equipo técnico agrupado por rol; ficha técnica que ramifica por película/serie (temporadas, episodios, cadena); premios y clasificación por edad de OMDb, que se normalizaban y nunca se mostraban
  - Riel de títulos relacionados: `recommendations` con fallback a `similar`, que se descargaban en cada ficha y se tiraban. La ficha dejó de ser un callejón sin salida
  - Seguimiento mío: moví el armado de `$related` de la vista a `MediaController::show` para poder correrle `attachWatchlistStatus` — en Blade no había forma de marcarle el estado a cada card. Probado: un título en la watchlist da `true`, uno que no está da `false`
  - Seguimiento mío: modal del trailer con `.trailer-modal-content` y el botón de cerrar propio. Ya no usa `bg-black border-0`

- [x] ~~**`/search`: paginación + pase de diseño**~~ (2026-09-07)
  - Partial nuevo `media/partials/tmdb-pagination.blade.php`, reutilizable para browse/discover cuando llegue. Tapa en 500 páginas, que es donde TMDB deja de devolver resultados aunque `total_pages` diga más
  - `_search.scss` nuevo. **Sin banda con fotograma a propósito**: es pantalla de utilidad, un hero ahí solo empuja la grilla fuera de pantalla
  - El campo hereda de `.modal-form-control`, así que tiene el mismo foco dorado que el resto
  - El término buscado iba en `.text-success` (verde de Bootstrap); ahora en el acento del proyecto
- [x] ~~**Modales re-estilizados de cero**~~ (2026-09-07)
  - Fuera la estrella gigante. En su lugar, la palabra que corresponde al número (Obra Maestra, Excelente…), que se actualiza mientras se arrastra el slider
  - El número pasó de peso 900 a `numeral(3.5rem, 500)` — era el mismo "caricaturesco" que se sacó del resto del sitio
  - Formulario alineado a la izquierda en vez de todo centrado; encabezado con badge y año arriba del título
  - Modal de "iniciá sesión" rehecho: usaba `bg-dark`/`text-warning` de Bootstrap
  - Fondo detrás del modal más oscuro y con desenfoque: a media opacidad los pósters seguían compitiendo
  - `ratingLabel()` en `htmx-config.js` es espejo exacto de `Review::getRatingLabelAttribute()`. **Si se toca una, hay que tocar la otra**
- [x] ~~**Diario y Watchlist: sacar los tiles de vidrio**~~ (2026-09-07)
  - Era el mismo problema que la card del home: cajas de vidrio flotando sobre el fotograma
  - Las estadísticas bajaron debajo del título, separadas por una línea fina y sin superficie propia. El separador es un `border-left` del propio tile, así que no hubo que tocar el markup de ninguna de las dos vistas
  - El degradé del encabezado se abrió hacia la derecha (0.55 → 0.3), que quedó libre
  - Si el problema de fondo era el **formato** y no las superficies (lista vertical vs. grilla de pósters en el diario), eso sigue abierto y es otro trabajo
- [x] ~~**Nuevo estilo para los números**~~ (2026-09-07)
  - Mixin `numeral($size, $weight)` en `_variables.scss`, aplicado a `.hero-stat-number`, `.stat-tile-value` y `.diary-rating-value`
  - Outfit 800 → 500/600 en tamaño mayor, `letter-spacing` de -0.02 a -0.01, y `tabular-nums` para que las cifras no bailen al actualizarse por HTMX
- [x] ~~**Card de Notas / Reseñas / Watchlist del home rehecha**~~ (2026-09-07)
  - Era el background: se sacó el panel de vidrio entero
  - Los contadores bajaron a la columna izquierda, debajo de los botones, separados por una línea fina. Quedan en la zona que el degradé ya protege, así que no necesitan caja, y el lado derecho del hero queda libre
  - De paso: `.text-accent-info` nuevo en utilities — el número de Watchlist usaba `.text-info` de Bootstrap (#0dcaf0), que no es de la paleta
- [x] ~~**Hero del home a sangre completa**~~ (2026-09-06)
  - Salió de la card, navbar transparente que se solidifica al scrollear, fundido al fondo real de la página, Ken Burns lento, scrim superior
- [x] ~~**Diario y watchlist al lenguaje visual del proyecto**~~ (2026-09-06)
  - Banda de encabezado con fotograma de tus propias películas, stat tiles de vidrio, selects con el foco dorado del proyecto, paginación propia, estados vacíos rediseñados
  - ⚠️ No convenció: ver "Rehacer Mis Notas" y "Rehacer la Watchlist" en Active
- [x] ~~**Cerrar la fuga de notas privadas en el feed público del home**~~ (2026-09-06)
  - `home.blade.php` mostraba `private_notes` de todos los usuarios, visible sin login
- [x] ~~**Footer con las superficies del proyecto**~~ (2026-09-06) - usaba `bg-dark` de Bootstrap sobre una página `#222831`
- [x] ~~**Crear los 4 agentes especializados**~~ (2026-09-06) - frontend, backend, research y architect en `.claude/agents/`
- [x] ~~**Auditoría del proyecto y roadmap priorizado**~~ (2026-09-06) - 11 features en `.claude/research/`
