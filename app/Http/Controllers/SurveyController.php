<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\AiMenuService;
// use App\Models\UserPreference;
// use App\Models\DailyMenu;

class SurveyController extends Controller
{
    protected $aiService;

    public function __construct(AiMenuService $aiService)
    {
        $this->aiService = $aiService;
    }

    public function create()
    {
        return view('survey');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'usage_purpose' => 'required|string',
            'dietary_habits' => 'required|string',
            'goals' => 'required|string'
        ]);

        /* 
        // 1. Save preferences to DB (Uncomment when Auth & DB are ready)
        $user = auth()->user();
        UserPreference::updateOrCreate(
            ['user_id' => $user->id],
            $validated
        );
        */

        // 2. Fetch available products (mocked for now, replace with Product::all()->pluck('name')->toArray())
        $availableProducts = [
            'Organic Kale', 'Seasonal Fruits', 'Free-Range Eggs', 'Cherry Tomatoes'
        ];

        // 3. Query the Gemini API Service
        $dailyMenu = $this->aiService->generateDailyMenu($validated, $availableProducts);

        /*
        // 4. Save the generated menu to DB
        DailyMenu::create([
            'user_id' => $user->id,
            'menu_content' => $dailyMenu,
            'date' => now()->toDateString()
        ]);
        */

        // For demo purposes, we will store it in the session to show on the dashboard immediately
        session(['daily_ai_menu' => $dailyMenu]);

        return redirect('/dashboard')->with('success', 'Profile updated! Your AI menu is ready.');
    }
}
