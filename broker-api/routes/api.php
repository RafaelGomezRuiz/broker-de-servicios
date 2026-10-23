<?php

use App\Http\Controllers\Admin\AuthController;
use App\Http\Controllers\Admin\PanelController;
use App\Http\Controllers\HealthController;
use App\Http\Controllers\V1\CorreoController;
use App\Http\Controllers\V1\LoteController;
use App\Http\Controllers\V1\SistemaActualController;
use Illuminate\Support\Facades\Route;

// Sondas de Kubernetes (liveness/readiness). Sin autenticación.
Route::get('/health', HealthController::class);

// API para los sistemas: siempre con llave y con límite por sistema.
Route::prefix('v1')
    ->middleware(['auth:sanctum', 'throttle:sistemas'])
    ->group(function () {
        Route::get('/sistema', SistemaActualController::class)->name('sistema.comprobar');

        Route::post('/correos', [CorreoController::class, 'store'])->name('correos.enviar');
        Route::post('/correos/lote', [LoteController::class, 'store'])->name('correos.enviarLote');
        Route::get('/correos', [CorreoController::class, 'index'])->name('correos.listar');
        Route::get('/correos/{id}', [CorreoController::class, 'show'])->name('correos.consultar');
    });

// Para la SPA: personas con JWT. No se documenta ni se consume a mano.
Route::prefix('admin')->group(function () {
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    Route::middleware(['auth:api', 'sesion.activa'])->group(function () {
        Route::get('/auth/me', [AuthController::class, 'yo']);
        Route::get('/servicios', [PanelController::class, 'servicios']);
        Route::get('/sistemas', [PanelController::class, 'sistemas']);
        Route::get('/correo/estado', [PanelController::class, 'estadoCorreo']);
    });
});
