<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class CheckinVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'outlet_id' => ['required', 'integer', 'exists:outlets,id'],
            'picture_visit' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:3072'],
            'latlong_in' => ['required', 'string'],
            'tipe_visit' => ['required', 'string', 'in:REGULAR,ACQUISITION,PRODUCT_KNOWLEDGE,EXTRACALL'],
        ];
    }

    public function messages(): array
    {
        return [
            'outlet_id.required' => 'Outlet wajib dipilih',
            'outlet_id.exists' => 'Outlet tidak ditemukan',
            'picture_visit.required' => 'Foto check-in wajib diupload',
            'picture_visit.image' => 'File harus berupa gambar',
            'picture_visit.mimes' => 'Format gambar harus jpg, jpeg, atau png',
            'picture_visit.max' => 'Ukuran gambar maksimal 3MB',
            'latlong_in.required' => 'Lokasi check-in wajib diisi',
            'tipe_visit.required' => 'Tipe visit wajib dipilih',
            'tipe_visit.in' => 'Tipe visit tidak valid',
        ];
    }
}
