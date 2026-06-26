@extends('layouts.app')

@section('title', 'Admin — GreenBite')

@section('content')
<div class="min-h-screen bg-gray-100 flex items-center justify-center px-4">
    <div class="w-full max-w-sm">
        <!-- Card -->
        <div class="bg-white rounded-2xl shadow-lg overflow-hidden border border-gray-200">
            <!-- Header -->
            <div class="bg-gray-900 px-6 py-5 text-center">
                <div class="inline-flex justify-center items-center bg-gray-700 rounded-xl w-12 h-12 mb-2">
                    <i data-lucide="shield-check" class="h-6 w-6 text-green-400"></i>
                </div>
                <h1 class="text-xl font-bold text-white tracking-tight">Admin Login</h1>
                <p class="text-gray-400 text-sm mt-1">GreenBite 后台管理</p>
            </div>

            <!-- Form -->
            <div class="px-6 py-6 space-y-4">
                <!-- Error -->
                <div id="login-error" class="hidden p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                    <span id="login-error-msg"></span>
                </div>

                <!-- Email -->
                <div>
                    <label for="login-email" class="block text-sm font-medium text-gray-700 mb-1">Email</label>
                    <input id="login-email" type="email" required autofocus
                           class="w-full px-3 py-2.5 bg-gray-50 border border-gray-300 rounded-lg focus:bg-white focus:ring-2 focus:ring-gray-900 focus:border-transparent transition text-sm"
                           placeholder="admin@greenbite.hk">
                </div>

                <!-- Password -->
                <div>
                    <label for="login-password" class="block text-sm font-medium text-gray-700 mb-1">Password</label>
                    <input id="login-password" type="password" required
                           class="w-full px-3 py-2.5 bg-gray-50 border border-gray-300 rounded-lg focus:bg-white focus:ring-2 focus:ring-gray-900 focus:border-transparent transition text-sm"
                           placeholder="••••••••">
                </div>

                <!-- Submit -->
                <button id="login-submit" type="button"
                        class="w-full bg-gray-900 hover:bg-gray-800 text-white py-2.5 rounded-lg font-semibold text-sm transition shadow-sm flex items-center justify-center">
                    <i data-lucide="lock" class="mr-2 w-4 h-4"></i> Sign In
                </button>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-6 py-3 border-t border-gray-100 text-center">
                <a href="{{ url('/') }}" class="text-sm text-gray-400 hover:text-gray-600 transition flex items-center justify-center">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5 mr-1"></i> Return to Store
                </a>
            </div>
        </div>

        <p class="text-center text-xs text-gray-400 mt-4">
            Admin access only &middot; Accounts managed via CLI
        </p>
    </div>
</div>

<script>
$(function() {
    const params = new URLSearchParams(location.search);
    const returnTo = params.get('return') || '/admin/products';

    // Already logged in? Go straight
    if (localStorage.getItem('gb_token')) {
        location.href = returnTo;
        return;
    }

    const $err = $('#login-error');
    const $msg = $('#login-error-msg');
    const $btn = $('#login-submit');

    function showErr(text) {
        $msg.text(text);
        $err.removeClass('hidden');
    }

    function hideErr() {
        $err.addClass('hidden');
        $msg.text('');
    }

    $('#login-submit').on('click', function() {
        const email = $('#login-email').val().trim();
        const password = $('#login-password').val();
        if (!email || !password) {
            showErr('请输入邮箱和密码');
            return;
        }
        hideErr();

        $btn.prop('disabled', true)
            .html('<i data-lucide="loader" class="animate-spin mr-2 w-4 h-4"></i> Signing in...');
        lucide.createIcons();

        fetch('/api/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
            body: JSON.stringify({ email, password }),
        })
        .then(r => r.json().then(d => ({ status: r.status, body: d })))
        .then(({ status, body }) => {
            if (status === 200) {
                // Check is_admin
                if (!body.user || !body.user.is_admin) {
                    showErr('此账户无管理员权限，请联系系统管理员提升权限');
                    $btn.prop('disabled', false)
                        .html('<i data-lucide="lock" class="mr-2 w-4 h-4"></i> Sign In');
                    lucide.createIcons();
                    return;
                }
                // Success
                localStorage.setItem('gb_token', body.token);
                localStorage.setItem('gb_user', JSON.stringify(body.user));
                location.href = returnTo;
            } else {
                const msg = (body.error && body.error.message) || '邮箱或密码错误';
                showErr(msg);
            }
        })
        .catch(() => {
            showErr('网络错误，请稍后重试');
        })
        .finally(() => {
            if ($btn.prop('disabled') && location.pathname === '/admin/login') {
                $btn.prop('disabled', false)
                    .html('<i data-lucide="lock" class="mr-2 w-4 h-4"></i> Sign In');
                lucide.createIcons();
            }
        });
    });

    // Enter key submit
    $('#login-password').on('keydown', function(e) {
        if (e.key === 'Enter') $('#login-submit').trigger('click');
    });
});
</script>
@endsection
