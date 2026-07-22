# Product Detail Page Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Deliver a public, shareable product detail page that completes the catalog-to-detail-to-cart flow for the graduation-design demo.

**Architecture:** Add a server-rendered `GET /products/{product}` route to the existing Web `ProductController`, render a focused Blade view, and reuse the global `addToCartAuth()` function for authenticated and guest carts. Keep the database unchanged, enforce `published` visibility at both Web and API detail boundaries, and link catalog images and titles to the new route.

**Tech Stack:** PHP 8.2, Laravel 12, Eloquent, Blade, Tailwind CSS 4, vanilla JavaScript/jQuery, PHPUnit 11, Vite 7.

## Global Constraints

- This is a graduation-design project: complete a demonstrable end-to-end feature before optional polish, abstraction, or refactoring.
- Reuse Laravel, Blade, Tailwind, the existing global `addToCartAuth(productId, name, price, qty)` function, and current project conventions.
- Only `published` products may be exposed by public Web or API detail endpoints.
- All new user-visible interface copy must have Simplified Chinese, Traditional Chinese, and English values.
- Do not add a database migration, Service, Repository, frontend framework, component library, review system, favorites, recommendations, sharing, gallery, or SEO structured data.
- Preserve server-side inventory validation; browser quantity constraints are only immediate feedback.
- Each task follows RED-GREEN-REFACTOR and ends with independently verifiable behavior.

---

## File Map

- Create `resources/views/shop/product-detail.blade.php`: server-rendered product detail and quantity-to-cart interaction.
- Create `tests/Feature/Web/ProductDetailTest.php`: public visibility, content, sold-out state, and catalog-link behavior.
- Create `tests/Feature/Api/ProductApiDetailTest.php`: API response contract and unpublished-product protection.
- Modify `routes/web.php`: name the catalog route and add the public product detail route.
- Modify `app/Http/Controllers/ProductController.php`: add `show(Product $product)` and load category data.
- Modify `app/Http/Controllers/Api/ProductController.php`: reject non-published products in `show()`.
- Modify `resources/views/shop/catalog.blade.php`: link product image and title while keeping direct-add behavior.
- Modify `resources/lang/zh.json`, `resources/lang/zhhk.json`, `resources/lang/en.json`: add the `productDetail` namespace.

---

### Task 1: Public Product Detail Route and Visibility Boundary

**Files:**
- Create: `tests/Feature/Web/ProductDetailTest.php`
- Create: `resources/views/shop/product-detail.blade.php`
- Modify: `routes/web.php:14`
- Modify: `app/Http/Controllers/ProductController.php:14-25`

**Interfaces:**
- Consumes: `App\Models\Product`, `Product::STATUS_PUBLISHED`, route-model binding, and the `category` relationship.
- Produces: named route `products.show`, `ProductController::show(Product $product): View`, and view variable `$product`.

- [ ] **Step 1: Write failing Web detail tests**

Create `tests/Feature/Web/ProductDetailTest.php`:

```php
<?php

namespace Tests\Feature\Web;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_product_detail_is_publicly_visible(): void
    {
        $category = Category::factory()->create(['name' => 'Leafy Greens']);
        $product = Product::factory()->create([
            'name' => 'Local Organic Choy Sum',
            'description' => 'Picked this morning in Yuen Long.',
            'price' => 28,
            'stock' => 7,
            'status' => Product::STATUS_PUBLISHED,
            'category_id' => $category->id,
        ]);

        $this->get("/products/{$product->id}")
            ->assertOk()
            ->assertViewIs('shop.product-detail')
            ->assertViewHas('product', fn (Product $viewProduct) => $viewProduct->is($product))
            ->assertSee('Local Organic Choy Sum')
            ->assertSee('Picked this morning in Yuen Long.')
            ->assertSee('Leafy Greens');
    }

    public function test_draft_product_detail_returns_404(): void
    {
        $product = Product::factory()->create(['status' => Product::STATUS_DRAFT]);

        $this->get("/products/{$product->id}")->assertNotFound();
    }

    public function test_archived_product_detail_returns_404(): void
    {
        $product = Product::factory()->create(['status' => Product::STATUS_ARCHIVED]);

        $this->get("/products/{$product->id}")->assertNotFound();
    }

    public function test_missing_product_detail_returns_404(): void
    {
        $this->get('/products/999999')->assertNotFound();
    }
}
```

