<?php

namespace App\Http\Controllers;

use App\Exceptions\Api\ResourceNotFoundException;
use App\Exceptions\Api\UnauthorizedException;
use App\Http\Resources\BadanUsahaResource;
use App\Http\Resources\ClusterResource;
use App\Http\Resources\DivisionResource;
use App\Http\Resources\RegionResource;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class SettingController extends Controller
{
    /**
     * Get All BadanUsaha (Business Units)
     *
     * Retrieves all business units with role-based filtering.
     * First level of organizational hierarchy.
     *
     * @authenticated
     *
     * @response 200 {
     *   "meta": {
     *     "code": 200,
     *     "status": "success",
     *     "message": "berhasil"
     *   },
     *   "data": [
     *     {"id": 1, "name": "CV.MAJU"},
     *     {"id": 2, "name": "PT.TEKNOLOGI"}
     *   ],
     *   "errors": null
     * }
     */
    public function getbadanusaha(Request $request)
    {
        try {
            $user = Auth::user();

            // CRITICAL: Block access if user or role is null
            if (! $user || ! $user->role) {
                throw new UnauthorizedException;
            }

            $scopeLevel = $user->role->organizational_scope_level;

            // CRITICAL: Block access if scope level is null
            if (! $scopeLevel) {
                throw new UnauthorizedException;
            }

            $query = BadanUsaha::active();

            // Apply scope filtering
            if ($scopeLevel !== 'all') {
                $badanUsahaIds = $user->badanUsahas()->pluck('badan_usahas.id')->toArray();

                // CRITICAL: If user has no assignments, return empty
                if (empty($badanUsahaIds)) {
                    return response()->json([
                        'meta' => [
                            'code' => 200,
                            'status' => 'success',
                            'message' => 'berhasil',
                        ],
                        'data' => [],
                        'errors' => null,
                    ]);
                }

                $query->whereIn('id', $badanUsahaIds);
            }

            $badanUsahas = $query->get();

            return BadanUsahaResource::collection($badanUsahas)->additional([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'berhasil',
                ],
                'errors' => null,
            ]);
        } catch (Exception $e) {
            Log::error('getBadanUsaha failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getdivisi(Request $request)
    {
        try {
            $user = Auth::user();

            // CRITICAL: Block access if user or role is null
            if (! $user || ! $user->role) {
                throw new UnauthorizedException;
            }

            $scopeLevel = $user->role->organizational_scope_level;

            // CRITICAL: Block access if scope level is null
            if (! $scopeLevel) {
                throw new UnauthorizedException;
            }

            $query = Division::active();

            // Apply scope filtering first
            if ($scopeLevel !== 'all') {
                $badanUsahaIds = $user->badanUsahas()->pluck('badan_usahas.id')->toArray();
                $divisiIds = $user->divisis()->pluck('divisions.id')->toArray();

                // CRITICAL: If user has no assignments, return empty
                $hasAnyAssignment = ! empty($badanUsahaIds) || ! empty($divisiIds);
                if (! $hasAnyAssignment) {
                    return response()->json([
                        'meta' => [
                            'code' => 200,
                            'status' => 'success',
                            'message' => 'berhasil',
                        ],
                        'data' => [],
                        'errors' => null,
                    ]);
                }

                // Filter by user's organizational scope
                if (! empty($badanUsahaIds)) {
                    $query->whereIn('badanusaha_id', $badanUsahaIds);
                }
                if (! empty($divisiIds)) {
                    $query->whereIn('id', $divisiIds);
                }
            }

            // Then apply optional filter by business unit parameter
            $query->when($request->filled('bu'), function ($q) use ($request) {
                $bu = $request->input('bu');
                $badanUsaha = is_numeric($bu)
                    ? BadanUsaha::findOrFail($bu)
                    : BadanUsaha::where('name', $bu)->firstOrFail();

                $q->where('badanusaha_id', $badanUsaha->id);
            });

            $divisions = $query->get();

            return DivisionResource::collection($divisions)->additional([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'berhasil',
                ],
                'errors' => null,
            ]);
        } catch (Exception $e) {
            Log::error('getDivisi failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    public function getregion(Request $request)
    {
        try {
            $user = Auth::user();

            // CRITICAL: Block access if user or role is null
            if (! $user || ! $user->role) {
                throw new UnauthorizedException;
            }

            $scopeLevel = $user->role->organizational_scope_level;

            // CRITICAL: Block access if scope level is null
            if (! $scopeLevel) {
                throw new UnauthorizedException;
            }

            $query = Region::active();

            // Track selected parents to avoid same-name collisions
            $selectedBadanUsaha = null;

            // Apply scope filtering first
            if ($scopeLevel !== 'all') {
                $badanUsahaIds = $user->badanUsahas()->pluck('badan_usahas.id')->toArray();
                $divisiIds = $user->divisis()->pluck('divisions.id')->toArray();
                $regionIds = $user->regions()->pluck('regions.id')->toArray();

                // CRITICAL: If user has no assignments, return empty
                $hasAnyAssignment = ! empty($badanUsahaIds) || ! empty($divisiIds) || ! empty($regionIds);
                if (! $hasAnyAssignment) {
                    return response()->json([
                        'meta' => [
                            'code' => 200,
                            'status' => 'success',
                            'message' => 'berhasil',
                        ],
                        'data' => [],
                        'errors' => null,
                    ]);
                }

                // Filter by user's organizational scope
                if (! empty($badanUsahaIds)) {
                    $query->whereIn('badanusaha_id', $badanUsahaIds);
                }
                if (! empty($divisiIds)) {
                    $query->whereIn('divisi_id', $divisiIds);
                }
                if (! empty($regionIds)) {
                    $query->whereIn('id', $regionIds);
                }
            }

            // Optional BU filter
            if ($request->filled('bu')) {
                $bu = $request->input('bu');
                $selectedBadanUsaha = is_numeric($bu)
                    ? BadanUsaha::findOrFail($bu)
                    : BadanUsaha::where('name', $bu)->firstOrFail();

                $query->where('badanusaha_id', $selectedBadanUsaha->id);
            }

            // Then apply optional filter by division parameter, scoped by BU if provided
            if ($request->filled('div')) {
                $div = $request->input('div');

                $divisionQuery = Division::query();
                if ($selectedBadanUsaha) {
                    $divisionQuery->where('badanusaha_id', $selectedBadanUsaha->id);
                }

                $division = is_numeric($div)
                    ? $divisionQuery->where('id', $div)->firstOrFail()
                    : $divisionQuery->where('name', $div)->firstOrFail();

                $query->where('divisi_id', $division->id);

                // If BU was not provided but division resolved, align BU filter to division owner
                if (! $selectedBadanUsaha) {
                    $query->where('badanusaha_id', $division->badanusaha_id);
                }
            }

            $regions = $query->get();

            return RegionResource::collection($regions)->additional([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'berhasil',
                ],
                'errors' => null,
            ]);
        } catch (Exception $e) {
            Log::error('getRegion failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get Clusters with Role-Based and Hierarchical Filtering
     *
     * Retrieves clusters with optional hierarchical filtering.
     * Final level of organizational hierarchy.
     *
     * @authenticated
     *
     * @queryParam bu string optional Business unit name or ID. Example: "CV.MAJU"
     * @queryParam div string optional Division name or ID. Example: "TECNO"
     * @queryParam reg string optional Region name or ID. Example: "JAKARTA"
     *
     * @response 200 {
     *   "meta": {
     *     "code": 200,
     *     "status": "success",
     *     "message": "berhasil"
     *   },
     *   "data": [
     *     {
     *       "id": 1,
     *       "name": "CLUSTER A",
     *       "badanusaha_id": 1,
     *       "divisi_id": 1,
     *       "region_id": 1
     *     }
     *   ],
     *   "errors": null
     * }
     */
    public function getcluster(Request $request)
    {
        try {
            $user = Auth::user();

            // CRITICAL: Block access if user or role is null
            if (! $user || ! $user->role) {
                throw new UnauthorizedException;
            }

            $scopeLevel = $user->role->organizational_scope_level;

            // CRITICAL: Block access if scope level is null
            if (! $scopeLevel) {
                throw new UnauthorizedException;
            }

            $query = Cluster::active();

            // Apply scope filtering first
            if ($scopeLevel !== 'all') {
                $badanUsahaIds = $user->badanUsahas()->pluck('badan_usahas.id')->toArray();
                $divisiIds = $user->divisis()->pluck('divisions.id')->toArray();
                $regionIds = $user->regions()->pluck('regions.id')->toArray();
                $clusterIds = $user->clusters()->pluck('clusters.id')->toArray();

                // CRITICAL: If user has no assignments, return empty
                $hasAnyAssignment = ! empty($badanUsahaIds) || ! empty($divisiIds) || ! empty($regionIds) || ! empty($clusterIds);
                if (! $hasAnyAssignment) {
                    return response()->json([
                        'meta' => [
                            'code' => 200,
                            'status' => 'success',
                            'message' => 'berhasil',
                        ],
                        'data' => [],
                        'errors' => null,
                    ]);
                }

                // Filter by user's organizational scope
                if (! empty($badanUsahaIds)) {
                    $query->whereIn('badanusaha_id', $badanUsahaIds);
                }
                if (! empty($divisiIds)) {
                    $query->whereIn('divisi_id', $divisiIds);
                }
                if (! empty($regionIds)) {
                    $query->whereIn('region_id', $regionIds);
                }
                if (! empty($clusterIds)) {
                    $query->whereIn('id', $clusterIds);
                }
            }

            // Then apply optional hierarchical filters (respect parent selections to avoid same-name collisions)
            $badanUsaha = null;
            $division = null;

            if ($request->filled('bu')) {
                $bu = $request->input('bu');
                $badanUsaha = is_numeric($bu)
                    ? BadanUsaha::findOrFail($bu)
                    : BadanUsaha::where('name', $bu)->firstOrFail();

                $query->where('badanusaha_id', $badanUsaha->id);
            }

            if ($request->filled('div')) {
                $div = $request->input('div');
                $divisionQuery = Division::query();

                if ($badanUsaha) {
                    $divisionQuery->where('badanusaha_id', $badanUsaha->id);
                }

                $division = is_numeric($div)
                    ? $divisionQuery->where('id', $div)->firstOrFail()
                    : $divisionQuery->where('name', $div)->firstOrFail();

                $query->where('divisi_id', $division->id);
            }

            if ($request->filled('reg')) {
                $reg = $request->input('reg');
                $regionQuery = Region::query();

                if ($badanUsaha) {
                    $regionQuery->where('badanusaha_id', $badanUsaha->id);
                }

                if ($division) {
                    $regionQuery->where('divisi_id', $division->id);
                }

                $region = is_numeric($reg)
                    ? $regionQuery->where('id', $reg)->firstOrFail()
                    : $regionQuery->where('name', $reg)->firstOrFail();

                $query->where('region_id', $region->id);
            }

            $clusters = $query->get();

            return ClusterResource::collection($clusters)->additional([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'berhasil',
                ],
                'errors' => null,
            ]);
        } catch (Exception $e) {
            Log::error('getCluster failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get form options based on role's organizational scope
     *
     * Returns which organizational fields should be visible/required
     * and their available options based on user's role and assignments.
     *
     * @queryParam role_id int optional Role ID to check scope requirements
     *
     * @response 200 {
     *   "meta": {"code": 200, "status": "success", "message": "berhasil"},
     *   "data": {
     *     "scope_level": "cluster",
     *     "fields": {
     *       "badanusaha": {
     *         "visible": true,
     *         "required": true,
     *         "options": [{"id": 1, "name": "CV.MAJU"}]
     *       },
     *       "divisi": {...},
     *       "region": {...},
     *       "cluster": {...}
     *     }
     *   }
     * }
     */
    public function getFormOptions(Request $request)
    {
        try {
            $user = Auth::user();
            $request->validate([
                'role_id' => 'sometimes|integer|exists:roles,id',
                'include_options' => 'sometimes|boolean',
            ]);

            if (! $user || ! $user->role) {
                throw new UnauthorizedException;
            }

            $includeOptions = $request->boolean('include_options', true);

            // Determine which role to check (for form validation)
            $roleId = $request->query('role_id');
            $targetRole = $roleId ? \App\Models\Role::find($roleId) : $user->role;

            if (! $targetRole) {
                throw new ResourceNotFoundException('Role tidak ditemukan');
            }

            $scopeLevel = $targetRole->organizational_scope_level;

            // Determine field visibility/requirement
            $needsOrgFields = in_array($scopeLevel, ['badanusaha', 'divisi', 'region', 'cluster']);

            $fields = [
                'badanusaha' => [
                    'visible' => $needsOrgFields,
                    'required' => $needsOrgFields,
                    'options' => [],
                ],
                'divisi' => [
                    'visible' => in_array($scopeLevel, ['divisi', 'region', 'cluster']),
                    'required' => in_array($scopeLevel, ['divisi', 'region', 'cluster']),
                    'options' => [],
                ],
                'region' => [
                    'visible' => in_array($scopeLevel, ['region', 'cluster']),
                    'required' => in_array($scopeLevel, ['region', 'cluster']),
                    'options' => [],
                ],
                'cluster' => [
                    'visible' => $scopeLevel === 'cluster',
                    'required' => $scopeLevel === 'cluster',
                    'options' => [],
                ],
            ];

            // Get options based on current user's permissions
            $userScopeLevel = $user->role->organizational_scope_level;

            if ($includeOptions) {
                // BadanUsaha options
                if ($fields['badanusaha']['visible']) {
                    if ($userScopeLevel === 'all') {
                        $fields['badanusaha']['options'] = BadanUsaha::active()->orderBy('name')->get(['id', 'name']);
                    } else {
                        $fields['badanusaha']['options'] = $user->badanUsahas()->active()->orderBy('name')->get(['badan_usahas.id as id', 'name']);
                    }
                }

                // Divisi options - use same logic as getdivisi endpoint
                if ($fields['divisi']['visible']) {
                    $query = Division::active();
                    if ($userScopeLevel !== 'all') {
                        $badanUsahaIds = $user->badanUsahas()->pluck('badan_usahas.id')->toArray();
                        $divisiIds = $user->divisis()->pluck('divisions.id')->toArray();

                        if (! empty($badanUsahaIds)) {
                            $query->whereIn('badanusaha_id', $badanUsahaIds);
                        }
                        if (! empty($divisiIds)) {
                            $query->whereIn('id', $divisiIds);
                        }
                    }
                    $fields['divisi']['options'] = $query->orderBy('name')->get(['id', 'name', 'badanusaha_id']);
                }

                // Region options - use same logic as getregion endpoint
                if ($fields['region']['visible']) {
                    $query = Region::active();
                    if ($userScopeLevel !== 'all') {
                        $badanUsahaIds = $user->badanUsahas()->pluck('badan_usahas.id')->toArray();
                        $divisiIds = $user->divisis()->pluck('divisions.id')->toArray();
                        $regionIds = $user->regions()->pluck('regions.id')->toArray();

                        if (! empty($badanUsahaIds)) {
                            $query->whereIn('badanusaha_id', $badanUsahaIds);
                        }
                        if (! empty($divisiIds)) {
                            $query->whereIn('divisi_id', $divisiIds);
                        }
                        if (! empty($regionIds)) {
                            $query->whereIn('id', $regionIds);
                        }
                    }
                    $fields['region']['options'] = $query->orderBy('name')->get(['id', 'name', 'badanusaha_id', 'divisi_id']);
                }

                // Cluster options - use same logic as getcluster endpoint
                if ($fields['cluster']['visible']) {
                    $query = Cluster::active();
                    if ($userScopeLevel !== 'all') {
                        $badanUsahaIds = $user->badanUsahas()->pluck('badan_usahas.id')->toArray();
                        $divisiIds = $user->divisis()->pluck('divisions.id')->toArray();
                        $regionIds = $user->regions()->pluck('regions.id')->toArray();
                        $clusterIds = $user->clusters()->pluck('clusters.id')->toArray();

                        if (! empty($badanUsahaIds)) {
                            $query->whereIn('badanusaha_id', $badanUsahaIds);
                        }
                        if (! empty($divisiIds)) {
                            $query->whereIn('divisi_id', $divisiIds);
                        }
                        if (! empty($regionIds)) {
                            $query->whereIn('region_id', $regionIds);
                        }
                        if (! empty($clusterIds)) {
                            $query->whereIn('id', $clusterIds);
                        }
                    }
                    $fields['cluster']['options'] = $query->orderBy('name')->get(['id', 'name', 'badanusaha_id', 'divisi_id', 'region_id']);
                }
            }

            return response()->json([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'berhasil',
                ],
                'data' => [
                    'scope_level' => $scopeLevel,
                    'fields' => $fields,
                ],
                'errors' => null,
            ]);
        } catch (Exception $e) {
            Log::error('getFormOptions failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Get role options based on user hierarchy
     *
     * Returns roles that the current user can assign.
     * SUPER ADMIN sees all roles, others see only descendant roles.
     *
     * @response 200 {
     *   "meta": {"code": 200, "status": "success", "message": "berhasil"},
     *   "data": [
     *     {"id": 1, "name": "ASM"},
     *     {"id": 2, "name": "DSF/DM"}
     *   ]
     * }
     */
    public function getRoleOptions(Request $request)
    {
        try {
            $user = Auth::user();

            if (! $user || ! $user->role) {
                throw new UnauthorizedException;
            }

            // SUPER ADMIN sees all roles
            if ($user->role->name === 'SUPER ADMIN') {
                $roles = \App\Models\Role::orderBy('name')->get(['id', 'name']);
            } else {
                // Get descendant roles using recursive helper
                $descendantIds = $this->getAllDescendantRoleIds($user->role);
                $roles = \App\Models\Role::whereIn('id', $descendantIds)
                    ->orderBy('name')
                    ->get(['id', 'name']);
            }

            return response()->json([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'berhasil',
                ],
                'data' => $roles,
                'errors' => null,
            ]);
        } catch (Exception $e) {
            Log::error('getRoleOptions failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Recursively get all descendant role IDs
     */
    private function getAllDescendantRoleIds(\App\Models\Role $role): array
    {
        $ids = [];
        foreach ($role->children as $child) {
            $ids[] = $child->id;
            $ids = array_merge($ids, $this->getAllDescendantRoleIds($child));
        }

        return $ids;
    }
}
