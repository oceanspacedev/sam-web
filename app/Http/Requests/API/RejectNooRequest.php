<?php

namespace App\Http\Requests\API;

use App\Models\Register;
use Illuminate\Foundation\Http\FormRequest;

class RejectNooRequest extends FormRequest
{
    public function authorize(): bool
    {
        $register = Register::find($this->id);

        if (! $register) {
            return false;
        }

        return $this->user()->can('reject', $register);
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:registers,id'],
            'status' => ['required', 'string', 'in:REJECTED'],
            'alasan' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'ID register wajib diisi',
            'id.exists' => 'Data register tidak ditemukan',
            'status.required' => 'Status wajib diisi',
            'status.in' => 'Status harus REJECTED',
            'alasan.required' => 'Alasan penolakan wajib diisi',
            'alasan.max' => 'Alasan penolakan maksimal 500 karakter',
        ];
    }
}
