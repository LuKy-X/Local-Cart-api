<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class KecamatanResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nama_kecamatan' => $this->nama_kecamatan,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,

            'umkms_count' => $this->whenCounted('umkms'),
            'customers_count' => $this->whenCounted('customers'),

            'umkms' => UmkmResource::collection($this->whenLoaded('umkms')),
            'customers' => CustomerResource::collection($this->whenLoaded('customers')),
        ];
    }
}
