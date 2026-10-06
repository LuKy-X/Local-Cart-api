<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderRequest extends FormRequest
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
            'status' => 'sometimes|in:pending,processing,shipped,delivered,cancelled',
            'nomor_resi' => 'sometimes|string|max:100',
            'alamat_pengiriman' => 'sometimes|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'status.in' => 'Status tidak valid',
            'nomor_resi.max' => 'Nomor resi maksimal 100 karakter',
            'alamat_pengiriman.max' => 'Alamat pengiriman maksimal 500 karakter',
        ];
    }
}
