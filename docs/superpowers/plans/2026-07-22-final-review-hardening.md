# Final Review Hardening Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make daily-menu reads DB-authoritative, preserve the concurrent winner, make regeneration counting monotonic, remove sensitive AI log data, escape the product title, and localize home-page guard failures.

**Architecture:** Keep `DailyMenu` as the source of truth and cache only its primary-key identity. Use the cache store's atomic `add` plus `increment` operations for regeneration counters. Translate service guard codes/reasons at the web boundary, and keep AI diagnostics limited to fixed reasons and provider/model/status metadata.

**Tech Stack:** Laravel 11, Eloquent, Blade, Laravel Cache and Log facades, PHPUnit, JSON locale dictionaries, Vite.

## Global Constraints

- Work only in `D:\FreshToday-AI\.worktrees\product-detail-page`.
- Do not read, print, or modify `.env`, secrets, credentials, or real API state.
- Apply strict RED/GREEN TDD to every production-code change.
- Preserve authentication, authorization, data integrity, user privacy, and the `en` / `zh` / `zhhk` i18n structure.
- Prefer the smallest complete graduation-project vertical slice and avoid unrelated refactors.

---

### Task 1: DB-authoritative cache reads and insert-race winner

**Files:**
- Modify: `tests/Unit/Services/DailyMenuLifecycleTest.php`
- Modify: `tests/Unit/Services/AiMenuServiceTest.php`
- Modify: `tests/Feature/Api/MenuRegenerateTest.php`
- Modify: `app/Services/AiMenuService.php`

**Interfaces:**
- Consumes: `generateDailyMenuForUser(User, ?array, bool): DailyMenu`.
- Produces: menu-cache values containing a persisted `DailyMenu` id, and an insert-race path that returns the already-persisted winner unchanged.

- [ ] **Step 1: Write failing cache and race regressions**

Add a normal-cache-hit test that snapshots `id`, `menu_content`, `menu_json`, `source`, `tokens_used`, `created_at`, and `updated_at`, places unpersisted content in cache, advances the clock, calls the service, and asserts both the return value and fresh database row still match the snapshot. Change the existing unique-race test to call the ordinary non-force path and assert the inserted winner's full data remains unchanged and its id is cached.

- [ ] **Step 2: Run tests to verify RED**

Run: `php artisan test tests/Unit/Services/DailyMenuLifecycleTest.php tests/Unit/Services/AiMenuServiceTest.php tests/Feature/Api/MenuRegenerateTest.php`

Expected: cache test reports persisted fields changed; race test reports winner content replaced by generated loser content.

- [ ] **Step 3: Implement DB-authoritative lookup**

On a non-force cache hit, resolve the cached id under the current user/date and return that row. For legacy/non-id/stale cache values, query the same user/date row and refresh the cache with its id. Cache only `$menu->getKey()` after successful persistence. In the supported unique-insert exception branch, return the queried winner without calling `fill()` or `save()`.

- [ ] **Step 4: Run focused tests to verify GREEN**

Run the same three files and expect all tests to pass.

### Task 2: Monotonic regeneration rate limit

**Files:**
- Modify: `tests/Feature/Api/MenuRegenerateTest.php`
- Modify: `app/Services/AiMenuService.php`

**Interfaces:**
- Consumes: Laravel cache store `add`, `increment`, and `forget`.
- Produces: `regenerate()` that atomically initializes a TTL-bearing zero counter and then atomically increments it without a stale write-back.

- [ ] **Step 1: Write failing cache-operation regression**

Mock the cache facade for a rate-limited attempt. Require `add($regenKey, 0, 86400)` before `increment($regenKey)`, return `4`, and forbid `put`; assert `GUARD-AI-RATE` is thrown.

- [ ] **Step 2: Run test to verify RED**

Run: `php artisan test tests/Feature/Api/MenuRegenerateTest.php --filter=initializes`

Expected: the current implementation fails because it never calls `add` and calls `put` after `increment`.

- [ ] **Step 3: Implement atomic initialization and increment**

Replace the stale write-back sequence with:

```php
Cache::add($regenKey, 0, self::CACHE_TTL_SECONDS);
$count = (int) Cache::increment($regenKey);
```

- [ ] **Step 4: Run focused tests to verify GREEN**

Run the feature test file and expect all tests to pass.

### Task 3: Privacy-safe AI logging

**Files:**
- Create: `tests/Unit/Services/Ai/AiLoggingPrivacyTest.php`
- Modify: `app/Services/AiMenuService.php`
- Modify: `app/Services/Ai/Providers/GeminiProvider.php`
- Modify: `app/Services/Ai/Providers/OpenAiProvider.php`
- Modify: `app/Services/Ai/Providers/DeepseekProvider.php`
- Modify: `app/Services/Ai/Providers/FailoverProvider.php`

**Interfaces:**
- Consumes: current AI provider responses and exceptions.
- Produces: logs containing fixed event/reason strings plus safe provider, model, and HTTP status metadata only.

