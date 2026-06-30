<?php

namespace App\Http\Requests\API\Management;

use App\Models\Region;
use Illuminate\Validation\Rule;

class StoreRegionRequest extends OrganizationalManagementRequest
{
    public function rules(): array
    {
        return [
            'divisi_id' => ['required', 'integer', Rule::exists('divisions', 'id')->whereNull('deleted_at')],
            'code' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    protected function uniqueContext(): array
    {
        return [
            'model' => Region::class,
            'parentColumn' => 'divisi_id',
            'parentKey' => 'divisi_id',
        ];
    }

    public function messages(): array
    {
        return [
            'divisi_id.required' => 'Divisi wajib dipilih',
            'divisi_id.exists' => 'Divisi tidak ditemukan',
            'code.required' => 'Kode wajib diisi',
            'name.required' => 'Nama wajib diisi',
        ];
    }
}
