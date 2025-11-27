# Release v1.0.7 - Controller Refactoring

**Release Date:** November 27, 2025  
**Package:** rmscms/shop  
**Type:** Minor Update (Non-Breaking for End Users)

## 🎯 Overview

This release introduces a significant architectural improvement by creating a base controller class for all shop admin controllers, eliminating dependency on application-level code and improving maintainability.

## ✨ What's New

### Base Controller Architecture

All shop admin controllers now extend a new `ShopAdminController` base class instead of directly depending on `App\Http\Controllers\Admin\AdminController`. This provides:

- **Centralized Control:** Single point of customization for all shop controllers
- **Better Isolation:** No tight coupling to application code
- **Easier Maintenance:** Override base behavior in one place
- **Laravel 12 Compatible:** Fully tested with latest Laravel version

## 🔧 Technical Changes

### New File
- `src/Http/Controllers/Admin/ShopAdminController.php` - Base controller for all shop admin controllers

### Updated Controllers (14 files)
All controllers now use:
```php
use RMS\Shop\Http\Controllers\Admin\ShopAdminController;

class YourController extends ShopAdminController
```

Instead of:
```php
use App\Http\Controllers\Admin\AdminController;

class YourController extends AdminController
```

**Updated Controllers:**
- CategoriesController
- ProductsController
- BrandsController
- AvifController
- OrdersController
- ShopDashboardController
- CartsController
- ImageLibraryController
- VideoLibraryController
- ProductPurchaseStatsController
- ProductFeatureCategoriesController
- CurrencyRatesController
- UserPointsController
- ShopSettingsController
- CurrenciesController

## 🐛 Bug Fixes

- **Fixed:** Circular dependency preventing `shop:install` on fresh Laravel installations
- **Fixed:** Package can now be installed without requiring application AdminController to exist first

## 📦 Installation

### New Installations

```bash
composer require rmscms/shop
php artisan shop:install
```

### Upgrading from 1.0.6

```bash
composer update rmscms/shop
# No additional steps required - fully backward compatible
```

## 🔄 Migration Guide

### For Package Users
✅ **No action required!** This change is internal to the package and doesn't affect your code.

### For Package Developers/Contributors
If you were extending shop controllers:
- Old: Extend `App\Http\Controllers\Admin\AdminController`
- New: Extend `RMS\Shop\Http\Controllers\Admin\ShopAdminController`

## 💡 Benefits

1. **Modularity:** Shop package is now more self-contained
2. **Flexibility:** Easier to customize controller behavior
3. **Testing:** Better isolation for unit testing
4. **Future-Proof:** Ready for Laravel framework updates

## 📋 Requirements

- PHP 8.2+
- Laravel 11.0 or 12.0
- RMS Core ^1.3

## 🔗 Links

- [Full Changelog](CHANGELOG.md)
- [Documentation](README.md)
- [GitHub Repository](https://github.com/rmscms/shop)

## 👏 Credits

Special thanks to the RMS team for continuous improvements!

---

**Need Help?** Open an issue on GitHub or contact support.

