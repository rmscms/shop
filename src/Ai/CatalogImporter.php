<?php

namespace RMS\Shop\Ai;

use Illuminate\Support\Str;
use RMS\Shop\Models\AiCatalogItem;
use RuntimeException;

/**
 * Stages a raw product list (first column name, second column spec/model)
 * so the AI can plan categories, brands and variants before anything is created.
 */
class CatalogImporter
{
    /**
     * @return array{created: int, updated: int, skipped: int, total: int}
     */
    public function import(string $path, string $batch): array
    {
        $created = $updated = $skipped = 0;
        $number = 0;

        foreach ($this->rows($path) as $index => $cells) {
            $name = $this->clean($cells[0] ?? '');
            $spec = $this->clean($cells[1] ?? '');

            // A header row names its columns instead of a product.
            if ($index === 0 && preg_match('/^(name|product|نام)/iu', $name)) {
                continue;
            }
            if ($name === '' && $spec === '') {
                continue;
            }

            $number = $index + 1;
            $item = AiCatalogItem::query()->firstOrNew(['batch' => $batch, 'row' => $number]);

            // Rows already turned into products are left alone on a re-import.
            if ($item->exists && $item->status !== AiCatalogItem::STATUS_STAGED) {
                $skipped++;
                continue;
            }

            $item->fill([
                'name' => $name !== '' ? $name : $spec,
                'spec' => $name !== '' && $spec !== '' ? $spec : null,
                'raw' => array_values($cells),
                'status' => AiCatalogItem::STATUS_STAGED,
            ]);
            $item->exists ? $updated++ : $created++;
            $item->save();
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
            'total' => AiCatalogItem::query()->where('batch', $batch)->count(),
        ];
    }

    public static function batchFromPath(string $path): string
    {
        $slug = Str::slug(pathinfo($path, PATHINFO_FILENAME));

        return mb_substr($slug !== '' ? $slug : 'import-'.now()->format('Ymd-His'), 0, 100);
    }

    /**
     * @return iterable<int, array<int, mixed>>
     */
    protected function rows(string $path): iterable
    {
        if (!is_file($path)) {
            throw new RuntimeException("File not found: {$path}");
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if (in_array($extension, ['xlsx', 'xls', 'ods'], true)) {
            if (!class_exists(\PhpOffice\PhpSpreadsheet\IOFactory::class)) {
                throw new RuntimeException('Reading Excel needs phpoffice/phpspreadsheet. Install it or save the sheet as CSV.');
            }

            $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($path)->getActiveSheet();

            return $sheet->toArray(null, true, false, false);
        }

        return $this->csvRows($path);
    }

    /**
     * @return iterable<int, array<int, string>>
     */
    protected function csvRows(string $path): iterable
    {
        $handle = fopen($path, 'r');
        $index = 0;
        while (($cells = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            if ($index === 0 && isset($cells[0])) {
                $cells[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $cells[0]);
            }
            yield $index++ => $cells;
        }
        fclose($handle);
    }

    protected function clean(mixed $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', (string) $value));
    }
}
