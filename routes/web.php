<?php

use App\Http\Controllers\Admin\ProductController as AdminProductController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\SurveyController;
use App\Http\Controllers\Web\CheckoutController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('pages.welcome');
});

Route::get('/catalog', [ProductController::class, 'index']);

Route::get('/login', function () {
    return view('auth.login');
})->name('login');

// Admin 登录入口（独立视图，校验 is_admin 后才放行）
Route::get('/admin/login', function () {
    return view('admin.login');
})->name('admin.login');

Route::get('/subscriptions', function () {
    return view('shop.subscriptions');
});

Route::get('/orders', function () {
    return view('shop.orders');
});

// Cart
Route::get('/cart', function () {
    return view('shop.cart');
});

// Checkout（session 认证）
Route::middleware('auth')->group(function () {
    Route::get('/checkout', [CheckoutController::class, 'show']);
    Route::post('/checkout/place', [CheckoutController::class, 'place'])->name('web.checkout.place');
});

Route::get('/survey', [SurveyController::class, 'create']);
Route::post('/survey', [SurveyController::class, 'store']);

Route::get('/dashboard', function () {
    $aiMenu = session('daily_ai_menu', 'No menu generated yet. Please complete your profile survey!');

    return view('shop.dashboard', compact('aiMenu'));
});

// Admin 路由组（最小版：仅产品列表 + 创建）
Route::prefix('admin')->middleware('admin')->name('admin.')->group(function () {
    Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [AdminProductController::class, 'create'])->name('products.create');
    Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [AdminProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [AdminProductController::class, 'update'])->name('products.update');
});
