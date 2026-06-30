<?php

namespace App\Http\Controllers\API\Management\Concerns;

use App\Exceptions\Api\BadRequestException;
use App\Exceptions\Api\ForbiddenException;
use App\Exceptions\Api\ResourceNotFoundException;
use App\Models\User;
use App\Support\OrganizationalDeleteGuard;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

trait HandlesOrganizationalManagement
{
    protected function ensurePermission(User $user, string $permission, string $message): void
    {
        if (! $user->can($permission)) {
            throw new ForbiddenException($message);
        }
    }

    protected function findScopedOrFail(
        Builder $query,
        User $user,
        int $id,
        callable $scopeApplier,
        string $notFoundMessage,
    ): Model {
        $scopedQuery = $scopeApplier($query, $user);

        $record = $scopedQuery->whereKey($id)->first();

        if (! $record) {
            throw new ResourceNotFoundException($notFoundMessage);
        }

        return $record;
    }

    protected function deleteOrFail(Model $record): void
    {
        if (OrganizationalDeleteGuard::hasDependencies($record)) {
            throw new BadRequestException(OrganizationalDeleteGuard::blockMessage($record));
        }

        $record->delete();
    }

    protected function successResponse(
        mixed $data,
        string $message = 'berhasil',
        int $code = 200,
        array $extraMeta = [],
    ): JsonResponse {
        return response()->json([
            'meta' => array_merge([
                'code' => $code,
                'status' => 'success',
                'message' => $message,
            ], $extraMeta),
            'data' => $data instanceof JsonResource ? $data : $data,
            'errors' => null,
        ], $code);
    }
}
