## Shop Panel API Roadmap

### 1. Base Architecture *(Delivered)*
- Versioned endpoints: `/api/v1/panel/...`
- Auth: Laravel Sanctum tokens (per-device) + optional JWT bridge.
- Response contract: `{ "status": "ok|error", "data": {}, "errors": [], "meta": {} }`
- Pagination meta fields: `total`, `per_page`, `current_page`, `last_page`.

### 1.5. Dependencies & Setup *(New)*
- Root project (`rms2`) must require `laravel/sanctum:^4.2` so the shared Laravel app can issue tokens for the package.
- Sanctum migrations are executed automatically inside test suites via `tests/Feature/ShopPanelApiTest.php`; for production run `php artisan migrate --path=vendor/laravel/sanctum/database/migrations`.
- `config/shop/panel_api.php` now exposes `auth_guard` (default `sanctum`) so consuming apps can switch to custom guards if needed.
- Eloquent user model that authenticates Panel API **must** use `Laravel\Sanctum\HasApiTokens`. Example:
  ```php
  use Laravel\Sanctum\HasApiTokens;

  class User extends Authenticatable {
      use HasApiTokens, HasFactory, Notifiable;
  }
  ```
- Login flow is driver-based: set `shop.panel_api.auth.driver` to map into `shop.panel_api.auth.drivers`. Default driver `email` expects `email + password`, but teams can register OTP / mobile / 2FA drivers without touching controllers.

#### Panel Auth Setup Checklist
1. **Composer Dependencies**  
   Shop package already requires `laravel/sanctum:^4.2`. If شما فقط دمو (`shop-test`) را بالا می‌آورید، همین پکیج را در ریشه پروژه خود نصب/آپدیت کنید:
   ```bash
   composer update laravel/sanctum
   ```

2. **Sanctum migrations**  
   جدول `personal_access_tokens` باید در هر پروژه‌ای که Panel API اجرا می‌شود وجود داشته باشد:
   ```bash
   php artisan migrate --path=vendor/laravel/sanctum/database/migrations
   ```

3. **User Model**  
   مدلی که از Panel API احراز هویت می‌گیرد باید trait زیر را اضافه کند:
   ```php
   use Laravel\Sanctum\HasApiTokens;

   class User extends Authenticatable {
       use HasApiTokens;
   }
   ```

4. **Driver Config**  
   در فایل `config/shop/panel_api.php`:
   ```php
   'auth' => [
       'driver' => env('SHOP_PANEL_AUTH_DRIVER', 'email'),
       'device_name' => env('SHOP_PANEL_DEVICE_NAME', 'shop-panel'),
       'drivers' => [
           'email' => RMS\Shop\Support\PanelApi\Auth\EmailPasswordDriver::class,
           // 'otp' => App\PanelAuth\Drivers\OtpDriver::class,
       ],
   ],
   ```
   - با تغییر env می‌توانید بین درایورها جابجا شوید.
   - برای افزودن OTP یا موبایل، یک Driver جدید بسازید که `RMS\Shop\Contracts\PanelApi\AuthDriver` را پیاده‌سازی کند و نام آن را در آرایه‌ی `drivers` ثبت کنید.

5. **.env نمونه**
   ```dotenv
   SHOP_PANEL_AUTH_DRIVER=email
   SHOP_PANEL_DEVICE_NAME=shop-web
   SHOP_PANEL_USER_MODEL=App\Models\User
   ```

6. **تست سلامت**
   ```bash
   php artisan test --filter=ShopPanelApiTest
   php artisan l5-swagger:generate
   ```
   بعد از هر بار تغییر درایور، این تست‌ها را اجرا کنید تا از عملکرد لاگین و مستندات مطمئن شوید.

### 2. Response Pipeline & Hooks
1. Controller builds `$payload`.
2. Dispatch `PanelApiResponseBuilding` event (payload reference).
3. Apply configurable modifiers (registered in `config/shop_api_hooks.php` implementing `ResponseModifier`).
4. Dispatch `PanelApiResponseReady` just before return.
5. Convert to JSON (Resource/JsonResponse).

Projects can register listeners/modifiers without touching package core.

### 3. Service Layer
- **Public Catalog (Delivered)**
  - `GET /products` list + filters (category, status, stock)
  - `GET /products/{id}` detail (basic, pricing, media + combinations)
  - `GET /categories/tree` cached tree for filters/sidemenu
  - Guest cart endpoints backed by cookie key + cache (`GET/POST/PATCH/DELETE /cart`)
- **Panel Auth & Profile (Delivered)**
  - `POST /auth/login` (Sanctum token issuance + cart merge)
  - `GET /auth/me`, `POST /auth/logout`
- **Address Book (Delivered)**
  - `GET /addresses`, `POST /addresses`, `PUT /addresses/{id}`, `DELETE /addresses/{id}`
  - `POST /addresses/{id}/default`
- **Orders (Phase 2 - Delivered)**
  - `GET /orders` paginated history with status labels
  - `GET /orders/{id}` detail (items, shipping info, tracking, notes)
  - `GET /orders/{id}/notes` + `POST /orders/{id}/notes` for two–way messaging (stored in `order_admin_notes`)
  - `POST /orders/{id}/status` limited to `cancelled/received` with config-driven transitions
  - `POST /checkout` moves cookie cart → DB cart → order, fires events
- **Currencies (Delivered)**
  - `GET /currencies` base list (IRT, CNY defaults) with filters
  - `GET /currency-rates` paginated history + filters for base/quote/effective range
  - `POST /currency-rates` (auth required) to publish new sell rates
- **Product Mutations & Media (Delivered)**
  - `POST /products/{id}/stock` bulk update for پایه و ترکیب‌ها
  - `POST /products/{id}/gallery` + delete/sort/set-main/assign/detach endpoints (combo-aware)
  - Chunk uploader: `POST /media/chunks/init`, `POST /media/chunks/upload` → returns temp path for subsequent gallery attach
- **Upcoming**
  - Product media metadata (alt/caption, tagging)
  - Order payments & invoices (JSON endpoints)
  - Swagger + automated feature tests

### 4. Testing & Documentation
- Feature tests per endpoint (success + validation error + unauthorized) implemented in `tests/Feature/ShopPanelApiTest.php`
- JSON schema snapshots (Pest expectations)
- Auto-generated Swagger (L5-Swagger) hosted at `/api/documentation` (run `php artisan l5-swagger:generate`)
- Example Postman collection synced from Swagger JSON

### 5. Rollout Plan
1. Implement response pipeline + config hooks
2. Ship read-only endpoints (products list/detail, categories)
3. Add orders + mutations (status change)
4. Add uploads & chunk API
5. Enable currency/rate endpoints
6. Publish docs + SDK snippets

Each phase -> shop-test verification → core release.

> **Note:** Configuration file `config/shop/panel_api.php` controls prefix, middleware, auth guard, and custom response modifiers so projects can opt-in without editing the package.

