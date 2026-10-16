<?php

namespace RMS\Shop\OpenApi;

use OpenApi\Annotations as OA;

/**
 * @OA\SecurityScheme(
 *     securityScheme="admin",
 *     type="http",
 *     scheme="bearer",
 *     bearerFormat="API token",
 *     description="Shop API access token from shop_api_accesses"
 * )
 *
 * @OA\Tag(
 *     name="Admin - Brands",
 *     description="Brand management endpoints"
 * )
 * @OA\Tag(
 *     name="Admin - Products",
 *     description="Product management endpoints"
 * )
 * @OA\Tag(
 *     name="Admin - Categories",
 *     description="Category management endpoints"
 * )
 * @OA\Tag(
 *     name="Admin - Orders",
 *     description="Order management endpoints"
 * )
 * @OA\Tag(
 *     name="Admin - Media",
 *     description="Media library management endpoints"
 * )
 * @OA\Tag(
 *     name="Admin - Carts",
 *     description="Cart management endpoints"
 * )
 * @OA\Tag(
 *     name="Admin - Currencies",
 *     description="Currency management endpoints"
 * )
 * @OA\Tag(
 *     name="Admin - Statistics",
 *     description="Statistics and reports endpoints"
 * )
 * @OA\Tag(
 *     name="Admin - Settings",
 *     description="Shop settings management endpoints"
 * )
 * @OA\Tag(
 *     name="Admin - Utilities",
 *     description="Utility endpoints"
 * )
 * @OA\Tag(
 *     name="Admin - Dashboard",
 *     description="Dashboard endpoints"
 * )
 * @OA\Tag(
 *     name="Admin - Authentication",
 *     description="Admin authentication endpoints"
 * )
 *
 * @OA\Schema(
 *     schema="AdminOrderResource",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", nullable=true),
 *     @OA\Property(property="status", type="string", example="pending"),
 *     @OA\Property(property="status_label", type="string", example="در انتظار"),
 *     @OA\Property(property="subtotal", type="number", format="float", example=100000),
 *     @OA\Property(property="discount", type="number", format="float", example=0),
 *     @OA\Property(property="shipping_cost", type="number", format="float", example=0),
 *     @OA\Property(property="total", type="number", format="float", example=100000),
 *     @OA\Property(property="items_count", type="integer", example=3),
 *     @OA\Property(property="tracking_code", type="string", nullable=true),
 *     @OA\Property(property="tracking_url", type="string", nullable=true),
 *     @OA\Property(property="paid_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="AdminCartResource",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="user_id", type="integer", nullable=true),
 *     @OA\Property(property="status", type="string", example="open"),
 *     @OA\Property(property="items_count", type="integer", example=2),
 *     @OA\Property(property="subtotal", type="number", format="float", example=200000),
 *     @OA\Property(property="created_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="AdminCurrencyResource",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="code", type="string", example="USD"),
 *     @OA\Property(property="name", type="string", example="US Dollar"),
 *     @OA\Property(property="symbol", type="string", example="$"),
 *     @OA\Property(property="decimals", type="integer", example=2),
 *     @OA\Property(property="is_base", type="boolean", example=false)
 * )
 *
 * @OA\Schema(
 *     schema="AdminCurrencyRateResource",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="base_code", type="string", example="CNY"),
 *     @OA\Property(property="quote_code", type="string", example="IRT"),
 *     @OA\Property(property="sell_rate", type="number", format="float", example=1000.5),
 *     @OA\Property(property="effective_at", type="string", format="date-time", nullable=true),
 *     @OA\Property(property="notes", type="string", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="AdminVideoLibraryResource",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="filename", type="string", example="video.mp4"),
 *     @OA\Property(property="title", type="string", example="Product Video"),
 *     @OA\Property(property="url", type="string", nullable=true),
 *     @OA\Property(property="hls_url", type="string", nullable=true),
 *     @OA\Property(property="poster_url", type="string", nullable=true),
 *     @OA\Property(property="duration_seconds", type="integer", nullable=true),
 *     @OA\Property(property="size_bytes", type="integer", nullable=true),
 *     @OA\Property(property="is_transcoded", type="boolean", example=false)
 * )
 *
 * @OA\Schema(
 *     schema="AdminProductFeatureCategoryResource",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Technical Specifications"),
 *     @OA\Property(property="slug", type="string", example="technical-specifications"),
 *     @OA\Property(property="icon", type="string", example="ph-cpu"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="sort", type="integer", example=0),
 *     @OA\Property(property="active", type="boolean", example=true)
 * )
 *
 * @OA\Schema(
 *     schema="AdminCategoryResource",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Electronics"),
 *     @OA\Property(property="slug", type="string", example="electronics"),
 *     @OA\Property(property="parent_id", type="integer", nullable=true),
 *     @OA\Property(property="active", type="boolean", example=true),
 *     @OA\Property(property="sort", type="integer", example=0),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="AdminImageLibraryResource",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="filename", type="string", example="image.jpg"),
 *     @OA\Property(property="path", type="string", example="uploads/images/image.jpg"),
 *     @OA\Property(property="url", type="string", example="http://example.com/storage/uploads/images/image.jpg"),
 *     @OA\Property(property="mime_type", type="string", example="image/jpeg"),
 *     @OA\Property(property="size_bytes", type="integer", example=102400),
 *     @OA\Property(property="formatted_size", type="string", example="100 KB"),
 *     @OA\Property(property="assignments_count", type="integer", example=5),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="AdminProductCombinationResource",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="product_id", type="integer", example=1),
 *     @OA\Property(property="sku", type="string", example="SKU-001-RED"),
 *     @OA\Property(property="price", type="number", format="float", nullable=true),
 *     @OA\Property(property="sale_price", type="number", format="float", nullable=true),
 *     @OA\Property(property="sale_price_cny", type="number", format="float", nullable=true),
 *     @OA\Property(property="stock_qty", type="integer", example=100),
 *     @OA\Property(property="active", type="boolean", example=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="AdminProductFeatureResource",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="product_id", type="integer", example=1),
 *     @OA\Property(property="category_id", type="integer", nullable=true),
 *     @OA\Property(property="feature_definition_id", type="integer", nullable=true),
 *     @OA\Property(property="feature_value_id", type="integer", nullable=true),
 *     @OA\Property(property="name", type="string", example="Screen Size"),
 *     @OA\Property(property="value", type="string", example="6.1 inches"),
 *     @OA\Property(property="sort", type="integer", example=0),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="AdminBrandResource",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Apple"),
 *     @OA\Property(property="slug", type="string", example="apple"),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="is_active", type="boolean", example=true),
 *     @OA\Property(property="sort", type="integer", example=0),
 *     @OA\Property(property="products_count", type="integer", example=10),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 *
 * @OA\Schema(
 *     schema="AdminProductResource",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Product Name"),
 *     @OA\Property(property="slug", type="string", example="product-name"),
 *     @OA\Property(property="sku", type="string", example="SKU-001"),
 *     @OA\Property(property="price", type="number", format="float", nullable=true),
 *     @OA\Property(property="sale_price", type="number", format="float", nullable=true),
 *     @OA\Property(property="cost_cny", type="number", format="float", nullable=true),
 *     @OA\Property(property="sale_price_cny", type="number", format="float", nullable=true),
 *     @OA\Property(property="discount_type", type="string", nullable=true),
 *     @OA\Property(property="discount_value", type="number", format="float", nullable=true),
 *     @OA\Property(property="stock_qty", type="integer", example=100),
 *     @OA\Property(property="point_per_unit", type="integer", example=10),
 *     @OA\Property(property="active", type="boolean", example=true),
 *     @OA\Property(property="short_desc", type="string", nullable=true),
 *     @OA\Property(property="description", type="string", nullable=true),
 *     @OA\Property(property="category_id", type="integer", nullable=true),
 *     @OA\Property(property="brand_id", type="integer", nullable=true),
 *     @OA\Property(property="main_image_url", type="string", nullable=true),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time"),
 *     @OA\Property(property="deleted_at", type="string", format="date-time", nullable=true)
 * )
 *
 * @OA\Schema(
 *     schema="AdminImageSizeResource",
 *     type="object",
 *     @OA\Property(property="id", type="integer", example=1),
 *     @OA\Property(property="name", type="string", example="Thumbnail"),
 *     @OA\Property(property="slug", type="string", example="thumb"),
 *     @OA\Property(property="width", type="integer", nullable=true, example=300),
 *     @OA\Property(property="height", type="integer", nullable=true, example=300),
 *     @OA\Property(property="quality", type="integer", example=85),
 *     @OA\Property(property="active", type="boolean", example=true),
 *     @OA\Property(property="sort", type="integer", example=0),
 *     @OA\Property(property="created_at", type="string", format="date-time"),
 *     @OA\Property(property="updated_at", type="string", format="date-time")
 * )
 */
class AdminApiDoc
{
    // Intentionally left blank; annotations only.
}
