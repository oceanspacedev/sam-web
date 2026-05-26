<?php

namespace App\Http\Requests\API;

use App\Models\Register;
use App\Services\RegisterApprovalService;
use Illuminate\Foundation\Http\FormRequest;

class ApproveNooRequest extends FormRequest
{
    public function authorize(): bool
    {
        $register = Register::find($this->id);

        if (! $register) {
            return false;
        }

        return $this->user()->can('approve', $register);
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:registers,id'],
            'status' => ['required', 'string', 'in:APPROVED'],
            'duplicate_resolution' => [
                'sometimes',
                'string',
                'in:'.implode(',', [
                    RegisterApprovalService::DUPLICATE_BRANCH,
                    RegisterApprovalService::DUPLICATE_OVERRIDE,
                    RegisterApprovalService::DUPLICATE_REJECT,
                ]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'ID register wajib diisi',
            'id.exists' => 'Data register tidak ditemukan',
            'status.required' => 'Status wajib diisi',
            'status.in' => 'Status tidak valid (harus APPROVED)',
            'duplicate_resolution.in' => 'Pilihan kode outlet duplikat tidak valid',
        ];
    }
}
