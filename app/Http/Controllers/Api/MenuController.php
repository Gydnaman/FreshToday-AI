<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\GuardFailedException;
use App\Http\Controllers\Controller;
use App\Services\AiMenuService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MenuController extends Controller
{
    public function __construct(private readonly AiMenuService $aiService) {}

    public function today(Request $request): JsonResponse
    {
        $menu = $this->aiService->getTodayMenu($request->user());
        if (! $menu) {
            return response()->json([
                'error' => ['code' => 'NO_PREFERENCES', 'message' => '请先完成问卷'],
            ], 404);
        }
        return response()->json([
            'data' => [
                'date'    => $menu->date->toDateString(),
                'content' => $menu->menu_content,
                'source'  => $menu->source ?? 'gemini',
                'cached'  => true,
            ],
        ]);
    }

    public function regenerate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'override_preferences' => 'nullable|array',
        ]);
        try {
            $menu = $this->aiService->regenerate($request->user(), $data['override_preferences'] ?? null);
            return response()->json([
                'data' => [
                    'date'        => $menu->date->toDateString(),
                    'content'     => $menu->menu_content,
                    'source'      => $menu->source ?? 'gemini',
                    'tokens_used' => $menu->tokens_used ?? 0,
                ],
            ]);
        } catch (GuardFailedException $e) {
            $payload = $e->toApiPayload();
            return response()->json(['error' => $payload], $payload['http']);
        }
    }
}
