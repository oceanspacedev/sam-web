<?php

namespace App\Http\Requests\API;

use App\Models\Register;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmNooRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'id' => $this->route('id'),
        ]);
    }

    public function authorize(): bool
    {
        $register = Register::find($this->id);

        if (! $register) {
            return false;
        }

        return $this->user()->can('confirm', $register);
    }

    public function rules(): array
    {
        return [
            'id' => ['required', 'integer', 'exists:registers,id'],
            'status' => ['required', 'string', 'in:CONFIRMED,PENDING'],
            'limit' => ['required', 'integer', 'min:0'],
            'kode_outlet' => [
                'required',
                'string',
                'max:50',
                'regex:/^\S+$/',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'id.required' => 'ID register wajib diisi',
            'id.exists' => 'Data register tidak ditemukan',
            'status.required' => 'Status wajib diisi',
            'status.in' => 'Status tidak valid',
            'limit.required' => 'Limit wajib diisi',
            'limit.integer' => 'Limit harus berupa angka',
            'limit.min' => 'Limit minimal 0',
            'kode_outlet.required' => 'Kode outlet wajib diisi',
            'kode_outlet.max' => 'Kode outlet maksimal 50 karakter',
            'kode_outlet.regex' => 'Kode outlet tidak boleh mengandung spasi',
        ];
    }
}
