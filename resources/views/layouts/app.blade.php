<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'GreenBite') - Sustainable Food Subscriptions</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://unpkg.com/lucide@latest"></script>
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
                <a href="{{ url('/catalog') }}" class="text-gray-600 hover:text-green-600 font-medium">Catalog</a>
                <a href="{{ url('/subscriptions') }}" class="text-gray-600 hover:text-green-600 font-medium">Subscriptions</a>
                <a href="{{ url('/orders') }}" class="text-gray-600 hover:text-green-600 font-medium">My Orders</a>
            </nav>
            <div class="flex items-center gap-4">
                <a href="{{ url('/cart') }}" class="text-gray-600 hover:text-green-600 relative">
                    <i data-lucide="shopping-cart" class="w-6 h-6"></i>
                    <span id="cart-count" class="absolute -top-2 -right-2 bg-red-500 text-white text-xs rounded-full h-5 w-5 flex items-center justify-center font-bold">0</span>
                </a>
                <a href="{{ url('/login') }}" class="bg-green-600 text-white px-5 py-2 rounded-lg hover:bg-green-700 transition font-medium shadow-sm">Sign In</a>
            </div>
        </div>
    </header>

    <!-- Main Content -->
    <main class="flex-grow">
        @yield('content')
    </main>
    
    <!-- Footer -->
    <footer class="bg-gray-900 text-white">
        <div class="container mx-auto px-4 py-12">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-8">
                <div>
                    <h3 class="text-xl font-bold flex items-center gap-2 mb-4 text-green-400">
                        <i data-lucide="leaf"></i> GreenBite
                    </h3>
                    <p class="text-gray-400 text-sm">Supporting local Hong Kong farmers and reducing carbon footprints through sustainable agriculture.</p>
                </div>
                <div>
                    <h4 class="font-bold mb-4">Quick Links</h4>
                    <ul class="space-y-2 text-sm text-gray-400">
                        <li><a href="{{ url('/catalog') }}" class="hover:text-white transition">Product Catalog</a></li>
                        <li><a href="{{ url('/subscriptions') }}" class="hover:text-white transition">Plans</a></li>
                        <li><a href="#" class="hover:text-white transition">About Us</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-bold mb-4">Help</h4>
                    <ul class="space-y-2 text-sm text-gray-400">
                        <li><a href="#" class="hover:text-white transition">FAQ</a></li>
                        <li><a href="#" class="hover:text-white transition">Shipping Policy</a></li>
                    </ul>
                </div>
                <div>
                    <h4 class="font-bold mb-4">Newsletter</h4>
                    <div class="flex">
                        <input type="email" placeholder="Your email" class="px-3 py-2 rounded-l-lg w-full text-black focus:outline-none">
                        <button class="bg-green-600 px-4 py-2 rounded-r-lg hover:bg-green-500 transition">Subscribe</button>
                    </div>
                </div>
            </div>
            <div class="border-t border-gray-800 mt-8 pt-8 text-center text-sm text-gray-500">
                &copy; {{ date('Y') }} GreenBite HK. All rights reserved.
            </div>
        </div>
    </footer>

    <!-- Global Scripts -->
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
