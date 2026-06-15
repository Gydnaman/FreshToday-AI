<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'GreenBite') - {{ i18n('home.subtitle') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
    <style>
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
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
                <a href="{{ url('/login') }}" class="bg-green-600 text-white px-5 py-2 rounded-lg hover:bg-green-700 transition font-medium shadow-sm">{{ i18n('nav.signIn') }}</a>
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

        // Cart Logic with jQuery & LocalStorage
        $(document).ready(function() {
            let cart = JSON.parse(localStorage.getItem('greenbite_cart') || '[]');
            $('#cart-count').text(cart.length);

            window.addToCart = function(productName, price) {
                cart.push({ name: productName, price: price });
                localStorage.setItem('greenbite_cart', JSON.stringify(cart));
                $('#cart-count').text(cart.length);
                $('#cart-count').addClass('scale-150').delay(200).queue(function(next){
                    $(this).removeClass('scale-150');
                    next();
                });
            }
        });
    </script>
</body>
</html>
