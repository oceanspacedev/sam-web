<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class CheckoutVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'latlong_out' => ['required', 'string'],
            'laporan_visit' => ['required', 'string', 'max:1000'],
            'picture_visit' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:3072'],
            'transaksi' => ['required', 'string', 'in:ADA,TIDAK ADA'],
        ];
    }

    public function messages(): array
    {
        return [
            'latlong_out.required' => 'Lokasi check-out wajib diisi',
            'laporan_visit.required' => 'Laporan visit wajib diisi',
            'laporan_visit.max' => 'Laporan visit maksimal 1000 karakter',
            'picture_visit.required' => 'Foto check-out wajib diupload',
            'picture_visit.image' => 'File harus berupa gambar',
            'picture_visit.mimes' => 'Format gambar harus jpg, jpeg, atau png',
            'picture_visit.max' => 'Ukuran gambar maksimal 3MB',
            'transaksi.required' => 'Status transaksi wajib dipilih',
            'transaksi.in' => 'Status transaksi tidak valid',
        ];
    }
}
