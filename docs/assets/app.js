(function () {
    'use strict';

    const PRODUCTS = [
        { id: 1, name: '本地有機菜心', category: 'Leafy greens', price: 28, stock: 50, organic: true, origin: 'Yuen Long Pat Heung Farm', image: 'choysum.jpg', description: 'Picked today. Tender, sweet, and locally certified organic.' },
        { id: 2, name: '本地有機白菜', category: 'Leafy greens', price: 24, stock: 60, organic: true, origin: 'Yuen Long San Tin Farm', image: 'napa-cabbage.jpg', description: 'Fresh local Chinese cabbage for stir-fry or soup.' },
        { id: 3, name: '本地西洋菜', category: 'Leafy greens', price: 22, stock: 40, organic: false, origin: 'Fanling Hok Tau', image: 'spinach.jpg', description: 'Fresh leafy greens for a light Hong Kong-style soup.' },
        { id: 4, name: '本地有機紅蘿蔔', category: 'Root vegetables', price: 18, stock: 80, organic: true, origin: 'Ta Kwu Ling Farm', image: 'carrot.jpg', description: 'Sweet local carrots for soup, roasting, or juice.' },
        { id: 5, name: '本地黃薑', category: 'Root vegetables', price: 32, stock: 30, organic: false, origin: 'Tai Po Lam Tsuen', image: 'ginger.jpg', description: 'Fresh ginger with a strong, warm aroma.' },
        { id: 6, name: '本地紫薯', category: 'Root vegetables', price: 38, stock: 0, organic: true, origin: 'Sai Kung Pak Tam Chung', image: 'purple-sweet-potato.jpg', description: 'Purple sweet potato. The next harvest arrives soon.' },
        { id: 7, name: '本地沙田柚', category: 'Seasonal fruit', price: 48, stock: 25, organic: false, origin: 'Sha Tin Lek Yuen', image: 'pomelo.jpg', description: 'Juicy local pomelo with thick aromatic peel.' },
        { id: 8, name: '本地楊桃', category: 'Seasonal fruit', price: 35, stock: 20, organic: true, origin: 'Tai Po Kau', image: 'starfruit.jpg', description: 'Crisp starfruit with a bright sweet-and-sour taste.' },
        { id: 9, name: '本地木瓜', category: 'Seasonal fruit', price: 42, stock: 18, organic: true, origin: 'Yuen Long Lau Fau Shan', image: 'papaya.jpg', description: 'Tree-ripened papaya with soft, sweet flesh.' },
        { id: 10, name: '本地有機臍橙', category: 'Citrus', price: 56, stock: 30, organic: true, origin: 'North District Lin Ma Hang', image: 'orange.jpg', description: 'Thin-skinned local oranges with plenty of juice.' },
        { id: 11, name: '本地青檸', category: 'Citrus', price: 28, stock: 40, organic: false, origin: 'Cheung Chau', image: 'lime.jpg', description: 'Fresh green limes for drinks and seafood.' },
        { id: 12, name: '本地走地雞蛋（10 隻）', category: 'Eggs', price: 68, stock: 50, organic: true, origin: 'Yuen Long Free-range Farm', image: 'eggs.jpg', description: 'Free-range brown eggs with a rich flavour.' },
        { id: 13, name: '本地初生蛋（6 隻）', category: 'Eggs', price: 58, stock: 0, organic: true, origin: 'Ta Kwu Ling', image: 'eggs2.jpg', description: 'Small first-laid eggs. Currently sold out.' },
        { id: 14, name: '本地新鮮烏頭', category: 'Fresh seafood', price: 88, stock: 12, organic: false, origin: 'Sai Kung Fisher Direct', image: 'mullet.jpg', description: 'Fresh grey mullet delivered from local fishers.' },
        { id: 15, name: '本地龍躉柳', category: 'Fresh seafood', price: 168, stock: 8, organic: false, origin: 'Lamma Island', image: 'fish2.jpg', description: 'Thick-cut local fish fillet for steaming.' },
        { id: 16, name: '本地蝦仁', category: 'Fresh seafood', price: 98, stock: 20, organic: false, origin: 'Aberdeen Fish Market', image: 'shrimp.jpg', description: 'Fresh peeled prawns, ready for quick cooking.' },
        { id: 17, name: '本地有機絲苗米 2kg', category: 'Rice and noodles', price: 78, stock: 40, organic: true, origin: 'Yuen Long San Tin', image: 'rice.jpg', description: 'Local fragrant rice with a soft texture.' },
        { id: 18, name: '本地蝦子麵', category: 'Rice and noodles', price: 32, stock: 60, organic: false, origin: 'Cheung Chau Noodle Shop', image: 'noodles.jpg', description: 'Traditional shrimp roe noodles with a firm bite.' },
        { id: 19, name: '本地米線', category: 'Rice and noodles', price: 28, stock: 45, organic: false, origin: 'Tai Po', image: 'ricenoodle.jpg', description: 'Additive-free rice noodles for quick meals.' },
        { id: 20, name: '本地手工豉油', category: 'Sauces', price: 45, stock: 35, organic: false, origin: 'Kowloon City Sauce Workshop', image: 'soysauce.jpg', description: 'Traditional soy sauce brewed for 180 days.' },
        { id: 21, name: '本地XO醬', category: 'Sauces', price: 88, stock: 20, organic: false, origin: 'Sheung Wan Sauce Workshop', image: 'xo-sauce.jpg', description: 'Rich XO sauce made with dried scallop and shrimp.' },
        { id: 22, name: '本地蜜糖（龍眼蜜）', category: 'Sauces', price: 120, stock: 15, organic: true, origin: 'Sha Tau Kok Apiary', image: 'honey.jpg', description: 'Pure longan honey with no added sugar.' },
        { id: 23, name: '本地有機豆腐 3 盒裝', category: 'Rice and noodles', price: 38, stock: 30, organic: true, origin: 'Yuen Long Tofu Workshop', image: 'tofu.jpg', description: 'Three packs of fresh tofu made the same day.' },
        { id: 24, name: '本地粟米', category: 'Root vegetables', price: 20, stock: 0, organic: false, origin: 'Tuen Mun Farm', image: 'corn.jpg', description: 'Sweet local corn. The next batch is open for preorder.' }
    ];

    const MENUS = [
        [
            ['Breakfast', 'Ginger egg rice bowl', 'Local eggs, rice, ginger'],
            ['Lunch', 'Steamed mullet with choy sum', 'Fresh mullet, choy sum, soy sauce'],
            ['Dinner', 'Tofu and carrot rice noodles', 'Fresh tofu, carrot, rice noodles']
        ],
        [
            ['Breakfast', 'Honey papaya bowl', 'Papaya, longan honey'],
            ['Lunch', 'XO prawn noodles', 'Prawns, noodles, XO sauce'],
            ['Dinner', 'Pomelo choy sum salad', 'Pomelo, choy sum, lime']
        ],
        [
            ['Breakfast', 'Sweet potato and egg plate', 'Purple sweet potato, eggs'],
            ['Lunch', 'Fish fillet rice bowl', 'Fish fillet, fragrant rice'],
            ['Dinner', 'Ginger tofu soup', 'Tofu, ginger, leafy greens']
        ]
    ];

    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));
    const money = value => `HK$${Number(value).toFixed(2)}`;
    const productImage = file => `assets/products/${file}`;
    const escapeHtml = value => String(value).replace(/[&<>'"]/g, char => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#39;', '"': '&quot;' }[char]));

    function getCart() {
        try { return JSON.parse(localStorage.getItem('greenbite_static_cart') || '[]'); }
        catch (_) { return []; }
    }

    function saveCart(cart) {
        localStorage.setItem('greenbite_static_cart', JSON.stringify(cart));
        updateCartCount();
    }

    function cartRows() {
        return getCart().map(row => {
            const product = PRODUCTS.find(item => item.id === row.productId);
            return product ? { ...row, product } : null;
        }).filter(Boolean);
    }

    function cartCount() {
        return getCart().reduce((total, row) => total + row.quantity, 0);
    }

    function addToCart(productId, quantity = 1) {
        const product = PRODUCTS.find(item => item.id === productId);
        if (!product || product.stock < 1) return;
        const cart = getCart();
        const row = cart.find(item => item.productId === productId);
        if (row) row.quantity = Math.min(product.stock, row.quantity + quantity);
        else cart.push({ productId, quantity: Math.min(product.stock, Math.max(1, quantity)) });
        saveCart(cart);
        showToast(`${product.name} added to cart`);
    }

    function updateCartCount() {
        const count = cartCount();
        $$('.js-cart-count').forEach(node => { node.textContent = count; });
    }

    function showToast(message) {
        let toast = $('#site-toast');
        if (!toast) {
            toast = document.createElement('div');
            toast.id = 'site-toast';
            toast.className = 'fixed bottom-5 left-1/2 z-50 -translate-x-1/2 rounded-xl bg-gray-900 px-5 py-3 text-sm font-semibold text-white shadow-xl transition';
            document.body.appendChild(toast);
        }
        toast.textContent = message;
        toast.classList.remove('opacity-0');
        clearTimeout(window.__toastTimer);
        window.__toastTimer = setTimeout(() => toast.classList.add('opacity-0'), 1800);
    }

    function renderShell() {
        const active = document.body.dataset.page;
        const signedIn = localStorage.getItem('greenbite_static_user') !== 'guest';
        $('#site-header').innerHTML = `
            <header class="sticky top-0 z-40 border-b border-gray-100 bg-white/95 backdrop-blur">
                <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6">
                    <a href="index.html" class="flex items-center gap-2 text-2xl font-extrabold text-green-600">
                        <i data-lucide="leaf" class="h-8 w-8"></i><span>GreenBite</span>
                    </a>
                    <button id="mobile-nav-button" class="rounded-lg p-2 text-gray-600 lg:hidden" aria-label="Open menu"><i data-lucide="menu"></i></button>
                    <nav id="main-nav" class="hidden items-center gap-6 lg:flex">
                        ${navLink('catalog', 'catalog.html', 'Catalog')}
                        ${navLink('subscriptions', 'subscriptions.html', 'Subscriptions')}
                        ${navLink('orders', 'orders.html', 'My Orders')}
                        <a href="cart.html" class="relative text-gray-600 hover:text-green-700" aria-label="Cart">
                            <i data-lucide="shopping-cart" class="h-6 w-6"></i>
                            <span class="js-cart-count absolute -right-3 -top-3 flex h-5 min-w-5 items-center justify-center rounded-full bg-red-500 px-1 text-xs font-bold text-white">0</span>
                        </a>
                        <span class="text-sm text-gray-500">🌐 GB English</span>
                        ${signedIn
                            ? '<a href="dashboard.html" class="text-sm font-semibold text-gray-700 hover:text-green-700">Demo User</a><button id="logout-button" class="text-sm text-gray-500 hover:text-red-600">Log Out</button>'
                            : '<a href="auth.html" class="rounded-lg bg-green-600 px-5 py-2.5 font-semibold text-white shadow-sm hover:bg-green-700">Sign In</a>'}
                    </nav>
                </div>
                <nav id="mobile-nav" class="hidden border-t border-gray-100 bg-white px-4 py-4 lg:hidden">
                    <div class="grid gap-3 text-sm font-semibold text-gray-700">
                        <a href="catalog.html">Catalog</a><a href="subscriptions.html">Subscriptions</a><a href="orders.html">My Orders</a><a href="cart.html">My Cart</a><a href="auth.html">Sign In</a>
                    </div>
                </nav>
            </header>`;
        $('#site-footer').innerHTML = `
            <footer class="mt-16 bg-gray-950 text-gray-300">
                <div class="mx-auto grid max-w-7xl gap-8 px-4 py-10 sm:grid-cols-3 sm:px-6">
                    <div><p class="flex items-center gap-2 text-xl font-bold text-green-400"><i data-lucide="leaf"></i> GreenBite</p><p class="mt-3 text-sm text-gray-400">Local farm products, simple shopping, and lower food miles.</p></div>
                    <div><p class="font-bold text-white">Quick Links</p><div class="mt-3 grid gap-2 text-sm"><a href="catalog.html">Product Catalog</a><a href="subscriptions.html">Plans</a><a href="orders.html">Orders</a></div></div>
                    <div><p class="font-bold text-white">Static Demonstration</p><p class="mt-3 text-sm text-gray-400">This HTML version stores demo cart and order data in this browser.</p></div>
                </div>
            </footer>`;
        $('#mobile-nav-button')?.addEventListener('click', () => $('#mobile-nav').classList.toggle('hidden'));
        $('#logout-button')?.addEventListener('click', () => { localStorage.setItem('greenbite_static_user', 'guest'); location.href = 'index.html'; });
        updateCartCount();
        if (window.lucide) window.lucide.createIcons();

        function navLink(key, href, text) {
            return `<a href="${href}" class="font-medium ${active === key ? 'text-green-700' : 'text-gray-600 hover:text-green-700'}">${text}</a>`;
        }
    }

    function productCard(product) {
        const soldOut = product.stock < 1;
        return `<article class="group overflow-hidden rounded-2xl border border-gray-100 bg-white shadow-sm transition hover:-translate-y-1 hover:shadow-xl" data-product-card data-name="${escapeHtml(product.name.toLowerCase())}" data-category="${escapeHtml(product.category)}" data-available="${soldOut ? '0' : '1'}">
            <a href="product-detail.html?id=${product.id}" class="relative block h-52 overflow-hidden bg-gray-100">
                <img src="${productImage(product.image)}" alt="${escapeHtml(product.name)}" class="h-full w-full object-cover transition duration-500 group-hover:scale-105">
                ${product.organic ? '<span class="absolute left-3 top-3 rounded-full bg-green-600 px-3 py-1 text-xs font-bold text-white">Organic</span>' : ''}
                ${soldOut ? '<span class="absolute inset-0 flex items-center justify-center bg-gray-900/45 text-lg font-bold text-white">Sold out</span>' : ''}
            </a>
            <div class="p-5">
                <p class="text-xs font-semibold uppercase tracking-wide text-green-700">${product.category}</p>
                <h2 class="mt-2 text-lg font-bold text-gray-900"><a href="product-detail.html?id=${product.id}" class="hover:text-green-700">${escapeHtml(product.name)}</a></h2>
                <p class="mt-1 flex items-center gap-1 text-xs text-gray-400"><i data-lucide="map-pin" class="h-3 w-3"></i>${escapeHtml(product.origin)}</p>
                <p class="mt-3 line-clamp-2 h-10 text-sm text-gray-500">${escapeHtml(product.description)}</p>
                <div class="mt-5 flex items-center justify-between"><strong class="text-xl text-green-600">${money(product.price)}</strong>
                    <button class="js-add-cart rounded-xl p-3 ${soldOut ? 'cursor-not-allowed bg-gray-100 text-gray-400' : 'bg-green-100 text-green-700 hover:bg-green-600 hover:text-white'}" data-product-id="${product.id}" ${soldOut ? 'disabled' : ''} aria-label="Add to cart"><i data-lucide="${soldOut ? 'ban' : 'plus'}" class="h-5 w-5"></i></button>
                </div>
            </div>
        </article>`;
    }

    function bindAddButtons(root = document) {
        $$('.js-add-cart', root).forEach(button => button.addEventListener('click', () => addToCart(Number(button.dataset.productId), Number(button.dataset.quantity || 1))));
    }

    function renderHome() {
        $('#page-content').innerHTML = `
            <section class="bg-gradient-to-br from-green-50 to-emerald-100 px-4 py-16 sm:py-20">
                <div class="mx-auto max-w-4xl text-center">
                    <span class="rounded-full border border-green-200 bg-green-100 px-4 py-1.5 text-sm font-semibold text-green-800">Hong Kong local farm marketplace</span>
                    <h1 class="mt-7 text-5xl font-extrabold tracking-tight text-gray-950 sm:text-6xl">Local Farm <span class="block text-green-600">Freshness</span></h1>
                    <p class="mx-auto mt-6 max-w-2xl text-lg leading-8 text-gray-600">Discover sustainably grown produce from Hong Kong farms. Every order supports local growers and reduces food miles.</p>
                    <div class="mt-9 flex flex-col justify-center gap-3 sm:flex-row"><a href="catalog.html" class="rounded-xl bg-green-600 px-8 py-4 font-bold text-white shadow-lg hover:bg-green-700">Browse Products</a><a href="subscriptions.html" class="rounded-xl border-2 border-green-600 bg-white px-8 py-4 font-bold text-green-700 hover:bg-green-50">View Subscription Plans</a></div>
                </div>
            </section>
            <section class="mx-auto max-w-7xl px-4 py-14 sm:px-6">
                <div class="grid gap-6 md:grid-cols-3">
                    ${feature('truck', 'Local delivery', 'Fresh products delivered across Hong Kong.')}
                    ${feature('leaf', 'Lower food miles', 'Clear local origins and carbon information.')}
                    ${feature('calendar-check', 'Daily menu ideas', 'Simple meal ideas based on products in stock.')}
                </div>
                <div class="mt-14 rounded-3xl border border-gray-100 bg-white p-6 shadow-xl sm:p-8" id="daily-menu">
                    <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-start"><div><p class="text-sm font-bold uppercase tracking-wide text-green-700">Personal menu</p><h2 class="mt-1 text-3xl font-extrabold text-gray-900">Today’s Menu</h2><p class="mt-2 text-gray-500">Generated from available local products.</p></div><button id="regenerate-menu" class="rounded-xl bg-green-600 px-5 py-3 font-bold text-white hover:bg-green-700">Regenerate Menu</button></div>
                    <div id="menu-content" class="mt-7 grid gap-4 md:grid-cols-3"></div>
                </div>
                <div class="mt-14"><div class="flex items-end justify-between"><div><p class="text-sm font-bold uppercase tracking-wide text-green-700">Available now</p><h2 class="mt-1 text-3xl font-extrabold">Fresh picks</h2></div><a href="catalog.html" class="font-bold text-green-700">View all →</a></div><div class="mt-7 grid gap-6 sm:grid-cols-2 lg:grid-cols-4">${PRODUCTS.filter(item => item.stock > 0).slice(0, 4).map(productCard).join('')}</div></div>
            </section>`;
        let menuIndex = Number(localStorage.getItem('greenbite_menu_index') || 0) % MENUS.length;
        const drawMenu = () => { $('#menu-content').innerHTML = MENUS[menuIndex].map(([type, title, ingredients]) => `<article class="rounded-2xl bg-gray-50 p-5"><p class="text-xs font-bold uppercase tracking-wide text-green-700">${type}</p><h3 class="mt-2 text-lg font-bold">${title}</h3><p class="mt-3 text-sm text-gray-500">${ingredients}</p></article>`).join(''); };
        drawMenu();
        $('#regenerate-menu').addEventListener('click', event => { const y = scrollY; menuIndex = (menuIndex + 1) % MENUS.length; localStorage.setItem('greenbite_menu_index', menuIndex); drawMenu(); event.currentTarget.textContent = 'Menu Updated'; setTimeout(() => { event.currentTarget.textContent = 'Regenerate Menu'; scrollTo(0, y); }, 700); });
        bindAddButtons();
    }

    function feature(icon, title, text) {
        return `<article class="rounded-2xl border border-white bg-white/80 p-7 text-center shadow-sm"><span class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-green-100 text-green-700"><i data-lucide="${icon}"></i></span><h2 class="mt-5 text-xl font-bold">${title}</h2><p class="mt-2 text-sm leading-6 text-gray-500">${text}</p></article>`;
    }

    function renderCatalog() {
        const categories = [...new Set(PRODUCTS.map(item => item.category))];
        $('#page-content').innerHTML = `<main class="mx-auto max-w-7xl px-4 py-12 sm:px-6"><div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between"><div><p class="text-sm font-bold uppercase tracking-wide text-green-700">Shop local</p><h1 class="mt-1 text-4xl font-extrabold">Product Catalog</h1><p class="mt-3 text-gray-500">Browse current products, local origins, stock, and prices.</p></div><div class="grid gap-3 sm:grid-cols-3"><input id="catalog-search" class="rounded-xl border border-gray-200 px-4 py-3" placeholder="Search products"><select id="catalog-category" class="rounded-xl border border-gray-200 px-4 py-3"><option value="">All categories</option>${categories.map(item => `<option>${item}</option>`).join('')}</select><label class="flex items-center gap-2 rounded-xl border border-gray-200 px-4 py-3 text-sm"><input id="catalog-stock" type="checkbox" class="accent-green-600"> In stock only</label></div></div><p id="catalog-count" class="mt-8 text-sm font-semibold text-gray-500"></p><div id="catalog-grid" class="mt-4 grid gap-6 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">${PRODUCTS.map(productCard).join('')}</div><div id="catalog-empty" class="hidden py-20 text-center text-gray-500">No products match these filters.</div></main>`;
        const filter = () => {
            const query = $('#catalog-search').value.trim().toLowerCase();
            const category = $('#catalog-category').value;
            const stockOnly = $('#catalog-stock').checked;
            let shown = 0;
            $$('[data-product-card]').forEach(card => {
                const visible = (!query || card.dataset.name.includes(query)) && (!category || card.dataset.category === category) && (!stockOnly || card.dataset.available === '1');
                card.classList.toggle('hidden', !visible); if (visible) shown++;
            });
            $('#catalog-count').textContent = `${shown} products`;
            $('#catalog-empty').classList.toggle('hidden', shown !== 0);
        };
        ['input', 'change'].forEach(type => { $('#catalog-search').addEventListener(type, filter); $('#catalog-category').addEventListener(type, filter); $('#catalog-stock').addEventListener(type, filter); });
        filter(); bindAddButtons();
    }

    function renderProductDetail() {
        const id = Number(new URLSearchParams(location.search).get('id') || 1);
        const product = PRODUCTS.find(item => item.id === id) || PRODUCTS[0];
        const soldOut = product.stock < 1;
        $('#page-content').innerHTML = `<main class="mx-auto max-w-6xl px-4 py-12 sm:px-6"><a href="catalog.html" class="text-sm font-bold text-green-700">← Back to catalog</a><div class="mt-6 grid overflow-hidden rounded-3xl border border-gray-100 bg-white shadow-xl lg:grid-cols-2"><div class="bg-gray-100"><img src="${productImage(product.image)}" alt="${escapeHtml(product.name)}" class="h-full min-h-96 w-full object-cover"></div><div class="p-8 sm:p-10"><p class="text-sm font-bold uppercase tracking-wide text-green-700">${product.category}</p><h1 class="mt-3 text-4xl font-extrabold">${escapeHtml(product.name)}</h1><p class="mt-4 flex items-center gap-2 text-sm text-gray-500"><i data-lucide="map-pin" class="h-4 w-4"></i>${escapeHtml(product.origin)}</p><p class="mt-6 text-lg leading-8 text-gray-600">${escapeHtml(product.description)}</p><div class="mt-7 grid grid-cols-2 gap-4"><div class="rounded-xl bg-green-50 p-4"><p class="text-xs text-green-700">Price</p><p class="mt-1 text-2xl font-extrabold text-green-700">${money(product.price)}</p></div><div class="rounded-xl bg-gray-50 p-4"><p class="text-xs text-gray-500">Availability</p><p class="mt-1 font-bold ${soldOut ? 'text-red-600' : 'text-gray-900'}">${soldOut ? 'Sold out' : `${product.stock} in stock`}</p></div></div><div class="mt-8 flex gap-3"><input id="detail-qty" type="number" min="1" max="${Math.max(1, product.stock)}" value="1" class="w-24 rounded-xl border border-gray-200 px-4 py-3"><button id="detail-add" class="flex-1 rounded-xl px-5 py-3 font-bold ${soldOut ? 'cursor-not-allowed bg-gray-200 text-gray-400' : 'bg-green-600 text-white hover:bg-green-700'}" ${soldOut ? 'disabled' : ''}>${soldOut ? 'Sold out' : 'Add to Cart'}</button></div><p class="mt-5 text-sm text-gray-500">${product.organic ? '✓ Certified organic product' : 'Locally sourced product'}</p></div></div></main>`;
        $('#detail-add')?.addEventListener('click', () => addToCart(product.id, Math.max(1, Number($('#detail-qty').value || 1))));
    }

    function renderCart() {
        const rows = cartRows();
        const subtotal = rows.reduce((sum, row) => sum + row.product.price * row.quantity, 0);
        const delivery = subtotal >= 200 || subtotal === 0 ? 0 : 30;
        $('#page-content').innerHTML = `<main class="mx-auto max-w-5xl px-4 py-12 sm:px-6"><div><h1 class="flex items-center gap-3 text-4xl font-extrabold"><i data-lucide="shopping-cart" class="text-green-600"></i>My Cart</h1><p class="mt-2 text-gray-500">Review your items before checkout.</p></div>${rows.length ? `<div class="mt-8 grid gap-7 lg:grid-cols-3"><div class="space-y-4 lg:col-span-2">${rows.map(row => `<article class="flex items-center gap-4 rounded-2xl border border-gray-100 bg-white p-4 shadow-sm"><img src="${productImage(row.product.image)}" class="h-20 w-20 rounded-xl object-cover" alt="${escapeHtml(row.product.name)}"><div class="min-w-0 flex-1"><a href="product-detail.html?id=${row.product.id}" class="font-bold hover:text-green-700">${escapeHtml(row.product.name)}</a><p class="mt-1 text-sm text-gray-400">${money(row.product.price)} each</p><div class="mt-3 flex items-center gap-2"><button class="js-qty h-8 w-8 rounded-lg border" data-id="${row.product.id}" data-delta="-1">−</button><span class="w-8 text-center font-bold">${row.quantity}</span><button class="js-qty h-8 w-8 rounded-lg border" data-id="${row.product.id}" data-delta="1">+</button><button class="js-remove ml-2 text-xs font-semibold text-red-500" data-id="${row.product.id}">Remove</button></div></div><strong class="text-green-600">${money(row.product.price * row.quantity)}</strong></article>`).join('')}</div><aside class="h-fit rounded-2xl border border-gray-100 bg-white p-6 shadow-sm"><h2 class="text-xl font-bold">Order Summary</h2><div class="mt-5 space-y-3 text-sm"><div class="flex justify-between"><span>Subtotal (${cartCount()} items)</span><strong>${money(subtotal)}</strong></div><div class="flex justify-between"><span>Delivery</span><strong>${delivery ? money(delivery) : 'Free'}</strong></div><div class="flex justify-between border-t pt-3 text-lg"><strong>Total</strong><strong class="text-green-600">${money(subtotal + delivery)}</strong></div></div><a href="checkout.html" class="mt-6 block rounded-xl bg-green-600 px-5 py-3 text-center font-bold text-white hover:bg-green-700">Proceed to Checkout →</a><a href="catalog.html" class="mt-4 block text-center text-sm text-gray-400">← Continue Shopping</a></aside></div>` : `<div class="mx-auto mt-16 max-w-xl rounded-3xl border border-gray-100 bg-white p-12 text-center shadow-sm"><span class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-green-50 text-green-600"><i data-lucide="shopping-basket"></i></span><h2 class="mt-5 text-2xl font-bold">Your cart is empty</h2><p class="mt-2 text-gray-500">Browse local products and add a few items.</p><a href="catalog.html" class="mt-7 inline-block rounded-xl bg-green-600 px-7 py-3 font-bold text-white">Shop Now</a></div>`}</main>`;
        $$('.js-qty').forEach(button => button.addEventListener('click', () => { const id = Number(button.dataset.id), delta = Number(button.dataset.delta), cart = getCart(), row = cart.find(item => item.productId === id), product = PRODUCTS.find(item => item.id === id); if (!row || !product) return; row.quantity = Math.min(product.stock, row.quantity + delta); if (row.quantity < 1) cart.splice(cart.indexOf(row), 1); saveCart(cart); renderCart(); renderShell(); }));
        $$('.js-remove').forEach(button => button.addEventListener('click', () => { saveCart(getCart().filter(row => row.productId !== Number(button.dataset.id))); renderCart(); renderShell(); }));
    }

    function renderCheckout() {
        const rows = cartRows(); if (!rows.length) { location.href = 'cart.html'; return; }
        const subtotal = rows.reduce((sum, row) => sum + row.product.price * row.quantity, 0), delivery = subtotal >= 200 ? 0 : 30, total = subtotal + delivery;
        $('#page-content').innerHTML = `<main class="mx-auto max-w-6xl px-4 py-12 sm:px-6"><h1 class="flex items-center gap-3 text-4xl font-extrabold"><i data-lucide="credit-card" class="text-green-600"></i>Checkout</h1><p class="mt-2 text-gray-500">Complete your demonstration order.</p><div class="mt-7 grid grid-cols-3 gap-3 text-sm font-semibold"><div class="js-step-label rounded-xl bg-green-600 p-3 text-center text-white">1 Delivery</div><div class="js-step-label rounded-xl bg-gray-100 p-3 text-center text-gray-400">2 Payment</div><div class="js-step-label rounded-xl bg-gray-100 p-3 text-center text-gray-400">3 Confirm</div></div><div class="mt-7 grid gap-7 lg:grid-cols-3"><section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm lg:col-span-2"><form id="checkout-form"><div id="checkout-step-1"><h2 class="text-xl font-bold">Delivery Address</h2><div class="mt-5 grid gap-4 sm:grid-cols-2"><label class="text-sm font-medium">Full Name<input name="name" required value="Demo User" class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3"></label><label class="text-sm font-medium">Phone Number<input name="phone" required value="66762172" class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3"></label><label class="text-sm font-medium sm:col-span-2">Delivery Address<input name="address" required value="268 Queen's Road, Hong Kong" class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3"></label><label class="text-sm font-medium">District<select name="district" required class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3"><option value="">Select district</option><option>Hong Kong Island</option><option>Kowloon</option><option>New Territories</option></select></label><fieldset><legend class="text-sm font-medium">Preferred Delivery Date</legend><div class="mt-1 grid grid-cols-[1fr_auto_1fr_auto_1fr] items-center gap-2"><input id="date-year" inputmode="numeric" maxlength="4" placeholder="YYYY" required class="rounded-xl border border-gray-200 px-3 py-3 text-center"><span>/</span><input id="date-month" inputmode="numeric" maxlength="2" placeholder="MM" required class="rounded-xl border border-gray-200 px-3 py-3 text-center"><span>/</span><input id="date-day" inputmode="numeric" maxlength="2" placeholder="DD" required class="rounded-xl border border-gray-200 px-3 py-3 text-center"></div><p id="date-error" class="mt-1 hidden text-xs text-red-600">Enter a valid future date.</p></fieldset><label class="text-sm font-medium sm:col-span-2">Delivery Notes (optional)<input name="notes" class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3" placeholder="Leave at door, ring twice"></label></div><button type="button" id="to-payment" class="mt-6 w-full rounded-xl bg-green-600 px-5 py-3 font-bold text-white">Continue to Payment →</button></div><div id="checkout-step-2" class="hidden"><h2 class="text-xl font-bold">Card Details</h2><p class="mt-2 text-sm text-gray-500">Use demo card 4242 4242 4242 4242. No payment is processed.</p><div class="mt-5 grid gap-4 sm:grid-cols-2"><label class="text-sm font-medium sm:col-span-2">Name on Card<input id="card-name" required value="DEMO USER" class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3"></label><label class="text-sm font-medium sm:col-span-2">Card Number<input id="card-number" inputmode="numeric" maxlength="19" required placeholder="4242 4242 4242 4242" class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3"></label><label class="text-sm font-medium">Expiry<input id="card-expiry" inputmode="numeric" maxlength="5" required placeholder="12/30" class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3"></label><label class="text-sm font-medium">CVC<input id="card-cvc" inputmode="numeric" maxlength="3" required placeholder="123" class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3"></label></div><p id="card-error" class="mt-3 hidden text-sm text-red-600">Enter the demo card details in the required format.</p><div class="mt-6 flex gap-3"><button type="button" class="js-back flex-1 rounded-xl border border-gray-200 px-5 py-3 font-bold">← Back</button><button type="button" id="to-confirm" class="flex-[2] rounded-xl bg-green-600 px-5 py-3 font-bold text-white">Review Order →</button></div></div><div id="checkout-step-3" class="hidden"><h2 class="text-xl font-bold">Confirm Order</h2><div id="confirm-details" class="mt-5 rounded-xl bg-gray-50 p-5 text-sm leading-7"></div><div class="mt-5 flex items-center justify-between rounded-xl bg-green-50 p-5"><strong>Total Payable</strong><strong class="text-2xl text-green-600">${money(total)}</strong></div><button type="button" id="place-order" class="mt-6 w-full rounded-xl bg-green-600 px-5 py-3 font-bold text-white">Place Demo Order →</button><button type="button" class="js-back mt-3 w-full text-sm text-gray-400">← Edit Payment</button></div></form></section><aside class="h-fit rounded-2xl border border-gray-100 bg-white p-6 shadow-sm"><h2 class="font-bold">Your Order</h2><div class="mt-4 space-y-2 text-sm">${rows.map(row => `<div class="flex justify-between gap-3"><span>${escapeHtml(row.product.name)} ×${row.quantity}</span><span>${money(row.product.price * row.quantity)}</span></div>`).join('')}</div><div class="mt-5 space-y-2 border-t pt-4 text-sm"><div class="flex justify-between"><span>Subtotal</span><strong>${money(subtotal)}</strong></div><div class="flex justify-between"><span>Delivery</span><strong>${delivery ? money(delivery) : 'Free'}</strong></div><div class="flex justify-between border-t pt-2 text-lg"><strong>Total</strong><strong class="text-green-600">${money(total)}</strong></div></div></aside></div></main>`;
        let step = 1;
        const showStep = number => { step = number; [1,2,3].forEach(value => $(`#checkout-step-${value}`).classList.toggle('hidden', value !== number)); $$('.js-step-label').forEach((label, index) => { const active = index + 1 <= number; label.className = `js-step-label rounded-xl p-3 text-center ${active ? 'bg-green-600 text-white' : 'bg-gray-100 text-gray-400'}`; }); };
        const digitsOnly = input => { input.value = input.value.replace(/\D/g, '').slice(0, Number(input.maxLength)); };
        ['date-year','date-month','date-day'].forEach((id, index, list) => { const input = $(`#${id}`); input.addEventListener('input', () => { digitsOnly(input); if (input.value.length === input.maxLength && list[index + 1]) $(`#${list[index + 1]}`).focus(); }); });
        const validDate = () => { const y=Number($('#date-year').value), m=Number($('#date-month').value), d=Number($('#date-day').value), date=new Date(y,m-1,d); return String(y).length===4 && m>=1 && m<=12 && d>=1 && date.getFullYear()===y && date.getMonth()===m-1 && date.getDate()===d && date >= new Date(new Date().setHours(0,0,0,0)); };
        $('#to-payment').addEventListener('click', () => { const form=$('#checkout-form'); if (!form.name.value || !form.phone.value || !form.address.value || !form.district.value || !validDate()) { $('#date-error').classList.toggle('hidden', validDate()); form.reportValidity(); return; } $('#date-error').classList.add('hidden'); showStep(2); });
        $('#card-number').addEventListener('input', event => { const value=event.target.value.replace(/\D/g,'').slice(0,16); event.target.value=value.replace(/(.{4})/g,'$1 ').trim(); });
        $('#card-expiry').addEventListener('input', event => { const value=event.target.value.replace(/\D/g,'').slice(0,4); event.target.value=value.length>2 ? `${value.slice(0,2)}/${value.slice(2)}` : value; });
        $('#card-cvc').addEventListener('input', event => digitsOnly(event.target));
        $('#to-confirm').addEventListener('click', () => { const number=$('#card-number').value.replace(/\D/g,''), expiry=$('#card-expiry').value, cvc=$('#card-cvc').value; if(number.length!==16 || !/^\d{2}\/\d{2}$/.test(expiry) || cvc.length!==3){ $('#card-error').classList.remove('hidden'); return; } $('#card-error').classList.add('hidden'); const form=$('#checkout-form'); $('#confirm-details').innerHTML=`<strong>Delivery</strong><br>Name: ${escapeHtml(form.name.value)}<br>Phone: ${escapeHtml(form.phone.value)}<br>Address: ${escapeHtml(form.address.value)}, ${escapeHtml(form.district.value)}<br>Date: ${$('#date-year').value}-${$('#date-month').value.padStart(2,'0')}-${$('#date-day').value.padStart(2,'0')}<br><br><strong>Payment</strong><br>Demo card ending in ${number.slice(-4)}`; showStep(3); });
        $$('.js-back').forEach(button => button.addEventListener('click', () => showStep(Math.max(1, step-1))));
        $('#place-order').addEventListener('click', event => { event.currentTarget.disabled=true; event.currentTarget.textContent='Processing…'; const orders=JSON.parse(localStorage.getItem('greenbite_static_orders')||'[]'); orders.unshift({ id:`GB-${Date.now().toString().slice(-8)}`, date:new Date().toISOString(), items:rows.map(row=>({productId:row.product.id,quantity:row.quantity})), total, status:'Confirmed' }); localStorage.setItem('greenbite_static_orders',JSON.stringify(orders)); saveCart([]); setTimeout(()=>location.href='orders.html?placed=1',700); });
    }

    function renderOrders() {
        const orders = JSON.parse(localStorage.getItem('greenbite_static_orders') || '[]');
        $('#page-content').innerHTML = `<main class="mx-auto max-w-5xl px-4 py-12 sm:px-6"><div class="flex items-end justify-between"><div><h1 class="text-4xl font-extrabold">My Orders</h1><p class="mt-2 text-gray-500">View orders saved by this static demonstration.</p></div><a href="catalog.html" class="rounded-xl bg-green-600 px-5 py-3 font-bold text-white">Shop Again</a></div>${new URLSearchParams(location.search).get('placed') ? '<div class="mt-7 rounded-xl border border-green-200 bg-green-50 p-4 font-semibold text-green-800">Your demonstration order was created successfully.</div>' : ''}<div class="mt-8 space-y-5">${orders.length ? orders.map(order => `<article class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm"><div class="flex flex-col justify-between gap-3 sm:flex-row"><div><p class="text-xs font-semibold text-gray-400">ORDER ${escapeHtml(order.id)}</p><p class="mt-1 font-bold">${new Date(order.date).toLocaleString()}</p></div><span class="h-fit rounded-full bg-green-100 px-3 py-1 text-sm font-bold text-green-700">${order.status}</span></div><div class="mt-5 space-y-2 border-t pt-4 text-sm">${order.items.map(item => { const product=PRODUCTS.find(p=>p.id===item.productId); return product ? `<div class="flex justify-between"><span>${escapeHtml(product.name)} ×${item.quantity}</span><span>${money(product.price*item.quantity)}</span></div>` : ''; }).join('')}</div><div class="mt-4 flex justify-between border-t pt-4 text-lg font-bold"><span>Total</span><span class="text-green-600">${money(order.total)}</span></div></article>`).join('') : '<div class="rounded-3xl border border-gray-100 bg-white p-12 text-center text-gray-500">No demonstration orders yet.</div>'}</div></main>`;
    }

    function renderSubscriptions() {
        const plans=[['Weekly Starter',280,'5 seasonal products each week','Best for 1–2 people'],['Family Harvest',520,'10 seasonal products each week','Best for a family'],['Flexible Market Box',360,'Choose your preferred products','Pause or change any week']];
        $('#page-content').innerHTML=`<main class="mx-auto max-w-7xl px-4 py-14 text-center sm:px-6"><p class="text-sm font-bold uppercase tracking-wide text-green-700">Flexible local boxes</p><h1 class="mt-2 text-4xl font-extrabold">Subscription Plans</h1><p class="mx-auto mt-4 max-w-2xl text-gray-500">Regular deliveries from Hong Kong farms. This static version demonstrates plan selection.</p><div class="mt-10 grid gap-6 lg:grid-cols-3">${plans.map((plan,index)=>`<article class="rounded-3xl border ${index===1?'border-green-500 ring-2 ring-green-100':'border-gray-100'} bg-white p-8 text-left shadow-lg"><p class="text-sm font-bold text-green-700">${index===1?'MOST POPULAR':'LOCAL BOX'}</p><h2 class="mt-3 text-2xl font-extrabold">${plan[0]}</h2><p class="mt-5"><span class="text-4xl font-extrabold text-green-600">${money(plan[1])}</span><span class="text-gray-400"> / week</span></p><p class="mt-6 text-gray-600">${plan[2]}</p><p class="mt-2 text-sm text-gray-400">${plan[3]}</p><button class="js-plan mt-8 w-full rounded-xl bg-green-600 px-5 py-3 font-bold text-white">Choose Plan</button></article>`).join('')}</div></main>`;
        $$('.js-plan').forEach(button=>button.addEventListener('click',()=>showToast('Plan selected for this demonstration')));
    }

    function renderAuth() {
        $('#page-content').innerHTML=`<main class="mx-auto grid max-w-5xl gap-8 px-4 py-14 sm:px-6 lg:grid-cols-2"><section class="rounded-3xl bg-gradient-to-br from-green-600 to-emerald-800 p-10 text-white"><p class="text-sm font-bold uppercase tracking-wide text-green-100">GreenBite account</p><h1 class="mt-4 text-4xl font-extrabold">Sign in before the demonstration</h1><p class="mt-5 leading-7 text-green-50">A signed-in user can view the personal menu, cart, checkout, and saved orders.</p><div class="mt-9 space-y-4 text-sm"><p>✓ Personal daily menu</p><p>✓ Persistent shopping cart</p><p>✓ Order history</p></div></section><section class="rounded-3xl border border-gray-100 bg-white p-8 shadow-xl"><h2 class="text-2xl font-extrabold">Welcome back</h2><p class="mt-2 text-sm text-gray-500">Any valid-looking email and password work in this static demonstration.</p><form id="static-login" class="mt-7 space-y-5"><label class="block text-sm font-medium">Email<input type="email" required value="demo@greenbite.hk" class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3"></label><label class="block text-sm font-medium">Password<input type="password" required value="12345678" minlength="8" class="mt-1 w-full rounded-xl border border-gray-200 px-4 py-3"></label><button class="w-full rounded-xl bg-green-600 px-5 py-3 font-bold text-white">Sign In</button></form></section></main>`;
        $('#static-login').addEventListener('submit',event=>{event.preventDefault();localStorage.setItem('greenbite_static_user','demo');location.href='dashboard.html';});
    }

    function renderDashboard() {
        $('#page-content').innerHTML=`<main class="mx-auto max-w-7xl px-4 py-12 sm:px-6"><div class="rounded-3xl bg-gradient-to-r from-green-600 to-emerald-700 p-8 text-white"><p class="text-green-100">Welcome back, Demo User</p><h1 class="mt-2 text-4xl font-extrabold">Your GreenBite Dashboard</h1><p class="mt-3 max-w-2xl text-green-50">Review the current menu, shop local products, and manage demonstration orders.</p></div><div class="mt-8 grid gap-6 md:grid-cols-3">${feature('shopping-basket','Browse products','View local origins, prices, stock, and product details.')}${feature('calendar-days','Daily menu','See meal ideas based on products currently available.')}${feature('package-check','Track orders','Review the order created during the checkout demonstration.')}</div><div class="mt-8 grid gap-6 lg:grid-cols-2"><section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm"><h2 class="text-xl font-bold">Today’s menu</h2><div class="mt-5 space-y-3">${MENUS[0].map(item=>`<div class="rounded-xl bg-gray-50 p-4"><p class="text-xs font-bold uppercase text-green-700">${item[0]}</p><p class="mt-1 font-bold">${item[1]}</p></div>`).join('')}</div><a href="index.html#daily-menu" class="mt-5 inline-block font-bold text-green-700">Open daily menu →</a></section><section class="rounded-2xl border border-gray-100 bg-white p-6 shadow-sm"><h2 class="text-xl font-bold">Quick actions</h2><div class="mt-5 grid gap-3"><a href="catalog.html" class="rounded-xl border border-gray-200 p-4 font-bold hover:border-green-400">Browse catalog</a><a href="survey.html" class="rounded-xl border border-gray-200 p-4 font-bold hover:border-green-400">Update food preferences</a><a href="orders.html" class="rounded-xl border border-gray-200 p-4 font-bold hover:border-green-400">View my orders</a></div></section></div></main>`;
    }

    function renderSurvey() {
        $('#page-content').innerHTML=`<main class="mx-auto max-w-3xl px-4 py-12 sm:px-6"><div class="text-center"><p class="text-sm font-bold uppercase text-green-700">Personal menu setup</p><h1 class="mt-2 text-4xl font-extrabold">Food Preferences</h1><p class="mt-3 text-gray-500">Choose a few preferences for the daily menu demonstration.</p></div><form id="survey-form" class="mt-8 space-y-7 rounded-3xl border border-gray-100 bg-white p-8 shadow-xl"><fieldset><legend class="font-bold">Diet style</legend><div class="mt-3 grid gap-3 sm:grid-cols-3">${['Balanced','Vegetarian','High protein'].map(item=>`<label class="rounded-xl border border-gray-200 p-4"><input type="radio" name="diet" value="${item}" class="mr-2 accent-green-600" ${item==='Balanced'?'checked':''}>${item}</label>`).join('')}</div></fieldset><fieldset><legend class="font-bold">Meals to include</legend><div class="mt-3 grid gap-3 sm:grid-cols-3">${['Breakfast','Lunch','Dinner'].map(item=>`<label class="rounded-xl border border-gray-200 p-4"><input type="checkbox" checked class="mr-2 accent-green-600">${item}</label>`).join('')}</div></fieldset><label class="block font-bold">Avoided ingredients<textarea class="mt-3 min-h-28 w-full rounded-xl border border-gray-200 px-4 py-3" placeholder="For example: peanuts"></textarea></label><button class="w-full rounded-xl bg-green-600 px-5 py-3 font-bold text-white">Save Preferences</button></form></main>`;
        $('#survey-form').addEventListener('submit',event=>{event.preventDefault();showToast('Preferences saved');setTimeout(()=>location.href='dashboard.html',600);});
    }

    const renderers={home:renderHome,catalog:renderCatalog,product:renderProductDetail,cart:renderCart,checkout:renderCheckout,orders:renderOrders,subscriptions:renderSubscriptions,auth:renderAuth,dashboard:renderDashboard,survey:renderSurvey};
    document.addEventListener('DOMContentLoaded',()=>{renderShell();(renderers[document.body.dataset.page]||renderHome)();updateCartCount();if(window.lucide)window.lucide.createIcons();});
})();
