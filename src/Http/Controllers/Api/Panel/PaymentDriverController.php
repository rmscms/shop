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
     *     summary="List active payment gateways"
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

