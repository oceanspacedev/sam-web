<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;

class DeletePlanVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'bulan' => ['required', 'integer', 'min:1', 'max:12'],
            'tahun' => ['required', 'integer', 'min:2020', 'max:2100'],
            'outlet_id' => ['nullable', 'integer', 'exists:outlets,id', 'required_without:register_id', 'prohibited_if:register_id,*'],
            'register_id' => ['nullable', 'integer', 'exists:registers,id', 'required_without:outlet_id', 'prohibited_if:outlet_id,*'],
        ];
    }

    public function messages(): array
    {
        return [
            'bulan.required' => 'Bulan wajib diisi',
            'bulan.integer' => 'Bulan harus berupa angka',
            'bulan.min' => 'Bulan minimal 1',
            'bulan.max' => 'Bulan maksimal 12',
            'tahun.required' => 'Tahun wajib diisi',
            'tahun.integer' => 'Tahun harus berupa angka',
            'outlet_id.required_without' => 'Outlet atau Register wajib dipilih',
            'outlet_id.exists' => 'Outlet tidak ditemukan',
            'register_id.required_without' => 'Outlet atau Register wajib dipilih',
            'register_id.exists' => 'Register tidak ditemukan',
        ];
    }
}
