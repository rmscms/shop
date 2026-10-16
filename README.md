# RMS Shop Package

🛍️ **Complete Mini Shop System for Laravel with RMS Core**

A professional e-commerce solution built on top of RMS Core, featuring a complete shop system with products, categories, cart, checkout, orders, and advanced features.

## Features

### 🎯 Core Features
- ✅ Multi-currency support (CNY, IRR, etc.)
- ✅ Product management with variants (combinations)
- ✅ Category tree with unlimited nesting
- ✅ Brand management with product assignment
- ✅ Shopping cart with Redis-based reservation
- ✅ Complete checkout flow
- ✅ Order management with status tracking
- ✅ Product images & videos (AVIF optimization)
- ✅ Product attributes & features
- ✅ Product ratings & reviews
- ✅ User addresses management
- ✅ Points/reward system
- ✅ Admin dashboard & statistics

### 📦 Product Features
- Product variants (size, color, etc.)
- Stock management
- Pricing (base price, sale price, CNY pricing)
- Discount system (percentage or fixed amount)
- Image gallery with multiple sizes
- AVIF image optimization with background jobs
- Video support
- Custom attributes per product
- Product features by category
- Purchase statistics

### 🛒 Shopping Experience
- Fast cart with Redis caching
- Soft stock reservation (prevents overselling)
- Multi-step checkout
- User address book
- Order history
- Real-time notifications

### 👨‍💼 Admin Panel
- Complete CRUD for products
- Category tree management
- Brand CRUD + sidebar integration
- Order management with status updates
- Currency & exchange rates
- Image size configuration
- Sales statistics
- Cart monitoring

## Installation

To install the RMS Shop package:

1. Require the package via Composer:
   ```
   composer require rms/shop
   ```

2. Run the installer:
   ```
   php artisan shop:install
   ```

This will publish resources, run migrations, update .env, configure queues, and add shop menus to the admin sidebar.

### Manage AVIF Directories

You can register additional folders (public or storage paths) for AVIF stats and queue jobs:

```bash
# List directories
php artisan shop:avif-directory list

# Add public path (relative to public/)
php artisan shop:avif-directory add uploads/blog/images --type=public

# Add storage path (relative to storage/app/public)
php artisan shop:avif-directory add uploads/blog/originals --type=storage

# Activate/deactivate
php artisan shop:avif-directory deactivate uploads/blog/images
php artisan shop:avif-directory activate uploads/blog/images

# Remove (needs --force to skip confirmation)
php artisan shop:avif-directory remove uploads/blog/originals --type=storage --force
```

These directories appear in the AVIF dashboard and can be extended from other packages such as `rms/blog`.

### 1. Install via Composer

```bash
composer require rmscms/shop
```

### 2. Publish Assets

```bash
# Publish config
php artisan vendor:publish --tag=shop-config

# Publish migrations
php artisan vendor:publish --tag=shop-migrations

# Publish views (optional - for customization)
php artisan vendor:publish --tag=shop-views

# Publish assets (Admin JS/CSS bundle)
php artisan vendor:publish --tag=shop-assets

# Publish plugins for Admin panel (CKEditor, FancyTree, Prism)
php artisan vendor:publish --tag=shop-plugins-admin
```

### 3. Run Migrations

```bash
php artisan migrate
```

### 4. Configure Environment Variables

Add these to your `.env` file:

```bash
# Queue Configuration
SHOP_QUEUE_AVIF=shop-avif
SHOP_QUEUE_MEDIA=shop-media
SHOP_QUEUE_DEFAULT=default

# FFmpeg paths (optional, defaults to 'ffmpeg' if in PATH)
FFMPEG_PATH=ffmpeg
FFPROBE_PATH=ffprobe

# For Windows with custom path:
# FFMPEG_PATH=C:\ffmpeg\bin\ffmpeg.exe
# FFPROBE_PATH=C:\ffmpeg\bin\ffprobe.exe
```

### 5. Run Queue Workers

For optimal performance, run separate workers for each queue:

