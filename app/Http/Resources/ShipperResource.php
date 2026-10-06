<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShipperResource extends JsonResource
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
            'shipper_name' => $this->shipper_name,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'orders_count' => $this->whenCounted('orders'),
            'orders' => OrderResource::collection($this->whenLoaded('orders')),
        ];
    }
}
