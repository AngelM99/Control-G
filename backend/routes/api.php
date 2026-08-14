<?php

use App\Http\Controllers\Api\ContactController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\OperationController;
use App\Http\Controllers\Api\PaymentController;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;

// Rutas públicas
Route::post('/login', [AuthController::class, 'login']);

// Rutas protegidas
Route::middleware('auth:sanctum')->group(function () {
    // Autenticación
    Route::get('/user', [AuthController::class, 'user']);
    Route::post('/logout', [AuthController::class, 'logout']);

    // Contactos
    Route::get('contacts/search', [ContactController::class, 'searchByDni']);
    Route::get('contacts/deudas-periodo', [ContactController::class, 'deudasPorPeriodo']);
    Route::get('contacts/{contact}/ficha', [ContactController::class, 'fichaConsolidada']);
    Route::apiResource('contacts', ContactController::class)->only(['index', 'store', 'update', 'destroy']);

    // Categorías y Métodos de Pago
    Route::apiResource('categories', \App\Http\Controllers\Api\CategoryController::class);
    Route::apiResource('payment-methods', \App\Http\Controllers\Api\PaymentMethodController::class)->only(['index', 'update', 'store']);

    // Operaciones
    Route::apiResource('operations', OperationController::class)->only(['index', 'store', 'show', 'destroy']);

    // Pagos / Abonos
    Route::apiResource('payments', PaymentController::class)->only(['store', 'show', 'destroy']);

    // Dashboard & KPIs
    Route::prefix('dashboard')->group(function () {
        Route::get('kpis', [DashboardController::class, 'kpis']);
        Route::get('tarjetas', [DashboardController::class, 'tarjetasCredito']);
        Route::get('exigibilidad', [DashboardController::class, 'exigibilidadMensual']);
    });
});
