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
            <nav class="hidden md:flex gap-6 items-center">
                <a href="{{ url('/catalog') }}" class="text-gray-600 hover:text-green-600 font-medium">{{ i18n('nav.catalog') }}</a>
                <a href="{{ url('/subscriptions') }}" class="text-gray-600 hover:text-green-600 font-medium">{{ i18n('nav.subscriptions') }}</a>
                <a href="{{ url('/orders') }}" class="text-gray-600 hover:text-green-600 font-medium">{{ i18n('nav.orders') }}</a>
            </nav>
            <div class="flex items-center gap-4">
                <a href="{{ url('/cart') }}" class="text-gray-600 hover:text-green-600 relative" data-i18n-aria="nav.cart" aria-label="{{ i18n('nav.cart') }}">
                    <i data-lucide="shopping-cart" class="w-6 h-6"></i>
                    <span id="cart-count" class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-bold">0</span>
                </a>
                @include('partials.lang-switcher')

                {{-- 右上角：guest → Sign In；logged-in → 用户名 + 退出 --}}
                <div id="auth-area">
                    <a href="{{ url('/login') }}" id="signin-btn" class="bg-green-600 text-white px-5 py-2 rounded-lg hover:bg-green-700 transition font-medium shadow-sm">{{ i18n('nav.signIn') }}</a>
                    <div id="user-area" class="hidden flex items-center gap-3">
                        <a href="{{ url('/orders') }}" id="user-name" class="text-sm text-gray-700 font-medium hover:text-green-600 transition"></a>
                        <button id="logout-btn" type="button" class="text-sm text-gray-500 hover:text-red-600 transition flex items-center gap-1" title="Sign Out">
                            <i data-lucide="log-out" class="w-4 h-4"></i> <span class="hidden sm:inline">Sign Out</span>
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
            // ── 工具：fetch 包装，统一 401 处理 ─────────────────────────
            window.gbFetch = function(url, opts) {
                opts = opts || {};
                const token = localStorage.getItem('gb_token');
                opts.headers = opts.headers || {};
                opts.headers['Accept'] = 'application/json';
                if (token) opts.headers['Authorization'] = 'Bearer ' + token;
                return fetch(url, opts).then(function(resp) {
                    if (resp.status === 401) {
                        // 全局 401：清 token + 跳登录
                        localStorage.removeItem('gb_token');
                        localStorage.removeItem('gb_user');
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
                const userJson = localStorage.getItem('gb_user');
                const user = userJson ? JSON.parse(userJson) : null;
                if (user && (user.id || user.email)) {
                    $('#signin-btn').addClass('hidden');
                    $('#user-area').removeClass('hidden').addClass('flex');
                    $('#user-name').text(user.name || user.email);
                } else {
                    $('#signin-btn').removeClass('hidden');
                    $('#user-area').addClass('hidden').removeClass('flex');
                }
            }
            renderAuthArea();

            // ── 退出按钮 ─────────────────────────────────────────────────
            $(document).on('click', '#logout-btn', function() {
                gbFetch('/api/logout', { method: 'POST' })
                    .catch(function(){ /* 即使 401 也清 */ })
                    .finally(function() {
                        localStorage.removeItem('gb_token');
                        localStorage.removeItem('gb_user');
                        localStorage.removeItem('greenbite_cart');
                        location.href = '/';
                    });
            });

            // ── 购物车计数（guest 走 localStorage；logged-in 走 API） ───
            function updateCartCount() {
                const token = localStorage.getItem('gb_token');
                if (token) {
                    gbFetch('/api/cart')
                        .then(r => r.json())
                        .then(d => $('#cart-count').text(d.item_count || 0))
                        .catch(() => fallbackLocalCount());
                } else {
                    fallbackLocalCount();
                }
            }
            function fallbackLocalCount() {
                const c = JSON.parse(localStorage.getItem('greenbite_cart') || '[]');
                $('#cart-count').text(c.length);
            }
            updateCartCount();

            // ── addToCartAuth：登录态走 API；未登录走 localStorage ────────
            // 签名：addToCartAuth(productId, name, price, qty=1)
            window.addToCartAuth = function(productId, name, price, qty) {
                qty = qty || 1;
                const token = localStorage.getItem('gb_token');
                if (token) {
                    gbFetch('/api/cart', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ product_id: productId, quantity: qty }),
                    })
                    .then(r => r.json())
                    .then(d => {
                        if (d.error) {
                            alert('加购失败：' + (d.error.message || '未知错误'));
                            return;
                        }
                        // 成功：用 API 返回的 quantity 之和刷新角标
                        return gbFetch('/api/cart').then(r2 => r2.json()).then(d2 => {
                            $('#cart-count').text(d2.item_count || 0);
                        });
                    })
                    .catch(err => {
                        if (err.message !== 'UNAUTHORIZED') {
                            alert('网络错误，请重试');
                        }
                    });
                } else {
                    // guest：localStorage 模式（保持向后兼容）
                    const cart = JSON.parse(localStorage.getItem('greenbite_cart') || '[]');
                    cart.push({ name: name, price: parseFloat(price), product_id: productId });
                    localStorage.setItem('greenbite_cart', JSON.stringify(cart));
                    $('#cart-count').text(cart.length);
                }
                // 角标动画
                $('#cart-count').addClass('scale-150').delay(200).queue(function(next){
                    $(this).removeClass('scale-150');
                    next();
                });
            };
        });
    </script>
</body>
</html>
