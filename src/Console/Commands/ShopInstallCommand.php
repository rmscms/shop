<?php

namespace RMS\Shop\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;

class ShopInstallCommand extends Command
{
    protected $signature = 'shop:install';
    protected $description = 'Install and configure RMS Shop package';

    public function handle()
    {
        $this->info('Installing RMS Shop...');

        // 1. Run rms::install
        $this->info('Running rms::install...');
        Artisan::call('rms:install');

        // 2. Publish resources
        $this->info('Publishing resources...');
        $tags = ['shop-config', 'shop-migrations', 'shop-views', 'shop-assets', 'shop-plugins-admin'];
        foreach ($tags as $tag) {
            Artisan::call('vendor:publish', ['--tag' => $tag, '--force' => true]);
        }

        // 3. Run migrations
        $this->info('Running migrations...');
        Artisan::call('migrate');

        // // Seed initial data
        // $this->info('Seeding initial shop data...');
        // Artisan::call('db:seed', ['--class' => 'RMS\\Shop\\Database\\Seeders\\ShopSeeder', '--force' => true]);

        // 4. Update admin sidebar
        $this->info('Updating admin sidebar...');
        $this->updateSidebar();

        // 5. Update .env
        $this->info('Updating .env...');
        $this->updateEnv();

        // 6. Configure queue to database
        $this->info('Configuring queue...');
        $this->configureQueue();

        $this->info('RMS Shop installed successfully! Run queue workers as per README.');
    }

    protected function updateSidebar()
    {
        $sidebarPath = resource_path('views/vendor/cms/admin/layout/sidebar.blade.php');

        if (!File::exists($sidebarPath)) {
            $this->error('Sidebar file not found! Make sure CMS views are published.');
            return;
        }

        $content = File::get($sidebarPath);

        // Check for duplicate
        if (strpos($content, '{{-- Shop Management --}}') !== false) {
            $this->info('Shop menu already exists in sidebar. Skipping insertion.');
            return;
        }

        // Load from stub
        $stubPath = __DIR__ . '/../../../resources/stubs/shop-menu.blade.stub';
        if (!File::exists($stubPath)) {
            $this->error('Shop menu stub not found!');
            return;
        }
        $shopMenu = "\n" . File::get($stubPath) . "\n";

        // Insert before the closing </ul> of main nav
        // This regex needs to be more robust to avoid multiple insertions or incorrect placement
        // Current regex: '/(\s*<\/ul>)/'
        // A better approach would be to find a specific marker or use a more precise regex.
        // For now, it inserts before the first closing </ul> of the nav-sidebar.
        $content = preg_replace('/(\\s*<\\/ul>)/', $shopMenu . '$1', $content, 1);
        File::put($sidebarPath, $content);
        $this->info('Admin sidebar updated with Shop menus from stub.');
    }

    protected function updateEnv()
    {
        $envPath = base_path('.env');
        if (!File::exists($envPath)) {
            $this->error('.env file not found!');
            return;
        }

        $currentEnv = File::get($envPath);
        if (str_contains($currentEnv, 'SHOP_QUEUE_AVIF')) {
            $this->info('.env already contains Shop queue configuration. Skipping.');
            return;
        }

        $additions = "\n# Shop Queues\nSHOP_QUEUE_AVIF=shop-avif\nSHOP_QUEUE_MEDIA=shop-media\nSHOP_QUEUE_DEFAULT=default\n\n# FFmpeg paths (optional, defaults to 'ffmpeg' if in PATH)\nFFMPEG_PATH=ffmpeg\nFFPROBE_PATH=ffprobe\n";

        File::append($envPath, $additions);
        $this->info('.env updated with Shop configurations.');
    }

    protected function configureQueue()
    {
        $queueConfigPath = config_path('queue.php');
        if (!File::exists($queueConfigPath)) {
            $this->error('queue.php not found!');
            return;
        }

        $content = File::get($queueConfigPath);
        $content = preg_replace("/'default' => env\('QUEUE_CONNECTION', 'sync'\),/", "'default' => env('QUEUE_CONNECTION', 'database'),", $content);
        File::put($queueConfigPath, $content);

        Artisan::call('queue:table');
        Artisan::call('migrate');

        $this->info('Queue configured to database driver. Shop queues ready.');
    }
}
