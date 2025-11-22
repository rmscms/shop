<?php

namespace RMS\Shop\OpenApi;

use OpenApi\Annotations as OA;

/**
 * @OA\Info(
 *     title="RMS Shop Panel API",
 *     version="1.0.0",
 *     description="REST API used by the RMS customer panel application."
 * )
 *
 * @OA\Server(
 *     url="/api/v1/panel",
 *     description="Panel API base URL"
 * )
 *
 * @OA\SecurityScheme(
 *     securityScheme="sanctum",
 *     type="apiKey",
 *     in="header",
 *     name="Authorization",
 *     description="Use token from /auth/login in format: `Bearer {token}`"
 * )
 *
 * @OA\Tag(
 *     name="Auth",
 *     description="Authentication endpoints"
 * )
 * @OA\Tag(
 *     name="Catalog",
 *     description="Product listing and category endpoints"
 * )
 * @OA\Tag(
 *     name="Cart",
 *     description="Customer cart operations"
 * )
 * @OA\Tag(
 *     name="Addresses",
 *     description="Customer address book"
 * )
 * @OA\Tag(
 *     name="Orders",
 *     description="Order history and checkout"
 * )
 * @OA\Tag(
 *     name="Currencies",
 *     description="Currency utilities"
 * )
 * @OA\Tag(
 *     name="Media",
 *     description="Reusable media upload helpers"
 * )
 */
class PanelApiDoc
{
    // Intentionally left blank; annotations only.
}

