<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CartResource extends JsonResource
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
            'umkm' => new UmkmResource($this->whenLoaded('umkm')),
            'cart_items' => CartItemResource::collection($this->whenLoaded('cartItems')),
            'total_items' => $this->cartItems->sum('quantity'),
            'total_price' => $this->cartItems->sum(function ($item) {
                return $item->product->harga * $item->quantity;
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