- [ ] **Step 2: Run the tests and verify RED**

Run:

```powershell
php artisan test tests/Feature/Web/ProductDetailTest.php
```

Expected: the published-product test fails with HTTP 404 because `/products/{product}` is not registered. The draft, archived, and missing cases may already return 404; that does not satisfy the missing success path.

- [ ] **Step 3: Add the route and controller method**

Change the public catalog routes in `routes/web.php` to:

```php
Route::get('/catalog', [ProductController::class, 'index'])->name('catalog');
Route::get('/products/{product}', [ProductController::class, 'show'])->name('products.show');
```

Add the import and method to `app/Http/Controllers/ProductController.php`:

```php
use Illuminate\Contracts\View\View;

public function show(Product $product): View
{
    abort_unless($product->status === Product::STATUS_PUBLISHED, 404);

    $product->load('category:id,name,slug');

    return view('shop.product-detail', ['product' => $product]);
}
```

- [ ] **Step 4: Add the minimal Blade view**

Create `resources/views/shop/product-detail.blade.php`:

```blade
@extends('layouts.app')

@section('title', $product->name)

@section('content')
<div class="container mx-auto px-4 py-12">
    <a href="{{ route('catalog') }}" class="text-green-700 hover:text-green-800">{{ i18n('common.back') }}</a>
    <article class="mt-6 bg-white rounded-2xl border border-gray-100 shadow-sm p-6">
        @if($product->category)
            <p>{{ $product->category->name }}</p>
        @endif
        <h1 class="text-3xl font-extrabold text-gray-900">{{ $product->name }}</h1>
        <p class="mt-4 text-2xl font-bold text-green-600">HK${{ number_format((float) $product->price, 2) }}</p>
        <p class="mt-6 text-gray-600">{{ $product->description }}</p>
    </article>
</div>
@endsection
```

- [ ] **Step 5: Run the Web detail tests and verify GREEN**

Run:

```powershell
php artisan test tests/Feature/Web/ProductDetailTest.php
```

Expected: 4 tests pass.

- [ ] **Step 6: Commit the public detail boundary**

```powershell
git add routes/web.php app/Http/Controllers/ProductController.php resources/views/shop/product-detail.blade.php tests/Feature/Web/ProductDetailTest.php
git commit -m "feat: add public product detail route"
```

---

### Task 2: Complete Responsive Detail UI, Quantity Control, and i18n

**Files:**
- Modify: `tests/Feature/Web/ProductDetailTest.php`
- Modify: `resources/views/shop/product-detail.blade.php`
- Modify: `resources/lang/zh.json`
- Modify: `resources/lang/zhhk.json`
- Modify: `resources/lang/en.json`

**Interfaces:**
- Consumes: named route `catalog`, `$product->image_url`, `$product->category`, and `addToCartAuth(int, string, float, int)`.
- Produces: `productDetail.*` translations, quantity input `#product-quantity`, and a disabled sold-out state.

- [ ] **Step 1: Add failing UI-state tests**

Add these methods to `ProductDetailTest`:

```php
public function test_in_stock_detail_renders_quantity_and_add_to_cart_contract(): void
{
    $product = Product::factory()->create([
        'name' => 'Fresh Carrot',
        'price' => 18,
        'stock' => 7,
        'status' => Product::STATUS_PUBLISHED,
    ]);

    $this->get(route('products.show', $product))
        ->assertOk()
        ->assertSee('id="product-quantity"', false)
        ->assertSee('min="1"', false)
        ->assertSee('max="7"', false)
        ->assertSee('addToCartAuth(', false)
        ->assertSee('Fresh Carrot');
}

public function test_sold_out_detail_disables_purchase_controls(): void
{
    $product = Product::factory()->outOfStock()->create([
        'status' => Product::STATUS_PUBLISHED,
    ]);

    $this->get(route('products.show', $product))
        ->assertOk()
        ->assertSee('data-testid="sold-out-button"', false)
        ->assertSee('disabled', false)
        ->assertDontSee('id="product-quantity"', false);
}

public function test_optional_product_fields_are_not_rendered_when_null(): void
{
    $product = Product::factory()->create([
        'status' => Product::STATUS_PUBLISHED,
        'origin' => null,
        'carbon_footprint' => null,
        'image' => null,
    ]);

    $this->get(route('products.show', $product))
        ->assertOk()
        ->assertSee('data-testid="product-image-placeholder"', false)
        ->assertDontSee('data-testid="product-origin"', false)
        ->assertDontSee('data-testid="product-carbon"', false);
}
```

