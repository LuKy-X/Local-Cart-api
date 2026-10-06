<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreUmkmRequest extends FormRequest
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
            'nama_umkm' => 'required|string|max:255|unique:umkms,nama_umkm',
            'deskripsi' => 'nullable|string|max:1000',
            'alamat' => 'required|string|max:500',
            'telepon' => 'required|string|max:15',
            'foto_logo' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];
    }

    public function messages()
    {
        return [
            'nama_umkm.required' => 'Nama UMKM wajib diisi',
            'nama_umkm.unique' => 'Nama UMKM sudah terdaftar',
            'alamat.required' => 'Alamat wajib diisi',
            'telepon.required' => 'Nomor telepon wajib diisi',
            'foto_logo.image' => 'File harus berupa gambar',
            'foto_logo.mimes' => 'Format gambar harus jpeg, png, jpg, atau gif',
            'foto_logo.max' => 'Ukuran gambar maksimal 2MB',
        ];
    }
}
