<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseFormatter;
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
                return ResponseFormatter::error(['message' => 'Unauthorized'], 'Unauthorized', 401);
            }

            $scopeLevel = $user->role->organizational_scope_level;

            // CRITICAL: Block access if scope level is null
            if (! $scopeLevel) {
                return ResponseFormatter::error(['message' => 'Unauthorized'], 'Unauthorized', 401);
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
            return ResponseFormatter::error([], $e->getMessage());
        }
    }

    public function getdivisi(Request $request)
    {
        try {
            $user = Auth::user();

            // CRITICAL: Block access if user or role is null
            if (! $user || ! $user->role) {
                return ResponseFormatter::error(['message' => 'Unauthorized'], 'Unauthorized', 401);
            }

            $scopeLevel = $user->role->organizational_scope_level;

            // CRITICAL: Block access if scope level is null
            if (! $scopeLevel) {
                return ResponseFormatter::error(['message' => 'Unauthorized'], 'Unauthorized', 401);
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
            return ResponseFormatter::error([], $e->getMessage());
        }
    }

    public function getregion(Request $request)
    {
        try {
            $user = Auth::user();

            // CRITICAL: Block access if user or role is null
            if (! $user || ! $user->role) {
                return ResponseFormatter::error(['message' => 'Unauthorized'], 'Unauthorized', 401);
            }

            $scopeLevel = $user->role->organizational_scope_level;

            // CRITICAL: Block access if scope level is null
            if (! $scopeLevel) {
                return ResponseFormatter::error(['message' => 'Unauthorized'], 'Unauthorized', 401);
            }

            $query = Region::active();

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

            // Then apply optional filter by division parameter
            $query->when($request->filled('div'), function ($q) use ($request) {
                $div = $request->input('div');
                $division = is_numeric($div)
                    ? Division::findOrFail($div)
                    : Division::where('name', $div)->firstOrFail();

                $q->where('divisi_id', $division->id);

                // Additional guard: if bu provided, ensure division belongs to it
                if ($request->filled('bu')) {
                    $bu = $request->input('bu');
                    $badanUsaha = is_numeric($bu)
                        ? BadanUsaha::findOrFail($bu)
                        : BadanUsaha::where('name', $bu)->firstOrFail();

                    $q->where('badanusaha_id', $badanUsaha->id);
                }
            });

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
            return ResponseFormatter::error([], $e->getMessage());
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
                return ResponseFormatter::error(['message' => 'Unauthorized'], 'Unauthorized', 401);
            }

            $scopeLevel = $user->role->organizational_scope_level;

            // CRITICAL: Block access if scope level is null
            if (! $scopeLevel) {
                return ResponseFormatter::error(['message' => 'Unauthorized'], 'Unauthorized', 401);
            }

            $query = Cluster::query();

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

            // Then apply optional hierarchical filters
            $query->when($request->filled('bu'), function ($q) use ($request) {
                $bu = $request->input('bu');
                $badanUsaha = is_numeric($bu)
                    ? BadanUsaha::findOrFail($bu)
                    : BadanUsaha::where('name', $bu)->firstOrFail();

                $q->where('badanusaha_id', $badanUsaha->id);
            });

            $query->when($request->filled('div'), function ($q) use ($request) {
                $div = $request->input('div');
                $division = is_numeric($div)
                    ? Division::findOrFail($div)
                    : Division::where('name', $div)->firstOrFail();

                $q->where('divisi_id', $division->id);
            });

            $query->when($request->filled('reg'), function ($q) use ($request) {
                $reg = $request->input('reg');
                $region = is_numeric($reg)
                    ? Region::findOrFail($reg)
                    : Region::where('name', $reg)->firstOrFail();

                $q->where('region_id', $region->id);
            });

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
            return ResponseFormatter::error([], $e->getMessage());
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

            if (! $user || ! $user->role) {
                return ResponseFormatter::error(['message' => 'Unauthorized'], 'Unauthorized', 401);
            }

            // Determine which role to check (for form validation)
            $roleId = $request->query('role_id');
            $targetRole = $roleId ? \App\Models\Role::find($roleId) : $user->role;

            if (! $targetRole) {
                return ResponseFormatter::error(['message' => 'Role not found'], 'Role not found', 404);
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

            // BadanUsaha options
            if ($fields['badanusaha']['visible']) {
                if ($userScopeLevel === 'all') {
                    $fields['badanusaha']['options'] = BadanUsaha::active()->orderBy('name')->get(['id', 'name']);
                } else {
                    $fields['badanusaha']['options'] = $user->badanUsahas()->active()->orderBy('name')->get(['badan_usahas.id as id', 'name']);
                }
            }

            // Divisi options
            if ($fields['divisi']['visible']) {
                if ($userScopeLevel === 'all') {
                    $fields['divisi']['options'] = Division::active()->orderBy('name')->get(['id', 'name', 'badanusaha_id']);
                } else {
                    $fields['divisi']['options'] = $user->divisis()->active()->orderBy('name')->get(['divisions.id as id', 'name', 'badanusaha_id']);
                }
            }

            // Region options
            if ($fields['region']['visible']) {
                if ($userScopeLevel === 'all') {
                    $fields['region']['options'] = Region::active()->orderBy('name')->get(['id', 'name', 'badanusaha_id', 'divisi_id']);
                } else {
                    $fields['region']['options'] = $user->regions()->active()->orderBy('name')->get(['regions.id as id', 'name', 'badanusaha_id', 'divisi_id']);
                }
            }

            // Cluster options
            if ($fields['cluster']['visible']) {
                if ($userScopeLevel === 'all') {
                    $fields['cluster']['options'] = Cluster::active()->orderBy('name')->get(['id', 'name', 'badanusaha_id', 'divisi_id', 'region_id']);
                } else {
                    $fields['cluster']['options'] = $user->clusters()->active()->orderBy('name')->get(['clusters.id as id', 'name', 'badanusaha_id', 'divisi_id', 'region_id']);
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
            return ResponseFormatter::error([], $e->getMessage());
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
                return ResponseFormatter::error(['message' => 'Unauthorized'], 'Unauthorized', 401);
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
            return ResponseFormatter::error([], $e->getMessage());
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
