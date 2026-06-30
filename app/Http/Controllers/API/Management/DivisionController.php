<?php

namespace App\Http\Controllers\API\Management;

use App\Exceptions\Api\BadRequestException;
use App\Http\Controllers\API\Management\Concerns\HandlesOrganizationalManagement;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\Management\StoreDivisionRequest;
use App\Http\Requests\API\Management\UpdateDivisionRequest;
use App\Http\Resources\DivisionResource;
use App\Models\Division;
use App\Support\OrganizationalManagementScope;
use App\Support\OrganizationalName;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class DivisionController extends Controller
{
    use HandlesOrganizationalManagement;

    public function index(Request $request)
    {
        $user = Auth::user();
        $this->ensurePermission($user, 'ViewAny:Division', 'Anda tidak memiliki akses untuk melihat daftar divisi');

        $request->validate([
            'search' => 'sometimes|string',
            'badanusaha_id' => 'sometimes|integer',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $query = OrganizationalManagementScope::applyDivisionQuery(Division::query()->active(), $user)
            ->with('badanusaha')
            ->orderBy('code');

        if ($request->filled('badanusaha_id')) {
            $query->where('badanusaha_id', (int) $request->input('badanusaha_id'));
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
                'message' => 'Daftar divisi berhasil diambil',
                'pagination' => [
                    'current_page' => $records->currentPage(),
                    'per_page' => $records->perPage(),
                    'has_more_pages' => $records->hasMorePages(),
                ],
            ],
            'data' => DivisionResource::collection($records),
            'errors' => null,
        ]);
    }

    public function show(int $id)
    {
        $user = Auth::user();
        $this->ensurePermission($user, 'ViewAny:Division', 'Anda tidak memiliki akses untuk melihat detail divisi');

        $record = $this->findScopedOrFail(
            Division::query()->active()->with('badanusaha'),
            $user,
            $id,
            fn ($query, $actor) => OrganizationalManagementScope::applyDivisionQuery($query, $actor),
            'Divisi tidak ditemukan',
        );

        return $this->successResponse(new DivisionResource($record), 'Detail divisi berhasil diambil');
    }

    public function store(StoreDivisionRequest $request)
    {
        $user = Auth::user();
        $this->ensurePermission($user, 'Create:Division', 'Anda tidak memiliki akses untuk membuat divisi');
        OrganizationalManagementScope::assertCanCreateAtLevel($user, 'divisi');

        $parent = OrganizationalManagementScope::findVisibleBadanUsaha($user, (int) $request->input('badanusaha_id'));

        if (! $parent) {
            throw new BadRequestException('Badan usaha tidak ditemukan atau di luar scope Anda.');
        }

        $record = Division::create([
            'badanusaha_id' => $parent->id,
            'code' => OrganizationalName::formatCode($request->input('code')),
            'name' => trim((string) $request->input('name')),
        ]);

        $record->load('badanusaha');

        return $this->successResponse(new DivisionResource($record), 'Divisi berhasil dibuat', 201);
    }

    public function update(UpdateDivisionRequest $request, int $id)
    {
        $user = Auth::user();
        $this->ensurePermission($user, 'Update:Division', 'Anda tidak memiliki akses untuk mengubah divisi');

        $record = $this->findScopedOrFail(
            Division::query()->active(),
            $user,
            $id,
            fn ($query, $actor) => OrganizationalManagementScope::applyDivisionQuery($query, $actor),
            'Divisi tidak ditemukan',
        );

        $payload = [];

        if ($request->has('badanusaha_id')) {
            $parent = OrganizationalManagementScope::findVisibleBadanUsaha($user, (int) $request->input('badanusaha_id'));

            if (! $parent) {
                throw new BadRequestException('Badan usaha tidak ditemukan atau di luar scope Anda.');
            }

            $payload['badanusaha_id'] = $parent->id;
        }

        if ($request->has('code')) {
            $payload['code'] = OrganizationalName::formatCode($request->input('code'));
        }

        if ($request->has('name')) {
            $payload['name'] = trim((string) $request->input('name'));
        }

        $record->update($payload);
        $record->load('badanusaha');

        return $this->successResponse(new DivisionResource($record->fresh(['badanusaha'])), 'Divisi berhasil diperbarui');
    }

    public function destroy(int $id)
    {
        $user = Auth::user();
        $this->ensurePermission($user, 'Delete:Division', 'Anda tidak memiliki akses untuk menghapus divisi');

        $record = $this->findScopedOrFail(
            Division::query()->active(),
            $user,
            $id,
            fn ($query, $actor) => OrganizationalManagementScope::applyDivisionQuery($query, $actor),
            'Divisi tidak ditemukan',
        );

        $this->deleteOrFail($record);

        return $this->successResponse(null, 'Divisi berhasil dihapus');
    }
}
