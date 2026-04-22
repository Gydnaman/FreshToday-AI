@extends('layouts.app')

@section('title', 'Sign In')

@section('content')
<div class="container mx-auto px-4 py-16 flex justify-center items-center">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-xl overflow-hidden p-8 border border-gray-100 animate-fade-in-up">
        <div class="text-center mb-8">
            <div class="inline-flex justify-center items-center bg-green-100 rounded-full w-16 h-16 mb-4">
                <i data-lucide="leaf" class="h-8 w-8 text-green-600"></i>
            </div>
            <h2 class="text-3xl font-extrabold text-gray-900" id="form-title">Welcome Back!</h2>
            <p class="text-gray-500 mt-2">Sustainable food subscriptions for Hong Kong</p>
        </div>
        
        <form id="auth-form" class="space-y-5">
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                <input type="email" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-transparent transition" placeholder="your@email.hk">
            </div>
            
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                <input type="password" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-transparent transition" placeholder="••••••••">
            </div>
            
            <div id="address-field" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-1">Hong Kong Address</label>
                <input type="text" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-transparent transition" placeholder="Delivery exact address">
            </div>
            
            <button type="submit" class="w-full bg-green-600 text-white py-3 px-4 rounded-xl hover:bg-green-700 transition-colors flex items-center justify-center font-bold text-lg shadow-md mt-4">
                <i data-lucide="log-in" class="mr-2 w-5 h-5" id="btn-icon"></i> <span id="btn-text">Sign In</span>
            </button>
        </form>
        
        <div class="mt-8 text-center border-t border-gray-100 pt-6">
            <button id="toggle-mode" class="text-green-600 hover:text-green-800 font-medium transition-colors">
                Don't have an account? Sign Up
            </button>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        let isLogin = true;
        
        $('#toggle-mode').on('click', function() {
            isLogin = !isLogin;
            if (isLogin) {
                $('#form-title').text('Welcome Back!');
                $('#address-field').addClass('hidden').find('input').removeAttr('required');
                $('#btn-text').text('Sign In');
                $(this).text("Don't have an account? Sign Up");
            } else {
                $('#form-title').text('Join GreenBite');
                $('#address-field').removeClass('hidden').hide().slideDown().find('input').attr('required', true);
                $('#btn-text').text('Create Account');
                $(this).text('Already have an account? Login');
            }
        });

        $('#auth-form').on('submit', function(e) {
            e.preventDefault();
            alert(isLogin ? "Simulating Login via jQuery..." : "Simulating Account Creation...");
            window.location.href = "{{ url('/catalog') }}";
        });
    });
</script>
@endsection