- [ ] **Step 1: Write failing static privacy regression**

Scan the five AI implementation files and assert they do not contain output preview keys, response-body preview logging, or raw exception-message logging expressions such as `content_preview`, `'text' => substr`, `'body' => substr`, and log contexts populated by `getMessage()`.

- [ ] **Step 2: Run test to verify RED**

Run: `php artisan test tests/Unit/Services/Ai/AiLoggingPrivacyTest.php`

Expected: failures identify the current content, response-body, and exception-message log sites.

- [ ] **Step 3: Replace sensitive context with safe metadata**

Use fixed `reason` values such as `invalid_output`, `invalid_json`, `provider_http_error`, and `provider_exception`; retain only provider/model/status fields. Do not log prompts, preferences, response bodies, generated text, or exception messages.

- [ ] **Step 4: Run privacy and provider tests to verify GREEN**

Run the privacy test together with `tests/Unit/Services/Ai/Providers` and the AI service tests; expect all to pass.

### Task 4: Escaped title and localized home guard failures

**Files:**
- Modify: `tests/Feature/Web/ProductDetailTest.php`
- Modify: `tests/Feature/Web/HomePageTest.php`
- Modify: `resources/views/shop/product-detail.blade.php`
- Modify: `app/Http/Controllers/Web/HomeController.php`
- Modify: `resources/lang/en.json`
- Modify: `resources/lang/zh.json`
- Modify: `resources/lang/zhhk.json`

**Interfaces:**
- Consumes: malicious stored product names and `GuardFailedException` code/context.
- Produces: escaped `<title>` output and web-localized `homeMenu.*` messages for English, Simplified Chinese, and Traditional Chinese.

- [ ] **Step 1: Write failing XSS and localization regressions**

Create a malicious product name containing `</title><script>` and assert the raw value is absent while the escaped value is present. Replace the old test that expected a service message with locale-driven guard tests for `en`, `zh`, and `zh-TW`; assert the correct `homeMenu.generationFailed`, `homeMenu.noProducts`, or `homeMenu.rateLimited` copy is rendered and the service-layer Simplified-Chinese sentinel is absent from English and Traditional responses.

- [ ] **Step 2: Run feature tests to verify RED**

Run the two feature files and expect title/raw-message assertions to fail.

- [ ] **Step 3: Escape and localize at web boundary**

Render the title section with escaped Blade braces. In `HomeController`, map `NO_AVAILABLE_PRODUCTS` to the no-products state, `GuardCode::AiRate` to `homeMenu.rateLimited`, and all other generation guards to `homeMenu.generationFailed`; never assign `GuardFailedException::$userMessage` to the view. Add `homeMenu.rateLimited` to all three JSON dictionaries.

- [ ] **Step 4: Run feature tests to verify GREEN**

Run the two feature files and expect all to pass.

### Task 5: Small test-covered review minors

**Files:**
- Modify: `tests/Unit/Services/Ai/MenuOutputValidatorTest.php`
- Modify: `tests/Unit/Services/DailyMenuLifecycleTest.php`
- Modify: `tests/Feature/Api/MenuRegenerateTest.php`
- Modify: `app/Services/Ai/MenuOutputValidator.php`
- Modify: `app/Services/AiMenuService.php`
- Modify: `app/Http/Controllers/Api/MenuController.php`

**Interfaces:**
- Produces: exact one-of-each meal validation, year-sensitive full-date candidate rotation, and published/in-stock API product links.

- [ ] **Step 1: Write three failing regressions**

Reject three-meal JSON with duplicate meal types; compare product order on the same month/day in consecutive years; call `/api/menu/today` with available, draft, and sold-out ingredients and assert only the available product receives a link.

- [ ] **Step 2: Run tests to verify RED**

Run the three test files with focused filters and expect one behavior failure per minor.

- [ ] **Step 3: Implement minimal fixes**

Compare sorted actual meal types to sorted `VALID_MEAL_TYPES`, derive the rotation seed from `Ymd`, and add `Product::STATUS_PUBLISHED` to the API link query.

- [ ] **Step 4: Run focused tests to verify GREEN**

Run the three test files and expect all to pass.

### Task 6: Final verification, report, and commit

**Files:**
- Create: `.superpowers/sdd/task-8-report.md`

- [ ] **Step 1: Run focused and full verification**

Run all affected test files, the complete `php artisan test` suite, `npm.cmd run build`, JSON parsing for all three locale files, a repository secret-pattern scan that excludes `.env`, and `git diff --check`.

- [ ] **Step 2: Review the final diff**

Use `git status --short`, `git diff --stat`, and `git diff` to confirm no unrelated change, credential, real API call, or `.env` access was introduced.

- [ ] **Step 3: Write the report**

Record each RED failure, GREEN verification, final test/build/JSON/scan result, changed files, and any deferred optional work in `.superpowers/sdd/task-8-report.md`.

- [ ] **Step 4: Commit**

Stage only the focused hardening files and commit with `fix: harden daily menu final review findings`.