- [ ] **Step 2: Run the tests and verify RED**

```powershell
php artisan test tests/Feature/Web/ProductDetailTest.php
```

Expected: the three new tests fail because the minimal view has no quantity control, sold-out contract, optional-field markers, or placeholder.

- [ ] **Step 3: Add the three translation namespaces**

Insert this top-level object after `catalog` in `resources/lang/en.json`:

```json
"productDetail": {
  "backToCatalog": "Back to catalog",
  "organic": "Organic",
  "carbonFootprint": "Carbon footprint",
  "carbonUnit": "kg CO2e",
  "origin": "Origin",
  "inStock": "In stock",
  "stockRemaining": ":count remaining",
  "quantity": "Quantity",
  "addToCart": "Add to cart",
  "soldOut": "Sold out",
  "description": "Product description",
  "imageUnavailable": "Image unavailable"
},
```

Insert this top-level object after `catalog` in `resources/lang/zh.json`:

```json
"productDetail": {
  "backToCatalog": "返回商品目录",
  "organic": "有机",
  "carbonFootprint": "碳足迹",
  "carbonUnit": "kg CO2e",
  "origin": "产地",
  "inStock": "有库存",
  "stockRemaining": "剩余 :count 件",
  "quantity": "数量",
  "addToCart": "加入购物车",
  "soldOut": "售罄",
  "description": "商品描述",
  "imageUnavailable": "暂无商品图片"
},
```

Insert this top-level object after `catalog` in `resources/lang/zhhk.json`:

```json
"productDetail": {
  "backToCatalog": "返回商品目錄",
  "organic": "有機",
  "carbonFootprint": "碳足跡",
  "carbonUnit": "kg CO2e",
  "origin": "產地",
  "inStock": "有存貨",
  "stockRemaining": "剩餘 :count 件",
  "quantity": "數量",
  "addToCart": "加入購物車",
  "soldOut": "售罄",
  "description": "商品描述",
  "imageUnavailable": "暫無商品圖片"
},
```

- [ ] **Step 4: Replace the minimal view with the complete functional view**

Replace `resources/views/shop/product-detail.blade.php` with:

