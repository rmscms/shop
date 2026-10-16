<?php

namespace RMS\Shop;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class ShopServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Merge config
        $this->mergeConfigFrom(__DIR__.'/../config/shop.php', 'shop');
        $this->mergeConfigFrom(__DIR__.'/../config/panel_api.php', 'shop.panel_api');
        $this->mergeConfigFrom(__DIR__.'/../config/admin_api.php', 'shop.admin_api');
        $this->mergeConfigFrom(__DIR__.'/../config/swagger.php', 'shop.swagger');
        $this->mergeConfigFrom(__DIR__.'/../config/ai.php', 'shop.ai');
        
        // Register services
        $this->app->singleton(\RMS\Shop\Services\CartService::class);
        $this->app->singleton(\RMS\Shop\Services\OrderService::class);
        $this->app->singleton(\RMS\Shop\Services\PricingService::class);
        $this->app->singleton(\RMS\Shop\Services\CategoryTreeService::class);
        $this->app->singleton(\RMS\Shop\Services\ProductPurchaseStatsService::class);
        $this->app->singleton(\RMS\Shop\Services\ProductService::class);
        $this->app->singleton(\RMS\Shop\Services\ProductAttributesService::class);
        $this->app->singleton(\RMS\Shop\Services\OrderViewService::class);
        $this->app->singleton(\RMS\Shop\Services\OrderAdminService::class);
        $this->app->singleton(\RMS\Shop\Services\OrderFinanceService::class);
        $this->app->singleton(\RMS\Shop\Services\CurrencyService::class);
        $this->app->singleton(\RMS\Shop\Services\ProductImagesService::class);
        $this->app->singleton(\RMS\Shop\Support\PanelApi\ResponsePipeline::class);
        $this->app->singleton(\RMS\Shop\Support\PanelApi\PanelApiResponder::class);
        $this->app->singleton(\RMS\Shop\Support\PanelApi\CartStorage::class, function ($app) {
            return new \RMS\Shop\Support\PanelApi\CartStorage($app['cache']->store());
        });
        $this->app->singleton(\RMS\Shop\Support\PanelApi\CartManager::class);
        $this->app->singleton(\RMS\Shop\Support\AdminApi\AdminApiResponder::class);
        $this->app->singleton(\RMS\Shop\Ai\AiManager::class);

        if ($this->app->runningInConsole()) {
            $this->commands([
                \RMS\Shop\Console\Commands\ShopInstallCommand::class,
                \RMS\Shop\Console\Commands\AvifDirectoryCommand::class,
                \RMS\Shop\Console\Commands\ShopApiAccessCommand::class,
                \RMS\Shop\Console\Commands\AiEnrichProductsCommand::class,
                \RMS\Shop\Console\Commands\CatalogImportCommand::class,
                \RMS\Shop\Console\Commands\CatalogPlanCommand::class,
            ]);
        }
    }

    public function boot()
    {
        // Publish config
        $this->publishes([
            __DIR__.'/../config/shop.php' => config_path('shop.php'),
            __DIR__.'/../config/panel_api.php' => config_path('shop/panel_api.php'),
            __DIR__.'/../config/admin_api.php' => config_path('shop/admin_api.php'),
            __DIR__.'/../config/swagger.php' => config_path('shop/swagger.php'),
            __DIR__.'/../config/ai.php' => config_path('shop/ai.php'),
        ], 'shop-config');

        // Publish migrations
        $this->publishes([
            __DIR__.'/../database/migrations' => database_path('migrations'),
        ], 'shop-migrations');

        // Publish views
        $this->publishes([
            __DIR__.'/../resources/views' => resource_path('views/vendor/shop'),
        ], 'shop-views');

        // Publish translations
        $this->publishes([
            __DIR__.'/../resources/lang/fa/shop.php' => lang_path('fa/shop.php'),
        ], 'shop-lang');

        // Publish assets (JS/CSS for admin panel)
        $this->publishes([
            __DIR__.'/../public/admin/js' => public_path('vendor/shop/admin/js'),
            __DIR__.'/../public/admin/css' => public_path('vendor/shop/admin/css'),
        ], 'shop-assets');

        // Publish plugins separately
        $this->publishes([
            __DIR__.'/../public/admin/plugins/ckeditor' => public_path('admin/plugins/ckeditor'),
            __DIR__.'/../public/admin/plugins/fancytree' => public_path('admin/plugins/fancytree'),
            __DIR__.'/../public/admin/plugins/prism' => public_path('admin/plugins/prism'),
        ], 'shop-plugins-admin');

        // Load routes (با چک کردن enabled)
        if (config('shop.routes.admin.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/admin.php');
        }
        
        if (config('shop.routes.payment.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/payment.php');
        }

        if (config('shop.panel_api.enabled', false)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/panel_api.php');
        }

        if (config('shop.admin_api.enabled', true)) {
            $this->loadRoutesFrom(__DIR__.'/../routes/admin_api.php');
        }

        // Load views. Login is applied after every provider so it stays ahead of published CMS views.
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'shop');

        Route::middleware('web')->get('vendor/shop/admin-login.css', function () {
            $path = __DIR__.'/../public/admin/css/login.css';
            abort_unless(is_file($path), 404);

            return response()->file($path, [
                'Content-Type' => 'text/css; charset=UTF-8',
                'Cache-Control' => 'public, max-age=3600',
            ]);
        })->name('shop.admin-login-css');

        $loginViews = __DIR__.'/../resources/views/cms';
        $docsViews = __DIR__.'/../resources/views/docs';

        // Scalar renders the OpenAPI file l5-swagger already generates.
        $this->app->booted(function () use ($loginViews, $docsViews) {
            View::prependNamespace('cms', $loginViews);

            if (class_exists(\L5Swagger\L5SwaggerServiceProvider::class)) {
                View::prependNamespace('l5-swagger', $docsViews);
            }
        });

        // Load translations
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'shop');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}

