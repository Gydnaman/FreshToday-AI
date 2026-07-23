<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'GreenBite') - {{ i18n('home.subtitle') }}</title>
    {{-- @vite 仅在 manifest 存在时注入（开发/生产构建后才有；单测与 CI 无构建步骤时跳过） --}}
    @if (file_exists(public_path('build/manifest.json')))
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    @endif
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in-up { animation: fadeInUp 0.8s ease-out forwards; }
    </style>
</head>
<body class="antialiased bg-gray-50 flex flex-col min-h-screen">

    <!-- Navbar -->
    <header class="bg-white shadow border-b border-gray-100">
        <div class="container mx-auto px-4 py-4 flex justify-between items-center">
            <a href="{{ url('/') }}" class="text-2xl font-bold text-green-600 flex items-center gap-2">
                <i data-lucide="leaf" class="w-8 h-8"></i>
                GreenBite
            </a>
            <div class="flex items-center gap-6">
                <nav class="hidden md:flex gap-6 items-center">
                    <a href="{{ url('/catalog') }}" class="text-gray-600 hover:text-green-600 font-medium">{{ i18n('nav.catalog') }}</a>
                    <a href="{{ url('/subscriptions') }}" class="text-gray-600 hover:text-green-600 font-medium">{{ i18n('nav.subscriptions') }}</a>
                    <a href="{{ url('/orders') }}" class="text-gray-600 hover:text-green-600 font-medium">{{ i18n('nav.orders') }}</a>
                </nav>
                <a href="{{ url('/cart') }}" class="text-gray-600 hover:text-green-600 relative" data-i18n-aria="nav.cart" aria-label="{{ i18n('nav.cart') }}">
                    <i data-lucide="shopping-cart" class="w-6 h-6"></i>
                    <span id="cart-count" class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-bold">0</span>
                </a>
                @include('partials.lang-switcher')

                {{-- 右上角：guest → Sign In；logged-in → admin管理 + 用户名 + 退出 --}}
                <div id="auth-area">
                    <a href="{{ url('/login') }}" id="signin-btn" class="bg-green-600 text-white px-5 py-2 rounded-lg hover:bg-green-700 transition font-medium shadow-sm">{{ i18n('nav.signIn') }}</a>
                    <div id="user-area" class="hidden flex items-center gap-3">
                        <a href="{{ url('/admin/products') }}" id="admin-link" class="hidden text-sm text-green-600 hover:text-green-800 font-medium transition items-center gap-1">
                            <i data-lucide="settings" class="w-4 h-4"></i> <span class="hidden sm:inline">{{ i18n('nav.admin') }}</span>
                        </a>
                        <a href="{{ url('/orders') }}" id="user-name" class="text-sm text-gray-700 font-medium hover:text-green-600 transition"></a>
                        <button id="logout-btn" type="button" class="text-sm text-gray-500 hover:text-red-600 transition flex items-center gap-1" title="{{ i18n('nav.signOut') }}">
                            <i data-lucide="log-out" class="w-4 h-4"></i> <span class="hidden sm:inline">{{ i18n('nav.signOut') }}</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow">
        @yield('content')
    </main>

    @include('partials.footer')

    <script>
        lucide.createIcons();

        // ── Auth-aware cart count + auth area rendering ───────────────────
        $(document).ready(function() {
            // ── 工具：fetch 包装，统一 401 处理（双模式：cookie + token）────
            window.gbFetch = function(url, opts) {
                opts = opts || {};
                opts.credentials = 'include';  // Sanctum SPA cookie
                opts.headers = opts.headers || {};
                opts.headers['Accept'] = 'application/json';
                // Token 模式：从 localStorage 读 token 附加 Authorization header
                const token = localStorage.getItem('gb_token');
                if (token) {
                    opts.headers['Authorization'] = 'Bearer ' + token;
                }
                // XSRF-TOKEN cookie 自动被 Laravel 读取；POST/PUT/DELETE 需加 X-XSRF-TOKEN header
                const xsrf = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
                if (xsrf && ['POST','PUT','PATCH','DELETE'].includes((opts.method||'GET').toUpperCase())) {
                    opts.headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrf[1]);
                }
                return fetch(url, opts).then(function(resp) {
                    if (resp.status === 401) {
                        // 全局 401：清除 token 并跳登录
                        localStorage.removeItem('gb_token');
                        if (location.pathname !== '/login') {
                            location.href = '/login?return=' + encodeURIComponent(location.pathname + location.search);
                        }
                        throw new Error('UNAUTHORIZED');
                    }
                    return resp;
                });
            };

            // ── 渲染右上角 auth 区域 ─────────────────────────────────────
            function renderAuthArea() {
                // session 模式：调 /api/me 判断登录态
                fetch('/api/me', { credentials: 'include' })
                    .then(r => { if (!r.ok) throw new Error('UNAUTHORIZED'); return r.json(); })
                    .then(d => {
                        const user = d.user;
                        if (user && (user.id || user.email)) {
                            $('#signin-btn').addClass('hidden');
                            $('#user-area').removeClass('hidden').addClass('flex');
                            $('#user-name').text(user.name || user.email);
                            if (user.is_admin) {
                                $('#admin-link').removeClass('hidden').addClass('flex');
                            }
                        }
                    })
                    .catch(() => {
                        $('#signin-btn').removeClass('hidden');
                        $('#user-area').addClass('hidden').removeClass('flex');
                    });
            }
            renderAuthArea();

            // ── 退出按钮 ─────────────────────────────────────────────────
            $(document).on('click', '#logout-btn', function() {
                gbFetch('/api/logout', { method: 'POST' })
                    .catch(function(){ /* 即使失败也清 */ })
                    .finally(function() {
                        localStorage.removeItem('gb_token');
                        localStorage.removeItem('greenbite_cart');
                        location.href = '/';
                    });
            });

            // 购物车计数（已登录走 API；未登录不显示）
            function updateCartCount() {
                fetch('/api/cart', { credentials: 'include' })
                    .then(r => {
                        if (!r.ok) throw new Error('UNAUTHORIZED');
                        return r.json();
                    })
                    .then(d => $('#cart-count').text(d.item_count || 0))
                    .catch(() => $('#cart-count').text('0'));
            }
            updateCartCount();

            // ── addToCartAuth：登录态走 API；未登录走 localStorage ────────
            // 签名：addToCartAuth(productId, name, price, qty=1)
            // F-3 修正：不用 gbFetch（避免 401 全局跳转），直接 fetch + 401 走 fallback
            window.addToCartAuth = function(productId, name, price, qty) {
                qty = Math.max(1, Math.floor(Number(qty) || 1));
                const headers = { 'Accept': 'application/json', 'Content-Type': 'application/json' };
                // Sanctum SPA stateful 请求必须带 XSRF header，否则 419
                const xsrf = document.cookie.match(/XSRF-TOKEN=([^;]+)/);
                if (xsrf) headers['X-XSRF-TOKEN'] = decodeURIComponent(xsrf[1]);
                fetch('/api/cart', {
                    method: 'POST',
                    credentials: 'include',
                    headers: headers,
                    body: JSON.stringify({ product_id: productId, quantity: qty }),
                }).then(r => {
                    if (r.status === 401 || r.status === 419) {
                        // guest / session 过期：走 localStorage，不跳登录
                        fallbackLocalAdd(productId, name, price, qty);
                        return null;
                    }
                    // 登录态的库存或校验错误由 API 保持权威，不降级写入本地购物车。
                    if (!r.ok) return null;
                    // 成功：刷新角标
                    return fetch('/api/cart', { credentials: 'include' })
                        .then(r2 => r2.json())
                        .then(d2 => { $('#cart-count').text(d2.item_count || 0); })
                        .catch(() => {});
                }, () => fallbackLocalAdd(productId, name, price, qty));
                // 角标动画
                $('#cart-count').addClass('scale-150').delay(200).queue(function(next){
                    $(this).removeClass('scale-150');
                    next();
                });
            };

            function fallbackLocalAdd(productId, name, price, qty) {
                const cart = JSON.parse(localStorage.getItem('greenbite_cart') || '[]');
                for (let i = 0; i < qty; i++) {
                    cart.push({ name: name, price: parseFloat(price), product_id: productId });
                }
                localStorage.setItem('greenbite_cart', JSON.stringify(cart));
                $('#cart-count').text(cart.length);
            }
        });
    </script>
</body>
</html>
