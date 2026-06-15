# GreenBite i18n 国际化方案

> **创建人**：architect-agent | **版本**：v1.0 | **日期**：2026-06-12
> **关联文档**：architecture.md §2 / api-contract.md §2.1 / 14 份业务文档
> **关联文件**：`docs/i18n/locales/{zh-HK,en,zh-CN}.json`

## 1. 设计目标

支持 3 语言：繁體中文（zh-HK，默认）、English（en）、简体中文（zh-CN）。

- **HTML 原型**（`docs/*.html`）用 jQuery + localStorage 方案，零后端依赖
- **Laravel Blade 视图**（`resources/views/*.blade.php`）用 Laravel 内建 `__()` + `app()->setLocale()`
- **API 错误消息**（`app/Services/*`）从 `resources/lang/{locale}/api.php` 取
- **共享文案库**：`docs/i18n/locales/*.json` 作为 SSOT（Single Source of Truth）
  - 前端原型直接 fetch
  - Laravel 服务端用 `trans()` 自动从同结构文件加载

## 2. 语言切换机制

### 2.1 HTML 原型
- 右上角加语言下拉（🌐 图标 + 当前语言代码）
- 选择后写入 `localStorage.gb_locale`
- 重新渲染当前页所有 `[data-i18n="key"]` 元素
- URL 同步：`?lang=zh-HK`（不强制，避免 SEO 复杂）

### 2.2 Laravel Blade
- Middleware `SetLocale` 从 session / cookie / header 读取
- 默认 `zh-HK`，可通过 `?lang=zh-CN` 切换并写入 cookie
- 路由策略：保持单语言 URL（`/cart`、`/orders`），不引入 locale 前缀
  - 理由：避免 SEO 重复 + 简化路由；切换靠 cookie/header

## 3. JSON 文案 SSOT 结构

```json
{
  "nav": { "catalog": "商品目錄", "subscriptions": "訂閱計劃", "orders": "我的訂單", "cart": "購物車", "signIn": "登入" },
  "common": { "loading": "載入中...", "save": "儲存", "cancel": "取消", "confirm": "確認" },
  "home": { ... },
  "catalog": { ... },
  "cart": { ... },
  "checkout": { ... },
  "auth": { ... },
  "dashboard": { ... },
  "orders": { ... },
  "subscriptions": { ... },
  "survey": { ... }
}
```

完整文件见：
- `docs/i18n/locales/zh-HK.json`（默认）
- `docs/i18n/locales/en.json`
- `docs/i18n/locales/zh-CN.json`

## 4. 同步到 Laravel 后端

将 3 份 JSON 转换为 Laravel PHP array：

```
resources/lang/
  zh-HK.json  (Laravel 9+ 支持 JSON 文件作为翻译)
  en.json
  zh-CN.json
```

Laravel 9+ 原生支持 `__()` + JSON 文件，**无需额外配置**。

## 5. 多 agent 分工

| Agent | 任务 | 产出 |
|---|---|---|
| **Bravo (我)** | i18n 架构设计 + JSON 骨架 | `docs/i18n/PLAN-i18n.md` + 3 JSON 文件 |
| **Charlie** | 翻译 3 语言文案库 | 3 份 JSON 完整版 |
| **Golf** | 改 docs/*.html + resources/views/*.blade.php + 加 SetLocale middleware | 9 HTML + N Blade 视图 |
| **Foxtrot** | 复评：术语一致 / 翻译准确 / 切换器实现 | `docs/bmad/REVIEW-REPORT.md` 追加 i18n 复评 |

---

*详细执行：i18n 复评标准在 i18n 复评章节*
