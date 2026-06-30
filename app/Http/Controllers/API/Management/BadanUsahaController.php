<?php

namespace App\Http\Controllers\API\Management;

use App\Http\Controllers\API\Management\Concerns\HandlesOrganizationalManagement;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\Management\StoreBadanUsahaRequest;
use App\Http\Requests\API\Management\UpdateBadanUsahaRequest;
use App\Http\Resources\BadanUsahaResource;
use App\Models\BadanUsaha;
use App\Support\OrganizationalManagementScope;
use App\Support\OrganizationalName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class BadanUsahaController extends Controller
{
    use HandlesOrganizationalManagement;

    public function index(Request $request)
    {
        $user = Auth::user();
        $this->ensurePermission($user, 'ViewAny:BadanUsaha', 'Anda tidak memiliki akses untuk melihat daftar badan usaha');

        $request->validate([
            'search' => 'sometimes|string',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = OrganizationalManagementScope::applyBadanUsahaQuery(BadanUsaha::query()->active(), $user)
            ->orderBy('code');

        if ($request->filled('search')) {
            $query = OrganizationalName::applySearch($query, (string) $request->input('search'));
        }

        $perPage = min((int) $request->input('per_page', 25), 100);
        $records = $query->simplePaginate($perPage);

        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'Daftar badan usaha berhasil diambil',
                'pagination' => [
                    'current_page' => $records->currentPage(),
                    'per_page' => $records->perPage(),
                    'has_more_pages' => $records->hasMorePages(),
                ],
            ],
            'data' => BadanUsahaResource::collection($records),
            'errors' => null,
        ]);
    }

    public function show(int $id)
    {
        $user = Auth::user();
        $this->ensurePermission($user, 'ViewAny:BadanUsaha', 'Anda tidak memiliki akses untuk melihat detail badan usaha');

        $record = $this->findScopedOrFail(
            BadanUsaha::query()->active(),
            $user,
            $id,
            fn ($query, $actor) => OrganizationalManagementScope::applyBadanUsahaQuery($query, $actor),
            'Badan usaha tidak ditemukan',
        );

        return $this->successResponse(new BadanUsahaResource($record), 'Detail badan usaha berhasil diambil');
    }

    public function store(StoreBadanUsahaRequest $request)
    {
        $user = Auth::user();
        $this->ensurePermission($user, 'Create:BadanUsaha', 'Anda tidak memiliki akses untuk membuat badan usaha');
        OrganizationalManagementScope::assertCanCreateAtLevel($user, 'badanusaha');

        $record = BadanUsaha::create([
            'code' => OrganizationalName::formatCode($request->input('code')),
            'name' => trim((string) $request->input('name')),
        ]);

        return $this->successResponse(new BadanUsahaResource($record), 'Badan usaha berhasil dibuat', 201);
    }

    public function update(UpdateBadanUsahaRequest $request, int $id)
    {
        $user = Auth::user();
        $this->ensurePermission($user, 'Update:BadanUsaha', 'Anda tidak memiliki akses untuk mengubah badan usaha');

        $record = $this->findScopedOrFail(
            BadanUsaha::query()->active(),
            $user,
            $id,
            fn ($query, $actor) => OrganizationalManagementScope::applyBadanUsahaQuery($query, $actor),
            'Badan usaha tidak ditemukan',
        );

        $payload = [];

        if ($request->has('code')) {
            $payload['code'] = OrganizationalName::formatCode($request->input('code'));
        }

        if ($request->has('name')) {
            $payload['name'] = trim((string) $request->input('name'));
        }

        $record->update($payload);

        return $this->successResponse(new BadanUsahaResource($record->fresh()), 'Badan usaha berhasil diperbarui');
    }

    public function destroy(int $id)
    {
        $user = Auth::user();
        $this->ensurePermission($user, 'Delete:BadanUsaha', 'Anda tidak memiliki akses untuk menghapus badan usaha');

        $record = $this->findScopedOrFail(
            BadanUsaha::query()->active(),
            $user,
            $id,
            fn ($query, $actor) => OrganizationalManagementScope::applyBadanUsahaQuery($query, $actor),
            'Badan usaha tidak ditemukan',
        );

        $this->deleteOrFail($record);

        return $this->successResponse(null, 'Badan usaha berhasil dihapus');
    }
}
