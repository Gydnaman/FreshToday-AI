<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SurveyController;

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

Route::get('/cart', function () {
    return view('welcome'); // Placeholder for cart page
});

Route::get('/survey', [SurveyController::class, 'create']);
Route::post('/survey', [SurveyController::class, 'store']);

Route::get('/dashboard', function () {
    $aiMenu = session('daily_ai_menu', 'No menu generated yet. Please complete your profile survey!');
    return view('dashboard', compact('aiMenu'));
});
