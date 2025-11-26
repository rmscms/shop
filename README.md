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

