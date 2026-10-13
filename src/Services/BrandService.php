<?php

namespace RMS\Shop\Services;

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
}
