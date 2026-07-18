@extends('layouts.app')

@section('title', i18n('auth.loginTitle'))

@section('content')
<div class="container mx-auto px-4 py-16 flex justify-center items-center">
    <div class="w-full max-w-md bg-white rounded-2xl shadow-xl overflow-hidden p-8 border border-gray-100 animate-fade-in-up">
        <div class="text-center mb-8">
            <div class="inline-flex justify-center items-center bg-green-100 rounded-full w-16 h-16 mb-4">
                <i data-lucide="leaf" class="h-8 w-8 text-green-600"></i>
            </div>
            <h2 class="text-3xl font-extrabold text-gray-900" id="form-title">{{ i18n('auth.loginTitle') }}</h2>
            <p class="text-gray-500 mt-2">{{ i18n('auth.subtitle') }}</p>
        </div>

        <form id="auth-form" class="space-y-5">
            <div id="name-field" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ i18n('auth.displayName') }}</label>
                <input id="auth-name" type="text" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-transparent transition" placeholder="{{ i18n('auth.namePlaceholder') }}">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ i18n('auth.email') }}</label>
                <input id="auth-email" type="email" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-transparent transition" placeholder="your@email.hk">
            </div>

            <div>
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ i18n('auth.password') }}</label>
                <input id="auth-password" type="password" required class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-transparent transition" placeholder="••••••••">
            </div>

            <div id="password-confirm-field" class="hidden">
                <label class="block text-sm font-medium text-gray-700 mb-1">{{ i18n('auth.confirmPassword') }}</label>
                <input id="auth-password-confirmation" type="password" class="w-full px-4 py-3 bg-gray-50 border border-gray-200 rounded-xl focus:bg-white focus:ring-2 focus:ring-green-500 focus:border-transparent transition" placeholder="{{ i18n('auth.confirmPasswordPlaceholder') }}">
            </div>

            <p id="auth-err" class="text-red-500 text-sm hidden"></p>

            <button type="submit" id="auth-submit" class="w-full bg-green-600 text-white py-3 px-4 rounded-xl hover:bg-green-700 transition-colors flex items-center justify-center font-bold text-lg shadow-md mt-4">
                <i data-lucide="log-in" class="mr-2 w-5 h-5" id="btn-icon"></i> <span id="btn-text">{{ i18n('auth.signIn') }}</span>
            </button>
        </form>

        <div class="mt-8 text-center border-t border-gray-100 pt-6">
            <button id="toggle-mode" type="button" class="text-green-600 hover:text-green-800 font-medium transition-colors">
                {!! i18n('auth.toggleToRegister') !!}
            </button>
        </div>
    </div>
</div>

