<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductSimpleResource extends JsonResource
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
            'nama_produk' => $this->nama_produk,
            'deskripsi' => $this->deskripsi,
            'harga' => (float) $this->harga,
            'stok' => $this->stok,
            'foto' => $this->foto,
            'is_active' => $this->is_active,
            'category' => $this->whenLoaded('category', function () {
                return [
                    'id' => $this->category->id,
                    'nama_kategori' => $this->category->nama_kategori
                ];
            }),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
