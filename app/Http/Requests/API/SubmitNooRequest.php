<?php

namespace App\Http\Requests\API;

use App\Rules\VideoMimeOrSignature;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SubmitNooRequest extends FormRequest
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
            'ktpnpwp' => ['required', 'string', 'max:255'],
            'distric' => ['required', 'string', 'max:255'],
            'latlong' => ['required', 'string', 'regex:/^-?\d+(\.\d+)?,-?\d+(\.\d+)?$/'],
            'oppo' => ['required', 'integer', 'min:0', 'max:99'],
            'vivo' => ['required', 'integer', 'min:0', 'max:99'],
            'samsung' => ['required', 'integer', 'min:0', 'max:99'],
            'xiaomi' => ['required', 'integer', 'min:0', 'max:99'],
            'realme' => ['required', 'integer', 'min:0', 'max:99'],
            'fl' => ['required', 'integer', 'min:0', 'max:99'],
            'badanusaha_id' => ['nullable', 'integer', Rule::exists('badan_usahas', 'id')->whereNull('deleted_at')],
            'divisi_id' => ['nullable', 'integer', Rule::exists('divisions', 'id')->whereNull('deleted_at')],
            'region_id' => ['nullable', 'integer', Rule::exists('regions', 'id')->whereNull('deleted_at')],
            'cluster_id' => ['nullable', 'integer', Rule::exists('clusters', 'id')->whereNull('deleted_at')],
            'clus' => ['nullable', 'string', 'max:255'],
            'reg' => ['nullable', 'string', 'max:255'],
            'div' => ['nullable', 'string', 'max:255'],
            'bu' => ['nullable', 'string', 'max:255'],
        ];

        for ($i = 0; $i <= 4; $i++) {
            $rules["photo{$i}"] = ['nullable', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:3072'];
        }

        $rules['video'] = [
            'nullable',
            'max:51200',
            function ($attribute, $value, $fail) {
                if ($value instanceof \Illuminate\Http\UploadedFile) {
                    (new VideoMimeOrSignature)->validate($attribute, $value, $fail);
                }
            },
        ];

        return $rules;
    }

    public function messages(): array
    {
        return [
            'nama_outlet.required' => 'Nama outlet wajib diisi',
            'alamat_outlet.required' => 'Alamat outlet wajib diisi',
            'nama_pemilik.required' => 'Nama pemilik wajib diisi',
            'nomer_pemilik.required' => 'Nomor pemilik wajib diisi',
            'ktpnpwp.required' => 'KTP/NPWP wajib diisi',
            'distric.required' => 'District wajib diisi',
            'latlong.required' => 'Lokasi wajib diisi',
            'latlong.regex' => 'Format lokasi tidak valid (gunakan format: latitude,longitude)',
            'oppo.integer' => 'Jumlah Oppo harus berupa angka',
            'oppo.min' => 'Jumlah Oppo minimal 0',
            'oppo.max' => 'Jumlah Oppo maksimal 99',
            'vivo.integer' => 'Jumlah Vivo harus berupa angka',
            'vivo.min' => 'Jumlah Vivo minimal 0',
            'vivo.max' => 'Jumlah Vivo maksimal 99',
            'samsung.integer' => 'Jumlah Samsung harus berupa angka',
            'samsung.min' => 'Jumlah Samsung minimal 0',
            'samsung.max' => 'Jumlah Samsung maksimal 99',
            'xiaomi.integer' => 'Jumlah Xiaomi harus berupa angka',
            'xiaomi.min' => 'Jumlah Xiaomi minimal 0',
            'xiaomi.max' => 'Jumlah Xiaomi maksimal 99',
            'realme.integer' => 'Jumlah Realme harus berupa angka',
            'realme.min' => 'Jumlah Realme minimal 0',
            'realme.max' => 'Jumlah Realme maksimal 99',
            'fl.integer' => 'Jumlah FL harus berupa angka',
            'fl.min' => 'Jumlah FL minimal 0',
            'fl.max' => 'Jumlah FL maksimal 99',
            'photo*.image' => 'File harus berupa gambar',
            'photo*.mimes' => 'Format gambar harus jpg, jpeg, atau png',
            'photo*.max' => 'Ukuran gambar maksimal 3MB',
            'video.mimetypes' => 'Format video harus mp4, quicktime, atau webm',
            'video.max' => 'Ukuran video maksimal 50MB',
        ];
    }
}
