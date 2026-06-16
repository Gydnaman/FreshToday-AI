<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\AiMenuService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class GenerateDailyMenuJob implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $userId) {}

    public function handle(AiMenuService $ai): void
    {
        $user = User::find($this->userId);
        if (! $user) {
            Log::warning('GenerateDailyMenuJob: user not found', ['user_id' => $this->userId]);

            return;
        }
        try {
            $ai->generateDailyMenuForUser($user);
        } catch (\Throwable $e) {
            Log::error('GenerateDailyMenuJob failed', [
                'user_id' => $this->userId, 'error' => $e->getMessage(),
            ]);
            throw $e; // 触发重试
        }
    }
}
