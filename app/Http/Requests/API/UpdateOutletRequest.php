<?php

namespace App\Http\Requests\API;

use App\Rules\VideoMimeOrSignature;
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
            'alamat_outlet' => ['nullable', 'string', 'max:2000'],
            'nama_pemilik_outlet' => ['nullable', 'string', 'max:255'],
            'nomer_tlp_outlet' => ['nullable', 'string', 'max:50'],
            'latlong' => ['nullable', 'string', 'regex:/^-?\d+(\.\d+)?,-?\d+(\.\d+)?$/'],
        ];

        // Prefer named upload fields that match DB columns.
        foreach ([
            'poto_shop_sign',
            'poto_depan',
            'poto_kanan',
            'poto_kiri',
            'poto_ktp',
        ] as $field) {
            $rules[$field] = ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:3072'];
        }

        for ($i = 0; $i <= 4; $i++) {
            $rules["photo{$i}"] = ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:3072'];
        }

        $rules['photos'] = ['nullable', 'array', 'max:5'];
        $rules['photos.*'] = ['file', 'image', 'mimes:jpg,jpeg,png', 'max:3072'];
        $rules['video'] = ['nullable', 'file', new VideoMimeOrSignature, 'max:51200'];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'alamat_outlet.string' => 'Alamat outlet harus berupa teks',
            'alamat_outlet.max' => 'Alamat outlet terlalu panjang',
            'nama_pemilik_outlet.string' => 'Nama pemilik outlet harus berupa teks',
            'nomer_tlp_outlet.string' => 'Nomor telepon outlet harus berupa teks',
            'latlong.string' => 'Lokasi harus berupa teks',
            'latlong.regex' => 'Format lokasi tidak valid (gunakan format: latitude,longitude)',
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
