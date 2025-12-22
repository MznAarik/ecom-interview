<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\ProductController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->name('auth.register');
Route::post('/login', [AuthController::class, 'login'])->name('auth.login');
Route::post('/logout', [AuthController::class, 'logout'])->name('auth.logout');


Route::middleware(['auth:api, check.admin'])->prefix('/products')->group(function () {
    Route::get('/', [ProductController::class, 'index']);
    Route::post('/', [ProductController::class, 'store']);
    Route::match(['put', 'patch'], '/{id}', [ProductController::class, 'update']);
    Route::get('/{id}', [ProductController::class, 'show']);
    Route::delete('/{id}', [ProductController::class, 'destroy']);
});


Route::middleware('auth:api')->group(function () {
    Route::post('/cart/add', [CartController::class, 'addToCart']);
    Route::get('/cart', [CartController::class, 'viewCart']);
    Route::put('/cart/update/{id}', [CartController::class, 'updateCartItem']);
    Route::delete('/cart/remove/{id}', [CartController::class, 'removeCartItem']);

    Route::post('/cart/checkout', [CartController::class, 'checkout']);
});
