<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RegisterRequest extends FormRequest
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
        $rules = [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'role' => 'required|in:admin,umkm,customer',
        ];

        if ($this->role === 'umkm') {
            $rules['nama_umkm'] = 'required|string|max:255';
            $rules['deskripsi'] = 'nullable|string';
            $rules['alamat'] = 'required|string';
            $rules['telepon'] = 'required|string|max:15';
            $rules['kecamatan_id'] = 'required|exists:kecamatans,id';
        }

        if ($this->role === 'customer') {
            $rules['alamat'] = 'required|string';
            $rules['telepon'] = 'required|string|max:15';
            $rules['kecamatan_id'] = 'required|exists:kecamatans,id';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama lengkap wajib diisi',
            'email.required' => 'Email wajib diisi',
            'email.unique' => 'Email sudah terdaftar',
            'password.required' => 'Password wajib diisi',
            'password.min' => 'Password minimal 8 karakter',
            'password.confirmed' => 'Konfirmasi password tidak sesuai',
            'role.required' => 'Role wajib dipilih',
            'nama_umkm.required' => 'Nama UMKM wajib diisi',
            'alamat.required' => 'Alamat wajib diisi',
            'telepon.required' => 'Nomor telepon wajib diisi',
            'kecamatan_id.required' => 'Kecamatan wajib dipilih',
            'kecamatan_id.exists' => 'Kecamatan tidak valid',
        ];
    }
}
