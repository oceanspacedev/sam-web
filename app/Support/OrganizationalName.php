<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class OrganizationalName
{
    public static function formatCode(?string $value): string
    {
        $value = trim((string) $value);
        $value = preg_replace('/\s+/', '_', $value) ?? $value;

        return Str::upper($value);
    }

    public static function normalizeLookup(?string $value): string
    {
        return Str::upper(str_replace([' ', '_'], '', trim((string) $value)));
    }

    public static function label(?Model $record): ?string
    {
        if (! $record) {
            return null;
        }

        $code = trim((string) ($record->code ?? ''));
        $name = trim((string) ($record->name ?? ''));

        if ($code === '') {
            return $name !== '' ? $name : null;
        }

        if ($name === '' || self::normalizeLookup($name) === self::normalizeLookup($code)) {
            return $code;
        }

        return "{$code} - {$name}";
    }

    public static function optionList(Builder $query): array
    {
        return $query
            ->get(['id', 'code', 'name'])
            ->mapWithKeys(fn (Model $record): array => [$record->getKey() => self::label($record)])
            ->toArray();
    }

    public static function applySearch(Builder $query, string $keyword): Builder
    {
        $keyword = trim($keyword);

        if ($keyword === '') {
            return $query;
        }

        return $query->where(function (Builder $query) use ($keyword): void {
            $query
                ->where('code', 'like', '%'.$keyword.'%')
                ->orWhere('name', 'like', '%'.$keyword.'%');
        });
    }

    public static function resource(?Model $record): ?array
    {
        if (! $record) {
            return null;
        }

        return [
            'id' => $record->id,
            'code' => $record->code,
            'name' => $record->name,
            'display_name' => self::label($record),
        ];
    }
}
