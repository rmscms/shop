<?php

namespace RMS\Shop\Ai\Resources;

use Illuminate\Database\Eloquent\Model;
use RMS\Shop\Models\Brand;

class BrandAiResource extends AiResource
{
    public function key(): string
    {
        return 'brand';
    }

    public function label(): string
    {
        return 'برند';
    }

    protected function modelClass(): string
    {
        return Brand::class;
    }

    public function readPath(): string
    {
        return 'brands/{id}';
    }

    public function fields(): array
    {
        return [
            'name' => ['label' => 'نام', 'type' => 'string', 'max' => 255],
            'slug' => ['label' => 'آدرس (slug)', 'type' => 'slug', 'max' => 190],
            'description' => ['label' => 'توضیحات', 'type' => 'html', 'max' => 20000],
            'meta_title' => ['label' => 'عنوان سئو', 'type' => 'string', 'max' => 120],
            'meta_description' => ['label' => 'توضیح متا', 'type' => 'text', 'max' => 320],
        ];
    }

    public function adminUrl(Model $model): ?string
    {
        return route('admin.shop.brands.edit', $model->getKey());
    }
}
