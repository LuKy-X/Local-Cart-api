<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
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
            'alamat' => 'required|string|max:500',
            'telepon' => 'required|string|max:15',
        ];
    }

    public function messages(): array
    {
        return [
            'alamat.required' => 'Alamat wajib diisi',
            'telepon.required' => 'Nomor telepon wajib diisi',
        ];
    }
}
