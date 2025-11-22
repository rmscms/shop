<?php

namespace RMS\Shop\Http\Controllers\Api\Panel;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Annotations as OA;
use RMS\Shop\Http\Requests\Api\Panel\StoreAddressRequest;
use RMS\Shop\Http\Requests\Api\Panel\UpdateAddressRequest;
use RMS\Shop\Http\Resources\Panel\UserAddressResource;
use RMS\Shop\Models\UserAddress;
use RMS\Shop\Support\Geo\IranProvinces;

class AddressController extends BaseController
{
    /**
     * @OA\Get(
     *     path="/addresses",
     *     tags={"Addresses"},
     *     security={{"sanctum":{}}},
     *     summary="List saved addresses",
     *     @OA\Response(
     *         response=200,
     *         description="List of addresses",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(ref="#/components/schemas/AddressResource")
     *             )
     *         )
     *     )
     * )
     */
    public function index(Request $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $addresses = UserAddress::query()
            ->where('user_id', $userId)
            ->orderByDesc('is_default')
            ->orderByDesc('updated_at')
            ->get();

        return $this->apiSuccess(UserAddressResource::collection($addresses)->toArray($request));
    }

    /**
     * @OA\Post(
     *     path="/addresses",
     *     tags={"Addresses"},
     *     security={{"sanctum":{}}},
     *     summary="Create address",
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/AddressPayload")
     *     ),
     *     @OA\Response(
     *         response=201,
     *         description="Address created",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AddressResource")
     *         )
     *     )
     * )
     */
    public function store(StoreAddressRequest $request): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $data = $this->fillProvinceMeta($request->validated());

        $isDefault = (bool) ($data['is_default'] ?? false);
        if ($isDefault) {
            UserAddress::where('user_id', $userId)->update(['is_default' => false]);
        }

        $address = UserAddress::create(array_merge($data, [
            'user_id' => $userId,
            'is_default' => $isDefault,
        ]));

        return $this->apiSuccess((new UserAddressResource($address))->toArray($request), status: 201);
    }

    /**
     * @OA\Put(
     *     path="/addresses/{address}",
     *     tags={"Addresses"},
     *     security={{"sanctum":{}}},
     *     summary="Update address",
     *     @OA\Parameter(name="address", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\RequestBody(
     *         required=true,
     *         @OA\JsonContent(ref="#/components/schemas/AddressPayload")
     *     ),
     *     @OA\Response(
     *         response=200,
     *         description="Address updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AddressResource")
     *         )
     *     )
     * )
     */
    public function update(UpdateAddressRequest $request, int $address): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $model = UserAddress::where('user_id', $userId)->findOrFail($address);

        $data = $this->fillProvinceMeta($request->validated());
        $isDefault = (bool) ($data['is_default'] ?? $model->is_default);

        if ($isDefault) {
            UserAddress::where('user_id', $userId)->where('id', '<>', $model->id)->update(['is_default' => false]);
        }

        $model->update(array_merge($data, ['is_default' => $isDefault]));

        return $this->apiSuccess((new UserAddressResource($model->refresh()))->toArray($request));
    }

    /**
     * @OA\Delete(
     *     path="/addresses/{address}",
     *     tags={"Addresses"},
     *     security={{"sanctum":{}}},
     *     summary="Delete address",
     *     @OA\Parameter(name="address", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
         description="Address deleted",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(
     *                 property="data",
     *                 type="object",
     *                 @OA\Property(property="deleted", type="boolean")
     *             )
     *         )
     *     )
     * )
     */
    public function destroy(Request $request, int $address): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $model = UserAddress::where('user_id', $userId)->findOrFail($address);
        $model->delete();

        return $this->apiSuccess(['deleted' => true]);
    }

    /**
     * @OA\Post(
     *     path="/addresses/{address}/default",
     *     tags={"Addresses"},
     *     security={{"sanctum":{}}},
     *     summary="Make address default",
     *     @OA\Parameter(name="address", in="path", required=true, @OA\Schema(type="integer")),
     *     @OA\Response(
     *         response=200,
     *         description="Default address updated",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(property="data", ref="#/components/schemas/AddressResource")
     *         )
     *     )
     * )
     */
    public function setDefault(Request $request, int $address): JsonResponse
    {
        $userId = (int) $request->user()->id;
        $model = UserAddress::where('user_id', $userId)->findOrFail($address);

        UserAddress::where('user_id', $userId)->update(['is_default' => false]);
        $model->update(['is_default' => true]);

        return $this->apiSuccess((new UserAddressResource($model->refresh()))->toArray($request));
    }

    /**
     * @OA\Get(
     *     path="/addresses/provinces",
     *     tags={"Addresses"},
     *     summary="List Iranian provinces",
     *     @OA\Response(
     *         response=200,
     *         description="Province options",
     *         @OA\JsonContent(
     *             @OA\Property(property="status", type="string", example="ok"),
     *             @OA\Property(
     *                 property="data",
     *                 type="array",
     *                 @OA\Items(
     *                     type="object",
     *                     @OA\Property(property="id", type="integer"),
     *                     @OA\Property(property="name", type="string"),
     *                     @OA\Property(property="code", type="string", nullable=true),
     *                     @OA\Property(property="slug", type="string", nullable=true)
     *                 )
     *             )
     *         )
     *     )
     * )
     */
    public function provinces(): JsonResponse
    {
        return $this->apiSuccess(IranProvinces::toSelect());
    }

    protected function fillProvinceMeta(array $data): array
    {
        $province = IranProvinces::find($data['province_id'] ?? null);

        if (!$province && !empty($data['province'])) {
            $province = IranProvinces::matchByName($data['province']);
            if ($province) {
                $data['province_id'] = $province['id'];
            }
        }

        if ($province) {
            $data['province'] = $province['name'];
        }

        return $data;
    }
}

