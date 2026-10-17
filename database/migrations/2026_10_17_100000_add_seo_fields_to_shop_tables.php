<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, array<string, callable(Blueprint): mixed>>
     */
    protected function columns(): array
    {
        return [
            'products' => [
                'meta_title' => fn (Blueprint $t) => $t->string('meta_title', 120)->nullable(),
                'meta_description' => fn (Blueprint $t) => $t->string('meta_description', 320)->nullable(),
                'focus_keyword' => fn (Blueprint $t) => $t->string('focus_keyword', 100)->nullable(),
                'canonical_url' => fn (Blueprint $t) => $t->string('canonical_url')->nullable(),
                'noindex' => fn (Blueprint $t) => $t->boolean('noindex')->default(false),
                'gtin' => fn (Blueprint $t) => $t->string('gtin', 32)->nullable(),
                'mpn' => fn (Blueprint $t) => $t->string('mpn', 64)->nullable(),
            ],
            'categories' => [
                'meta_title' => fn (Blueprint $t) => $t->string('meta_title', 120)->nullable(),
                'meta_description' => fn (Blueprint $t) => $t->string('meta_description', 320)->nullable(),
                // Intro text shown on the category page; gives the page its own content.
                'seo_content' => fn (Blueprint $t) => $t->text('seo_content')->nullable(),
                'noindex' => fn (Blueprint $t) => $t->boolean('noindex')->default(false),
            ],
            'shop_brands' => [
                'meta_title' => fn (Blueprint $t) => $t->string('meta_title', 120)->nullable(),
                'meta_description' => fn (Blueprint $t) => $t->string('meta_description', 320)->nullable(),
            ],
            'image_library' => [
                'alt' => fn (Blueprint $t) => $t->string('alt')->nullable(),
            ],
        ];
    }

    public function up(): void
    {
        foreach ($this->columns() as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            foreach ($columns as $column => $define) {
                if (!Schema::hasColumn($table, $column)) {
                    Schema::table($table, fn (Blueprint $t) => $define($t));
                }
            }
        }
    }

    public function down(): void
    {
        foreach ($this->columns() as $table => $columns) {
            if (!Schema::hasTable($table)) {
                continue;
            }
            $existing = array_values(array_filter(array_keys($columns), fn ($c) => Schema::hasColumn($table, $c)));
            if ($existing !== []) {
                Schema::table($table, fn (Blueprint $t) => $t->dropColumn($existing));
            }
        }
    }
};
