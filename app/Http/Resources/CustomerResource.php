<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerResource extends JsonResource
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
            'nama_customer' => $this->nama_customer,
            'alamat' => $this->alamat,
            'telepon' => $this->telepon,
            'foto' => $this->foto,
            'foto_url' => $this->foto ? asset('storage/' . $this->foto) : null,
            'user' => new UserResource($this->whenLoaded('user')),
            'kecamatan' => new KecamatanResource($this->whenLoaded('kecamatan')),
            'orders_count' => $this->whenCounted('orders'),
            'carts_count' => $this->whenCounted('carts'),
            'ratings_count' => $this->whenCounted('ratings'),
        ];
    }
}
