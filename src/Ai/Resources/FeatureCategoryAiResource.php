<?php

namespace RMS\Shop\Ai\Resources;

use Illuminate\Database\Eloquent\Model;
use RMS\Shop\Models\ProductFeatureCategory;
use RMS\Shop\Services\ProductFeaturesService;

/** A new feature group (e.g. "ابعاد"): created only after an admin approves the proposal. */
class FeatureCategoryAiResource extends AiResource
{
    public function key(): string
    {
        return 'feature_category';
    }

    public function label(): string
    {
        return 'دستهٔ ویژگی';
    }

    protected function modelClass(): string
    {
        return ProductFeatureCategory::class;
    }

    public function readPath(): string
    {
        return 'product-feature-categories';
    }

    public function creates(): bool
    {
        return true;
    }

    public function fields(): array
    {
        return [
            'name' => ['label' => 'نام دسته', 'type' => 'string', 'max' => 190],
            'icon' => ['label' => 'آیکن', 'type' => 'string', 'max' => 50],
        ];
    }

    public function hints(): array
    {
        $hints = ['note' => 'CREATE: بدون id پیشنهاد بده؛ فقط name لازم است (icon اختیاری، مثل ph-ruler). اول فهرست موجود را بخوان تا تکراری نسازی.'];
        try {
            $hints['existing'] = ProductFeatureCategory::query()->orderBy('sort')->limit(100)->pluck('name')->all();
        } catch (\Throwable) {
            // Table not migrated yet.
        }

        return $hints;
    }

    public function clean(?Model $model, array $changes): array
    {
        [$cleaned, $errors] = parent::clean($model, $changes);

        if (! isset($errors['name'])) {
            if (! isset($cleaned['name'])) {
                $errors['name'][] = 'نام دسته الزامی است.';
            } elseif (ProductFeatureCategory::query()->whereRaw('LOWER(name) = ?', [mb_strtolower($cleaned['name'])])->exists()) {
                $errors['name'][] = 'دسته‌ای با این نام از قبل هست.';
            }
        }
        if (isset($cleaned['icon']) && ! preg_match('/^ph-[a-z0-9-]+$/', $cleaned['icon'])) {
            $errors['icon'][] = 'آیکن باید از نوع Phosphor باشد، مثل ph-ruler.';
        }

        return [$cleaned, $errors];
    }

    public function create(array $cleaned): Model
    {
        return app(ProductFeaturesService::class)->createFeatureCategory([
            'name' => $cleaned['name'],
            'icon' => $cleaned['icon'] ?? 'ph-tag',
        ]);
    }

    public function adminUrl(Model $model): ?string
    {
        return null;
    }
}
