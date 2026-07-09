# ADR: 产品所有权模型（product-user ownership）

> 日期：2026-07-09  
> 状态：方案阶段（待 Review）  
> 关联：ADR-0007（新）

---

## 1. 背景与动机

### 1.1 当前状态（审计结论）

| 检查项 | 是否具备 |
|---|:---:|
| `products` 表有 `user_id` 列 | ❌ |
| `Product` 模型有 `user()` 关系 | ❌ |
| `User` 模型有 `products()` 关系 | ❌ |
| `Admin\ProductController` 有所有权检查 | ❌ |
| `IsAdmin` 中间件区分角色 + 资源所有权 | ❌（只检查 `is_admin` 布尔值） |
| 导航栏对非 admin 隐藏"管理"入口 | ✅（通过 JS 检查 `user.is_admin`） |
| 相关测试 | ❌（零覆盖） |

**当前系统是"单管理员"模式**：只有 `is_admin=true` 的用户能进管理后台，所有商品都属于平台（无归属者）。

### 1.2 目标

- 普通用户（`is_admin=false`）可创建并管理**自己**的商品
- 管理员（`is_admin=true`）可管理**所有**商品
- 最小改动，不破坏现有 API / 公开页面

---

## 2. 方案设计

### 2.1 数据层：`products` 表加 `user_id`

```sql
-- 新建 migration: 2026_07_09_000001_add_user_id_to_products_table.php
ALTER TABLE products ADD COLUMN user_id INTEGER NULL
  REFERENCES users(id) ON DELETE SET NULL;

-- 索引（按 user 查询自己的产品）
CREATE INDEX idx_products_user_id ON products(user_id);
```

**设计决策**：
- `nullable`：历史数据 `user_id=NULL` 视为平台商品（或 admin 创建）
- `onDelete SET NULL`：用户删除后商品保留（不级联删除），归属变"无主"
- 不设 `default`，由 Controller 在 `store()` 中显式赋值

### 2.2 模型层

**Product**：
```php
// $fillable 新增
'user_id',

// 新增关系
public function user(): BelongsTo
{
    return $this->belongsTo(User::class);
}
```

**User**：
```php
// 新增关系
public function products(): HasMany
{
    return $this->hasMany(Product::class);
}
```

### 2.3 权限层：两阶段 Guards

不再扩大 `IsAdmin` 职责，改用 **资源级授权 Policy** 做"谁能编辑哪个产品"的判断。

#### 2.3.1 新增 `ProductPolicy`

```php
class ProductPolicy
{
    // 谁可以看管理列表？
    public function viewAny(User $user): bool
    {
        return true; // 登录即允许（Controller 内按角色过滤数据）
    }

    // 谁可以创建？
    public function create(User $user): bool
    {
        return true; // 登录即允许
    }

    // 谁可以编辑/更新某个产品？
    public function update(User $user, Product $product): bool
    {
        return $user->is_admin || $product->user_id === $user->id;
    }
}
```

#### 2.3.2 放宽 `IsAdmin` → `RequireAuth`

```php
// 改名后：只检查登录状态，不再检查 is_admin
class RequireAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return redirect()->route('login');
        }
        return $next($request);
    }
}
```

理由：`is_admin` 检查移到 Controller 层做，中间件只做"是否登录"这一个职责。

#### 2.3.3 `routes/web.php` 路由组

```php
// 旧：middleware('admin') → 只允许 is_admin
// 新：middleware('auth') → 允许所有登录用户
Route::prefix('admin')->middleware('auth')->name('admin.')->group(function () {
    Route::get('/products', [AdminProductController::class, 'index']);
    Route::get('/products/create', [AdminProductController::class, 'create']);
    Route::post('/products', [AdminProductController::class, 'store']);
    Route::get('/products/{product}/edit', [AdminProductController::class, 'edit']);
    Route::put('/products/{product}', [AdminProductController::class, 'update']);
});
```

### 2.4 Controller 层：`Admin\ProductController`

| 方法 | 当前 | 改动后 |
|------|------|--------|
| `index()` | 返回全部产品 | admin → 全部；普通用户 → `where('user_id', auth()->id())` |
| `store()` | 不记 user_id | 自动 `$data['user_id'] = auth()->id()` |
| `edit()` | 无权限检查 | `$this->authorize('update', $product)` |
| `update()` | 无权限检查 | `$this->authorize('update', $product)` |

```php
public function index(Request $request): View
{
    $q = Product::with('category:id,name')->orderByDesc('updated_at');

    if (! $request->user()->is_admin) {
        $q->where('user_id', $request->user()->id);
    }

    return view('admin.products.index', ['products' => $q->paginate(20)]);
}

public function store(Request $request): RedirectResponse
{
    $data = $request->validate([...]);
    $data['user_id'] = $request->user()->id;
    // ... 其余不变
}

public function edit(Product $product): View
{
    $this->authorize('update', $product);
    // ... 其余不变
}

public function update(Request $request, Product $product): RedirectResponse
{
    $this->authorize('update', $product);
    // ... 其余不变
}
```

### 2.5 导航栏：`layouts/app.blade.php`

