# Changelog

All notable changes to the RMS Shop package will be documented in this file.

## [1.0.0] - 2025-11-15

### Added
- 🎉 Initial release of RMS Shop package
- ✅ Complete product management system
- ✅ Category tree with unlimited nesting
- ✅ Product variants (combinations) support
- ✅ Shopping cart with Redis caching
- ✅ Checkout flow with address management
- ✅ Order management system
- ✅ Multi-currency support
- ✅ Product images with AVIF optimization
- ✅ Product videos
- ✅ Product attributes & features
- ✅ Product ratings & reviews
- ✅ User addresses management
- ✅ Points/reward system
- ✅ Admin dashboard with statistics
- ✅ Event-driven notifications
- ✅ Stock reservation system
- ✅ Discount system (percentage/fixed)
- ✅ Complete admin panel
- ✅ User-facing catalog
- ✅ Comprehensive documentation

### Features
- Models: 23 models covering all shop entities
- Controllers: 13 admin controllers + 1 panel controller
- Views: Complete Blade templates for admin & panel
- Assets: JavaScript files for dynamic interactions
- Migrations: Database schema for complete shop system
- Events: 2 main events (OrderPlaced, ProductStockDepleted)
- Listeners: 4 listeners for notifications and cache
- Policies: Order and Product policies
- Services: Pricing service for calculations

### Technical Details
- PHP 8.2+ compatible
- Laravel 11 ready
- RMS Core integration
- PSR-4 autoloading
- Service Provider registration
- Route auto-loading
- View auto-loading
- Migration auto-loading

### Package Structure
```
rms/shop/
├── src/
│   ├── Models/              (23 models)
│   ├── Http/Controllers/    (14 controllers)
│   ├── Events/              (2 events)
│   ├── Listeners/           (4 listeners)
│   ├── Policies/            (2 policies)
│   └── Services/            (1 service)
├── config/
│   └── shop.php
├── database/migrations/     (2 migrations)
├── resources/views/         (Admin & Panel views)
├── public/                  (JS/CSS assets)
├── routes/                  (admin.php, panel.php)
└── README.md
```

### Migration from App

This package was extracted from a production application (IRAS) and refactored into a reusable package with:
- Namespace changes: `App\*` → `RMS\Shop\*`
- Service Provider for auto-registration
- Publishable assets (config, migrations, views, public)
- Complete documentation
- Standalone functionality

---

## Future Plans

- [ ] Integration with payment gateways
- [ ] Advanced inventory management
- [ ] Product bundles
- [ ] Coupons & promotions
- [ ] Wishlist functionality
- [ ] Product comparison
- [ ] Advanced search & filters
- [ ] SEO optimization
- [ ] Multi-language support
- [ ] API endpoints
- [ ] Admin API
- [ ] Webhooks
- [ ] Analytics dashboard
- [ ] Export functionality
- [ ] Bulk operations

## [1.0.2] - 2025-11-23
### Added
- ShopInstallCommand for automated installation including migrations, publishing, env updates, queue config, and sidebar menu insertion from stub.

## [1.0.3] - 2025-11-25
### Added
- AVIF directory manager console (`shop:avif-directory`) to add/list/activate/remove custom public or storage paths.
- New AVIF dashboard view with directory controls and per-directory statistics.
- Database support for AVIF directories (`shop_avif_directories` migration + model).

### Changed
- AVIF helper now reads directories from database and includes storage paths such as `uploads/products/library/orig`.
- Image library uploader JS refactored to handle missing routes/modals gracefully.
- Admin routes expose upload & AJAX delete endpoints explicitly for image library.

## [1.0.4] - 2025-11-25
### Fixed
- Re-registered filter/clear-filter helper routes for all shop admin resources (e.g. `admin.shop.products.filter`) so the admin list filters load without errors.

## [1.0.5] - 2025-11-26
### Added
- Order line items now capture the exact gallery image (`image_id` + snapshot metadata) at checkout so later image changes don't affect historical orders.
- API and admin order views read the stored snapshot (including AVIF variants) and expose both `image_url` and `image_avif_url`.

## [1.0.6] - 2025-11-26
### Added
- Brand management (CRUD, sidebar entry, migrations) and brand assignment for every product.
- Panel API now exposes `GET /brands`, accepts `brand_id` filters, and all product/cart/order resources embed brand details.
- Product admin form/tab includes a required brand selector; migrations backfill existing products with a default brand.

