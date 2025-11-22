<?php

namespace RMS\Shop\Http\Resources\Panel;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use RMS\Shop\Support\Geo\IranProvinces;

class UserAddressResource extends JsonResource
{
    /**
     * @param Request $request
     * @return array<string, mixed>
     */
    public function toArray($request): array
    {
        $provinceMeta = IranProvinces::find($this->province_id);
        $provinceName = $this->province ?? ($provinceMeta['name'] ?? null);

        return [
            'id' => (int) $this->id,
            'full_name' => $this->full_name,
            'mobile' => $this->mobile,
            'phone' => $this->phone,
            'province_id' => $this->province_id,
            'province' => $provinceName,
            'province_code' => $provinceMeta['code'] ?? null,
            'city' => $this->city,
            'postal_code' => $this->postal_code,
            'address_line' => $this->address_line,
            'is_default' => (bool) $this->is_default,
            'created_at' => optional($this->created_at)->toDateTimeString(),
            'updated_at' => optional($this->updated_at)->toDateTimeString(),
        ];
    }
}

