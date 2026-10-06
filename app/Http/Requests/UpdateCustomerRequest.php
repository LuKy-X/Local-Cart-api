<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerRequest extends FormRequest
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
            'name' => 'sometimes|string|max:255',
            'alamat' => 'sometimes|string|max:500',
            'telepon' => 'sometimes|string|max:15',
            'kecamatan_id' => 'sometimes|exists:kecamatans,id',
        ];
    }

    public function messages(): array
    {
        return [
            'name.string' => 'Nama harus berupa teks',
            'alamat.string' => 'Alamat harus berupa teks',
            'telepon.string' => 'Telepon harus berupa teks',
            'kecamatan_id.exists' => 'Kecamatan tidak valid',
        ];
    }
}
