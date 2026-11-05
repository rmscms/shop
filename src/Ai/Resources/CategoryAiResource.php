<?php

namespace RMS\Shop\Ai\Resources;

use Illuminate\Database\Eloquent\Model;
use RMS\Shop\Models\Category;

class CategoryAiResource extends AiResource
{
    public function key(): string
    {
        return 'category';
    }

    public function label(): string
    {
        return 'دسته‌بندی';
    }

    protected function modelClass(): string
    {
        return Category::class;
    }

    public function readPath(): string
    {
        return 'categories/{id}';
    }

    /** Content only; the tree (parent, order, active) stays with admins. */
    public function fields(): array
    {
        return [
            'name' => ['label' => 'نام', 'type' => 'string', 'max' => 255],
            'slug' => ['label' => 'آدرس (slug)', 'type' => 'slug', 'max' => 190],
            'meta_title' => ['label' => 'عنوان سئو', 'type' => 'string', 'max' => 120],
            'meta_description' => ['label' => 'توضیح متا', 'type' => 'text', 'max' => 320],
            'seo_content' => ['label' => 'متن سئوی دسته', 'type' => 'html', 'max' => 60000],
        ];
    }

    public function adminUrl(Model $model): ?string
    {
        return route('admin.shop.categories.edit', $model->getKey());
    }
}
