@extends('layouts.app')

@section('title', i18n('admin.login.title') . ' — GreenBite')

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
                <h1 class="text-xl font-bold text-white tracking-tight">{{ i18n('admin.login.title') }}</h1>
                <p class="text-gray-400 text-sm mt-1">{{ i18n('admin.login.subtitle') }}</p>
            </div>

            <!-- Form -->
            <div class="px-6 py-6 space-y-4">
                <!-- Error -->
                <div id="login-error" class="hidden p-3 bg-red-50 border border-red-200 text-red-700 rounded-lg text-sm">
                    <span id="login-error-msg"></span>
                </div>

                <!-- Email -->
                <div>
                    <label for="login-email" class="block text-sm font-medium text-gray-700 mb-1">{{ i18n('admin.login.email') }}</label>
                    <input id="login-email" type="email" required autofocus
                           class="w-full px-3 py-2.5 bg-gray-50 border border-gray-300 rounded-lg focus:bg-white focus:ring-2 focus:ring-gray-900 focus:border-transparent transition text-sm"
                           placeholder="admin@greenbite.hk">
                </div>

                <!-- Password -->
                <div>
                    <label for="login-password" class="block text-sm font-medium text-gray-700 mb-1">{{ i18n('admin.login.password') }}</label>
                    <input id="login-password" type="password" required
                           class="w-full px-3 py-2.5 bg-gray-50 border border-gray-300 rounded-lg focus:bg-white focus:ring-2 focus:ring-gray-900 focus:border-transparent transition text-sm"
                           placeholder="••••••••">
                </div>

                <!-- Submit -->
                <button id="login-submit" type="button"
                        class="w-full bg-gray-900 hover:bg-gray-800 text-white py-2.5 rounded-lg font-semibold text-sm transition shadow-sm flex items-center justify-center">
                    <i data-lucide="lock" class="mr-2 w-4 h-4"></i> {{ i18n('admin.login.signIn') }}
                </button>
            </div>

            <!-- Footer -->
            <div class="bg-gray-50 px-6 py-3 border-t border-gray-100 text-center">
                <a href="{{ url('/') }}" class="text-sm text-gray-400 hover:text-gray-600 transition flex items-center justify-center">
                    <i data-lucide="arrow-left" class="w-3.5 h-3.5 mr-1"></i> {{ i18n('admin.login.returnToStore') }}
                </a>
            </div>
        </div>

        <p class="text-center text-xs text-gray-400 mt-4">
            {{ i18n('admin.login.accessOnly') }}
        </p>
    </div>
</div>

<script>
$(function() {
    const params = new URLSearchParams(location.search);
    const returnTo = params.get('return') || '/admin/products';
    const i18n = {
        emailPasswordRequired: @json(i18n('admin.login.emailPasswordRequired')),
        noPermission: @json(i18n('admin.login.noPermission')),
        networkError: @json(i18n('admin.login.networkError')),
        signIn: @json(i18n('admin.login.signIn')),
    };

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

    function resetBtn() {
        $btn.prop('disabled', false)
            .html('<i data-lucide="lock" class="mr-2 w-4 h-4"></i> ' + i18n.signIn);
        lucide.createIcons();
    }

    $('#login-submit').on('click', function() {
        const email = $('#login-email').val().trim();
        const password = $('#login-password').val();
        if (!email || !password) {
            showErr(i18n.emailPasswordRequired);
            return;
        }
        hideErr();

        $btn.prop('disabled', true)
            .html('<i data-lucide="loader" class="animate-spin mr-2 w-4 h-4"></i> ' + i18n.signIn + '...');
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
                    showErr(i18n.noPermission);
                    resetBtn();
                    return;
                }
                // Success
                localStorage.setItem('gb_token', body.token);
                localStorage.setItem('gb_user', JSON.stringify(body.user));
                location.href = returnTo;
            } else {
                const msg = (body.error && body.error.message) || i18n.networkError;
                showErr(msg);
            }
        })
        .catch(() => {
            showErr(i18n.networkError);
        })
        .finally(() => {
            if ($btn.prop('disabled') && location.pathname === '/admin/login') {
                resetBtn();
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
