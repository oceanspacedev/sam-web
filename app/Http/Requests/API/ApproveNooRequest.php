<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class ApproveNooRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:registers,id'],
            'status' => ['required', 'string', 'in:APPROVED,REJECTED'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'ID register wajib diisi',
            'id.exists' => 'Data register tidak ditemukan',
            'status.required' => 'Status wajib diisi',
            'status.in' => 'Status tidak valid (harus APPROVED atau REJECTED)',
        ];
    }
}
