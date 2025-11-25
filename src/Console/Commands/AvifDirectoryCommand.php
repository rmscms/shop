<?php

namespace RMS\Shop\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use RMS\Shop\Models\AvifDirectory;

class AvifDirectoryCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shop:avif-directory {action : add|list|remove|activate|deactivate}
                                              {path? : Directory path relative to storage or public}
                                              {--type=public : Directory type (public|storage)}
                                              {--force : Skip confirmation for destructive actions}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage AVIF directories (add/list/remove/activate/deactivate)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $action = strtolower($this->argument('action'));
        $path = $this->argument('path');

        return match ($action) {
            'add' => $this->handleAdd($path),
            'list' => $this->handleList(),
            'remove' => $this->handleRemove($path),
            'activate' => $this->handleToggle($path, true),
            'deactivate' => $this->handleToggle($path, false),
            default => $this->invalidAction($action),
        };
    }

    protected function handleAdd(?string $path): int
    {
        if (!$path) {
            $path = $this->ask('Enter directory path (e.g. uploads/blog/images or uploads/blog/originals)');
        }

        $path = trim($path, '/');
        $type = strtolower($this->option('type')) === 'storage' ? 'storage' : 'public';

        $validator = Validator::make(
            ['path' => $path, 'type' => $type],
            [
                'path' => ['required', 'string', 'max:255'],
                'type' => ['required', 'in:public,storage'],
            ]
        );

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }
            return self::FAILURE;
        }

        [$model, $created] = AvifDirectory::query()->firstOrCreate(
            ['path' => $path, 'type' => $type],
            [
                'active' => true,
                'is_default' => false,
            ]
        )->wasRecentlyCreated
            ? [AvifDirectory::where('path', $path)->where('type', $type)->first(), true]
            : [AvifDirectory::where('path', $path)->where('type', $type)->first(), false];

        if ($created) {
            $this->info("✅ Directory [{$model->type}: {$model->path}] added and activated.");
        } else {
            $this->warn("ℹ️ Directory already exists. No changes made.");
        }

        return self::SUCCESS;
    }

    protected function handleList(): int
    {
        $directories = AvifDirectory::query()
            ->orderBy('type')
            ->orderBy('path')
            ->get(['id', 'path', 'type', 'active', 'is_default', 'created_at']);

        if ($directories->isEmpty()) {
            $this->warn('No directories found.');
            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Type', 'Path', 'Active', 'Default', 'Created At'],
            $directories->map(function ($dir) {
                return [
                    $dir->id,
                    $dir->type,
                    $dir->path,
                    $dir->active ? 'Yes' : 'No',
                    $dir->is_default ? 'Yes' : '-',
                    $dir->created_at?->toDateTimeString() ?? '-',
                ];
            })
        );

        return self::SUCCESS;
    }

    protected function handleRemove(?string $path): int
    {
        if (!$path) {
            $path = $this->ask('Enter directory path to remove');
        }

        $path = trim($path, '/');

        $query = AvifDirectory::query()->where('path', $path);
        if ($query->count() > 1) {
            $type = $this->choice('Multiple types found for this path. Choose type:', ['public', 'storage'], 0);
            $query->where('type', $type);
        }

        $directory = $query->first();
        if (!$directory) {
            $this->error("Directory [{$path}] not found.");
            return self::FAILURE;
        }

        if ($directory->is_default) {
            $this->error('Cannot remove default directories.');
            return self::FAILURE;
        }

        if (!$this->option('force')) {
            if (!$this->confirm("Are you sure you want to remove [{$directory->type}: {$directory->path}]?", false)) {
                $this->info('Operation cancelled.');
                return self::SUCCESS;
            }
        }

        $directory->delete();

        $this->info("✅ Directory [{$directory->type}: {$directory->path}] removed.");

        return self::SUCCESS;
    }

    protected function handleToggle(?string $path, bool $activate): int
    {
        if (!$path) {
            $path = $this->ask('Enter directory path');
        }

        $path = trim($path, '/');

        $directory = AvifDirectory::query()
            ->where('path', $path)
            ->when(! $this->option('type'), function ($query) {
                return $query;
            }, function ($query) {
                return $query->where('type', strtolower($this->option('type')));
            })
            ->first();

        if (!$directory) {
            $this->error('Directory not found. Use `shop:avif-directory list` to view available directories.');
            return self::FAILURE;
        }

        if ($directory->active === $activate) {
            $this->info("Directory already " . ($activate ? 'active' : 'inactive') . '.');
            return self::SUCCESS;
        }

        $directory->active = $activate;
        $directory->save();

        $this->info("✅ Directory [{$directory->type}: {$directory->path}] is now " . ($activate ? 'active' : 'inactive') . '.');

        return self::SUCCESS;
    }

    protected function invalidAction(string $action): int
    {
        $this->error("Invalid action: {$action}");
        $this->line('Supported actions: add, list, remove, activate, deactivate');

        return self::INVALID;
    }
}