```bash
# Terminal 1: AVIF conversion (high priority, for image optimization)
php artisan queue:work --queue=shop-avif --tries=3 --timeout=300

# Terminal 2: Video processing (CPU intensive)
php artisan queue:work --queue=shop-media --tries=2 --timeout=600

# Terminal 3: Other tasks
php artisan queue:work --queue=default --tries=3 --timeout=60
```

### 6. Configure

Edit `config/shop.php`:

```php
return [
    'default_status' => 'pending',
    'active_order_statuses' => 'pending,preparing,paid',
    'cart_reservation_ttl' => 1800, // 30 minutes
    'image_sizes' => [ // Image optimization sizes
        'thumb' => [200, 200],
        'medium' => [600, 600],
        'large' => [1200, 1200],
    ],
    'avif_quality' => 75, // AVIF compression quality (0-100)
    // ... more options
];
```

## Usage

### Admin Routes

All admin routes are prefixed with `/admin/shop`:

- `/admin/shop` - Dashboard
- `/admin/shop/products` - Products management
- `/admin/shop/categories` - Categories management
- `/admin/shop/orders` - Orders management
- `/admin/shop/settings` - Shop settings

### Panel API (User-Facing)

The customer experience is delivered via the Panel API (`routes/panel_api.php`) so you can build SPA/SSR front-ends (like `shop-test`) without relying on Blade.

Default prefix: `/api/v1/panel`

- `POST /auth/login` – Obtain Sanctum token
- `GET /products` – Catalog listing
- `GET /brands` – Active brand list (for filters & selectors)
- `GET /cart` / `POST /cart/items` – Cart operations
- `POST /checkout` – Start checkout & payment
- `GET /orders` / `GET /orders/{id}` – Order history & detail
- `POST /orders/{id}/notes` – Customer notes

### Models

```php
use RMS\Shop\Models\Product;
use RMS\Shop\Models\Category;
use RMS\Shop\Models\Order;
use RMS\Shop\Models\Cart;

// Get active products
$products = Product::where('active', true)->get();

// Get category tree
$categories = Category::tree()->get();

// Get user's cart
$cart = Cart::where('user_id', auth()->id())->get();
```

### Services

```php
use RMS\Shop\Services\PricingService;

// Apply discount
$discountedPrice = PricingService::applyDiscount(100, 'percent', 10); // 90.00

// Get discount meta
$meta = PricingService::discountMeta('percent', 10); 
// ['kind' => 'percent', 'value' => 10.0]
```

## AI Product Assistant

Reads each product's name, text and photos and suggests Persian copy (name, short and full description, SEO title/meta, keywords, image alt), a category and brand from your own lists, and features. Chinese or English source text is translated. Nothing changes on a product until an admin applies the suggestion, field by field.

1. Run migrations (`shop_ai_suggestions` table).
2. Set the driver and key in `.env` (see `ENV_EXAMPLE.md`): `SHOP_AI_DRIVER=anthropic` with `SHOP_AI_ANTHROPIC_KEY`, or `SHOP_AI_DRIVER=openai` with `SHOP_AI_OPENAI_KEY` (any OpenAI-compatible endpoint via `SHOP_AI_OPENAI_URL`), or `SHOP_AI_DRIVER=gemini` with `SHOP_AI_GEMINI_KEY`.
   - Gemini can check specs with Google Search (`SHOP_AI_GEMINI_SEARCH=true`, the default). The pages it used are listed under **منابع** on the review page.
   - Google blocks requests from Iranian IPs: run the worker on a server abroad or point `SHOP_AI_GEMINI_URL` at a proxy.
   - Free tiers are rate limited: set `SHOP_AI_DISPATCH_SPACING` (seconds between queued jobs) for bulk runs.
3. Start a worker: `php artisan queue:work --queue=shop-ai`
4. Send products from **Admin → Shop → AI assistant** (`/admin/shop/ai`) or the CLI:

```bash
php artisan shop:ai-enrich --product=12 --sync   # try one product now
php artisan shop:ai-enrich                       # queue every product not processed yet
php artisan shop:ai-enrich --missing --limit=50  # only products without a description
```

5. Review on the same page: tick the fields to keep and press apply, or reject. Existing features are kept; only new ones are added. SEO fields are shown for copying because the products table has no SEO columns.

