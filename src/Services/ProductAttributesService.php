<?php
// git-trigger
namespace RMS\Shop\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RMS\Shop\Models\ProductAttribute;
use RMS\Shop\Models\ProductAttributeDefinition;
use RMS\Shop\Models\ProductAttributeDefinitionValue;
use RMS\Shop\Models\ProductAttributeValue;
use RMS\Shop\Models\ProductCombination;
use RMS\Shop\Models\ProductCombinationValue;

class ProductAttributesService
{
    /**
     * Save attributes and combinations using arrays (already decoded)
     */
    public static function save(int $productId, array $attrs, array $combs): void
    {
        DB::transaction(function() use ($productId, $attrs, $combs) {
            // 1) Upsert attributes and values (preserve IDs where possible)
            $existingAttrIds = ProductAttribute::query()->where('product_id', $productId)->pluck('id')->all();
            $payloadAttrIds = [];
            $valIdMap = [];

            foreach ($attrs as $i => $a) {
                $attrId = isset($a['id']) && is_numeric($a['id']) ? (int)$a['id'] : null;
                $definitionId = static::resolveAttributeDefinition(
                    $a['attribute_definition_id'] ?? null,
                    $a['name'] ?? '',
                    $a['type'] ?? 'text',
                    $a['ui'] ?? 'pill'
                );

                $dataAttr = [
                    'product_id' => $productId,
                    'name' => (string)($a['name'] ?? ''),
                    'type' => (string)($a['type'] ?? 'text'),
                    'ui' => (string)($a['ui'] ?? 'pill'),
                    'sort' => (int)($a['sort'] ?? $i),
                    'attribute_definition_id' => $definitionId,
                ];
                if ($attrId && in_array($attrId, $existingAttrIds, true)) {
                    ProductAttribute::query()->where('id', $attrId)->update($dataAttr);
                } else {
                    $attrId = (int) ProductAttribute::query()->create($dataAttr)->id;
                }
                $payloadAttrIds[] = $attrId;

                // Values upsert for this attribute
                $existingVals = ProductAttributeValue::query()->where('attribute_id', $attrId)->pluck('id')->all();
                $payloadValIds = [];
                foreach (($a['values'] ?? []) as $j => $v) {
                    $valId = isset($v['id']) && is_numeric($v['id']) ? (int)$v['id'] : null;
                    $definitionValueId = static::resolveAttributeDefinitionValue(
                        $definitionId,
                        $v['definition_value_id'] ?? null,
                        $v['value'] ?? '',
                        $v['color'] ?? null,
                        $v['image_path'] ?? null
                    );

                    $dataVal = [
                        'attribute_id' => $attrId,
                        'value' => (string)($v['value'] ?? ''),
                        'image_path' => $v['image_path'] ?? null,
                        'color' => $v['color'] ?? null,
                        'sort' => (int)($v['sort'] ?? $j),
                        'definition_value_id' => $definitionValueId,
                    ];
                    if ($valId && in_array($valId, $existingVals, true)) {
                        ProductAttributeValue::query()->where('id', $valId)->update($dataVal);
                    } else {
                        $valId = (int) ProductAttributeValue::query()->create($dataVal)->id;
                    }
                    $payloadValIds[] = $valId;
                    // map incoming ids to persisted ids for later combos mapping
                    if (!empty($v['tmpId']) && is_string($v['tmpId'])) { $valIdMap[$v['tmpId']] = $valId; }
                    if (isset($v['id']) && is_numeric($v['id'])) { $valIdMap[(int)$v['id']] = $valId; }
                    static::bumpDefinitionUsage($definitionId, $definitionValueId);
                }
                // delete values not in payload for this attribute
                $toDeleteVals = array_diff($existingVals, $payloadValIds);
                if (!empty($toDeleteVals)) {
                    // remove PCV references then values
                    ProductCombinationValue::query()->whereIn('attribute_value_id', $toDeleteVals)->delete();
                    ProductAttributeValue::query()->whereIn('id', $toDeleteVals)->delete();
                }
            }
            // delete attributes not in payload (and their values + PCVs)
            $toDeleteAttrs = array_diff($existingAttrIds, $payloadAttrIds);
            if (!empty($toDeleteAttrs)) {
                $valIds = ProductAttributeValue::query()->whereIn('attribute_id', $toDeleteAttrs)->pluck('id')->all();
                if (!empty($valIds)) { ProductCombinationValue::query()->whereIn('attribute_value_id', $valIds)->delete(); }
                ProductAttributeValue::query()->whereIn('attribute_id', $toDeleteAttrs)->delete();
                ProductAttribute::query()->whereIn('id', $toDeleteAttrs)->delete();
            }

            // 2) Sync combinations by stable key (preserve existing combination IDs to keep images)
            // Build existing combos map: key => combination_id
            $existingCombIds = ProductCombination::query()->where('product_id', $productId)->pluck('id')->all();
            $existingKeyToId = [];
            if (!empty($existingCombIds)) {
                $rows = ProductCombinationValue::query()
                    ->whereIn('combination_id', $existingCombIds)
                    ->orderBy('combination_id')->get(['combination_id','attribute_value_id']);
                $tmp = [];
                foreach ($rows as $r) { $tmp[$r->combination_id][] = (int)$r->attribute_value_id; }
                foreach ($tmp as $cid => $vals) {
                    sort($vals); $existingKeyToId[implode('-', $vals)] = (int)$cid;
                }
            }

            $seenCombIds = [];
            foreach ($combs as $c) {
                $skuVal = trim((string)($c['sku'] ?? ''));
                // Map incoming value ids (which might include tmpId strings) to persisted ids
                $incoming = (array)($c['attribute_value_ids'] ?? []);
                $destIds = [];
                foreach ($incoming as $vid) {
                    if (is_numeric($vid)) { $vid = (int)$vid; $destIds[] = (int)($valIdMap[$vid] ?? $vid); }
                    elseif (is_string($vid) && isset($valIdMap[$vid])) { $destIds[] = (int)$valIdMap[$vid]; }
                }
                $destIds = array_values(array_unique(array_filter($destIds, fn($x)=>$x>0)));
                sort($destIds);
                if (empty($destIds)) { continue; }
                $key = implode('-', $destIds);

                $combId = $existingKeyToId[$key] ?? null;
                if ($combId) {
                    // update existing combination (preserve id -> images stay attached)
                    ProductCombination::query()->where('id', $combId)->update([
                        'sku' => ($skuVal !== '' ? $skuVal : null),
                        'price' => $c['price'] ?? null,
                        'sale_price' => null,
                        'sale_price_cny' => $c['price_cny'] ?? null,
                        'stock_qty' => (int)($c['stock'] ?? 0),
                        'active' => !empty($c['active']),
                        'updated_at' => now(),
                    ]);
                    // sync PCVs to dest ids
                    ProductCombinationValue::query()->where('combination_id', $combId)->delete();
                    foreach ($destIds as $did) {
                        ProductCombinationValue::query()->create([
                            'combination_id' => (int)$combId,
                            'attribute_value_id' => (int)$did,
                        ]);
                    }
                    $seenCombIds[] = (int)$combId;
                } else {
                    // create new combination
                    $comb = ProductCombination::query()->create([
                        'product_id' => $productId,
                        'sku' => ($skuVal !== '' ? $skuVal : null),
                        'price' => $c['price'] ?? null,
                        'sale_price' => null,
                        'sale_price_cny' => $c['price_cny'] ?? null,
                        'stock_qty' => (int)($c['stock'] ?? 0),
                        'active' => !empty($c['active']),
                    ]);
                    foreach ($destIds as $did) {
                        ProductCombinationValue::query()->create([
                            'combination_id' => (int)$comb->id,
                            'attribute_value_id' => (int)$did,
                        ]);
                    }
                    $seenCombIds[] = (int)$comb->id;
                }
            }

            // delete combinations not seen in payload
            $toDeleteCombs = array_diff($existingCombIds, $seenCombIds);
            if (!empty($toDeleteCombs)) {
                ProductCombinationValue::query()->whereIn('combination_id', $toDeleteCombs)->delete();
                ProductCombination::query()->whereIn('id', $toDeleteCombs)->delete();
            }
        });
    }

