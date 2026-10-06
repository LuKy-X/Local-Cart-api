<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
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
            'umkm_id' => $this->umkm_id,
            'customer_id' => $this->customer_id,
            'shipper_id' => $this->shipper_id,
            'kode_order' => $this->kode_order,
            'nomor_resi' => $this->nomor_resi,
            'alamat_pengiriman' => $this->alamat_pengiriman,
            'total_harga' => (float) $this->total_harga,
            'ongkir' => (float) $this->ongkir,
            'grand_total' => (float) $this->grand_total,
            'estimasi_pengiriman' => $this->estimasi_pengiriman,
            'jarak_km' => $this->jarak_km,
            'status' => $this->status,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'umkm' => new UmkmResource($this->whenLoaded('umkm')),
            'customer' => new CustomerResource($this->whenLoaded('customer')),
            'shipper' => new ShipperResource($this->whenLoaded('shipper')),
            'order_items' => OrderItemResource::collection($this->whenLoaded('orderItems')),
            'order_items_count' => $this->whenCounted('orderItems'),
        ];
    }
}
