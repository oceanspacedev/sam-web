<?php

namespace App\Http\Requests\API;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'username' => ['required', 'string', 'unique:users,username', 'max:255', 'regex:/^\S*$/', 'alpha_dash'],
            'nama_lengkap' => ['required', 'string', 'max:255'],
            'password' => ['required', 'string', 'min:6'],
            'role_id' => ['required', 'integer', 'exists:roles,id'],
            'id_notif' => ['nullable', 'string', 'max:255'],
            // Optional for API compatibility; strict requirement is enforced in controller
            // after fallback assignment resolution.
            'badanusaha_ids' => ['nullable', 'array'],
            'badanusaha_ids.*' => [Rule::exists('badan_usahas', 'id')->whereNull('deleted_at')],
            'divisi_ids' => ['nullable', 'array'],
            'divisi_ids.*' => [Rule::exists('divisions', 'id')->whereNull('deleted_at')],
            'region_ids' => ['nullable', 'array'],
            'region_ids.*' => [Rule::exists('regions', 'id')->whereNull('deleted_at')],
            'cluster_ids' => ['nullable', 'array'],
            'cluster_ids.*' => [Rule::exists('clusters', 'id')->whereNull('deleted_at')],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'Username wajib diisi',
            'username.unique' => 'Username sudah digunakan',
            'username.regex' => 'Username tidak boleh mengandung spasi',
            'username.alpha_dash' => 'Username hanya boleh huruf, angka, dash dan underscore',
            'nama_lengkap.required' => 'Nama lengkap wajib diisi',
            'password.required' => 'Password wajib diisi',
            'password.min' => 'Password minimal 6 karakter',
            'role_id.required' => 'Role wajib dipilih',
            'role_id.exists' => 'Role tidak ditemukan',
            'badanusaha_ids.required' => 'Badan usaha wajib dipilih untuk role ini',
            'divisi_ids.required' => 'Divisi wajib dipilih untuk role ini',
            'region_ids.required' => 'Region wajib dipilih untuk role ini',
            'cluster_ids.required' => 'Cluster wajib dipilih untuk role ini',
        ];
    }
}