```javascript
// 旧：只有 is_admin 能看
if (user.is_admin) {
    $('#admin-link').removeClass('hidden').addClass('flex');
}

// 新：登录即可看
if (user) {
    $('#admin-link').removeClass('hidden').addClass('flex');
}
```

### 2.6 公开 API 无影响

`Api\ProductController::index()` 只展示 `status=published` 的产品，与 `user_id` 无关。`Api\ProductController::show()` 同样通过路由模型绑定，无需变动。

---

## 3. 影响范围总表

| 层 | 文件 | 操作 |
|----|------|------|
| DB | `database/migrations/2026_07_09_000001_add_user_id_to_products_table.php` | **新增** |
| Model | `app/Models/Product.php` | 改：加 `user_id` fillable、`user()` 关系 |
| Model | `app/Models/User.php` | 改：加 `products()` 关系 |
| Policy | `app/Policies/ProductPolicy.php` | **新增** |
| Policy | `app/Providers/AuthServiceProvider.php` | 改：注册 Policy |
| Middleware | `app/Http/Middleware/IsAdmin.php` | 改：重命名为 `RequireAuth`，去掉 is_admin 检查 |
| Middleware | `bootstrap/app.php` | 改：中间件别名 `admin` → `auth` 或在 routes 直接改用 `auth` |
| Controller | `app/Http/Controllers/Admin/ProductController.php` | 改：index/store/edit/update |
| Routes | `routes/web.php` | 改：admin 组 middleware `admin` → `auth` |
| View | `resources/views/layouts/app.blade.php` | 改：JS 条件 `is_admin` → 登录 |
| View | `resources/views/admin/products/index.blade.php` | 可能需：普通用户列表隐藏"编辑"操作列（只显示自己的） — 实际上不改，因为 index 已经过滤了，用户只能看到自己的 |
| Test | `tests/Feature/Admin/ProductOwnershipTest.php` | **新增** |
| Config | `config/auth.php` | 无需改（Policy 自动发现） |

### 不改的文件

| 文件 | 原因 |
|------|------|
| `Api\ProductController.php` | 公开 API 只展示 `published`，不受 `user_id` 影响 |
| `Api\AuthController.php` | 注册/登录与所有权无关 |
| `resources/views/shop/catalog.blade.php` | 只展示 `published` + `image_url`，不受影响 |
| `resources/views/admin/products/create.blade.php` | 不加 user_id 字段（Controller 自动设） |
| `resources/views/admin/products/edit.blade.php` | 不加 user_id 字段（不可编辑归属） |
| `app/Services/OrderService.php` | 下单时检查库存，与 `user_id` 无关 |

---

## 4. 测试计划

```php
// tests/Feature/Admin/ProductOwnershipTest.php
class ProductOwnershipTest extends TestCase
{
    use RefreshDatabase;

    // 1. 普通用户创建产品 → 自动带上自己的 user_id
    public function test_store_auto_assigns_user_id(): void

    // 2. 普通用户 index → 只看到自己的产品
    public function test_index_filters_by_owner(): void

    // 3. admin index → 看到全部产品（包括无主产品）
    public function test_admin_sees_all_products(): void

    // 4. 用户A不能编辑用户B的产品 → 403
    public function test_cannot_edit_others_product(): void

    // 5. admin 可以编辑任何人的产品
    public function test_admin_can_edit_any_product(): void

    // 6. 未登录访问 /admin/products → 302 redirect login
    public function test_guest_redirected(): void

    // 7. 删除用户后产品保留（user_id = NULL）
    public function test_product_survives_user_deletion(): void
}
```

---

## 5. 风险与边界

| 风险 | 缓解 |
|------|------|
| 历史产品 `user_id=NULL`，普通用户看不到 | 预期行为：这些是"平台商品"，应由 admin 认领或重新分配 |
| `IsAdmin` 重命名 → 所有引用需同步 | 全局搜索 `use.*IsAdmin` + `middleware('admin')` + `bootstrap/app.php` 别名 |
| 非 admin 创建的产品可能错误 `published` | `store()` 默认 `status=draft` 已在之前实现，无需额外处理 |
| 用户删号后商品变"无主" | `onDelete SET NULL` 保证不丢失数据 |

---

## 6. 替代方案评估

| 方案 | 优点 | 缺点 | 选择 |
|------|------|------|------|
| **A（推荐）** Policy + Controller 层过滤 | 职责清晰、可测试、Laravel 惯例 | 需要新建 Policy 类 | ✅ |
| B：只改 Controller 不用 Policy | 少一个文件 | 授权逻辑散落 Controller、不易单独测试 | ❌ |
| C：Gateway 中间件做所有权检查 | 集中 | 中间件拿不到路由参数（Product model）、需要额外解析 | ❌ |

---

## 7. 回滚计划

若需回滚：
1. 执行 `php artisan migrate:rollback --step=1`（删除 user_id 列）
2. 还原 `Admin\ProductController` 中 user_id 相关过滤
3. 还原 `IsAdmin` 中间件 + `routes/web.php` 路由组
4. 导航栏改回 `is_admin` 条件

---

*待 BMAD Review 审批后进入执行阶段。*
