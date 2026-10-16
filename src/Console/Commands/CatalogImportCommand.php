<?php

namespace RMS\Shop\Console\Commands;

use Illuminate\Console\Command;
use RMS\Shop\Ai\CatalogImporter;
use Throwable;

class CatalogImportCommand extends Command
{
    protected $signature = 'shop:catalog-import
        {file : Excel (.xlsx) or CSV file; first column name, second column spec/model}
        {--batch= : Name for this list (defaults to the file name)}';

    protected $description = 'Stage a raw product list so the AI can plan categories, brands and variants';

    public function handle(CatalogImporter $importer): int
    {
        $file = (string) $this->argument('file');
        $batch = (string) ($this->option('batch') ?: CatalogImporter::batchFromPath($file));

        try {
            $stats = $importer->import($file, $batch);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info("Batch {$batch}: {$stats['created']} new, {$stats['updated']} updated, {$stats['skipped']} already imported, {$stats['total']} rows in total.");
        $this->line("Next: php artisan shop:catalog-plan {$batch} --sync");

        return self::SUCCESS;
    }
}
