<?php

namespace App\Http\Requests\API\Management;

use App\Support\OrganizationalFormFields;
use App\Support\OrganizationalName;
use Illuminate\Foundation\Http\FormRequest;

abstract class OrganizationalManagementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array{model: class-string, parentColumn: ?string, parentKey: ?string}
     */
    abstract protected function uniqueContext(): array;

    protected function prepareForValidation(): void
    {
        if ($this->has('code')) {
            $this->merge([
                'code' => OrganizationalName::formatCode($this->input('code')),
            ]);
        }

        if ($this->has('name')) {
            $this->merge([
                'name' => trim((string) $this->input('name')),
            ]);
        }
    }

    public function withValidator($validator): void
    {
        $validator->after(function ($validator): void {
            ['model' => $modelClass, 'parentColumn' => $parentColumn, 'parentKey' => $parentKey] = $this->uniqueContext();

            $parentValue = $this->resolveParentValue($parentColumn, $parentKey);
            $ignoreId = $this->route('id') ? (int) $this->route('id') : null;

            if ($this->filled('code') && ! OrganizationalFormFields::ensureUnique($modelClass, 'code', $this->input('code'), $parentColumn, $parentValue, $ignoreId)) {
                $validator->errors()->add('code', 'Kode sudah digunakan pada parent yang sama.');
            }

            if ($this->filled('name') && ! OrganizationalFormFields::ensureUnique($modelClass, 'name', $this->input('name'), $parentColumn, $parentValue, $ignoreId)) {
                $validator->errors()->add('name', 'Nama sudah digunakan pada parent yang sama.');
            }
        });
    }

    protected function resolveParentValue(?string $parentColumn, ?string $parentKey): mixed
    {
        if ($parentKey !== null && $this->filled($parentKey)) {
            return $this->input($parentKey);
        }

        if ($parentColumn === null || ! $this->route('id')) {
            return null;
        }

        ['model' => $modelClass] = $this->uniqueContext();
        $record = $modelClass::query()->find($this->route('id'));

        return $record?->getAttribute($parentColumn);
    }
}
