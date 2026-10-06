<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UmkmResource extends JsonResource
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
            'user_id' => $this->user_id,
            'kecamatan_id' => $this->kecamatan_id,
            'nama_umkm' => $this->nama_umkm,
            'deskripsi' => $this->deskripsi,
            'alamat' => $this->alamat,
            'telepon' => $this->telepon,
            'foto_logo' => $this->foto_logo ? asset('storage/' . str_replace('storage/', '', $this->foto_logo)) : null,
            'is_approved' => $this->is_approved,
            'average_rating' => round($this->average_rating, 1),
            'total_ratings' => $this->total_ratings,
            'total_sold' => $this->total_sold,
            'products_count' => $this->products_count,
            'created_at' => $this->created_at,
            'user' => new UserResource($this->whenLoaded('user')),
            'kecamatan' => new KecamatanResource($this->whenLoaded('kecamatan')),
            'orders_count' => $this->whenCounted('orders'),
            'orders' => OrderResource::collection($this->whenLoaded('orders')),
        ];
    }
}
