<?php

namespace RMS\Shop\Ai\Resources;

use Illuminate\Database\Eloquent\Model;
use RMS\Shop\Models\Product;

class ProductAiResource extends AiResource
{
    public function key(): string
    {
        return 'product';
    }

    public function label(): string
    {
        return 'محصول';
    }

    protected function modelClass(): string
    {
        return Product::class;
    }

    public function readPath(): string
    {
        return 'products/{id}';
    }

    public function fields(): array
    {
        return [
            'name' => ['label' => 'نام', 'type' => 'string', 'max' => 255],
            'slug' => ['label' => 'آدرس (slug)', 'type' => 'slug', 'max' => 190],
            'short_desc' => ['label' => 'توضیح کوتاه', 'type' => 'html', 'max' => 5000],
            'description' => ['label' => 'توضیحات', 'type' => 'html', 'max' => 60000],
            'meta_title' => ['label' => 'عنوان سئو', 'type' => 'string', 'max' => 120],
            'meta_description' => ['label' => 'توضیح متا', 'type' => 'text', 'max' => 320],
            'focus_keyword' => ['label' => 'کلمهٔ کلیدی', 'type' => 'string', 'max' => 100],
        ];
    }

    public function adminUrl(Model $model): ?string
    {
        return route('admin.shop.products.edit', $model->getKey());
    }
}
