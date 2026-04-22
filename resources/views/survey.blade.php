@extends('layouts.app')

@section('title', 'Welcome Questionnaire')

@section('content')
<div class="container mx-auto px-4 py-16">
    <div class="max-w-2xl mx-auto bg-white rounded-3xl shadow-xl overflow-hidden border border-gray-100 animate-fade-in-up">
        
        <div class="bg-gradient-to-r from-green-500 to-emerald-600 p-8 text-center text-white">
            <h2 class="text-3xl font-extrabold mb-2">Help Us Personalize Your Experience</h2>
            <p class="text-green-50">Tell our AI Nutritionist a bit about your lifestyle and goals.</p>
        </div>

        <div class="p-8 md:p-12">
            <form id="survey-form" action="{{ url('/survey') }}" method="POST" class="space-y-8">
                @csrf
                
                <!-- Purpose -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4">1. What brings you to GreenBite?</h3>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <label class="flex items-center p-4 border border-gray-200 rounded-xl cursor-pointer hover:bg-green-50 hover:border-green-300 transition group">
                            <input type="radio" name="usage_purpose" value="Eat Healthier" required class="w-5 h-5 text-green-600 border-gray-300 focus:ring-green-500">
                            <span class="ml-3 text-gray-700 font-medium group-hover:text-green-700">Eat Healthier</span>
                        </label>
                        <label class="flex items-center p-4 border border-gray-200 rounded-xl cursor-pointer hover:bg-green-50 hover:border-green-300 transition group">
                            <input type="radio" name="usage_purpose" value="Support Local Farms" class="w-5 h-5 text-green-600 border-gray-300 focus:ring-green-500">
                            <span class="ml-3 text-gray-700 font-medium group-hover:text-green-700">Support Local Farms</span>
                        </label>
                        <label class="flex items-center p-4 border border-gray-200 rounded-xl cursor-pointer hover:bg-green-50 hover:border-green-300 transition group">
                            <input type="radio" name="usage_purpose" value="Reduce Carbon Footprint" class="w-5 h-5 text-green-600 border-gray-300 focus:ring-green-500">
                            <span class="ml-3 text-gray-700 font-medium group-hover:text-green-700">Reduce Carbon Footprint</span>
                        </label>
                    </div>
                </div>

                <!-- Dietary Habits -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4">2. Do you have any specific dietary habits?</h3>
                    <select name="dietary_habits" required class="w-full px-4 py-4 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-transparent transition font-medium text-gray-700">
                        <option value="" disabled selected>Select an option</option>
                        <option value="No Restrictions">No Restrictions</option>
                        <option value="Vegetarian/Vegan">Vegetarian / Vegan</option>
                        <option value="Keto/Low Carb">Keto / Low Carb</option>
                        <option value="High Protein">High Protein</option>
                    </select>
                </div>

                <!-- Goals -->
                <div>
                    <h3 class="text-lg font-bold text-gray-900 mb-4">3. What is your primary health goal?</h3>
                    <input type="text" name="goals" placeholder="e.g., Lose weight, Gain muscle, More energy..." required class="w-full px-4 py-4 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-transparent transition font-medium">
                </div>

                <div class="pt-6 border-t border-gray-100">
                    <button type="submit" class="w-full bg-green-600 text-white py-4 px-6 rounded-xl hover:bg-green-700 transition-colors font-bold text-lg shadow-lg shadow-green-600/30 flex items-center justify-center">
                        Generate My AI Profile <i data-lucide="sparkles" class="ml-2 w-5 h-5"></i>
                    </button>
                    <p class="text-center text-sm text-gray-500 mt-4">We respect your privacy. This data is only used for personalized recommendations.</p>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    $('#survey-form').on('submit', function(e) {
        // Simple UI enhancement before actual POST
        const btn = $(this).find('button[type="submit"]');
        btn.html('<i data-lucide="loader" class="animate-spin mr-2 w-5 h-5"></i> Processing Data...');
        lucide.createIcons();
    });
</script>
@endsection
