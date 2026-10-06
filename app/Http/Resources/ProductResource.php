<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'umkm_id' => $this->umkm_id,
            'category_id' => $this->category_id,
            'nama_produk' => $this->nama_produk,
            'deskripsi' => $this->deskripsi,
            'harga' => (float) $this->harga,
            'stok' => $this->stok,
            'foto' => $this->foto,
            'is_active' => (bool) $this->is_active,
            'created_at' => $this->created_at->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),

            // Calculated attributes
            'average_rating' => (float) $this->average_rating,
            'total_views' => (int) $this->total_views,
            'total_ratings' => (int) $this->total_ratings,
            'sales_count' => (int) $this->sales_count,

            // Relationships
            'umkm' => new UmkmResource($this->whenLoaded('umkm')),
            'category' => new CategoryResource($this->whenLoaded('category')),
            'ratings' => RatingResource::collection($this->whenLoaded('ratings')),

            // Untuk frontend
            'image_url' => $this->foto ? asset('storage/' . $this->foto) : asset('images/default-product.jpg'),
        ];
    }
}