    /** Convenience wrapper: accept JSON strings from controller */
    public static function saveFromJson(int $productId, string $attrsJson, string $combsJson): void
    {
        $attrs = json_decode($attrsJson, true) ?: [];
        $combs = json_decode($combsJson, true) ?: [];
        static::save($productId, $attrs, $combs);
    }

    protected static function resolveAttributeDefinition(?int $definitionId, string $name, string $type, string $ui): ?int
    {
        $name = trim($name);
        if ($definitionId) {
            return $definitionId;
        }
        if ($name === '') {
            return null;
        }

        $existing = ProductAttributeDefinition::query()
            ->where('name', $name)
            ->where('type', $type)
            ->where('ui', $ui)
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $slugBase = Str::slug($name);
        $slug = $slugBase ?: Str::slug($name . '-' . now()->timestamp);
        $counter = 1;
        while (ProductAttributeDefinition::where('slug', $slug)->exists()) {
            $slug = $slugBase . '-' . $counter++;
        }

        $definition = ProductAttributeDefinition::create([
            'name' => $name,
            'slug' => $slug,
            'type' => $type,
            'ui' => $ui,
            'active' => true,
        ]);

        return (int) $definition->id;
    }

    protected static function resolveAttributeDefinitionValue(
        ?int $definitionId,
        ?int $valueId,
        string $value,
        ?string $color,
        ?string $imagePath
    ): ?int {
        $value = trim($value);
        if ($valueId) {
            return $valueId;
        }
        if (!$definitionId || $value === '') {
            return null;
        }

        $existing = ProductAttributeDefinitionValue::query()
            ->where('attribute_definition_id', $definitionId)
            ->where('value', $value)
            ->first();

        if ($existing) {
            return (int) $existing->id;
        }

        $slugBase = Str::slug($value);
        $slug = $slugBase ?: Str::slug($value . '-' . now()->timestamp);
        $counter = 1;
        while (
            ProductAttributeDefinitionValue::where('attribute_definition_id', $definitionId)
                ->where('slug', $slug)
                ->exists()
        ) {
            $slug = $slugBase . '-' . $counter++;
        }

        $definitionValue = ProductAttributeDefinitionValue::create([
            'attribute_definition_id' => $definitionId,
            'value' => $value,
            'slug' => $slug,
            'color' => $color,
            'image_path' => $imagePath,
            'active' => true,
        ]);

        return (int) $definitionValue->id;
    }

    protected static function bumpDefinitionUsage(?int $definitionId, ?int $valueId): void
    {
        if ($definitionId) {
            ProductAttributeDefinition::whereKey($definitionId)->increment('usage_count');
        }
        if ($valueId) {
            ProductAttributeDefinitionValue::whereKey($valueId)->increment('usage_count');
        }
    }
}
