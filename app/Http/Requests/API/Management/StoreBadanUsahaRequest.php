<?php

namespace App\Http\Requests\API\Management;

use App\Models\BadanUsaha;

class StoreBadanUsahaRequest extends OrganizationalManagementRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:255'],
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    protected function uniqueContext(): array
    {
        return [
            'model' => BadanUsaha::class,
            'parentColumn' => null,
            'parentKey' => null,
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'Kode wajib diisi',
            'name.required' => 'Nama wajib diisi',
        ];
    }
}
