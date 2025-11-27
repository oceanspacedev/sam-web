<?php

namespace App\Http\Requests\API;

use App\Models\Register;
use Illuminate\Foundation\Http\FormRequest;

class ApproveNooRequest extends FormRequest
{
    public function authorize(): bool
    {
        $register = Register::find($this->id);
        
        if (!$register) {
            return false;
        }
        
        return $this->user()->can('approve', $register);
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
