<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCategoryRequest extends FormRequest
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
        $categoryId = $this->route('category')->id;

        return [
            'nama_kategori' => 'sometimes|string|max:255|unique:categories,nama_kategori,' . $categoryId,
            'deskripsi' => 'nullable|string|max:500',
        ];
    }

    public function messages(): array
    {
        return [
            'nama_kategori.unique' => 'Nama kategori sudah terdaftar',
            'nama_kategori.max' => 'Nama kategori maksimal 255 karakter',
            'deskripsi.max' => 'Deskripsi maksimal 500 karakter',
        ];
    }
}
