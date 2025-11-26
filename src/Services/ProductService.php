<?php

namespace RMS\Shop\Services;

use RMS\Shop\Models\Product;

class ProductService
{
    /**
     * Create a product with normalized fields.
     */
    public function create(array $fields): Product
    {
        $normalized = $this->normalizeFields($fields);
        return Product::create($normalized);
    }

    /**
     * Update a product with normalized fields.
     */
    public function update(Product $product, array $fields): Product
    {
        $normalized = $this->normalizeFields($fields);
        $product->fill($normalized);
        $product->save();
        return $product;
    }

    /**
     * Normalize inputs: booleans, integers, discount clamp, empty strings → null where applicable.
     */
    protected function normalizeFields(array $fields): array
    {
        $out = $fields;
        // booleans
        if (array_key_exists('active', $out)) { $out['active'] = !empty($out['active']); }
        // integers
        if (array_key_exists('stock_qty', $out)) { $out['stock_qty'] = (int)($out['stock_qty'] ?? 0); }
        if (array_key_exists('point_per_unit', $out)) { $out['point_per_unit'] = (int)($out['point_per_unit'] ?? 0); }
        if (array_key_exists('brand_id', $out)) {
            $out['brand_id'] = $out['brand_id'] !== null && $out['brand_id'] !== ''
                ? (int) $out['brand_id']
                : null;
        }
        // discount clamp percent 0..100
        if (($out['discount_type'] ?? null) === 'percent' && isset($out['discount_value'])) {
            $out['discount_value'] = max(0, min(100, (float)$out['discount_value']));
        }
        // empty strings to null for nullable string/number fields
        foreach (['sku','short_desc','description','price','sale_price','cost_cny','sale_price_cny','category_id','brand_id'] as $k) {
            if (array_key_exists($k, $out) && ($out[$k] === '' || $out[$k] === null)) {
                $out[$k] = null;
            }
        }
        return $out;
    }
}