Existing installs: add the menu item from `resources/stubs/shop-menu.blade.stub` (route `admin.shop.ai.index`) to your sidebar.

### Catalog plan (first-time setup from a product list)

For a new store with a raw list of products, let the AI plan the whole catalog at once before any product exists:

1. Import the list (Excel or CSV; first column name, second column spec/model) from **AI assistant → طرح کاتالوگ** (`/admin/shop/ai/catalog`) or the CLI. Rows go into a staging table only; re-importing the same file updates them.
2. Build the plan: one AI call reads every row and proposes a category tree (up to 3 levels), brands, and product groups. Each group is either one product with variants (same item in different sizes, shapes or models) or separate products. Rows that look like typos or spreadsheet autofill mistakes are flagged.
3. Review: untick groups you don't want yet, switch a group between variants and separate products, then apply. Categories and brands are created as needed, and products are created **inactive and without a price**, with one combination per variant.
4. Run `shop:ai-enrich --missing` to write their copy and features, set yuan prices, and activate them.

```bash
php artisan shop:catalog-import products.xlsx --batch=tools
php artisan shop:catalog-plan tools --sync
# or load a plan written by hand (same JSON shape as the AI answer; items may use "row" instead of "id")
php artisan shop:catalog-plan tools --from-json=storage/app/plan.json
```

### Photo suggestions

AI models can't fetch real product photos, so photos are suggested as links and an admin picks them at **AI assistant → عکس‌های پیشنهادی** (`/admin/shop/ai/images`). Ticked photos are downloaded, added to the image library and assigned to the product (the first becomes the main image if the product has none); the rest are dismissed. Suggestions can target staged catalog rows before their products exist; applying the catalog plan moves them to the new products.

```bash
# [{"row": 68, "images": [{"url": "https://…/photo.jpg", "source_url": "https://…/page", "title": "…"}]}, {"product_id": 12, "images": […]}]
php artisan shop:image-candidates photos.json --batch=tools
```

Links are checked to return a JPEG, PNG or WebP before they are stored. Photos from other shops may be copyrighted; the source page is shown under each one.

The plan call reads the whole list, so it has its own limits: `SHOP_AI_CATALOG_MAX_TOKENS` (default 16000) and `SHOP_AI_CATALOG_TIMEOUT` (default 300 s). Excel import uses `phpoffice/phpspreadsheet` when the app has it; otherwise save the sheet as CSV.

## Database Schema

### Main Tables
- `products` - Products
- `categories` - Category tree
- `product_combinations` - Product variants
- `product_images` - Product images
- `product_videos` - Product videos
- `product_attributes` - Custom attributes
- `product_features` - Features by category
- `carts` - Shopping carts
- `orders` - Orders
- `order_items` - Order line items
- `user_addresses` - User addresses
- `currencies` - Supported currencies
- `currency_rates` - Exchange rates

## Events & Listeners

### Events
- `OrderPlacedEvent` - Fired when order is placed
- `ProductStockDepleted` - Fired when product runs out of stock

### Listeners
- `SendOrderPlacedNotifications` - Send notifications on new order
- `SendOrderStatusUpdatedNotifications` - Send notifications on status change
- `InvalidateCartForDepletedStock` - Clear cart items when stock depleted
- `UpdateProductAvailabilityCache` - Update product cache

## Views Customization

Publish views to customize:

```bash
php artisan vendor:publish --tag=shop-views
```

Views will be copied to `resources/views/vendor/shop/`

## Configuration

### Order Statuses

```php
// config/shop.php
'default_status' => 'pending',
'active_statuses' => ['pending', 'preparing', 'paid'],
'refund_statuses' => ['returned', 'rejected'],
```

### Categories

```php
'categories' => [
    'default_name' => 'Uncategorized',
    'default_slug' => 'uncategorized',
    'cache_ttl' => 300,
],
```

## Requirements

- PHP ^8.2
- Laravel ^11.0
- RMS Core ^1.0
- Redis (for cart caching)

## Swagger API Documentation

پکیج Shop از Swagger/OpenAPI برای مستندسازی API استفاده می‌کند. تمام endpoint های Panel API با annotation های کامل Swagger مستندسازی شده‌اند.

