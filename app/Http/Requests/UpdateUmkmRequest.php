<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateUmkmRequest extends FormRequest
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
            'nama_umkm' => 'sometimes|string|max:255',
            'deskripsi' => 'nullable|string|max:1000',
            'alamat' => 'sometimes|string|max:500',
            'telepon' => 'sometimes|string|max:15',
            'kecamatan_id' => 'sometimes|exists:kecamatans,id',
            'foto_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }

    public function messages(): array
    {
        return [
            'nama_umkm.required' => 'Nama UMKM wajib diisi',
            'nama_umkm.max' => 'Nama UMKM maksimal 255 karakter',
            'kecamatan_id.required' => 'Kecamatan wajib dipilih',
            'kecamatan_id.exists' => 'Kecamatan tidak valid',
            'alamat.required' => 'Alamat wajib diisi',
            'telepon.required' => 'Nomor telepon wajib diisi',
            'telepon.max' => 'Nomor telepon maksimal 20 karakter',
            'foto_logo.image' => 'File harus berupa gambar',
            'foto_logo.mimes' => 'Format gambar harus jpeg, png, atau jpg',
            'foto_logo.max' => 'Ukuran gambar maksimal 2MB'
        ];
    }
}
