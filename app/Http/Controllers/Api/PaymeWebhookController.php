<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class PaymeWebhookController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();
        $signature = $request->header('X-Payme-Signature');

        // Sprint 2 接入：复用 StripeWebhookEvent 表（provider='payme'）
        try {
            $this->payments->handleWebhook('payme', $payload, $signature);
        } catch (\Throwable $e) {
            Log::error('Payme webhook error', ['error' => $e->getMessage()]);
        }

        return response()->json(['received' => true]);
    }
}
