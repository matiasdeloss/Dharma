<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\DiaryStatsController;
use App\Http\Controllers\ExploreController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\MediaListController;
use App\Http\Controllers\PersonController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\WatchlistController;
use Illuminate\Support\Facades\Route;

// =========================================================================
// Public Routes (Exploration & Discovery)
// =========================================================================
Route::get('/', [MediaController::class, 'index'])->name('home');
Route::get('/explorar', [ExploreController::class, 'index'])->name('explore.index');
Route::get('/search', [MediaController::class, 'search'])->name('media.search');
Route::get('/media/{type}/{id}', [MediaController::class, 'show'])
    ->where('type', 'movie|tv')
    ->whereNumber('id')
    ->name('media.show');
// Panel lateral de una persona del reparto (fragmento HTMX, sin pagina propia).
Route::get('/personas/{id}', [PersonController::class, 'show'])
    ->whereNumber('id')
    ->name('person.show');

// =========================================================================
// Guest Authentication Routes (Login & Register)
// =========================================================================
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegisterForm'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// =========================================================================
// HTMX Interactive Actions (Handled with custom auth triggers & toasts)
// =========================================================================
Route::post('/watchlist/toggle', [WatchlistController::class, 'toggle'])->name('watchlist.toggle');
// Dos modales sobre la misma entrada del diario: calificar (estrellas + fecha)
// y reseñar (texto, spoilers, nota privada). Los dos guardan en `reviews.store`.
Route::get('/reviews/rate/{type}/{id}', [ReviewController::class, 'rateModal'])
    ->where('type', 'movie|tv')
    ->whereNumber('id')
    ->name('reviews.rate');
Route::get('/reviews/write/{type}/{id}', [ReviewController::class, 'writeModal'])
    ->where('type', 'movie|tv')
    ->whereNumber('id')
    ->name('reviews.write');
Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');
// Modal "Agregar a lista" (ficha y diario). Fuera de `auth` por lo mismo que
// los de arriba: al invitado le devuelve el modal de iniciar sesión.
Route::get('/listas/agregar/{type}/{id}', [MediaListController::class, 'picker'])
    ->where('type', 'movie|tv')
    ->whereNumber('id')
    ->name('lists.picker');

// =========================================================================
// Authenticated User Routes (Diary, Full Watchlist & Logout)
// =========================================================================
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/diary', [ReviewController::class, 'index'])->name('reviews.index');
    Route::get('/diary/stats', [DiaryStatsController::class, 'index'])->name('reviews.stats');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
    Route::get('/watchlist', [WatchlistController::class, 'index'])->name('watchlist.index');
    Route::get('/watchlist/al-azar', [WatchlistController::class, 'random'])->name('watchlist.random');
    Route::patch('/watchlist/{watchlist}', [WatchlistController::class, 'update'])->name('watchlist.update');

    // Listas propias
    Route::get('/listas', [MediaListController::class, 'index'])->name('lists.index');
    Route::get('/listas/nueva', [MediaListController::class, 'create'])->name('lists.create');
    Route::post('/listas', [MediaListController::class, 'store'])->name('lists.store');
    Route::get('/listas/{list}', [MediaListController::class, 'show'])->whereNumber('list')->name('lists.show');
    Route::get('/listas/{list}/editar', [MediaListController::class, 'edit'])->whereNumber('list')->name('lists.edit');
    Route::put('/listas/{list}', [MediaListController::class, 'update'])->whereNumber('list')->name('lists.update');
    Route::delete('/listas/{list}', [MediaListController::class, 'destroy'])->whereNumber('list')->name('lists.destroy');
    Route::post('/listas/{list}/titulos', [MediaListController::class, 'toggleItem'])->whereNumber('list')->name('lists.items.toggle');
    Route::delete('/listas/{list}/titulos/{item}', [MediaListController::class, 'removeItem'])->whereNumber(['list', 'item'])->name('lists.items.destroy');
    Route::post('/listas/{list}/orden', [MediaListController::class, 'reorder'])->whereNumber('list')->name('lists.reorder');
    Route::get('/ajustes', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::put('/ajustes', [SettingsController::class, 'update'])->name('settings.update');
});
