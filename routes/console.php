<?php

use App\Jobs\AutoDeliverOrdersJob;
use App\Jobs\CancelExpiredOrdersJob;
use App\Jobs\FulfillSubscriptionsJob;
use App\Jobs\GenerateDailyMenuJob;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| 任务调度（Sprint 1）
|--------------------------------------------------------------------------
| 详见 docs/bmad/deployment.md §9.5
|
| 生产环境需在 crontab 添加：
|   * * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
*/

// 每 5 分钟：取消 30 分钟未支付的订单
Schedule::call(function () {
    CancelExpiredOrdersJob::dispatchSync();
})->everyFiveMinutes()->name('cancel-expired-orders')->withoutOverlapping();

// 每日 02:00：自动确认 7 天前发货的订单
Schedule::call(function () {
    AutoDeliverOrdersJob::dispatchSync();
})->dailyAt('02:00')->name('auto-deliver-orders')->withoutOverlapping();

// 每日 03:00：为到期订阅生成履约订单
Schedule::call(function () {
    FulfillSubscriptionsJob::dispatchSync();
})->dailyAt('03:00')->name('fulfill-subscriptions')->withoutOverlapping();

// 每日 04:00：批量为活跃用户生成 AI 菜单
Schedule::call(function () {
    User::whereHas('userPreferences')->chunk(100, function ($users) {
        foreach ($users as $user) {
            GenerateDailyMenuJob::dispatch($user->id);
        }
    });
})->dailyAt('04:00')->name('generate-daily-menus')->withoutOverlapping();