```blade
@extends('layouts.app')

@section('title', $product->name)

@section('content')
@php($soldOut = (int) $product->stock <= 0)
<div class="container mx-auto px-4 py-8 md:py-12">
    <a href="{{ route('catalog') }}" class="inline-flex items-center gap-2 text-green-700 hover:text-green-800 font-semibold">
        <i data-lucide="arrow-left" class="w-4 h-4"></i>
        {{ i18n('productDetail.backToCatalog') }}
    </a>

    <article class="mt-6 grid grid-cols-1 lg:grid-cols-2 gap-8 lg:gap-12 bg-white rounded-2xl border border-gray-100 shadow-sm p-5 md:p-8">
        <div class="rounded-2xl overflow-hidden bg-gray-100 aspect-square">
            @if($product->image_url)
                <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="w-full h-full object-cover">
            @else
                <div data-testid="product-image-placeholder" class="w-full h-full flex flex-col items-center justify-center text-gray-400">
                    <i data-lucide="image-off" class="w-14 h-14 mb-3"></i>
                    <span>{{ i18n('productDetail.imageUnavailable') }}</span>
                </div>
            @endif
        </div>

        <div class="flex flex-col">
            @if($product->category)
                <p class="text-sm font-semibold uppercase tracking-wide text-green-700">{{ $product->category->name }}</p>
            @endif
            <h1 class="mt-2 text-3xl md:text-4xl font-extrabold text-gray-900">{{ $product->name }}</h1>

            <div class="mt-4 flex flex-wrap gap-2">
                @if($product->is_organic)
                    <span class="inline-flex items-center gap-1 rounded-full bg-green-100 px-3 py-1 text-sm font-semibold text-green-700">
                        <i data-lucide="sprout" class="w-4 h-4"></i>{{ i18n('productDetail.organic') }}
                    </span>
                @endif
                @if(!is_null($product->carbon_footprint))
                    <span data-testid="product-carbon" class="inline-flex items-center gap-1 rounded-full bg-gray-100 px-3 py-1 text-sm text-gray-700">
                        <i data-lucide="leaf" class="w-4 h-4"></i>
                        {{ i18n('productDetail.carbonFootprint') }}: {{ number_format((float) $product->carbon_footprint, 2) }} {{ i18n('productDetail.carbonUnit') }}
                    </span>
                @endif
            </div>

            @if($product->origin)
                <p data-testid="product-origin" class="mt-5 flex items-center gap-2 text-gray-600">
                    <i data-lucide="map-pin" class="w-5 h-5 text-green-600"></i>
                    <span><strong>{{ i18n('productDetail.origin') }}:</strong> {{ $product->origin }}</span>
                </p>
            @endif

            <p class="mt-6 text-3xl font-extrabold text-green-600">HK${{ number_format((float) $product->price, 2) }}</p>

            <p class="mt-2 {{ $soldOut ? 'text-red-600' : 'text-green-700' }} font-semibold">
                {{ $soldOut ? i18n('productDetail.soldOut') : i18n('productDetail.stockRemaining', ['count' => $product->stock]) }}
            </p>

            <section class="mt-8">
                <h2 class="text-lg font-bold text-gray-900">{{ i18n('productDetail.description') }}</h2>
                <p class="mt-2 text-gray-600 leading-7">{{ $product->description }}</p>
            </section>

            <div class="mt-8 pt-6 border-t border-gray-100">
                @if($soldOut)
                    <button data-testid="sold-out-button" type="button" disabled class="w-full rounded-xl bg-gray-200 px-6 py-4 font-bold text-gray-500 cursor-not-allowed">
                        {{ i18n('productDetail.soldOut') }}
                    </button>
                @else
                    <label for="product-quantity" class="block text-sm font-semibold text-gray-700 mb-2">{{ i18n('productDetail.quantity') }}</label>
                    <div class="flex flex-col sm:flex-row gap-3">
                        <input id="product-quantity" type="number" min="1" max="{{ (int) $product->stock }}" value="1"
                            class="w-full sm:w-28 rounded-xl border border-gray-300 px-4 py-3 focus:border-green-500 focus:ring-green-500">
                        <button type="button"
                            onclick="addToCartAuth({{ (int) $product->id }}, {{ \Illuminate\Support\Js::from($product->name) }}, {{ (float) $product->price }}, Number(document.getElementById('product-quantity').value))"
                            class="flex-1 inline-flex items-center justify-center gap-2 rounded-xl bg-green-600 px-6 py-3 font-bold text-white hover:bg-green-700 transition">
                            <i data-lucide="shopping-cart" class="w-5 h-5"></i>
                            {{ i18n('productDetail.addToCart') }}
                        </button>
                    </div>
                @endif
            </div>
        </div>
    </article>
</div>
@endsection
```

- [ ] **Step 5: Validate JSON, run tests, and build assets**

Run:

```powershell
Get-Content -Raw resources\lang\zh.json | ConvertFrom-Json | Out-Null
Get-Content -Raw resources\lang\zhhk.json | ConvertFrom-Json | Out-Null
Get-Content -Raw resources\lang\en.json | ConvertFrom-Json | Out-Null
php artisan test tests/Feature/Web/ProductDetailTest.php
npm run build
```

Expected: all three JSON paths print, 7 Web detail tests pass, and Vite exits successfully.

