<?php

namespace App\Http\Requests\API;

use Carbon\Carbon;
use Illuminate\Foundation\Http\FormRequest;

class StorePlanVisitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'tanggal_visit' => [
                'required',
                'date',
                'after_or_equal:'.Carbon::now()->addDays(3)->toDateString(),
            ],
            'outlet_id' => ['required', 'integer', 'exists:outlets,id'],
        ];
    }

    public function messages(): array
    {
        return [
            'tanggal_visit.required' => 'Tanggal visit wajib diisi',
            'tanggal_visit.date' => 'Format tanggal tidak valid',
            'tanggal_visit.after_or_equal' => 'Plan visit harus dibuat minimal H-3 sebelum tanggal kunjungan',
            'outlet_id.required' => 'Outlet wajib dipilih',
            'outlet_id.exists' => 'Outlet tidak ditemukan',
        ];
    }
}
