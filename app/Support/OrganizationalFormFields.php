<?php

namespace App\Support;

use Closure;
use Filament\Forms\Components\TextInput;
use Illuminate\Database\Eloquent\Model;

class OrganizationalFormFields
{
    public static function code(string $modelClass, ?string $parentColumn = null, ?string $parentStatePath = null): TextInput
    {
        return TextInput::make('code')
            ->label('Kode')
            ->required()
            ->maxLength(255)
            ->helperText('Kode unik untuk sistem. Teks akan otomatis diformat menjadi huruf besar dan spasi diganti garis bawah.')
            ->dehydrateStateUsing(fn ($state): string => OrganizationalName::formatCode($state))
            ->rule(self::uniqueRule($modelClass, 'code', $parentColumn, $parentStatePath, 'Kode sudah digunakan pada parent yang sama.'));
    }

    public static function name(string $modelClass, ?string $parentColumn = null, ?string $parentStatePath = null): TextInput
    {
        return TextInput::make('name')
            ->label('Nama Deskriptif')
            ->required()
            ->maxLength(255)
            ->helperText('Nama lengkap yang akan ditampilkan pada aplikasi.')
            ->dehydrateStateUsing(fn ($state): string => trim((string) $state))
            ->rule(self::uniqueRule($modelClass, 'name', $parentColumn, $parentStatePath, 'Nama sudah digunakan pada parent yang sama.'));
    }

    public static function ensureUnique(
        string $modelClass,
        string $column,
        mixed $value,
        ?string $parentColumn = null,
        mixed $parentValue = null,
        ?int $ignoreId = null,
    ): bool {
        $value = $column === 'code'
            ? OrganizationalName::formatCode($value)
            : trim((string) $value);

        if ($value === '') {
            return true;
        }

        $query = $modelClass::query()->where($column, $value);

        if ($parentColumn !== null) {
            if (! $parentValue) {
                return true;
            }

            $query->where($parentColumn, $parentValue);
        }

        if ($ignoreId) {
            $query->whereKeyNot($ignoreId);
        }

        return ! $query->exists();
    }

    private static function uniqueRule(
        string $modelClass,
        string $column,
        ?string $parentColumn,
        ?string $parentStatePath,
        string $message,
    ): Closure {
        return function (?Model $record = null, ?callable $get = null) use ($modelClass, $column, $parentColumn, $parentStatePath, $message): Closure {
            return function (string $attribute, mixed $value, Closure $fail) use ($modelClass, $column, $parentColumn, $parentStatePath, $message, $record, $get): void {
                $parentValue = $parentStatePath && $get ? $get($parentStatePath) : null;
                $ignoreId = $record?->getKey();

                if (! self::ensureUnique($modelClass, $column, $value, $parentColumn, $parentValue, $ignoreId)) {
                    $fail($message);
                }
            };
        };
    }
}