- [ ] **Step 6: Commit the complete detail UI**

```powershell
git add resources/views/shop/product-detail.blade.php resources/lang/zh.json resources/lang/zhhk.json resources/lang/en.json tests/Feature/Web/ProductDetailTest.php
git commit -m "feat: build product detail purchase view"
```

---

### Task 3: Catalog-to-Detail Navigation

**Files:**
- Modify: `tests/Feature/Web/ProductDetailTest.php`
- Modify: `resources/views/shop/catalog.blade.php:18-38`

**Interfaces:**
- Consumes: named route `products.show` from Task 1.
- Produces: exactly two detail links per product card (image and title), while preserving the direct-add button.

- [ ] **Step 1: Add the failing catalog-link test**

Add to `ProductDetailTest`:

```php
public function test_catalog_links_product_image_and_title_to_detail(): void
{
    $product = Product::factory()->create([
        'name' => 'Linked Product',
        'status' => Product::STATUS_PUBLISHED,
    ]);

    $response = $this->get(route('catalog'))->assertOk();
    $detailUrl = route('products.show', $product);

    $response->assertSee($detailUrl, false);
    $this->assertSame(2, substr_count($response->getContent(), 'href="'.$detailUrl.'"'));
    $response->assertSee('addToCartAuth('.$product->id, false);
}
```

- [ ] **Step 2: Run the test and verify RED**

```powershell
php artisan test tests/Feature/Web/ProductDetailTest.php --filter=catalog_links
```

Expected: FAIL because the catalog contains zero links to `products.show`.

- [ ] **Step 3: Link the image and title without nesting the add button**

In `resources/views/shop/catalog.blade.php`, replace the product image element with:

```blade
<a href="{{ route('products.show', $product) }}" aria-label="{{ $product->name }}">
    <img src="{{ $product->image_url }}" alt="{{ $product->name }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-500">
</a>
```

Replace the `<h3>` with:

```blade
<h3 class="text-lg font-bold text-gray-900 mb-1">
    <a href="{{ route('products.show', $product) }}" class="hover:text-green-700 transition-colors">{{ $product->name }}</a>
</h3>
```

Do not move or wrap the existing add-to-cart button.

- [ ] **Step 4: Run the Web detail tests and verify GREEN**

```powershell
php artisan test tests/Feature/Web/ProductDetailTest.php
```

Expected: 8 tests pass, including exactly two detail links and the retained direct-add contract.

- [ ] **Step 5: Commit catalog navigation**

```powershell
git add resources/views/shop/catalog.blade.php tests/Feature/Web/ProductDetailTest.php
git commit -m "feat: link catalog products to detail pages"
```

---

### Task 4: Align Public API Detail Visibility

**Files:**
- Create: `tests/Feature/Api/ProductApiDetailTest.php`
- Modify: `app/Http/Controllers/Api/ProductController.php:51-56`

**Interfaces:**
- Consumes: `GET /api/products/{product}` and `Product::STATUS_*` constants.
- Produces: unchanged successful payload `{"data": product}` for published products and HTTP 404 for draft, archived, or missing products.

- [ ] **Step 1: Write failing API visibility tests**

Create `tests/Feature/Api/ProductApiDetailTest.php`:

```php
<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductApiDetailTest extends TestCase
{
    use RefreshDatabase;

    public function test_published_product_detail_keeps_existing_response_contract(): void
    {
        $product = Product::factory()->create([
            'name' => 'API Product',
            'status' => Product::STATUS_PUBLISHED,
        ]);

        $this->getJson("/api/products/{$product->id}")
            ->assertOk()
            ->assertJsonPath('data.id', $product->id)
            ->assertJsonPath('data.name', 'API Product')
            ->assertJsonStructure(['data' => ['id', 'name', 'price', 'stock', 'image_url', 'category']]);
    }

    public function test_draft_product_api_detail_returns_404(): void
    {
        $product = Product::factory()->create(['status' => Product::STATUS_DRAFT]);

        $this->getJson("/api/products/{$product->id}")->assertNotFound();
    }

    public function test_archived_product_api_detail_returns_404(): void
    {
        $product = Product::factory()->create(['status' => Product::STATUS_ARCHIVED]);

        $this->getJson("/api/products/{$product->id}")->assertNotFound();
    }

    public function test_missing_product_api_detail_returns_404(): void
    {
        $this->getJson('/api/products/999999')->assertNotFound();
    }
}
```

