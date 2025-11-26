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
            if (!$user || !$user->role) {
                return ResponseFormatter::error(['message' => 'Unauthorized'], 'Unauthorized', 401);
            }

            $scopeLevel = $user->role->organizational_scope_level;

            // CRITICAL: Block access if scope level is null
            if (!$scopeLevel) {
                return ResponseFormatter::error(['message' => 'Unauthorized'], 'Unauthorized', 401);
            }

            $query = BadanUsaha::query();

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
            if (!$user || !$user->role) {
                return ResponseFormatter::error(['message' => 'Unauthorized'], 'Unauthorized', 401);
            }

            $scopeLevel = $user->role->organizational_scope_level;

            // CRITICAL: Block access if scope level is null
            if (!$scopeLevel) {
                return ResponseFormatter::error(['message' => 'Unauthorized'], 'Unauthorized', 401);
            }

            $query = Division::query();

            // Apply scope filtering first
            if ($scopeLevel !== 'all') {
                $badanUsahaIds = $user->badanUsahas()->pluck('badan_usahas.id')->toArray();
                $divisiIds = $user->divisis()->pluck('divisions.id')->toArray();

                // CRITICAL: If user has no assignments, return empty
                $hasAnyAssignment = !empty($badanUsahaIds) || !empty($divisiIds);
                if (!$hasAnyAssignment) {
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
                if (!empty($badanUsahaIds)) {
                    $query->whereIn('badanusaha_id', $badanUsahaIds);
                }
                if (!empty($divisiIds)) {
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
            if (!$user || !$user->role) {
                return ResponseFormatter::error(['message' => 'Unauthorized'], 'Unauthorized', 401);
            }

            $scopeLevel = $user->role->organizational_scope_level;

            // CRITICAL: Block access if scope level is null
            if (!$scopeLevel) {
                return ResponseFormatter::error(['message' => 'Unauthorized'], 'Unauthorized', 401);
            }

            $query = Region::query();

            // Apply scope filtering first
            if ($scopeLevel !== 'all') {
                $badanUsahaIds = $user->badanUsahas()->pluck('badan_usahas.id')->toArray();
                $divisiIds = $user->divisis()->pluck('divisions.id')->toArray();
                $regionIds = $user->regions()->pluck('regions.id')->toArray();

                // CRITICAL: If user has no assignments, return empty
                $hasAnyAssignment = !empty($badanUsahaIds) || !empty($divisiIds) || !empty($regionIds);
                if (!$hasAnyAssignment) {
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
                if (!empty($badanUsahaIds)) {
                    $query->whereIn('badanusaha_id', $badanUsahaIds);
                }
                if (!empty($divisiIds)) {
                    $query->whereIn('divisi_id', $divisiIds);
                }
                if (!empty($regionIds)) {
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
            if (!$user || !$user->role) {
                return ResponseFormatter::error(['message' => 'Unauthorized'], 'Unauthorized', 401);
            }

            $scopeLevel = $user->role->organizational_scope_level;

            // CRITICAL: Block access if scope level is null
            if (!$scopeLevel) {
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
                $hasAnyAssignment = !empty($badanUsahaIds) || !empty($divisiIds) || !empty($regionIds) || !empty($clusterIds);
                if (!$hasAnyAssignment) {
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
                if (!empty($badanUsahaIds)) {
                    $query->whereIn('badanusaha_id', $badanUsahaIds);
                }
                if (!empty($divisiIds)) {
                    $query->whereIn('divisi_id', $divisiIds);
                }
                if (!empty($regionIds)) {
                    $query->whereIn('region_id', $regionIds);
                }
                if (!empty($clusterIds)) {
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
}
