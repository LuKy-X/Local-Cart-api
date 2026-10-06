<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreKecamatanRequest extends FormRequest
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
            'nama_kecamatan' => 'required|string|max:255|unique:kecamatans,nama_kecamatan',
            'kode_pos' => 'nullable|string|max:10',
            'latitude' => 'required|numeric|between:-90,90',
            'longitude' => 'required|numeric|between:-180,180',
        ];
    }

    public function messages(): array
    {
        return [
            'nama_kecamatan.required' => 'Nama kecamatan wajib diisi',
            'nama_kecamatan.unique' => 'Nama kecamatan sudah terdaftar',
            'latitude.required' => 'Latitude wajib diisi',
            'latitude.between' => 'Latitude harus antara -90 dan 90',
            'longitude.required' => 'Longitude wajib diisi',
            'longitude.between' => 'Longitude harus antara -180 dan 180',
        ];
    }
}
