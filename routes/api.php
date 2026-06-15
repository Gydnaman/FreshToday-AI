<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\CartController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\MenuController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\PaymeWebhookController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StripeWebhookController;
use App\Http\Controllers\Api\SubscriptionController;
use App\Http\Controllers\Api\SurveyController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes（Sprint 1）
|--------------------------------------------------------------------------
| 完整定义见 docs/bmad/api-contract.md §2
*/

// 公开端点
Route::post('/register', [AuthController::class, 'register']);
Route::post('/login',    [AuthController::class, 'login']);

Route::get('/products',           [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::get('/categories',         [CategoryController::class, 'index']);

// Webhook（无 auth，签名校验）
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle']);
Route::post('/payme/webhook',  [PaymeWebhookController::class, 'handle']);

// staging-only 调试端点
if (app()->environment('testing', 'staging')) {
    Route::middleware('auth:sanctum')->prefix('test')->group(function () {
        Route::get('orders/{order}', fn (\App\Models\Order $order) => response()->json(['data' => $order->load('products')]));
        Route::post('tick', fn (\Illuminate\Http\Request $r) => \Illuminate\Support\Carbon::setTestNow(now()->addSeconds($r->input('advance_seconds', 0))) && response()->json(['now' => now()]));
    });
}

// 鉴权端点（Sanctum token）
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me',      [AuthController::class, 'me']);

    Route::get   ('/cart',          [CartController::class, 'index']);
    Route::post  ('/cart',          [CartController::class, 'store']);
    Route::patch ('/cart/{item}',   [CartController::class, 'update']);
    Route::delete('/cart/{item}',   [CartController::class, 'destroy']);

    Route::get('/orders',                  [OrderController::class, 'index']);
    Route::post('/orders',                 [OrderController::class, 'store']);
    Route::get('/orders/{order}',          [OrderController::class, 'show']);
    Route::post('/orders/{order}/pay',     [OrderController::class, 'pay']);

    Route::get ('/survey', [SurveyController::class, 'show']);
    Route::post('/survey', [SurveyController::class, 'store']);

    Route::get ('/menu/today',      [MenuController::class, 'today']);
    Route::post('/menu/regenerate', [MenuController::class, 'regenerate']);

    Route::get   ('/subscriptions',                 [SubscriptionController::class, 'index']);
    Route::post  ('/subscriptions',                 [SubscriptionController::class, 'store']);
    Route::delete('/subscriptions/{subscription}',  [SubscriptionController::class, 'destroy']);
});
