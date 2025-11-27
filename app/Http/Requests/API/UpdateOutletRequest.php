<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOutletRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'nama_pemilik_outlet' => ['required', 'string', 'max:255'],
            'nomer_tlp_outlet' => ['required', 'string', 'max:50'],
            'latlong' => ['required', 'string'],
        ];

        for ($i = 0; $i <= 4; $i++) {
            $rules["photo{$i}"] = ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:3072'];
        }

        $rules['photos'] = ['nullable', 'array', 'max:5'];
        $rules['photos.*'] = ['file', 'image', 'mimes:jpg,jpeg,png', 'max:3072'];
        $rules['video'] = ['nullable', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm', 'max:51200'];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'nama_pemilik_outlet.required' => 'Nama pemilik outlet wajib diisi',
            'nomer_tlp_outlet.required' => 'Nomor telepon outlet wajib diisi',
            'latlong.required' => 'Lokasi wajib diisi',
            'photo*.image' => 'File harus berupa gambar',
            'photo*.mimes' => 'Format gambar harus jpg, jpeg, atau png',
            'photo*.max' => 'Ukuran gambar maksimal 3MB',
            'photos.*.image' => 'File harus berupa gambar',
            'photos.*.mimes' => 'Format gambar harus jpg, jpeg, atau png',
            'photos.*.max' => 'Ukuran gambar maksimal 3MB',
            'video.mimetypes' => 'Format video harus mp4, quicktime, atau webm',
            'video.max' => 'Ukuran video maksimal 50MB',
        ];
    }
}
