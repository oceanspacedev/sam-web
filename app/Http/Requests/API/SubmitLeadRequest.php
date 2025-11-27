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
        return [
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
    }
}
