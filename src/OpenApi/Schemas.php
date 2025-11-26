<?php

namespace RMS\Shop\OpenApi;

use OpenApi\Annotations as OA;

/**
 * @OA\Schema(
 *     schema="PaginatedMeta",
 *     @OA\Property(property="total", type="integer"),
 *     @OA\Property(property="per_page", type="integer"),
 *     @OA\Property(property="current_page", type="integer"),
 *     @OA\Property(property="last_page", type="integer"),
 *     @OA\Property(property="from", type="integer", nullable=true),
 *     @OA\Property(property="to", type="integer", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="CategoryTreeNode",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="slug", type="string"),
 *     @OA\Property(
 *         property="children",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/CategoryTreeNode")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="BrandResource",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="slug", type="string"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="sort", type="integer")
 * )
 *
 * @OA\Schema(
 *     schema="ProductResource",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="slug", type="string"),
 *     @OA\Property(property="sku", type="string", nullable=true),
 *     @OA\Property(property="price", type="number", format="float"),
 *     @OA\Property(property="sale_price", type="number", format="float", nullable=true),
 *     @OA\Property(property="active", type="boolean"),
 *     @OA\Property(property="stock_qty", type="integer"),
 *     @OA\Property(property="main_image_url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="category", type="object",
 *         @OA\Property(property="id", type="integer"),
 *         @OA\Property(property="name", type="string"),
 *         @OA\Property(property="slug", type="string")
 *     ),
 *     @OA\Property(property="brand_id", type="integer", nullable=true),
 *     @OA\Property(property="brand", ref="#/components/schemas/BrandResource", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="ProductImageResource",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="path", type="string"),
 *     @OA\Property(property="url", type="string", format="uri"),
 *     @OA\Property(property="avif_url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="is_main", type="boolean"),
 *     @OA\Property(property="sort", type="integer")
 * )
 *
 * @OA\Schema(
 *     schema="ProductCombinationResource",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="sku", type="string", nullable=true),
 *     @OA\Property(property="price", type="number", format="float"),
 *     @OA\Property(property="sale_price", type="number", format="float", nullable=true),
 *     @OA\Property(property="stock_qty", type="integer"),
 *     @OA\Property(property="active", type="boolean"),
 *     @OA\Property(
 *         property="values",
 *         type="array",
 *         @OA\Items(
 *             @OA\Property(property="attribute_id", type="integer", nullable=true),
 *             @OA\Property(property="attribute_name", type="string", nullable=true),
 *             @OA\Property(property="value_id", type="integer", nullable=true),
 *             @OA\Property(property="value", type="string", nullable=true),
 *             @OA\Property(property="color", type="string", nullable=true)
 *         )
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="ProductDetailResource",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/ProductResource"),
 *         @OA\Schema(
 *             @OA\Property(property="short_desc", type="string", nullable=true),
 *             @OA\Property(property="description", type="string", nullable=true),
 *             @OA\Property(property="point_per_unit", type="integer"),
 *             @OA\Property(property="discount_type", type="string", nullable=true),
 *             @OA\Property(property="discount_value", type="number", format="float", nullable=true),
 *             @OA\Property(
 *                 property="images",
 *                 type="array",
 *                 @OA\Items(ref="#/components/schemas/ProductImageResource")
 *             ),
 *             @OA\Property(
 *                 property="combinations",
 *                 type="array",
 *                 @OA\Items(ref="#/components/schemas/ProductCombinationResource")
 *             )
 *         )
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="CartItemResource",
 *     @OA\Property(property="line_id", type="string"),
 *     @OA\Property(property="qty", type="integer"),
 *     @OA\Property(property="unit_price", type="number", format="float"),
 *     @OA\Property(property="subtotal", type="number", format="float"),
 *     @OA\Property(property="product", ref="#/components/schemas/ProductResource"),
 *     @OA\Property(
 *         property="combination",
 *         type="object",
 *         nullable=true,
 *         @OA\Property(property="id", type="integer"),
 *         @OA\Property(property="sku", type="string", nullable=true)
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="CartResource",
 *     @OA\Property(property="cart_key", type="string"),
 *     @OA\Property(
 *         property="items",
 *         type="array",
 *         @OA\Items(ref="#/components/schemas/CartItemResource")
 *     ),
 *     @OA\Property(
 *         property="summary",
 *         type="object",
 *         @OA\Property(property="item_count", type="integer"),
 *         @OA\Property(property="total_qty", type="integer"),
 *         @OA\Property(property="total_amount", type="number", format="float")
 *     )
 * )
 *
 * @OA\Schema(
 *     schema="AddressResource",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="full_name", type="string"),
 *     @OA\Property(property="mobile", type="string", nullable=true),
 *     @OA\Property(property="phone", type="string", nullable=true),
 *     @OA\Property(property="province_id", type="integer", nullable=true),
 *     @OA\Property(property="province", type="string", nullable=true),
 *     @OA\Property(property="province_code", type="string", nullable=true),
 *     @OA\Property(property="city", type="string", nullable=true),
 *     @OA\Property(property="postal_code", type="string", nullable=true),
 *     @OA\Property(property="address_line", type="string"),
 *     @OA\Property(property="is_default", type="boolean")
 * )
 *
 * @OA\Schema(
 *     schema="OrderItemResource",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="product_id", type="integer"),
 *     @OA\Property(property="combination_id", type="integer", nullable=true),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="sku", type="string", nullable=true),
 *     @OA\Property(property="qty", type="integer"),
 *     @OA\Property(property="unit_price", type="number", format="float"),
 *     @OA\Property(property="total", type="number", format="float"),
 *     @OA\Property(property="image_url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="image_avif_url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="brand_id", type="integer", nullable=true),
 *     @OA\Property(property="brand", ref="#/components/schemas/BrandResource", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="OrderResource",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="status", type="string"),
 *     @OA\Property(property="status_label", type="string"),
 *     @OA\Property(property="subtotal", type="number", format="float"),
 *     @OA\Property(property="discount", type="number", format="float"),
 *     @OA\Property(property="shipping_cost", type="number", format="float"),
 *     @OA\Property(property="total", type="number", format="float"),
 *     @OA\Property(property="items_count", type="integer", nullable=true),
 *     @OA\Property(property="tracking_code", type="string", nullable=true),
 *     @OA\Property(property="tracking_url", type="string", format="uri", nullable=true),
 *     @OA\Property(property="paid_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="OrderDetailResource",
 *     allOf={
 *         @OA\Schema(ref="#/components/schemas/OrderResource"),
 *         @OA\Schema(
 *             @OA\Property(
 *                 property="shipping",
 *                 type="object",
 *                 @OA\Property(property="name", type="string", nullable=true),
 *                 @OA\Property(property="mobile", type="string", nullable=true),
 *                 @OA\Property(property="postal_code", type="string", nullable=true),
 *                 @OA\Property(property="address", type="string", nullable=true),
 *                 @OA\Property(property="customer_note", type="string", nullable=true)
 *             ),
 *             @OA\Property(
 *                 property="items",
 *                 type="array",
 *                 @OA\Items(ref="#/components/schemas/OrderItemResource")
 *             )
 *         )
 *     }
 * )
 *
 * @OA\Schema(
 *     schema="OrderNoteResource",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="text", type="string"),
 *     @OA\Property(property="visible_to_user", type="boolean"),
 *     @OA\Property(
 *         property="author",
 *         type="object",
 *         @OA\Property(property="type", type="string", enum={"admin","user"}),
 *         @OA\Property(property="name", type="string"),
 *         @OA\Property(property="admin_id", type="integer", nullable=true)
 *     ),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="CurrencyResource",
 *     @OA\Property(property="code", type="string"),
 *     @OA\Property(property="name", type="string"),
 *     @OA\Property(property="symbol", type="string", nullable=true),
 *     @OA\Property(property="decimals", type="integer"),
 *     @OA\Property(property="is_base", type="boolean")
 * )
 *
 * @OA\Schema(
 *     schema="CurrencyRateResource",
 *     @OA\Property(property="id", type="integer"),
 *     @OA\Property(property="base_code", type="string"),
 *     @OA\Property(property="quote_code", type="string"),
 *     @OA\Property(property="sell_rate", type="number", format="float"),
 *     @OA\Property(property="effective_at", type="string", format="date-time"),
 *     @OA\Property(property="notes", type="string", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="AddressPayload",
 *     required={"full_name","address_line","province_id"},
 *     @OA\Property(property="full_name", type="string"),
 *     @OA\Property(property="mobile", type="string", nullable=true),
 *     @OA\Property(property="phone", type="string", nullable=true),
 *     @OA\Property(property="province_id", type="integer"),
 *     @OA\Property(property="city", type="string", nullable=true),
 *     @OA\Property(property="postal_code", type="string", nullable=true),
 *     @OA\Property(property="address_line", type="string"),
 *     @OA\Property(property="is_default", type="boolean", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="CheckoutPayload",
 *     required={"address_id"},
 *     @OA\Property(property="address_id", type="integer"),
 *     @OA\Property(property="customer_note", type="string", nullable=true)
 * )
 */
class Schemas
{
    // Holder class for shared schema annotations.
}

