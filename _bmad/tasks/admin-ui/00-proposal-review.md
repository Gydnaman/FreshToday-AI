# 方案 — 导航栏 admin 按钮 + 商品编辑 + 布局调整

> **日期**：2026-07-04
> **需求**：①导航栏加 admin 管理入口 ②商品编辑/图片修改/上下架 ③导航栏布局调整

## 需求 1：导航栏加 admin 管理入口

**现状**：admin 管理只能手动输入 `/admin/products` URL，导航栏无入口。

**方案**：在 `layouts/app.blade.php` 的 `#user-area` 里加 admin 管理链接。由于 auth-area 是前端 JS 控制（renderAuthArea 调 /api/me 判断登录态），admin 按钮也需 JS 动态显示——renderAuthArea 拿到 `user.is_admin` 时显示 admin 链接。

## 需求 2：商品编辑功能

**现状**：Admin ProductController 只有 index/create/store，没有 edit/update。商品列表只显示不可改。

**方案**：
- Controller 加 `edit(Product)` + `update(Request, Product)` 方法
- 路由加 `GET /admin/products/{product}/edit` + `PUT /admin/products/{product}`
- 新建 `edit.blade.php`（复用 create 表单结构，预填数据）
- 支持图片更换（新图替换旧图）
- 支持状态切换（draft ↔ published ↔ archived）
- index 列表每行加"编辑"按钮

## 需求 3：导航栏布局调整

**现状**：Logo 左 | nav(catalog/subscriptions/orders) 中 | cart+lang+auth 右

**目标**：Logo 左 | (空) | nav+cart+lang+auth 右——把 nav 移到右侧购物车左边。

**方案**：调整 `layouts/app.blade.php` header 的 flex 布局，把 nav 从 logo 旁边移到右侧 div 里。

---

# BMad Review

### F-1 [Important] 需求 1 的 admin 按钮依赖 /api/me 返回 is_admin 字段

**问题**：renderAuthArea 调 /api/me，但 AuthController::me 返回 `Auth::user()->load(['userPreferences', 'notificationPreference'])`——需确认 User model 的 toArray 是否包含 is_admin。

**验证**：User model 的 $fillable 或 $casts 需含 is_admin。需检查。

### F-2 [Medium] 需求 2 的图片更新需处理旧图清理

**问题**：编辑时上传新图，旧图应删除避免 storage 堆积。

### F-3 [Low] 需求 3 的 nav 移到右侧后，移动端需确认

**问题**：当前 nav 有 `hidden md:flex`（移动端隐藏）。移到右侧后保持同样行为。

## 判定：Pass（F-1 需执行时验证，F-2 执行时处理）
