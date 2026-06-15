<?php

namespace App\Services;

use App\Models\DailyMenu;
use App\Models\NotificationPreference;
use App\Models\Order;
use App\Models\User;
use App\Models\UserSubscription;
use Illuminate\Support\Facades\Log;

/**
 * 通知服务（Day 5，最易并行）
 */
class NotificationService
{
    /** 邮件/站内信统一入口 */
    public function sendOrderUpdate(Order $order, string $event): void
    {
        $prefs = $this->getPrefs($order->user);
        if (! $prefs?->email_order) {
            Log::info('Order email skipped by user preference', ['order_id' => $order->id]);
            return;
        }
        if ($this->isQuietHours($prefs)) {
            Log::info('Order email deferred: quiet hours', ['order_id' => $order->id]);
            return;
        }

        // Sprint 1 占位：实际 Mail::to()->send()
        Log::info('[Notification] order update', [
            'order_id' => $order->id, 'event' => $event, 'user_id' => $order->user_id,
        ]);
    }

    public function sendMenuReminder(User $user, DailyMenu $menu): void
    {
        $prefs = $this->getPrefs($user);
        if (! $prefs?->email_menu) return;

        Log::info('[Notification] daily menu', ['user_id' => $user->id, 'menu_id' => $menu->id]);
    }

    public function sendSubscriptionRenewal(UserSubscription $sub): void
    {
        Log::info('[Notification] subscription renewal', ['sub_id' => $sub->id]);
    }

    private function getPrefs(User $user): ?NotificationPreference
    {
        return NotificationPreference::firstOrCreate(
            ['user_id' => $user->id],
            ['email_order' => 1, 'email_menu' => 1, 'push_enabled' => 1],
        );
    }

    private function isQuietHours(NotificationPreference $prefs): bool
    {
        if (! $prefs->quiet_hours_start || ! $prefs->quiet_hours_end) return false;
        $now = now()->format('H:i:s');
        return $now >= $prefs->quiet_hours_start && $now <= $prefs->quiet_hours_end;
    }
}
