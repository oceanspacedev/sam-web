<?php

namespace App\Http\Requests\API\Management;

use App\Models\Division;
use Illuminate\Validation\Rule;

class StoreDivisionRequest extends OrganizationalManagementRequest
{
    public function rules(): array
    {
        return [
            'badanusaha_id' => ['required', 'integer', Rule::exists('badan_usahas', 'id')->whereNull('deleted_at')],
            'code' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    protected function uniqueContext(): array
    {
        return [
            'model' => Division::class,
            'parentColumn' => 'badanusaha_id',
            'parentKey' => 'badanusaha_id',
        ];
    }

    public function messages(): array
    {
        return [
            'badanusaha_id.required' => 'Badan usaha wajib dipilih',
            'badanusaha_id.exists' => 'Badan usaha tidak ditemukan',
            'code.required' => 'Kode wajib diisi',
            'name.required' => 'Nama wajib diisi',
        ];
    }
}
