<?php

namespace App\Http\Requests\API\Management;

use App\Models\Cluster;
use Illuminate\Validation\Rule;

class StoreClusterRequest extends OrganizationalManagementRequest
{
    public function rules(): array
    {
        return [
            'region_id' => ['required', 'integer', Rule::exists('regions', 'id')->whereNull('deleted_at')],
            'code' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    protected function uniqueContext(): array
    {
        return [
            'model' => Cluster::class,
            'parentColumn' => 'region_id',
            'parentKey' => 'region_id',
        ];
    }

    public function messages(): array
    {
        return [
            'region_id.required' => 'Region wajib dipilih',
            'region_id.exists' => 'Region tidak ditemukan',
            'code.required' => 'Kode wajib diisi',
            'name.required' => 'Nama wajib diisi',
        ];
    }
}
