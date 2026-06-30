<?php

namespace App\Http\Controllers\API\Management;

use App\Exceptions\Api\BadRequestException;
use App\Http\Controllers\API\Management\Concerns\HandlesOrganizationalManagement;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\Management\StoreClusterRequest;
use App\Http\Requests\API\Management\UpdateClusterRequest;
use App\Http\Resources\ClusterResource;
use App\Models\Cluster;
use App\Support\OrganizationalManagementScope;
use App\Support\OrganizationalName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ClusterController extends Controller
{
    use HandlesOrganizationalManagement;

    public function index(Request $request)
    {
        $user = Auth::user();
        $this->ensurePermission($user, 'ViewAny:Cluster', 'Anda tidak memiliki akses untuk melihat daftar cluster');

        $request->validate([
            'search' => 'sometimes|string',
            'badanusaha_id' => 'sometimes|integer',
            'divisi_id' => 'sometimes|integer',
            'region_id' => 'sometimes|integer',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = OrganizationalManagementScope::applyClusterQuery(Cluster::query()->active(), $user)
            ->with(['badanusaha', 'divisi', 'region'])
            ->orderBy('code');

        if ($request->filled('badanusaha_id')) {
            $query->where('badanusaha_id', (int) $request->input('badanusaha_id'));
        }

        if ($request->filled('divisi_id')) {
            $query->where('divisi_id', (int) $request->input('divisi_id'));
        }

        if ($request->filled('region_id')) {
            $query->where('region_id', (int) $request->input('region_id'));
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
                'message' => 'Daftar cluster berhasil diambil',
                'pagination' => [
                    'current_page' => $records->currentPage(),
                    'per_page' => $records->perPage(),
                    'has_more_pages' => $records->hasMorePages(),
                ],
            ],
            'data' => ClusterResource::collection($records),
            'errors' => null,
        ]);
    }

    public function show(int $id)
    {
        $user = Auth::user();
        $this->ensurePermission($user, 'ViewAny:Cluster', 'Anda tidak memiliki akses untuk melihat detail cluster');

        $record = $this->findScopedOrFail(
            Cluster::query()->active()->with(['badanusaha', 'divisi', 'region']),
            $user,
            $id,
            fn ($query, $actor) => OrganizationalManagementScope::applyClusterQuery($query, $actor),
            'Cluster tidak ditemukan',
        );

        return $this->successResponse(new ClusterResource($record), 'Detail cluster berhasil diambil');
    }

    public function store(StoreClusterRequest $request)
    {
        $user = Auth::user();
        $this->ensurePermission($user, 'Create:Cluster', 'Anda tidak memiliki akses untuk membuat cluster');
        OrganizationalManagementScope::assertCanCreateAtLevel($user, 'cluster');

        $parent = OrganizationalManagementScope::findVisibleRegion($user, (int) $request->input('region_id'));

        if (! $parent) {
            throw new BadRequestException('Region tidak ditemukan atau di luar scope Anda.');
        }

        $record = Cluster::create([
            'region_id' => $parent->id,
            'code' => OrganizationalName::formatCode($request->input('code')),
            'name' => trim((string) $request->input('name')),
        ]);

        $record->load(['badanusaha', 'divisi', 'region']);

        return $this->successResponse(new ClusterResource($record), 'Cluster berhasil dibuat', 201);
    }

    public function update(UpdateClusterRequest $request, int $id)
    {
        $user = Auth::user();
        $this->ensurePermission($user, 'Update:Cluster', 'Anda tidak memiliki akses untuk mengubah cluster');

        $record = $this->findScopedOrFail(
            Cluster::query()->active(),
            $user,
            $id,
            fn ($query, $actor) => OrganizationalManagementScope::applyClusterQuery($query, $actor),
            'Cluster tidak ditemukan',
        );

        $payload = [];

        if ($request->has('region_id')) {
            $parent = OrganizationalManagementScope::findVisibleRegion($user, (int) $request->input('region_id'));

            if (! $parent) {
                throw new BadRequestException('Region tidak ditemukan atau di luar scope Anda.');
            }

            $payload['region_id'] = $parent->id;
        }

        if ($request->has('code')) {
            $payload['code'] = OrganizationalName::formatCode($request->input('code'));
        }

        if ($request->has('name')) {
            $payload['name'] = trim((string) $request->input('name'));
        }

        $record->update($payload);
        $record->load(['badanusaha', 'divisi', 'region']);

        return $this->successResponse(new ClusterResource($record->fresh(['badanusaha', 'divisi', 'region'])), 'Cluster berhasil diperbarui');
    }

    public function destroy(int $id)
    {
        $user = Auth::user();
        $this->ensurePermission($user, 'Delete:Cluster', 'Anda tidak memiliki akses untuk menghapus cluster');

        $record = $this->findScopedOrFail(
            Cluster::query()->active(),
            $user,
            $id,
            fn ($query, $actor) => OrganizationalManagementScope::applyClusterQuery($query, $actor),
            'Cluster tidak ditemukan',
        );

        $this->deleteOrFail($record);

        return $this->successResponse(null, 'Cluster berhasil dihapus');
    }
}