- [ ] **Step 2: Run tests and verify RED**

```powershell
php artisan test tests/Feature/Api/ProductApiDetailTest.php
```

Expected: the draft and archived tests fail with HTTP 200 because the existing `show()` exposes every bound product.

- [ ] **Step 3: Enforce published visibility in the API controller**

Change `app/Http/Controllers/Api/ProductController.php::show()` to:

```php
public function show(Product $product): JsonResponse
{
    abort_unless($product->status === Product::STATUS_PUBLISHED, 404);

    $product->load('category:id,name,slug');

    return response()->json(['data' => $product]);
}
```

- [ ] **Step 4: Run API tests and verify GREEN**

```powershell
php artisan test tests/Feature/Api/ProductApiDetailTest.php
```

Expected: 4 tests pass and the success response remains nested under `data`.

- [ ] **Step 5: Commit API visibility protection**

```powershell
git add app/Http/Controllers/Api/ProductController.php tests/Feature/Api/ProductApiDetailTest.php
git commit -m "fix: hide unpublished product API details"
```

---

### Task 5: Full Regression and Demonstration Verification

**Files:**
- Verify only; do not add unrelated code.

**Interfaces:**
- Consumes: all outputs from Tasks 1-4.
- Produces: evidence that the functional vertical slice is ready for graduation-project demonstration.

- [ ] **Step 1: Run focused product and cart tests**

```powershell
php artisan test tests/Feature/Web/ProductDetailTest.php
php artisan test tests/Feature/Api/ProductApiDetailTest.php
php artisan test tests/Feature/Web/CartAuthGuardTest.php
```

Expected: all focused tests pass with zero failures.

- [ ] **Step 2: Run the complete PHPUnit suite**

```powershell
php artisan test
```

Expected: the existing 147 tests plus the 12 new tests pass; the exact total may be higher if other work lands first, but failures must remain zero.

- [ ] **Step 3: Run the production frontend build**

```powershell
npm run build
```

Expected: Vite exits with code 0 and writes `public/build/manifest.json` and hashed CSS/JS assets.

- [ ] **Step 4: Verify routes**

```powershell
php artisan route:list --path=products
```

Expected: output contains `GET|HEAD products/{product}` named `products.show`, public API list/detail routes, and admin product routes.

- [ ] **Step 5: Perform local browser demonstration**

With the existing Laravel server running at `http://127.0.0.1:8000`:

1. Open `/catalog` and click a product image; verify navigation to `/products/{id}`.
2. Return to `/catalog` and click a product title; verify the same navigation.
3. On an in-stock detail, set quantity to 2 and add to cart; verify the header count increases by 2.
4. Open a sold-out product; verify the sold-out button is disabled and quantity is absent.
5. Resize to a narrow viewport; verify image, information, quantity, and button stack without horizontal overflow.
6. Request a draft or archived product by ID through Web and API; verify HTTP 404.
7. Switch Simplified Chinese, Traditional Chinese, and English; verify all detail-page labels resolve without raw translation keys.

Expected: every step succeeds with no browser console error produced by the product detail page.

---

## Completion Checklist

- [ ] Catalog image and title navigate to a stable public detail URL.
- [ ] Published product data renders correctly on desktop and mobile layouts.
- [ ] Quantity-to-cart flow works for guest and authenticated users through the existing cart mechanism.
- [ ] Sold-out products remain viewable but cannot be added.
- [ ] Draft and archived products return 404 from Web and API detail endpoints.
- [ ] Simplified Chinese, Traditional Chinese, and English copy is complete.
- [ ] Focused tests, full PHPUnit suite, JSON parsing, Vite build, and browser demonstration pass.
- [ ] Optional enhancements remain outside this iteration.