### نصب و راه‌اندازی Swagger

#### 1. نصب پکیج l5-swagger

```bash
composer require darkaonline/l5-swagger
```

#### 2. Publish کردن فایل‌های Swagger

```bash
php artisan vendor:publish --provider "L5Swagger\L5SwaggerServiceProvider"
```

این دستور فایل‌های config و views مربوط به Swagger را publish می‌کند.

#### 3. تنظیم routes/api.php

در Laravel 11، باید فایل `routes/api.php` را ایجاد کنید (اگر وجود ندارد):

```php
<?php

use Illuminate\Support\Facades\Route;

// Route های shop از طریق ShopServiceProvider لود می‌شوند
// این فایل برای route های API خودتان است (در صورت نیاز)
```

#### 4. تنظیم bootstrap/app.php

در فایل `bootstrap/app.php` باید API routes را فعال کنید:

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',  // این خط را اضافه کنید
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    // ...
```

**نکته مهم:** حتی اگر `routes/api.php` خالی باشد، این تنظیم لازم است تا Laravel API routing را فعال کند.

#### 5. تنظیم config/l5-swagger.php

در فایل `config/l5-swagger.php`، باید مسیر پکیج shop را به بخش `annotations` اضافه کنید:

```php
'annotations' => [
    base_path('app'),
    base_path('vendor/rmscms/shop/src'),  // این خط را اضافه کنید
],
```

**اگر از path repository استفاده می‌کنید** (مثل `../../rms2-packages/packages/rms/shop`)، باید مسیر کامل را اضافه کنید:

```php
'annotations' => [
    base_path('app'),
    'C:\laragon\www\rms2-packages\packages\rms\shop\src',  // مسیر کامل پکیج
],
```

#### 6. Publish کردن config پکیج shop

```bash
php artisan vendor:publish --tag=shop-config
```

این دستور فایل `config/shop/swagger.php` را publish می‌کند.

#### 7. Generate کردن Swagger Documentation

```bash
php artisan l5-swagger:generate
```

این دستور تمام annotation های Swagger را از پکیج shop و پروژه شما scan می‌کند و فایل JSON documentation را ایجاد می‌کند.

#### 8. دسترسی به Swagger UI

بعد از generate موفق، می‌توانید به Swagger UI دسترسی داشته باشید:

```
http://127.0.0.1:8000/api/documentation
```

### تنظیمات Swagger در پکیج Shop

فایل `config/shop/swagger.php` شامل تنظیمات زیر است:

```php
return [
    'enabled' => env('SHOP_SWAGGER_ENABLED', false),
    'route' => env('SHOP_SWAGGER_ROUTE', 'api/documentation'),
    'annotations_path' => base_path('vendor/rmscms/shop/src'),
    'info' => [
        'title' => 'RMS Shop Panel API',
        'version' => '1.0.0',
        'description' => 'REST API used by the RMS customer panel application.',
    ],
    'server' => [
        'url' => '/api/v1/panel',
        'description' => 'Panel API base URL',
    ],
];
```

### API Endpoints مستندسازی شده

تمام endpoint های Panel API با Swagger مستندسازی شده‌اند:

#### Authentication
- `POST /api/v1/panel/auth/login` - ورود کاربر
- `POST /api/v1/panel/auth/register` - ثبت‌نام کاربر
- `GET /api/v1/panel/auth/me` - اطلاعات کاربر فعلی
- `POST /api/v1/panel/auth/logout` - خروج کاربر

#### Catalog
- `GET /api/v1/panel/products` - لیست محصولات (با فیلتر و pagination)
- `GET /api/v1/panel/products/{id}` - جزئیات محصول
- `GET /api/v1/panel/brands` - لیست برندها
- `GET /api/v1/panel/categories/tree` - درخت دسته‌بندی‌ها

#### Cart
- `GET /api/v1/panel/cart` - مشاهده سبد خرید
- `POST /api/v1/panel/cart/items` - افزودن محصول به سبد
- `PATCH /api/v1/panel/cart/items/{lineId}` - به‌روزرسانی تعداد
- `DELETE /api/v1/panel/cart/items/{lineId}` - حذف از سبد

#### Addresses
- `GET /api/v1/panel/addresses` - لیست آدرس‌ها
- `POST /api/v1/panel/addresses` - افزودن آدرس
- `PUT /api/v1/panel/addresses/{id}` - به‌روزرسانی آدرس
- `DELETE /api/v1/panel/addresses/{id}` - حذف آدرس
- `POST /api/v1/panel/addresses/{id}/default` - تنظیم آدرس پیش‌فرض

#### Orders
- `GET /api/v1/panel/orders` - لیست سفارش‌ها
- `GET /api/v1/panel/orders/{id}` - جزئیات سفارش
- `GET /api/v1/panel/orders/{id}/notes` - یادداشت‌های سفارش
- `POST /api/v1/panel/orders/{id}/notes` - افزودن یادداشت
- `POST /api/v1/panel/orders/{id}/status` - تغییر وضعیت سفارش

#### Checkout
- `POST /api/v1/panel/checkout` - شروع فرآیند پرداخت

#### Payments
- `GET /api/v1/panel/payment/drivers` - لیست درگاه‌های پرداخت فعال

#### Currencies
- `GET /api/v1/panel/currencies` - لیست ارزها
- `GET /api/v1/panel/currency-rates` - نرخ‌های ارز
- `POST /api/v1/panel/currency-rates` - افزودن نرخ ارز

### رفع مشکلات رایج

#### خطا: `Required @OA\PathItem() not found`
این خطا زمانی رخ می‌دهد که `@OA\Info()` در چند جا تعریف شده باشد. پکیج shop خودش `PanelApiDoc.php` دارد که `@OA\Info()` را تعریف می‌کند، پس نباید در controller های خودتان دوباره تعریف کنید.

#### خطا: `@OA\Get() requires at least one @OA\Response()`
این خطا زمانی رخ می‌دهد که annotation یک endpoint فاقد `@OA\Response` باشد. تمام endpoint های پکیج shop این مشکل را ندارند، اما اگر endpoint جدیدی اضافه می‌کنید، حتماً `@OA\Response` را اضافه کنید.

#### خطا: `The "..." directory does not exist`
این خطا زمانی رخ می‌دهد که مسیر annotations در `config/l5-swagger.php` اشتباه باشد. مطمئن شوید که مسیر درست است:
- اگر از vendor استفاده می‌کنید: `base_path('vendor/rmscms/shop/src')`
- اگر از path repository استفاده می‌کنید: مسیر کامل پکیج

### به‌روزرسانی Documentation

هر بار که تغییراتی در API ایجاد می‌کنید، باید دوباره generate کنید:

```bash
php artisan l5-swagger:generate
```

برای development، می‌توانید در `.env` تنظیم کنید:

```env
L5_SWAGGER_GENERATE_ALWAYS=true
```

این باعث می‌شود که documentation در هر request به‌روزرسانی شود (فقط برای development).

### استفاده از Swagger در Frontend

Swagger UI امکان تست مستقیم API را فراهم می‌کند:

1. باز کردن `http://127.0.0.1:8000/api/documentation`
2. کلیک روی endpoint مورد نظر
3. کلیک روی "Try it out"
4. وارد کردن پارامترها
5. کلیک روی "Execute"

برای استفاده از authentication:
1. ابتدا از endpoint `/auth/login` token دریافت کنید
2. در بالای صفحه Swagger، روی دکمه "Authorize" کلیک کنید
3. token را در فرمت `Bearer {token}` وارد کنید
4. حالا می‌توانید endpoint های protected را تست کنید

## Plugins

This package includes the following JavaScript plugins:

### Admin Panel Plugins
- **CKEditor** - WYSIWYG text editor for product descriptions
- **FancyTree** - Interactive tree widget for category hierarchy
- **Prism** - Code highlighter for documentation blocks

All plugins are automatically published when you run:
```bash
php artisan vendor:publish --tag=shop-plugins-admin
```

## Author

**Sharif Ahrari**  
📧 msharif.ahrari@gmail.com

## License

MIT License

