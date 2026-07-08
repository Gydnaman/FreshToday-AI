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
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes（Sprint 1）
|--------------------------------------------------------------------------
| 完整定义见 docs/bmad/api-contract.md §2
*/

// 公开端点（限流：60/min 防暴力破解）
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:30,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:30,1');

Route::get('/products', [ProductController::class, 'index']);
Route::get('/products/{product}', [ProductController::class, 'show']);
Route::get('/categories', [CategoryController::class, 'index']);

// Webhook（无 auth，签名校验 + 显式高频限流 10000/min）
//   - 不用 throttle:api（60/min 不够 Stripe/PayMe 高频重发）
//   - 路由级挂 throttle:10000,1 数字形式
//   - 不用 withoutMiddleware 排除 throttle 别名（会误伤后面挂的 throttle）
//   - 改为：将 webhook 路由移出 api middleware group，自定义挂 throttle
//   - 10000/min 等于"几乎无限"——为防完全失控的网关被打爆
//   - 幂等性由 stripe_webhook_events.provider_event_id UQ + Payment.status==='succeeded' 短路保证
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handle'])
    ->middleware('throttle:10000,1');
Route::post('/payme/webhook', [PaymeWebhookController::class, 'handle'])
    ->middleware('throttle:10000,1');

// staging-only 调试端点
if (app()->environment('testing', 'staging')) {
    Route::middleware('auth:sanctum')->prefix('test')->group(function () {
        Route::get('orders/{order}', fn (Order $order) => response()->json(['data' => $order->load('products')]));
        Route::post('tick', fn (Request $r) => Carbon::setTestNow(now()->addSeconds($r->input('advance_seconds', 0))) && response()->json(['now' => now()]));
    });
}

// 鉴权端点（Sanctum token）
Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/cart', [CartController::class, 'index']);
    Route::post('/cart', [CartController::class, 'store']);
    Route::patch('/cart/{item}', [CartController::class, 'update']);
    Route::delete('/cart/{item}', [CartController::class, 'destroy']);

    Route::get('/orders', [OrderController::class, 'index']);
    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{order}', [OrderController::class, 'show']);
    Route::post('/orders/{order}/pay', [OrderController::class, 'pay']);

    Route::get('/survey', [SurveyController::class, 'show']);
    Route::post('/survey', [SurveyController::class, 'store']);

    Route::get('/menu/today', [MenuController::class, 'today']);
    Route::post('/menu/regenerate', [MenuController::class, 'regenerate']);

    Route::get('/subscriptions', [SubscriptionController::class, 'index']);
    Route::post('/subscriptions', [SubscriptionController::class, 'store']);
    Route::delete('/subscriptions/{subscription}', [SubscriptionController::class, 'destroy']);
});
