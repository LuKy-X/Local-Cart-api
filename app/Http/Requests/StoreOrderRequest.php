<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'umkm_id' => 'required|exists:umkms,id',
            'shipper_id' => 'nullable|exists:shippers,id',
            'alamat_pengiriman' => 'required|string|max:500',
            'order_items' => 'required|array|min:1',
            'order_items.*.product_id' => 'required|exists:products,id',
            'order_items.*.quantity' => 'required|integer|min:1',
        ];
    }

    public function messages(): array
    {
        return [
            'umkm_id.required' => 'UMKM harus dipilih',
            'umkm_id.exists' => 'UMKM tidak valid',
            'shipper_id.exists' => 'Kurir tidak valid',
            'alamat_pengiriman.required' => 'Alamat pengiriman wajib diisi',
            'order_items.required' => 'Item order wajib ada',
            'order_items.min' => 'Minimal 1 item order',
            'order_items.*.product_id.required' => 'Product ID wajib diisi',
            'order_items.*.product_id.exists' => 'Product tidak valid',
            'order_items.*.quantity.required' => 'Quantity wajib diisi',
            'order_items.*.quantity.integer' => 'Quantity harus berupa angka bulat',
            'order_items.*.quantity.min' => 'Quantity minimal 1',
        ];
    }
}
