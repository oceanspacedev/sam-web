<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitLeadRequest extends FormRequest
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
            'nama_outlet' => ['required', 'string', 'max:255'],
            'alamat_outlet' => ['required', 'string'],
            'nama_pemilik' => ['required', 'string', 'max:255'],
            'nomer_pemilik' => ['required', 'string', 'max:50'],
            'nomer_perwakilan' => ['nullable', 'string', 'max:50'],
            'distric' => ['required', 'string', 'max:255'],
            'latlong' => ['required', 'string'],
            'oppo' => ['required', 'string', 'max:255'],
            'vivo' => ['required', 'string', 'max:255'],
            'samsung' => ['required', 'string', 'max:255'],
            'xiaomi' => ['required', 'string', 'max:255'],
            'realme' => ['required', 'string', 'max:255'],
            'fl' => ['required', 'string', 'max:255'],
            'ktpnpwp' => ['nullable', 'string', 'max:255'],
            'badanusaha_id' => ['nullable', 'integer', Rule::exists('badan_usahas', 'id')->whereNull('deleted_at')],
            'divisi_id' => ['nullable', 'integer', Rule::exists('divisions', 'id')->whereNull('deleted_at')],
            'region_id' => ['nullable', 'integer', Rule::exists('regions', 'id')->whereNull('deleted_at')],
            'cluster_id' => ['nullable', 'integer', Rule::exists('clusters', 'id')->whereNull('deleted_at')],
            'clus' => ['nullable', 'string', 'max:255'],
            'reg' => ['nullable', 'string', 'max:255'],
            'div' => ['nullable', 'string', 'max:255'],
            'bu' => ['nullable', 'string', 'max:255'],
        ];

        for ($i = 0; $i <= 3; $i++) {
            $rules["photo{$i}"] = ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:3072'];
        }

        $rules['video'] = ['nullable', 'file', 'mimetypes:video/mp4,video/quicktime,video/webm', 'max:51200'];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'nama_outlet.required' => 'Nama outlet wajib diisi',
            'alamat_outlet.required' => 'Alamat outlet wajib diisi',
            'nama_pemilik.required' => 'Nama pemilik wajib diisi',
            'nomer_pemilik.required' => 'Nomor pemilik wajib diisi',
            'distric.required' => 'District wajib diisi',
            'latlong.required' => 'Lokasi wajib diisi',
            'photo*.image' => 'File harus berupa gambar',
            'photo*.mimes' => 'Format gambar harus jpg, jpeg, atau png',
            'photo*.max' => 'Ukuran gambar maksimal 3MB',
            'video.mimetypes' => 'Format video harus mp4, quicktime, atau webm',
            'video.max' => 'Ukuran video maksimal 50MB',
        ];
    }
}
