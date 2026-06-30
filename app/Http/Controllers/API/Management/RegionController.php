<?php

namespace App\Http\Controllers\API\Management;

use App\Exceptions\Api\BadRequestException;
use App\Http\Controllers\API\Management\Concerns\HandlesOrganizationalManagement;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\Management\StoreRegionRequest;
use App\Http\Requests\API\Management\UpdateRegionRequest;
use App\Http\Resources\RegionResource;
use App\Models\Region;
use App\Support\OrganizationalManagementScope;
use App\Support\OrganizationalName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RegionController extends Controller
{
    use HandlesOrganizationalManagement;

    public function index(Request $request)
    {
        $user = Auth::user();
        $this->ensurePermission($user, 'ViewAny:Region', 'Anda tidak memiliki akses untuk melihat daftar region');

        $request->validate([
            'search' => 'sometimes|string',
            'badanusaha_id' => 'sometimes|integer',
            'divisi_id' => 'sometimes|integer',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = OrganizationalManagementScope::applyRegionQuery(Region::query()->active(), $user)
            ->with(['badanusaha', 'divisi'])
            ->orderBy('code');

        if ($request->filled('badanusaha_id')) {
            $query->where('badanusaha_id', (int) $request->input('badanusaha_id'));
        }

        if ($request->filled('divisi_id')) {
            $query->where('divisi_id', (int) $request->input('divisi_id'));
        }

        if ($request->filled('search')) {
            $query = OrganizationalName::applySearch($query, (string) $request->input('search'));
        }

        $perPage = min((int) $request->input('per_page', 25), 100);
        $records = $query->simplePaginate($perPage);

        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'Daftar region berhasil diambil',
                'pagination' => [
                    'current_page' => $records->currentPage(),
                    'per_page' => $records->perPage(),
                    'has_more_pages' => $records->hasMorePages(),
                ],
            ],
            'data' => RegionResource::collection($records),
            'errors' => null,
        ]);
    }

    public function show(int $id)
    {
        $user = Auth::user();
        $this->ensurePermission($user, 'ViewAny:Region', 'Anda tidak memiliki akses untuk melihat detail region');

        $record = $this->findScopedOrFail(
            Region::query()->active()->with(['badanusaha', 'divisi']),
            $user,
            $id,
            fn ($query, $actor) => OrganizationalManagementScope::applyRegionQuery($query, $actor),
            'Region tidak ditemukan',
        );

        return $this->successResponse(new RegionResource($record), 'Detail region berhasil diambil');
    }

    public function store(StoreRegionRequest $request)
    {
        $user = Auth::user();
        $this->ensurePermission($user, 'Create:Region', 'Anda tidak memiliki akses untuk membuat region');
        OrganizationalManagementScope::assertCanCreateAtLevel($user, 'region');

        $parent = OrganizationalManagementScope::findVisibleDivision($user, (int) $request->input('divisi_id'));

        if (! $parent) {
            throw new BadRequestException('Divisi tidak ditemukan atau di luar scope Anda.');
        }

        $record = Region::create([
            'divisi_id' => $parent->id,
            'code' => OrganizationalName::formatCode($request->input('code')),
            'name' => trim((string) $request->input('name')),
        ]);

        $record->load(['badanusaha', 'divisi']);

        return $this->successResponse(new RegionResource($record), 'Region berhasil dibuat', 201);
    }

    public function update(UpdateRegionRequest $request, int $id)
    {
        $user = Auth::user();
        $this->ensurePermission($user, 'Update:Region', 'Anda tidak memiliki akses untuk mengubah region');

        $record = $this->findScopedOrFail(
            Region::query()->active(),
            $user,
            $id,
            fn ($query, $actor) => OrganizationalManagementScope::applyRegionQuery($query, $actor),
            'Region tidak ditemukan',
        );

        $payload = [];

        if ($request->has('divisi_id')) {
            $parent = OrganizationalManagementScope::findVisibleDivision($user, (int) $request->input('divisi_id'));

            if (! $parent) {
                throw new BadRequestException('Divisi tidak ditemukan atau di luar scope Anda.');
            }

            $payload['divisi_id'] = $parent->id;
        }

        if ($request->has('code')) {
            $payload['code'] = OrganizationalName::formatCode($request->input('code'));
        }

        if ($request->has('name')) {
            $payload['name'] = trim((string) $request->input('name'));
        }

        $record->update($payload);
        $record->load(['badanusaha', 'divisi']);

        return $this->successResponse(new RegionResource($record->fresh(['badanusaha', 'divisi'])), 'Region berhasil diperbarui');
    }

    public function destroy(int $id)
    {
        $user = Auth::user();
        $this->ensurePermission($user, 'Delete:Region', 'Anda tidak memiliki akses untuk menghapus region');

        $record = $this->findScopedOrFail(
            Region::query()->active(),
            $user,
            $id,
            fn ($query, $actor) => OrganizationalManagementScope::applyRegionQuery($query, $actor),
            'Region tidak ditemukan',
        );

        $this->deleteOrFail($record);

        return $this->successResponse(null, 'Region berhasil dihapus');
    }
}
