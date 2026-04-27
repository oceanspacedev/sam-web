<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class UpgradeLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        // Merge route parameter into request for validation
        $this->merge([
            'id' => $this->route('id'),
        ]);
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:registers,id'],
            'noktp' => ['required', 'string', 'max:20'],
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:10240'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'ID register wajib diisi',
            'id.exists' => 'Data register tidak ditemukan',
            'noktp.required' => 'Nomor KTP wajib diisi',
            'noktp.max' => 'Nomor KTP maksimal 20 karakter',
            'photo.required' => 'Foto KTP wajib diupload',
            'photo.image' => 'File harus berupa gambar',
            'photo.mimes' => 'Format gambar harus jpg, jpeg, atau png',
            'photo.max' => 'Ukuran gambar maksimal 10MB',
        ];
    }
}
