<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\MediaController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\WatchlistController;
use Illuminate\Support\Facades\Route;

// =========================================================================
// Public Routes (Exploration & Discovery)
// =========================================================================
Route::get('/', [MediaController::class, 'index'])->name('home');
Route::get('/search', [MediaController::class, 'search'])->name('media.search');
Route::get('/media/{type}/{id}', [MediaController::class, 'show'])
    ->where('type', 'movie|tv')
    ->whereNumber('id')
    ->name('media.show');

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
Route::get('/reviews/modal/{type}/{id}', [ReviewController::class, 'createModal'])
    ->where('type', 'movie|tv')
    ->whereNumber('id')
    ->name('reviews.modal');
Route::post('/reviews', [ReviewController::class, 'store'])->name('reviews.store');
// Editar una entrada concreta del diario. Fuera de `auth` como el resto de las
// acciones del modal: con la sesión vencida devuelven el modal de "iniciá
// sesión" en vez de un redirect que htmx metería adentro del propio modal.
Route::get('/reviews/{review}/edit', [ReviewController::class, 'editModal'])
    ->whereNumber('review')
    ->name('reviews.edit');
Route::patch('/reviews/{review}', [ReviewController::class, 'update'])
    ->whereNumber('review')
    ->name('reviews.update');

// =========================================================================
// Authenticated User Routes (Diary, Full Watchlist & Logout)
// =========================================================================
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/diary', [ReviewController::class, 'index'])->name('reviews.index');
    Route::delete('/reviews/{review}', [ReviewController::class, 'destroy'])->name('reviews.destroy');
    Route::get('/watchlist', [WatchlistController::class, 'index'])->name('watchlist.index');
    Route::patch('/watchlist/{watchlist}', [WatchlistController::class, 'update'])->name('watchlist.update');
});
