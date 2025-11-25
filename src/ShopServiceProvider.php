<?php

namespace RMS\Shop;

use Illuminate\Support\ServiceProvider;

class ShopServiceProvider extends ServiceProvider
{
    public function register()
    {
        // Merge config
        $this->mergeConfigFrom(__DIR__.'/../config/shop.php', 'shop');
        $this->mergeConfigFrom(__DIR__.'/../config/panel_api.php', 'shop.panel_api');
        
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

        if ($this->app->runningInConsole()) {
            $this->commands([
                \RMS\Shop\Console\Commands\ShopInstallCommand::class,
                \RMS\Shop\Console\Commands\AvifDirectoryCommand::class,
            ]);
        }
    }

    public function boot()
    {
        // Publish config
        $this->publishes([
            __DIR__.'/../config/shop.php' => config_path('shop.php'),
            __DIR__.'/../config/panel_api.php' => config_path('shop/panel_api.php'),
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
            __DIR__.'/../resources/lang' => lang_path('vendor/shop'),
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

        // Load views
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'shop');

        // Load translations
        $this->loadTranslationsFrom(__DIR__.'/../resources/lang', 'shop');

        // Load migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
    }
}

