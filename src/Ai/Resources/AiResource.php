<?php

namespace RMS\Shop\Ai\Resources;

use Illuminate\Database\Eloquent\Model;
use RMS\Shop\Ai\SlugTask;

/**
 * One shop resource an agent may propose changes for. To add another (a blog post, say),
 * extend this, list the fields an agent may touch and register it in AiResourceRegistry.
 */
abstract class AiResource
{
    /** Short key used in the API, e.g. "product". */
    abstract public function key(): string;

    abstract public function label(): string;

    /** @return class-string<Model> */
    abstract protected function modelClass(): string;

    /**
     * Fields an agent may propose. type: string | text | html | slug.
     *
     * @return array<string, array{label: string, type: string, max?: int}>
     */
    abstract public function fields(): array;

    /** API path an agent reads the resource from, e.g. "products/{id}". */
    abstract public function readPath(): string;

    public function find(int $id): ?Model
    {
        return ($this->modelClass())::query()->find($id);
    }

    public function title(Model $model): string
    {
        return (string) ($model->getAttribute('name') ?? $model->getAttribute('title') ?? '#'.$model->getKey());
    }

    public function adminUrl(Model $model): ?string
    {
        return null;
    }

    /** @param array<int, string> $fields */
    public function current(Model $model, array $fields): array
    {
        $out = [];
        foreach ($fields as $field) {
            $out[$field] = $model->getAttribute($field);
        }

        return $out;
    }

    /**
     * Clean and check proposed values. Unknown fields and bad values come back as errors.
     *
     * @param  array<string, mixed>  $changes
     * @return array{0: array<string, string>, 1: array<string, array<int, string>>}  [cleaned, errors]
     */
    public function clean(Model $model, array $changes): array
    {
        $spec = $this->fields();
        $cleaned = [];
        $errors = [];

        foreach ($changes as $field => $value) {
            if (! isset($spec[$field])) {
                $errors[$field][] = 'این فیلد برای این منبع مجاز نیست. مجاز: '.implode(', ', array_keys($spec));
                continue;
            }
            if (! is_scalar($value) && $value !== null) {
                $errors[$field][] = 'مقدار باید متن باشد.';
                continue;
            }

            $type = $spec[$field]['type'];
            $max = (int) ($spec[$field]['max'] ?? 255);
            $text = trim((string) $value);

            if ($type === 'slug') {
                $text = SlugTask::clean($text);
                if ($text === '') {
                    $errors[$field][] = 'آدرس معتبر نیست.';
                    continue;
                }
                if (($this->modelClass())::query()->where('slug', $text)->whereKeyNot($model->getKey())->exists()) {
                    $errors[$field][] = 'این آدرس برای مورد دیگری استفاده شده است.';
                    continue;
                }
            } elseif ($type === 'html') {
                // Keep a few formatting tags and strip their attributes.
                $text = preg_replace(
                    '/<(\/?)(p|h2|h3|h4|ul|ol|li|strong|em|br)\b[^>]*>/i',
                    '<$1$2>',
                    strip_tags($text, '<p><h2><h3><h4><ul><ol><li><strong><em><br>')
                );
            } else {
                $text = strip_tags($text);
            }

            if ($text === '' && $field === 'name') {
                $errors[$field][] = 'نام نمی‌تواند خالی باشد.';
                continue;
            }
            if (mb_strlen($text) > $max) {
                $errors[$field][] = "حداکثر {$max} کاراکتر.";
                continue;
            }

            $cleaned[$field] = $text;
        }

        return [$cleaned, $errors];
    }

    /** @param array<string, string> $changes already cleaned */
    public function apply(Model $model, array $changes): void
    {
        $model->forceFill($changes)->save();
    }
}
