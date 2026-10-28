<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductSummaryController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// Públicas
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/auth/code/request', [AuthController::class, 'requestCode'])->middleware('throttle:5,1');
Route::post('/auth/code/verify', [AuthController::class, 'verifyCode'])->middleware('throttle:10,1');
Route::post('/auth/2fa/challenge', [AuthController::class, 'twoFactorChallenge'])->middleware('throttle:10,1');
Route::post('/auth/google', [AuthController::class, 'google'])->middleware('throttle:10,1');

// Protegidas
Route::middleware('auth:sanctum')->group(function () {
    Route::get('/me', [AuthController::class, 'me']);
    Route::post('/logout', [AuthController::class, 'logout']);

    Route::post('/password/change', [AuthController::class, 'changePassword']);

    // Productos (cada ruta exige su permiso)
    // /products/summary va ANTES de /products/{product}, si no "summary" se tomaría como un id
    Route::get('/products/summary', ProductSummaryController::class)->middleware('permission:ver-productos');
    Route::get('/products', [ProductController::class, 'index'])->middleware('permission:ver-productos');
    Route::post('/products', [ProductController::class, 'store'])->middleware('permission:crear-productos');
    Route::get('/products/{product}', [ProductController::class, 'show'])->middleware('permission:ver-productos');
    Route::put('/products/{product}', [ProductController::class, 'update'])->middleware('permission:editar-productos');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('permission:eliminar-productos');

    // Usuarios (solo con el permiso gestionar-usuarios)
    Route::middleware('permission:gestionar-usuarios')->group(function () {
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::patch('/users/{user}/status', [UserController::class, 'status']);
        Route::post('/users/{user}/reset-2fa', [UserController::class, 'resetTwoFactor']);
    });

    // 2FA con QR
    Route::post('/2fa/setup', [AuthController::class, 'twoFactorSetup']);
    Route::post('/2fa/enable', [AuthController::class, 'twoFactorEnable'])->middleware('throttle:10,1');
    Route::post('/2fa/disable', [AuthController::class, 'twoFactorDisable']);
});
