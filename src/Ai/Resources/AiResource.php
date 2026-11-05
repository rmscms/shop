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
     * Fields an agent may propose. type: string | text | html | slug | features.
     *
     * @return array<string, array{label: string, type: string, max?: int}>
     */
    abstract public function fields(): array;

    /** API path an agent reads the resource from, e.g. "products/{id}". */
    abstract public function readPath(): string;

    /** True for resources an agent can propose to CREATE (no id in the proposal). */
    public function creates(): bool
    {
        return false;
    }

    /**
     * Create the record from cleaned values (only for resources where creates() is true).
     *
     * @param  array<string, mixed>  $cleaned
     */
    public function create(array $cleaned): Model
    {
        throw new \LogicException($this->key().' cannot be created from a proposal.');
    }

    /** Extra guidance for agents (existing names to reuse, value shapes); shown by GET ai/resources. */
    public function hints(): array
    {
        return [];
    }

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
     * Drop what is already there. Returns the value that still changes something, or null when nothing does.
     */
    public function reduce(?Model $model, string $field, mixed $cleaned): mixed
    {
        if ($model === null) {
            return $cleaned;
        }
        $current = $this->current($model, [$field])[$field] ?? '';

        return trim((string) $current) === (string) $cleaned ? null : $cleaned;
    }

    /**
     * Clean and check proposed values. Unknown fields and bad values come back as errors.
     *
     * @param  array<string, mixed>  $changes
     * @return array{0: array<string, mixed>, 1: array<string, array<int, string>>}  [cleaned, errors]
     */
    public function clean(?Model $model, array $changes): array
    {
        $spec = $this->fields();
        $cleaned = [];
        $errors = [];

        foreach ($changes as $field => $value) {
            if (! isset($spec[$field])) {
                $errors[$field][] = 'این فیلد برای این منبع مجاز نیست. مجاز: '.implode(', ', array_keys($spec));
                continue;
            }

            $type = $spec[$field]['type'];
            if ($type === 'features') {
                [$groups, $problem] = static::cleanFeatures($value);
                if ($problem) {
                    $errors[$field][] = $problem;
                } else {
                    $cleaned[$field] = $groups;
                }
                continue;
            }

            if (! is_scalar($value) && $value !== null) {
                $errors[$field][] = 'مقدار باید متن باشد.';
                continue;
            }

            $max = (int) ($spec[$field]['max'] ?? 255);
            $text = trim((string) $value);

            if ($type === 'slug') {
                $text = SlugTask::clean($text);
                if ($text === '') {
                    $errors[$field][] = 'آدرس معتبر نیست.';
                    continue;
                }
                if (($this->modelClass())::query()->where('slug', $text)->when($model, fn ($q) => $q->whereKeyNot($model->getKey()))->exists()) {
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

    /**
     * Shape: [{"group": "ابعاد", "features": [{"name": "ارتفاع", "value": "35 میلی‌متر"}]}]
     *
     * @return array{0: array<int, array{group: string, features: array<int, array{name: string, value: string}>}>, 1: ?string}
     */
    public static function cleanFeatures(mixed $value): array
    {
        if (! is_array($value) || $value === []) {
            return [[], 'ویژگی‌ها باید فهرستی از گروه‌ها باشد: [{"group":"…","features":[{"name":"…","value":"…"}]}].'];
        }
        if (count($value) > 40) {
            return [[], 'حداکثر ۴۰ گروه ویژگی در هر پیشنهاد.'];
        }

        $groups = [];
        foreach ($value as $group) {
            if (! is_array($group) || ! is_array($group['features'] ?? null)) {
                return [[], 'هر گروه باید کلید features (فهرست ویژگی‌ها) داشته باشد.'];
            }
            $name = trim(strip_tags((string) ($group['group'] ?? '')));
            if (mb_strlen($name) > 100) {
                return [[], 'نام گروه حداکثر ۱۰۰ کاراکتر.'];
            }
            $features = [];
            foreach ($group['features'] as $feature) {
                $fName = trim(strip_tags((string) ($feature['name'] ?? '')));
                $fValue = trim(strip_tags((string) ($feature['value'] ?? '')));
                if ($fName === '' || $fValue === '') {
                    continue;
                }
                if (mb_strlen($fName) > 100 || mb_strlen($fValue) > 255) {
                    return [[], 'نام ویژگی حداکثر ۱۰۰ و مقدارش حداکثر ۲۵۵ کاراکتر.'];
                }
                $features[] = ['name' => $fName, 'value' => $fValue];
            }
            if ($features !== []) {
                $groups[] = ['group' => $name, 'features' => array_slice($features, 0, 80)];
            }
        }

        return $groups === [] ? [[], 'هیچ ویژگی معتبری (نام و مقدار) پیدا نشد.'] : [$groups, null];
    }

    /** @param array<string, mixed> $changes already cleaned */
    public function apply(Model $model, array $changes): void
    {
        $model->forceFill($changes)->save();
    }
}
