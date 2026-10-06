<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateShipperRequest extends FormRequest
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
        $shipperId = $this->route('shipper')->id;

        return [
            'shipper_name' => 'sometimes|string|max:255|unique:shippers,shipper_name,' . $shipperId,
        ];
    }

    public function messages(): array
    {
        return [
            'shipper_name.unique' => 'Nama kurir sudah terdaftar',
            'shipper_name.max' => 'Nama kurir maksimal 255 karakter',
        ];
    }
}
