<?php

namespace RMS\Shop\Services;

use Illuminate\Support\Facades\Schema;
use RMS\Shop\Models\Brand;

class BrandService
{
    /**
     * Get brand options for select dropdowns
     *
     * @param bool $includeInactive
     * @return array
     */
    public function getOptions(bool $includeInactive = false): array
    {
        $query = Brand::query()
            ->orderBy('sort')
            ->orderBy('name');

        if (!$includeInactive) {
            $query->where('is_active', true);
        }

        return $query->pluck('name', 'id')->toArray();
    }

    /**
     * @return array<int, array{id:int,name:string,logo_url:?string}>
     */
    public function getSelectItems(bool $includeInactive = false): array
    {
        $query = Brand::query()
            ->orderBy('sort')
            ->orderBy('name');

        if (!$includeInactive) {
            $query->where('is_active', true);
        }

        $columns = ['id', 'name'];
        if (Schema::hasColumn('shop_brands', 'logo_path')) {
            $columns[] = 'logo_path';
        }

        return $query->get($columns)->map(static function (Brand $brand): array {
            return [
                'id' => (int) $brand->id,
                'name' => (string) $brand->name,
                'logo_url' => Schema::hasColumn('shop_brands', 'logo_path') ? $brand->logo_url : null,
            ];
        })->values()->all();
    }
}
