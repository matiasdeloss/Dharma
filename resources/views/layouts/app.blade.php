<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" data-bs-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Dharma') }} - @yield('title', 'Reseñas y Notas de Películas')</title>

    <!-- Google Fonts (Outfit + Plus Jakarta Sans) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800;900&family=Plus+Jakarta+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400&display=swap" rel="stylesheet">

    <!-- Vite Assets (Modular SCSS, Bootstrap 5, HTMX, Icons) -->
    @vite(['resources/scss/app.scss', 'resources/js/app.js'])

    @stack('styles')
</head>
<body>
    <!-- Navbar -->
    {{-- Paginas que arrancan con un fotograma a sangre completa: el hero del
         home o la banda de encabezado de <x-page-header>. En ellas el navbar va
         transparente mientras estamos arriba de todo (`navbar-over-hero`, se
         vuelve solido al scrollear: ver modules/navbar.js) y <main> va sin
         padding. Las dos cosas salen de esta misma lista: cuando eran dos
         listas separadas, Explorar, Estadisticas y Ajustes quedaron solo en
         una, la banda arrancaba 24px mas abajo y el navbar quedaba a medio
         camino entre el fondo y la imagen. --}}
    @php
        $fullBleed = request()->routeIs('home', 'reviews.index', 'reviews.stats', 'watchlist.index', 'explore.index', 'settings.edit', 'lists.index', 'lists.show');
    @endphp
    <nav class="navbar navbar-expand-lg navbar-cine sticky-top {{ $fullBleed ? 'navbar-over-hero' : '' }}">
        <div class="container">
            <!-- Brand -->
            {{-- Marca grande y links chicos en mayusculas (estilo Letterboxd):
                 la jerarquia la da el tamano, ver .navbar-brand / .nav-link en
                 components/_navbar.scss. --}}
            <a class="navbar-brand text-white" href="{{ route('home') }}">
                <i class="bi bi-film text-accent"></i>
                <span>Dharma</span>
            </a>

            <!-- Mobile Toggle -->
            <button class="navbar-toggler border-0" type="button" data-bs-toggle="collapse" data-bs-target="#navbarMain" aria-controls="navbarMain" aria-expanded="false" aria-label="Toggle navigation">
                <i class="bi bi-list fs-2 text-white"></i>
            </button>

            <!-- Navbar Links & Search -->
            <div class="collapse navbar-collapse" id="navbarMain">
                <!-- Navigation links -->
                <ul class="navbar-nav me-auto mb-2 mb-lg-0 ms-lg-3">
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('home') ? 'active text-accent fw-semibold' : 'text-light-emphasis' }}" href="{{ route('home') }}">
                            Inicio
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('explore.index') ? 'active text-accent fw-semibold' : 'text-light-emphasis' }}" href="{{ route('explore.index') }}">
                            Explorar
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('reviews.index') ? 'active text-accent fw-semibold' : 'text-light-emphasis' }}" href="{{ route('reviews.index') }}">
                            Mis Notas
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link {{ request()->routeIs('watchlist.index') ? 'active text-accent fw-semibold' : 'text-light-emphasis' }}" href="{{ route('watchlist.index') }}">
                            Watchlist
                        </a>
                    </li>
                    {{-- Solo con sesión: las listas son personales y no hay nada que mostrarle a un invitado. --}}
                    @auth
                        <li class="nav-item">
                            <a class="nav-link {{ request()->routeIs('lists.*') ? 'active text-accent fw-semibold' : 'text-light-emphasis' }}" href="{{ route('lists.index') }}">
                                Listas
                            </a>
                        </li>
                    @endauth
                </ul>

                <!-- Live Search Bar with HTMX -->
                <div class="navbar-search-wrapper position-relative me-lg-3 my-2 my-lg-0">
                    <form action="{{ route('media.search') }}" method="GET" class="d-flex">
                        <div class="input-group">
                            <span class="input-group-text text-secondary">
                                <i class="bi bi-search"></i>
                            </span>
                            <input 
                                type="text" 
                                name="q" 
                                class="form-control" 
                                placeholder="Buscar películas o series..." 
                                autocomplete="off"
                                value="{{ request('q') }}"
                                hx-get="{{ route('media.search') }}"
                                hx-trigger="keyup changed delay:350ms"
                                hx-target="#search-results-dropdown"
                                hx-indicator="#search-spinner"
                                hx-vals='{"dropdown": "1"}'
                            >
                            <span class="input-group-text">
                                <div class="spinner-border spinner-border-sm text-accent htmx-indicator" id="search-spinner" role="status">
                                    <span class="visually-hidden">Buscando...</span>
                                </div>
                            </span>
                        </div>
                    </form>

                    <!-- HTMX Dropdown Results Container -->
                    <div id="search-results-dropdown"></div>
                </div>

                <!-- User Profile / Auth Actions -->
                <div class="d-flex align-items-center gap-2">
                    @auth
                        <div class="dropdown">
                            <button class="btn nav-user-btn rounded-pill py-1 px-3 d-flex align-items-center gap-2 dropdown-toggle text-white" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <span class="nav-user-avatar" aria-hidden="true">
                                    <i class="bi bi-person-fill"></i>
                                </span>
                                <span class="fw-semibold small text-truncate nav-user-name">{{ Auth::user()->name }}</span>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-dark dropdown-menu-end shadow-lg border border-secondary mt-2">
                                <li class="px-3 py-2 border-bottom border-secondary opacity-75">
                                    <div class="text-xs text-secondary text-uppercase fw-bold">Cuenta Activa</div>
                                    <div class="small fw-bold text-white text-truncate">{{ Auth::user()->name }}</div>
                                    <div class="text-xs text-secondary text-truncate">{{ Auth::user()->email }}</div>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2" href="{{ route('reviews.index') }}">
                                        <i class="bi bi-journal-text me-2 text-success"></i> Mis Notas
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2" href="{{ route('watchlist.index') }}">
                                        <i class="bi bi-bookmark-heart me-2 text-info"></i> Mi Watchlist
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2" href="{{ route('lists.index') }}">
                                        <i class="bi bi-collection me-2 text-purple"></i> Mis listas
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2" href="{{ route('reviews.stats') }}">
                                        <i class="bi bi-bar-chart-line me-2 text-warning"></i> Estadísticas
                                    </a>
                                </li>
                                <li>
                                    <a class="dropdown-item py-2" href="{{ route('settings.edit') }}">
                                        <i class="bi bi-tv me-2 text-accent"></i> Plataformas y región
                                    </a>
                                </li>
                                <li><hr class="dropdown-divider border-secondary opacity-25"></li>
                                <li>
                                    <form action="{{ route('logout') }}" method="POST" class="m-0 p-0">
                                        @csrf
                                        <button type="submit" class="dropdown-item py-2 text-danger">
                                            <i class="bi bi-box-arrow-right me-2"></i> Cerrar Sesión
                                        </button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-sm btn-cine-secondary px-3 py-1 fw-semibold">
                            <i class="bi bi-box-arrow-in-right me-1"></i> Ingresar
                        </a>
                        <a href="{{ route('register') }}" class="btn btn-sm btn-cine-primary px-3 py-1 fw-semibold">
                            Registrarse
                        </a>
                    @endauth
                </div>
            </div>
        </div>
    </nav>

    <!-- Toast Notifications (Dharma Cinema Architecture - Top Centered) -->
    <div id="toast-container">
        @if(session('success'))
            <div class="toast-dharma toast-success" role="alert">
                <div class="toast-dharma-header">
                    <i class="bi bi-check-circle-fill"></i>
                    <span class="toast-dharma-title">¡Éxito!</span>
                </div>
                <div class="toast-dharma-body">
                    {{ session('success') }}
                </div>
            </div>
        @endif

        @if(session('info'))
            <div class="toast-dharma toast-info" role="alert">
                <div class="toast-dharma-header">
                    <i class="bi bi-info-circle-fill"></i>
                    <span class="toast-dharma-title">Información</span>
                </div>
                <div class="toast-dharma-body">
                    {{ session('info') }}
                </div>
            </div>
        @endif

        @if(session('error'))
            <div class="toast-dharma toast-error" role="alert">
                <div class="toast-dharma-header">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span class="toast-dharma-title">Atención</span>
                </div>
                <div class="toast-dharma-body">
                    {{ session('error') }}
                </div>
            </div>
        @endif

        @if(session('warning'))
            <div class="toast-dharma toast-warning" role="alert">
                <div class="toast-dharma-header">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    <span class="toast-dharma-title">Aviso</span>
                </div>
                <div class="toast-dharma-body">
                    {{ session('warning') }}
                </div>
            </div>
        @endif
    </div>

    <!-- Main Content -->
    {{-- Las vistas que arrancan con un fotograma a sangre completa manejan su
         propio espaciado: cualquier padding aca dejaria una franja entre el
         navbar y la imagen (ver $fullBleed arriba). --}}
    <main class="{{ $fullBleed || request()->routeIs('login', 'register', 'media.show') ? 'p-0 m-0' : 'py-4' }}">
        @yield('content')
    </main>

    <!-- Modal Container for HTMX Quick Log / Notes -->
    <div class="modal fade" id="logModal" tabindex="-1" aria-labelledby="logModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered rate-modal-dialog">
            <div class="modal-content modal-content-dharma" id="logModalContent">
                {{-- HTMX reemplaza esto con el formulario --}}
                <div class="modal-loading">
                    <div class="spinner-border text-accent" role="status">
                        <span class="visually-hidden">Cargando...</span>
                    </div>
                    <span>Cargando</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Panel lateral de una persona del reparto. El contenido lo trae HTMX
         (PersonController::show); al cerrarlo se limpia para que la proxima
         apertura muestre el spinner y no la persona anterior (ver modules/person-panel.js). --}}
    <div class="offcanvas offcanvas-end person-panel" tabindex="-1" id="personPanel" aria-labelledby="personPanelLabel">
        <div class="person-panel-top">
            <span class="person-panel-eyebrow" id="personPanelLabel">Reparto</span>
            <button type="button" class="modal-close-dharma person-panel-close" data-bs-dismiss="offcanvas" aria-label="Cerrar">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="offcanvas-body person-panel-body" id="personPanelContent">
            <div class="modal-loading">
                <div class="spinner-border text-accent" role="status">
                    <span class="visually-hidden">Cargando...</span>
                </div>
                <span>Cargando</span>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="site-footer mt-auto py-5">
        <div class="container text-center">
            <div class="d-flex justify-content-center align-items-center gap-2 mb-2">
                <i class="bi bi-film text-accent"></i>
                <span class="text-white fw-bold fs-5">Dharma</span>
                <span class="text-secondary">&bull;</span>
                <span class="text-secondary small fst-italic">"Live Together. Watch Together."</span>
            </div>
            <p class="text-secondary small mb-1">
                Namaste and happy watching.
            </p>
            <p class="small text-muted mb-0">
                Desarrollado con Laravel 12, Bootstrap 5, HTMX y la API de TMDB.
            </p>
        </div>
    </footer>

    @stack('scripts')
</body>
</html>
