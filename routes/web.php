<?php

use App\Http\Controllers\Admin\RepartidorController as AdminRepartidorController;
use App\Http\Controllers\Admin\RetiroConfigController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ImportController;
use App\Http\Controllers\PosController;
use App\Http\Controllers\RepartidorController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::post('/envios/{envio}/estado', [DashboardController::class, 'actualizarEstado'])->name('envios.estado');
    Route::post('/envios/{envio}/repartidor', [DashboardController::class, 'asignarRepartidor'])->name('envios.repartidor');

    Route::get('/import', [ImportController::class, 'index'])->name('import.index');
    Route::post('/import', [ImportController::class, 'store'])->name('import.store');
    Route::post('/import/{eventoDeliveryConfig}/sync', [ImportController::class, 'sync'])->name('import.sync');

    Route::get('/repartidores', [AdminRepartidorController::class, 'index'])->name('repartidores.index');
    Route::post('/repartidores', [AdminRepartidorController::class, 'store'])->name('repartidores.store');
    Route::post('/repartidores/{repartidor}/desactivar', [AdminRepartidorController::class, 'destroy'])->name('repartidores.destroy');

    Route::get('/mapa', [AdminRepartidorController::class, 'mapa'])->name('repartidores.mapa');
    Route::get('/repartidores-ubicaciones.json', [AdminRepartidorController::class, 'ubicaciones'])->name('repartidores.ubicaciones');

    Route::get('/retiro', [RetiroConfigController::class, 'index'])->name('retiro.index');
    Route::post('/retiro', [RetiroConfigController::class, 'store'])->name('retiro.store');
    Route::post('/retiro/{eventoRetiroConfig}/sync', [RetiroConfigController::class, 'sync'])->name('retiro.sync');

    Route::get('/pos', [PosController::class, 'index'])->name('pos.index');
    Route::get('/pos/buscar', [PosController::class, 'buscar'])->name('pos.buscar');
    Route::post('/pos/retiros/{retiro}/entregar', [PosController::class, 'entregar'])->name('pos.entregar');
    Route::post('/pos/retiros/{retiro}/deshacer', [PosController::class, 'deshacer'])->name('pos.deshacer');
});

// Acceso del repartidor: token opaco en la URL, sin login (ver
// RepartidorController).
Route::get('/repartidor/{token}', [RepartidorController::class, 'show'])->name('repartidor.show');
Route::post('/repartidor/{token}/ubicacion', [RepartidorController::class, 'ubicacion'])->name('repartidor.ubicacion');
Route::post('/repartidor/{token}/envios/{envio}/entregar', [RepartidorController::class, 'marcarEntregado'])->name('repartidor.entregar');
