<?php

namespace App\Http\Requests\API;

use App\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $userId = $this->route('id');

        $rules = [
            'username' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^\S*$/',
                'alpha_dash',
                Rule::unique('users', 'username')->ignore($userId),
            ],
            'nama_lengkap' => ['sometimes', 'string', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'min:8'],
            'role_id' => ['sometimes', 'integer', 'exists:roles,id'],
            'id_notif' => ['nullable', 'string', 'max:255'],
        ];

        // Only validate organizational fields if role_id is provided
        if ($this->role_id) {
            $targetRole = Role::find($this->role_id);
            $scopeLevel = $targetRole?->organizational_scope_level;

            if (in_array($scopeLevel, ['badanusaha', 'divisi', 'region', 'cluster'])) {
                $rules['badanusaha_ids'] = ['sometimes', 'array', 'min:1'];
                $rules['badanusaha_ids.*'] = [Rule::exists('badan_usahas', 'id')->whereNull('deleted_at')];
            } else {
                $rules['badanusaha_ids'] = ['nullable', 'array'];
                $rules['badanusaha_ids.*'] = [Rule::exists('badan_usahas', 'id')->whereNull('deleted_at')];
            }

            if (in_array($scopeLevel, ['divisi', 'region', 'cluster'])) {
                $rules['divisi_ids'] = ['sometimes', 'array', 'min:1'];
                $rules['divisi_ids.*'] = [Rule::exists('divisions', 'id')->whereNull('deleted_at')];
            } else {
                $rules['divisi_ids'] = ['nullable', 'array'];
                $rules['divisi_ids.*'] = [Rule::exists('divisions', 'id')->whereNull('deleted_at')];
            }

            if (in_array($scopeLevel, ['region', 'cluster'])) {
                $rules['region_ids'] = ['sometimes', 'array', 'min:1'];
                $rules['region_ids.*'] = [Rule::exists('regions', 'id')->whereNull('deleted_at')];
            } else {
                $rules['region_ids'] = ['nullable', 'array'];
                $rules['region_ids.*'] = [Rule::exists('regions', 'id')->whereNull('deleted_at')];
            }

            if ($scopeLevel === 'cluster') {
                $rules['cluster_ids'] = ['sometimes', 'array', 'min:1'];
                $rules['cluster_ids.*'] = [Rule::exists('clusters', 'id')->whereNull('deleted_at')];
            } else {
                $rules['cluster_ids'] = ['nullable', 'array'];
                $rules['cluster_ids.*'] = [Rule::exists('clusters', 'id')->whereNull('deleted_at')];
            }
        } else {
            $rules['badanusaha_ids'] = ['nullable', 'array'];
            $rules['divisi_ids'] = ['nullable', 'array'];
            $rules['region_ids'] = ['nullable', 'array'];
            $rules['cluster_ids'] = ['nullable', 'array'];
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'username.unique' => 'Username sudah digunakan',
            'username.regex' => 'Username tidak boleh mengandung spasi',
            'username.alpha_dash' => 'Username hanya boleh huruf, angka, dash dan underscore',
            'password.min' => 'Password minimal 8 karakter',
            'role_id.exists' => 'Role tidak ditemukan',
            'badanusaha_ids.min' => 'Badan usaha wajib dipilih untuk role ini',
            'divisi_ids.min' => 'Divisi wajib dipilih untuk role ini',
            'region_ids.min' => 'Region wajib dipilih untuk role ini',
            'cluster_ids.min' => 'Cluster wajib dipilih untuk role ini',
        ];
    }
}
