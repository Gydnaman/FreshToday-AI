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

// 需登录的页面
Route::middleware('auth')->group(function () {
    Route::get('/orders', function () {
        return view('shop.orders');
    });
    Route::get('/cart', function () {
        return view('shop.cart');
    });
    Route::get('/dashboard', [\App\Http\Controllers\Web\DashboardController::class, 'index'])->name('dashboard');
    Route::get('/checkout', [CheckoutController::class, 'show']);
    Route::post('/checkout/place', [CheckoutController::class, 'place'])->name('web.checkout.place');
});

Route::get('/survey', [SurveyController::class, 'create']);
Route::post('/survey', [SurveyController::class, 'store']);

// Admin 路由组（登录即可访问，权限由 Controller + Policy 控制）
Route::prefix('admin')->middleware('auth')->name('admin.')->group(function () {
    Route::get('/products', [AdminProductController::class, 'index'])->name('products.index');
    Route::get('/products/create', [AdminProductController::class, 'create'])->name('products.create');
    Route::post('/products', [AdminProductController::class, 'store'])->name('products.store');
    Route::get('/products/{product}/edit', [AdminProductController::class, 'edit'])->name('products.edit');
    Route::put('/products/{product}', [AdminProductController::class, 'update'])->name('products.update');
});