<script>
    $(document).ready(function() {
        let isLogin = true;
        // 读 ?return= 路径，登录后回跳
        const params = new URLSearchParams(location.search);
        const returnTo = params.get('return') || '/catalog';

        // 已登录则直接跳（session 模式：调 /api/me 判断）
        fetch('/api/me', { credentials: 'include' })
            .then(r => { if (r.ok) location.href = returnTo; })
            .catch(() => {});

        // i18n 文案（按当前 locale 给出常用 label）
        const lang = (document.documentElement.lang || 'zh');
        const isHK = lang === 'zhhk' || lang.startsWith('zh-HK') || lang.startsWith('zh-hk');
        const isCN = lang === 'zh' || lang.startsWith('zh-CN') || lang.startsWith('zh-cn');
        const i18n = {
            signIn: isHK ? '登入' : (isCN ? '登录' : 'Sign In'),
            signUp: isHK ? '註冊' : (isCN ? '注册' : 'Sign Up'),
            signInTitle: isHK ? '歡迎回來！' : (isCN ? '欢迎回来！' : 'Welcome Back!'),
            signUpTitle: isHK ? '加入 GreenBite' : (isCN ? '加入 GreenBite' : 'Join GreenBite'),
            toggleToSignUp: isHK ? '沒有帳號? 註冊' : (isCN ? '没有账号? 注册' : "Don't have an account? Sign Up"),
            toggleToSignIn: isHK ? '已有帳號? 登入' : (isCN ? '已有账号? 登录' : 'Already have an account? Login'),
            namePh: isHK ? '您的稱呼' : (isCN ? '您的称呼' : 'Your name'),
        };

        function applyMode() {
            if (isLogin) {
                $('#form-title').text(i18n.signInTitle);
                $('#name-field, #password-confirm-field').addClass('hidden');
                $('#name-field input, #password-confirm-field input').removeAttr('required');
                $('#btn-text').text(i18n.signIn);
                $('#btn-icon').attr('data-lucide', 'log-in');
                $('#toggle-mode').text(i18n.toggleToSignUp);
            } else {
                $('#form-title').text(i18n.signUpTitle);
                $('#name-field, #password-confirm-field').removeClass('hidden');
                $('#name-field input, #password-confirm-field input').attr('required', true);
                $('#btn-text').text(i18n.signUp);
                $('#btn-icon').attr('data-lucide', 'user-plus');
                $('#toggle-mode').text(i18n.toggleToSignIn);
            }
            lucide.createIcons();
        }
        applyMode();

        $('#toggle-mode').on('click', function() {
            isLogin = !isLogin;
            $('#auth-err').addClass('hidden').text('');
            applyMode();
        });

        $('#auth-form').on('submit', function(e) {
            e.preventDefault();
            const email = $('#auth-email').val().trim();
            const password = $('#auth-password').val();
            if (!email || !password) return;

            const $err = $('#auth-err');
            $err.addClass('hidden').text('');

            // 按钮 loading
            const $btn = $('#auth-submit');
            $btn.prop('disabled', true).html('<i data-lucide="loader" class="animate-spin mr-2 w-5 h-5"></i> ' + (isLogin ? i18n.signIn : i18n.signUp));
            lucide.createIcons();

            const url = isLogin ? '/api/login' : '/api/register';
            const body = isLogin
                ? { email: email, password: password }
                : {
                    email: email,
                    password: password,
                    password_confirmation: $('#auth-password-confirmation').val(),
                    name: $('#auth-name').val().trim() || email.split('@')[0],
                    locale: (lang || 'zh'),
                };

            // Sanctum 双模式：先拿 csrf-cookie（SPA 模式需要），再登录
            fetch('/sanctum/csrf-cookie', { credentials: 'include' })
                .then(() => fetch(url, {
                    method: 'POST',
                    credentials: 'include',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-XSRF-TOKEN': decodeURIComponent((document.cookie.match(/XSRF-TOKEN=([^;]+)/) || [,''])[1]),
                        'X-Requested-With': 'XMLHttpRequest', // 确保后端识别为前端请求
                    },
                    body: JSON.stringify(body),
                }))
                .then(r => {
                    // 从响应头读取 token（纯 API 模式）
                    const token = r.headers.get('X-Auth-Token');
                    if (token) {
                        localStorage.setItem('gb_token', token);
                    }
                    return r.json().then(j => ({ status: r.status, body: j }));
                })
                .then(({ status, body }) => {
                    if (status === 200 || status === 201) {
                        // session 模式：cookie 已自动设置
                        // token 模式：localStorage 已在上面设置
                        if (typeof renderAuthArea === 'function') renderAuthArea();
                        location.href = returnTo;
                    } else {
                        // 错误
                        const msg = (body.error && body.error.message)
                            || (body.message)
                            || (body.errors ? Object.values(body.errors).flat().join('; ') : null)
                            || '请求失败';
                        $err.removeClass('hidden').text(msg);
                    }
                })
            .catch(err => {
                $err.removeClass('hidden').text('网络错误：' + (err.message || ''));
            })
            .finally(() => {
                $btn.prop('disabled', false);
                applyMode();
            });
        });
    });
</script>
@endsection
