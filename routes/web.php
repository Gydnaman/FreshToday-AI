<?php

use App\Http\Controllers\ProductController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\Web\CheckoutController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/catalog', [ProductController::class, 'index']);

Route::get('/login', function () {
    return view('auth');
});

Route::get('/subscriptions', function () {
    return view('subscriptions');
});

Route::get('/orders', function () {
    return view('orders'); // Placeholder for orders
});

// Cart（已接通：catalog/checkout 用同一份 cart.blade，guest 走 localStorage 兜底）
Route::get('/cart', function () {
    return view('cart');
});

// Checkout（前端用 localStorage token 鉴权；服务端不强校验，避免双重门）
Route::get('/checkout', [CheckoutController::class, 'show']);
Route::post('/checkout/place', [CheckoutController::class, 'place'])->name('web.checkout.place');

Route::get('/survey', [SurveyController::class, 'create']);
Route::post('/survey', [SurveyController::class, 'store']);

Route::get('/dashboard', function () {
    $aiMenu = session('daily_ai_menu', 'No menu generated yet. Please complete your profile survey!');
    return view('dashboard', compact('aiMenu'));
});
