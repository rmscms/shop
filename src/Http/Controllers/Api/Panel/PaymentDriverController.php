<?php

namespace RMS\Shop\Http\Controllers\Api\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;
use OpenApi\Annotations as OA;
use RMS\Payment\Models\PaymentDriver;

class PaymentDriverController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/payment/drivers",
     *     tags={"Payments"},
     *     summary="List active payment gateways",
     *     @OA\Response(
     *         response=200,
     *         description="List of active payment drivers",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="driver", type="string"),
     *                     @OA\Property(property="title", type="string"),
     *                     @OA\Property(property="description", type="string", nullable=true),
     *                     @OA\Property(property="logo", type="string", nullable=true),
     *                     @OA\Property(property="logo_url", type="string")
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function index(): JsonResponse
    {
        $defaultLogo = url(config('payment.frontend.default_logo', '/images/payment/gateway-default.svg'));

        $drivers = PaymentDriver::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('title')
            ->get()
            ->map(function (PaymentDriver $driver) use ($defaultLogo) {
                $logo = $driver->logo ?: null;
                $logoUrl = $logo
                    ? (Str::startsWith($logo, ['http://', 'https://']) ? $logo : url($logo))
                    : $defaultLogo;

                return [
                    'driver' => $driver->driver,
                    'title' => $driver->title,
                    'description' => $driver->description,
                    'logo' => $logo,
                    'logo_url' => $logoUrl,
                ];
            })
            ->values();

        return $this->apiSuccess($drivers);
    }
}

