<?php

namespace RMS\Shop\Database\Seeders;

use Illuminate\Database\Seeder;
use RMS\Shop\Models\Category;
use RMS\Shop\Models\Product;

class ShopSeeder extends Seeder
{
    public function run()
    {
        // Seed categories
        Category::create([
            'name' => 'Uncategorized',
            'slug' => 'uncategorized',
            'active' => true,
        ]);

        // Seed sample product
        Product::create([
            'name' => 'Sample Product',
            'slug' => 'sample-product',
            'sku' => 'SP-001',
            'category_id' => 1,
            'active' => true,
            'cost_cny' => 100,
            'sale_price_cny' => 150,
            'stock_qty' => 10,
        ]);

        $this->command->info('Shop seeded with initial data.');
    }
}
