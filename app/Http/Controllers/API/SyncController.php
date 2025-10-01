<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Sync Controller for Data Synchronization
 *
 * This controller provides comprehensive data synchronization endpoints between the SAM system
 * and external systems. It handles master data, user data, visit data, and external visit creation
 * with robust error handling and data transformation.
 *
 * All endpoints are unauthenticated (outside auth:sanctum group) and implement throttling
 * for expensive operations to ensure system stability and prevent abuse.
 *
 * @mixin \App\Http\Controllers\Controller
 */
class SyncController extends Controller
{
    /**
     * Normalize kode_outlet to a standard ###.### format, padding with zeros.
     */
    private function normalizeKodeOutlet(mixed $kode): ?string
    {
        if ($kode === null) {
            return null;
        }

        $raw = trim((string) $kode);
        if ($raw === '') {
            return null;
        }

        // Remove spaces
        $raw = preg_replace('/\s+/', '', $raw);

        if (str_contains($raw, '.')) {
            [$left, $right] = explode('.', $raw, 2);
            $left = preg_replace('/\D/', '', (string) $left);
            $right = preg_replace('/\D/', '', (string) $right);
            $left = str_pad($left, 3, '0', STR_PAD_LEFT);
            $right = str_pad(substr($right, 0, 3), 3, '0', STR_PAD_LEFT);

            return $left.'.'.$right;
        }

        // No dot, treat last 3 digits as right segment
        $digits = preg_replace('/\D/', '', $raw);
        if ($digits === '') {
            return null;
        }
        if (strlen($digits) <= 3) {
            $left = '0';
            $right = $digits;
        } else {
            $left = substr($digits, 0, -3);
            $right = substr($digits, -3);
        }
        $left = str_pad($left, 3, '0', STR_PAD_LEFT);
        $right = str_pad($right, 3, '0', STR_PAD_LEFT);

        return $left.'.'.$right;
    }

    /**
     * Normalize transaksi input to match DB enum values (YES/NO).
     */
    private function normalizeTransaksi(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        // Handle booleans and numbers quickly
        if (is_bool($value)) {
            return $value ? 'YES' : 'NO';
        }

        if (is_numeric($value)) {
            return ((int) $value) > 0 ? 'YES' : 'NO';
        }

        // String mapping (case-insensitive)
        $v = strtoupper(trim((string) $value));

        $truthy = [
            'YES', 'Y', 'TRUE', 'OK', 'SUCCESS', 'SUCCEED', 'BERHASIL', 'YA', 'DONE', 'SUKSES',
        ];
        $falsy = [
            'NO', 'N', 'FALSE', 'FAIL', 'FAILED', 'TIDAK', 'GA', 'NOK', 'CANCEL', 'BATAL',
        ];

        if (in_array($v, $truthy, true)) {
            return 'YES';
        }

        if (in_array($v, $falsy, true)) {
            return 'NO';
        }

        // Default: map any non-empty string to YES to be permissive
        return $v === '' ? null : 'YES';
    }

    /**
     * Format date safely from database datetime
     *
     * Safely converts database datetime values to standardized 'Y-m-d H:i:s' format.
     * Handles both Carbon/DateTime objects and string dates with exception handling.
     *
     * @param  mixed  $date  Database datetime value (Carbon object, DateTime object, or string)
     * @return string|null Formatted date string or null if input is empty
     *
     * @throws \Exception Returns original value if parsing fails
     */
    private function formatDate($date)
    {
        if (! $date) {
            return null;
        }

        try {
            if (is_object($date)) {
                // Handle Carbon/DateTime objects
                return $date->format('Y-m-d H:i:s');
            } else {
                // Handle string dates from database
                return Carbon::parse($date)->format('Y-m-d H:i:s');
            }
        } catch (\Exception $e) {
            // If parsing fails, return the original value
            return $date;
        }
    }

    /**
     * Format tanggal visit specifically for timestamp in milliseconds
     *
     * Converts timestamps from various formats to standardized 'Y-m-d H:i:s' format.
     * Detects timestamp units automatically: if numeric and >= 12 digits (or >= 100000000000),
     * treats as milliseconds and divides by 1000. Otherwise treats as seconds or objects.
     *
     * @param  mixed  $timestamp  Timestamp in milliseconds, seconds, or as a date string
     * @return string|null Formatted timestamp string or null if input is empty
     *
     * @throws \Exception Returns original value if parsing fails
     */
    private function formatTanggalVisit($timestamp)
    {
        if (! $timestamp) {
            return null;
        }

        try {
            if (is_numeric($timestamp)) {
                // Detect if timestamp is in milliseconds (12+ digits or >= 100000000000)
                if (strlen((string) $timestamp) >= 12 || $timestamp >= 100000000000) {
                    // Convert milliseconds to seconds and create Carbon instance
                    return Carbon::createFromTimestamp($timestamp / 1000)->format('Y-m-d H:i:s');
                } else {
                    // Treat as seconds
                    return Carbon::createFromTimestamp($timestamp)->format('Y-m-d H:i:s');
                }
            } elseif (is_object($timestamp)) {
                return $timestamp->format('Y-m-d H:i:s');
            } else {
                return Carbon::parse($timestamp)->format('Y-m-d H:i:s');
            }
        } catch (\Exception $e) {
            return $timestamp;
        }
    }

    /**
     * Get all Badan Usaha data
     *
     * Retrieves all business entity data from the SAM system for external synchronization.
     * Returns basic information including ID and name with formatted timestamps.
     * This data is typically used as master data for external systems to reference
     * organizational structure and business entities.
     *
     * @unauthenticated
     *
     * @group Sync
     *
     * @response 200 {"meta":{"code":200,"status":"success","message":"Data Badan Usaha berhasil diambil"},"data":[{"id":1,"name":"Sample Badan Usaha","created_at":"2024-01-01 00:00:00","updated_at":"2024-01-01 00:00:00"}]}
     * @response 500 {"meta":{"code":500,"status":"error","message":"Gagal mengambil data Badan Usaha"},"data":null}
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getBadanUsaha()
    {
        try {
            $badanUsaha = BadanUsaha::select('id', 'name', 'created_at', 'updated_at')
                ->orderBy('id')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'created_at' => $this->formatDate($item->created_at),
                        'updated_at' => $this->formatDate($item->updated_at),
                    ];
                });

            return ResponseFormatter::success($badanUsaha, 'Data Badan Usaha berhasil diambil');
        } catch (\Exception $e) {
            return ResponseFormatter::error(null, 'Gagal mengambil data Badan Usaha: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get all Division data with Badan Usaha relation
     *
     * Retrieves all division data with related Badan Usaha information for external synchronization.
     * Each division includes its parent business entity reference, enabling external systems
     * to maintain complete organizational hierarchy and structure relationships.
     *
     * @unauthenticated
     *
     * @group Sync
     *
     * @response 200 {"meta":{"code":200,"status":"success","message":"Data Divisi berhasil diambil"},"data":[{"id":1,"name":"Sample Division","badanusaha_id":1,"badanusaha":{"id":1,"name":"Sample Badan Usaha"},"created_at":"2024-01-01 00:00:00","updated_at":"2024-01-01 00:00:00"}]}
     * @response 500 {"meta":{"code":500,"status":"error","message":"Gagal mengambil data Divisi"},"data":null}
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDivision()
    {
        try {
            $divisions = Division::select('id', 'name', 'badanusaha_id', 'created_at', 'updated_at')
                ->with(['badanusaha:id,name'])
                ->orderBy('id')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'badanusaha_id' => $item->badanusaha_id,
                        'badanusaha' => $item->badanusaha,
                        'created_at' => $this->formatDate($item->created_at),
                        'updated_at' => $this->formatDate($item->updated_at),
                    ];
                });

            return ResponseFormatter::success($divisions, 'Data Divisi berhasil diambil');
        } catch (\Exception $e) {
            return ResponseFormatter::error(null, 'Gagal mengambil data Divisi: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get all Region data with relations
     *
     * Retrieves all region data with complete hierarchical relationships including
     * Badan Usaha and Division references. This enables external systems to maintain
     * geographical organizational structure and complete business entity mappings.
     *
     * @unauthenticated
     *
     * @group Sync
     *
     * @response 200 {"meta":{"code":200,"status":"success","message":"Data Region berhasil diambil"},"data":[{"id":1,"name":"Sample Region","badanusaha_id":1,"divisi_id":1,"badanusaha":{"id":1,"name":"Sample Badan Usaha"},"divisi":{"id":1,"name":"Sample Division"},"created_at":"2024-01-01 00:00:00","updated_at":"2024-01-01 00:00:00"}]}
     * @response 500 {"meta":{"code":500,"status":"error","message":"Gagal mengambil data Region"},"data":null}
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRegion()
    {
        try {
            $regions = Region::select('id', 'name', 'badanusaha_id', 'divisi_id', 'created_at', 'updated_at')
                ->with([
                    'badanusaha:id,name',
                    'divisi:id,name',
                ])
                ->orderBy('id')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'badanusaha_id' => $item->badanusaha_id,
                        'divisi_id' => $item->divisi_id,
                        'badanusaha' => $item->badanusaha,
                        'divisi' => $item->divisi,
                        'created_at' => $this->formatDate($item->created_at),
                        'updated_at' => $this->formatDate($item->updated_at),
                    ];
                });

            return ResponseFormatter::success($regions, 'Data Region berhasil diambil');
        } catch (\Exception $e) {
            return ResponseFormatter::error(null, 'Gagal mengambil data Region: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get all Cluster data with relations
     *
     * Retrieves all cluster data with complete hierarchical relationships including
     * Badan Usaha, Division, and Region. This provides the complete organizational
     * structure for external systems to maintain territory and area management.
     *
     * @unauthenticated
     *
     * @group Sync
     *
     * @response 200 {"meta":{"code":200,"status":"success","message":"Data Cluster berhasil diambil"},"data":[{"id":1,"name":"Sample Cluster","badanusaha_id":1,"divisi_id":1,"region_id":1,"badanusaha":{"id":1,"name":"Sample Badan Usaha"},"divisi":{"id":1,"name":"Sample Division"},"region":{"id":1,"name":"Sample Region"},"created_at":"2024-01-01 00:00:00","updated_at":"2024-01-01 00:00:00"}]}
     * @response 500 {"meta":{"code":500,"status":"error","message":"Gagal mengambil data Cluster"},"data":null}
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCluster()
    {
        try {
            $clusters = Cluster::select('id', 'name', 'badanusaha_id', 'divisi_id', 'region_id', 'created_at', 'updated_at')
                ->with([
                    'badanusaha:id,name',
                    'divisi:id,name',
                    'region:id,name',
                ])
                ->orderBy('id')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'badanusaha_id' => $item->badanusaha_id,
                        'divisi_id' => $item->divisi_id,
                        'region_id' => $item->region_id,
                        'badanusaha' => $item->badanusaha,
                        'divisi' => $item->divisi,
                        'region' => $item->region,
                        'created_at' => $this->formatDate($item->created_at),
                        'updated_at' => $this->formatDate($item->updated_at),
                    ];
                });

            return ResponseFormatter::success($clusters, 'Data Cluster berhasil diambil');
        } catch (\Exception $e) {
            return ResponseFormatter::error(null, 'Gagal mengambil data Cluster: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get all Role data
     *
     * Retrieves all user role definitions including web access permissions.
     * This data is essential for external systems to understand user permissions
     * and access control within the SAM system hierarchy.
     *
     * @unauthenticated
     *
     * @group Sync
     *
     * @response 200 {"meta":{"code":200,"status":"success","message":"Data Role berhasil diambil"},"data":[{"id":1,"name":"Admin","can_access_web":true,"created_at":"2024-01-01 00:00:00","updated_at":"2024-01-01 00:00:00"}]}
     * @response 500 {"meta":{"code":500,"status":"error","message":"Gagal mengambil data Role"},"data":null}
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRole()
    {
        try {
            $roles = Role::select('id', 'name', 'can_access_web', 'created_at', 'updated_at')
                ->orderBy('id')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'name' => $item->name,
                        'can_access_web' => $item->can_access_web,
                        'created_at' => $this->formatDate($item->created_at),
                        'updated_at' => $this->formatDate($item->updated_at),
                    ];
                });

            return ResponseFormatter::success($roles, 'Data Role berhasil diambil');
        } catch (\Exception $e) {
            return ResponseFormatter::error(null, 'Gagal mengambil data Role: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get all User data with relations
     *
     * Retrieves comprehensive user data with complete organizational hierarchy relationships.
     * Excludes user ID 691 for data security and includes role, business entity, division,
     * region, cluster assignments, and team leader relationships. This enables external
     * systems to maintain complete user management and organizational structure.
     *
     * @unauthenticated
     *
     * @group Sync
     *
     * @response 200 {"meta":{"code":200,"status":"success","message":"Data User berhasil diambil"},"data":[{"id":1,"nama_lengkap":"John Doe","username":"johndoe","role_id":1,"badanusaha_id":1,"divisi_id":1,"region_id":1,"cluster_id":1,"cluster_id2":null,"tm_id":null,"role":{"id":1,"name":"Admin"},"badanusaha":{"id":1,"name":"Sample Badan Usaha"},"divisi":{"id":1,"name":"Sample Division"},"region":{"id":1,"name":"Sample Region"},"cluster":{"id":1,"name":"Sample Cluster"},"cluster2":null,"tm":null,"created_at":"2024-01-01 00:00:00","updated_at":"2024-01-01 00:00:00"}]}
     * @response 500 {"meta":{"code":500,"status":"error","message":"Gagal mengambil data User"},"data":null}
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUser()
    {
        try {
            $users = User::select('id', 'nama_lengkap', 'username', 'role_id', 'badanusaha_id', 'divisi_id', 'region_id', 'cluster_id', 'cluster_id2', 'tm_id', 'created_at', 'updated_at')
                ->where('id', '!=', 691)
                ->with([
                    'role:id,name',
                    'badanusaha:id,name',
                    'divisi:id,name',
                    'region:id,name',
                    'cluster:id,name',
                    'cluster2:id,name',
                    'tm:id,nama_lengkap',
                ])
                ->orderBy('id')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'nama_lengkap' => $item->nama_lengkap,
                        'username' => $item->username,
                        'role_id' => $item->role_id,
                        'badanusaha_id' => $item->badanusaha_id,
                        'divisi_id' => $item->divisi_id,
                        'region_id' => $item->region_id,
                        'cluster_id' => $item->cluster_id,
                        'cluster_id2' => $item->cluster_id2,
                        'tm_id' => $item->tm_id,
                        'role' => $item->role,
                        'badanusaha' => $item->badanusaha,
                        'divisi' => $item->divisi,
                        'region' => $item->region,
                        'cluster' => $item->cluster,
                        'cluster2' => $item->cluster2,
                        'tm' => $item->tm,
                        'created_at' => $this->formatDate($item->created_at),
                        'updated_at' => $this->formatDate($item->updated_at),
                    ];
                });

            return ResponseFormatter::success($users, 'Data User berhasil diambil');
        } catch (\Exception $e) {
            return ResponseFormatter::error(null, 'Gagal mengambil data User: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get all Outlet data with relations
     *
     * Retrieves complete outlet information including location data, status, membership,
     * visit radius, and organizational assignments. This data enables external systems
     * to maintain outlet management, geolocation services, and territory planning.
     *
     * @unauthenticated
     *
     * @group Sync
     *
     * @response 200 {"meta":{"code":200,"status":"success","message":"Data Outlet berhasil diambil"},"data":[{"id":1,"kode_outlet":"OUT001","nama_outlet":"Sample Outlet","alamat_outlet":"123 Main St","distric":"Downtown","status_outlet":"Active","is_member":true,"radius":100,"limit":50,"latlong":"-6.2088,106.8456","badanusaha_id":1,"divisi_id":1,"region_id":1,"cluster_id":1,"created_at":"2024-01-01 00:00:00","updated_at":"2024-01-01 00:00:00"}]}
     * @response 500 {"meta":{"code":500,"status":"error","message":"Gagal mengambil data Outlet"},"data":null}
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOutlet()
    {
        try {
            $outlets = Outlet::select('id', 'kode_outlet', 'nama_outlet', 'alamat_outlet', 'distric', 'status_outlet', 'is_member', 'radius', 'limit', 'latlong', 'badanusaha_id', 'divisi_id', 'region_id', 'cluster_id', 'created_at', 'updated_at')
                ->orderBy('id')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'kode_outlet' => $item->kode_outlet,
                        'nama_outlet' => $item->nama_outlet,
                        'alamat_outlet' => $item->alamat_outlet,
                        'distric' => $item->distric,
                        'status_outlet' => $item->status_outlet,
                        'is_member' => $item->is_member,
                        'radius' => $item->radius,
                        'limit' => $item->limit,
                        'latlong' => $item->latlong,
                        'badanusaha_id' => $item->badanusaha_id,
                        'divisi_id' => $item->divisi_id,
                        'region_id' => $item->region_id,
                        'cluster_id' => $item->cluster_id,
                        'created_at' => $this->formatDate($item->created_at),
                        'updated_at' => $this->formatDate($item->updated_at),
                    ];
                });

            return ResponseFormatter::success($outlets, 'Data Outlet berhasil diambil');
        } catch (\Exception $e) {
            return ResponseFormatter::error(null, 'Gagal mengambil data Outlet: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get all Visit data with relations
     *
     * Retrieves visit data with month/year filtering and timestamp conversion.
     * Converts milliseconds timestamps to standard datetime format and includes
     * complete visit information including photos, coordinates, and transaction data.
     * Filtering by month and year allows external systems to sync specific time periods.
     *
     * @unauthenticated
     *
     * @group Sync
     *
     * @queryParam int month Month number (1-12). Defaults to current month. Example: 12
     * @queryParam int year Year number. Defaults to current year. Example: 2024
     *
     * @response 200 {"meta":{"code":200,"status":"success","message":"Data Visit berhasil diambil"},"data":[{"id":1,"tanggal_visit":"2024-01-01 10:00:00","user_id":123,"outlet_id":456,"tipe_visit":"Sales Call","picture_visit_in":"photo_in.jpg","picture_visit_out":"photo_out.jpg","latlong_in":"-6.2088,106.8456","latlong_out":"-6.2088,106.8456","check_in_time":"2024-01-01 10:00:00","check_out_time":"2024-01-01 11:30:00","durasi_visit":90,"transaksi":"Success","laporan_visit":"Good visit","created_at":"2024-01-01 00:00:00","updated_at":"2024-01-01 00:00:00"}]}
     * @response 500 {"meta":{"code":500,"status":"error","message":"Gagal mengambil data Visit"},"data":null}
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function getVisit()
    {
        try {
            // Get parameters from request with default current month and year
            $month = request('month', date('m'));
            $year = request('year', date('Y'));

            // Build date range for the specified month
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth()->format('Y-m-d');

            $visits = Visit::select('id', 'tanggal_visit', 'user_id', 'outlet_id', 'tipe_visit', 'picture_visit_in', 'picture_visit_out', 'latlong_in', 'latlong_out', 'check_in_time', 'check_out_time', 'durasi_visit', 'transaksi', 'laporan_visit', 'created_at', 'updated_at')
                ->whereBetween('tanggal_visit', [$startDate, $endDate])
                ->orderBy('id')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'tanggal_visit' => $this->formatTanggalVisit($item->tanggal_visit),
                        'user_id' => $item->user_id,
                        'outlet_id' => $item->outlet_id,
                        'tipe_visit' => $item->tipe_visit,
                        'picture_visit_in' => $item->picture_visit_in,
                        'picture_visit_out' => $item->picture_visit_out,
                        'latlong_in' => $item->latlong_in,
                        'latlong_out' => $item->latlong_out,
                        'check_in_time' => $item->check_in_time,
                        'check_out_time' => $item->check_out_time,
                        'durasi_visit' => $item->durasi_visit,
                        'transaksi' => $item->transaksi,
                        'laporan_visit' => $item->laporan_visit,
                        'created_at' => $this->formatDate($item->created_at),
                        'updated_at' => $this->formatDate($item->updated_at),
                    ];
                });

            return ResponseFormatter::success($visits, 'Data Visit berhasil diambil');
        } catch (\Exception $e) {
            return ResponseFormatter::error(null, 'Gagal mengambil data Visit: '.$e->getMessage(), 500);
        }
    }

    /**
     * Get all Plan Visit data with relations
     *
     * Retrieves planned visit data with month/year filtering and timestamp conversion.
     * Converts milliseconds timestamps to standard datetime format and includes
     * basic user-outlet assignment information. This enables external systems
     * to sync visit planning data for operational coordination and scheduling.
     * Filtering by month and year allows external systems to sync specific time periods.
     *
     * @unauthenticated
     *
     * @group Sync
     *
     * @queryParam int month Month number (1-12). Defaults to current month. Example: 7
     * @queryParam int year Year number. Defaults to current year. Example: 2025
     *
     * @response 200 {"meta":{"code":200,"status":"success","message":"Data Plan Visit berhasil diambil"},"data":[{"id":1,"tanggal_visit":"2025-07-01 00:00:00","user_id":123,"outlet_id":456,"created_at":"2024-01-01 00:00:00","updated_at":"2024-01-01 00:00:00"}]}
     * @response 500 {"meta":{"code":500,"status":"error","message":"Gagal mengambil data Plan Visit"},"data":null}
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function deleteInstantDuplicateVisit(Request $request)
    {
        try {
            $request->validate([
                'username' => ['required', 'string'],
            ]);

            $username = trim((string) $request->string('username'));
            $user = User::query()->where('username', $username)->first();
            if (! $user) {
                return ResponseFormatter::error(null, 'User not found', 404);
            }

            $today = Carbon::now()->toDateString();

            // Fetch today's visits for this user
            $visits = Visit::query()
                ->where('user_id', $user->id)
                ->whereDate('tanggal_visit', $today)
                ->orderBy('id')
                ->get();

            if ($visits->isEmpty()) {
                return ResponseFormatter::success([
                    'username' => $username,
                    'user_id' => $user->id,
                    'date' => $today,
                    'deleted_total' => 0,
                    'deleted_ids' => [],
                ], 'No visits to clean today');
            }

            $deletedIds = [];

            // Group by outlet and prune duplicates
            $visits->groupBy('outlet_id')->each(function ($group) use (&$deletedIds) {
                if ($group->count() <= 1) {
                    return; // nothing to delete
                }

                // Always keep one; candidates to delete are the rest
                // Prefer to keep the first with both IN and OUT present
                $keep = $group->first(function ($v) {
                    return ! empty($v->latlong_out) && ! empty($v->check_out_time);
                }) ?? $group->first();

                $toConsider = $group->filter(fn ($v) => $v->id !== $keep->id);

                // First pass: delete those with missing OUT info
                $missingOut = $toConsider->filter(function ($v) {
                    return empty($v->latlong_out) || empty($v->check_out_time);
                });

                foreach ($missingOut as $v) {
                    $v->delete();
                    $deletedIds[] = $v->id;
                }
            });

            // Re-query to finish pruning arbitrarily if needed
            $remainingGroups = Visit::query()
                ->whereDate('tanggal_visit', $today)
                ->where('user_id', $user->id)
                ->get()
                ->groupBy('outlet_id');

            foreach ($remainingGroups as $outletId => $group) {
                if ($group->count() <= 1) {
                    continue;
                }
                // Keep the earliest (smallest id), delete the rest
                $sorted = $group->sortBy('id')->values();
                $keep = $sorted->shift();
                foreach ($sorted as $v) {
                    $v->delete();
                    $deletedIds[] = $v->id;
                }
            }

            return ResponseFormatter::success([
                'username' => $username,
                'user_id' => $user->id,
                'date' => $today,
                'deleted_total' => count($deletedIds),
                'deleted_ids' => array_values($deletedIds),
            ], 'Duplicate visits cleaned');
        } catch (\Exception $e) {
            return ResponseFormatter::error([
                'error' => $e->getMessage(),
            ], 'Failed to clean duplicate visits: '.$e->getMessage(), 500);
        }
    }

    /**
     * Create an instant visit (check-in and check-out in one request)
     *
     * This endpoint mirrors the behavior in VisitController but performs both check-in and
     * check-out at once. It stores both photos, calculates duration automatically, and enforces
     * the business rule: only 1 visit per user for the same outlet on the same day.
     *
     * Notes:
     * - If `tanggal_visit` is not provided, today is assumed.
     * - If `check_in_time` / `check_out_time` are not provided, both default to now() and duration is 0.
     * - Photos are stored in `storage/app/public/visits/in` and `storage/app/public/visits/out`.
     *
     * @unauthenticated
     *
     * @group Sync
     *
     * @bodyParam int user_id required User ID who performed the visit. Must exist in users table.
     * @bodyParam int outlet_id required Outlet ID that was visited. Must exist in outlets table.
     * @bodyParam string tipe_visit required Type of visit performed. Example: "Sales Call"
     * @bodyParam string latlong_in required Check-in coordinates ("lat,lng").
     * @bodyParam string latlong_out required Check-out coordinates ("lat,lng").
     * @bodyParam string laporan_visit required Visit report/notes.
     * @bodyParam string transaksi required Transaction details or status.
     * @bodyParam file picture_visit_in required Check-in photo (jpg,jpeg,png; max 5MB).
     * @bodyParam file picture_visit_out required Check-out photo (jpg,jpeg,png; max 5MB).
     * @bodyParam mixed tanggal_visit optional Visit date (ms/sec timestamp, datetime, or string). Defaults to today.
     * @bodyParam mixed check_in_time optional Check-in time (ISO 8601 / datetime / timestamp). Defaults to now.
     * @bodyParam mixed check_out_time optional Check-out time (ISO 8601 / datetime / timestamp). Defaults to now.
     *
     * @response 201 {"meta":{"code":201,"status":"success","message":"Visit berhasil dibuat"},"data":{"visit":{...}}}
     * @response 422 {"meta":{"code":422,"status":"error","message":"Visit untuk outlet ini sudah dibuat hari ini"},"data":null}
     */
    public function createInstantVisit(Request $request)
    {
        try {
            // Validate inputs
            $request->validate([
                'user_id' => ['required', 'exists:users,id'],
                'outlet_id' => ['required', 'exists:outlets,id'],
                'tipe_visit' => ['required', 'string'],
                'latlong_in' => ['required', 'string'],
                'latlong_out' => ['required', 'string'],
                'laporan_visit' => ['required', 'string'],
                'transaksi' => ['required', 'string'],
                'picture_visit_in' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
                'picture_visit_out' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
                'tanggal_visit' => ['nullable'],
                'check_in_time' => ['nullable'],
                'check_out_time' => ['nullable'],
            ]);

            // Determine tanggal_visit (default: today)
            $tanggalVisit = $request->filled('tanggal_visit')
                ? $this->formatTanggalVisit($request->tanggal_visit)
                : Carbon::now()->format('Y-m-d H:i:s');
            $tanggalVisitDate = Carbon::parse($tanggalVisit)->toDateString();

            // Enforce: only 1x visit per user per outlet per day
            $isDuplicate = Visit::query()
                ->where('user_id', $request->user_id)
                ->where('outlet_id', $request->outlet_id)
                ->whereDate('tanggal_visit', $tanggalVisitDate)
                ->exists();

            if ($isDuplicate) {
                return ResponseFormatter::error(null, 'Visit untuk outlet ini sudah dibuat hari ini', 422);
            }

            // Resolve username for file naming
            $username = optional(User::find($request->user_id))->username ?? (string) $request->user_id;

            // Store images (IN / OUT)
            $inExt = $request->file('picture_visit_in')->guessExtension() ?: $request->file('picture_visit_in')->extension();
            $outExt = $request->file('picture_visit_out')->guessExtension() ?: $request->file('picture_visit_out')->extension();

            $imageNameIn = date('Y-m-d').'-'.$username.'-'.'IN-'.Carbon::now()->getPreciseTimestamp(3).'.'.$inExt;
            $imageNameOut = date('Y-m-d').'-'.$username.'-'.'OUT-'.Carbon::now()->getPreciseTimestamp(3).'.'.$outExt;

            $pathIn = $request->file('picture_visit_in')->storeAs('visits/in', $imageNameIn, 'public');
            $pathOut = $request->file('picture_visit_out')->storeAs('visits/out', $imageNameOut, 'public');

            // Determine times and duration
            $checkInTime = $request->filled('check_in_time') ? Carbon::parse($request->check_in_time) : Carbon::now();
            $checkOutTime = $request->filled('check_out_time') ? Carbon::parse($request->check_out_time) : Carbon::now();
            $durasi = $checkInTime->diffInMinutes($checkOutTime);

            // Create visit
            $visit = Visit::create([
                'tanggal_visit' => $tanggalVisit,
                'user_id' => $request->user_id,
                'outlet_id' => $request->outlet_id,
                'tipe_visit' => $request->tipe_visit,
                'picture_visit_in' => $pathIn,
                'picture_visit_out' => $pathOut,
                'latlong_in' => $request->latlong_in,
                'latlong_out' => $request->latlong_out,
                'check_in_time' => $checkInTime,
                'check_out_time' => $checkOutTime,
                'durasi_visit' => $durasi,
                'laporan_visit' => $request->laporan_visit,
                'transaksi' => $this->normalizeTransaksi($request->transaksi),
            ]);

            return ResponseFormatter::success([
                'visit' => $visit,
            ], 'Visit berhasil dibuat', 201);
        } catch (\Exception $e) {
            return ResponseFormatter::error([
                'error' => $e->getMessage(),
            ], 'Gagal membuat visit: '.$e->getMessage(), 500);
        }
    }

    /**
     * Reset an outlet's media and specific fields via Sync API.
     *
     * Mirrors the Filament OutletResource bulk "Reset Data Outlet" behavior.
     * Identify outlet by `kode_outlet` combined with the user's division inferred from `username`.
     *
     * @unauthenticated
     *
     * @group Sync
     *
     * @bodyParam string kode_outlet required The outlet code to reset.
     * @bodyParam string username required Username used to disambiguate the outlet by the user's `divisi_id` when `kode_outlet` exists across divisions.
     *
     * @response 200 {"meta":{"code":200,"status":"success","message":"Outlet reset successfully"},"data":{"outlet_id":123,"kode_outlet":"OUT-001"}}
     * @response 404 {"meta":{"code":404,"status":"error","message":"User not found or outlet not found"},"data":null}
     */
    public function resetOutlet(Request $request)
    {
        try {
            $request->validate([
                'kode_outlet' => ['required', 'string'],
                'username' => ['required', 'string'],
            ]);
            // Resolve user and outlet by kode_outlet within user's division
            $username = trim((string) $request->input('username'));
            $user = User::where('username', $username)->first();
            if (! $user) {
                return ResponseFormatter::error(null, 'User not found', 404);
            }

            // Normalize kode_outlet to ###.### so 5.081 matches 005.081
            $kode = $this->normalizeKodeOutlet($request->input('kode_outlet'));
            if (! $kode) {
                return ResponseFormatter::error(null, 'Outlet not found', 404);
            }
            $outlet = Outlet::query()
                ->where('kode_outlet', $kode)
                ->where('divisi_id', $user->divisi_id)
                ->orderBy('id')
                ->first();

            // Fallback: try unnormalized raw input trimmed if exact normalized not found (defensive)
            if (! $outlet) {
                $rawKode = trim((string) $request->input('kode_outlet'));
                if ($rawKode !== '') {
                    $outlet = Outlet::query()
                        ->whereRaw('TRIM(kode_outlet) = ?', [trim($rawKode)])
                        ->where('divisi_id', $user->divisi_id)
                        ->orderBy('id')
                        ->first();
                }
            }

            if (! $outlet) {
                return ResponseFormatter::error(null, 'Outlet not found', 404);
            }

            // Delete related public files if present
            foreach (['poto_shop_sign', 'poto_depan', 'poto_kiri', 'poto_kanan', 'poto_ktp', 'video'] as $mediaField) {
                $path = $outlet->{$mediaField} ?? null;
                if ($path && Storage::disk('public')->exists($path)) {
                    Storage::disk('public')->delete($path);
                }
            }

            // Reset fields to null like in the Filament bulk action
            $outlet->update([
                'nama_pemilik_outlet' => null,
                'nomer_tlp_outlet' => null,
                'latlong' => null,
                'poto_shop_sign' => null,
                'poto_depan' => null,
                'poto_kiri' => null,
                'poto_kanan' => null,
                'poto_ktp' => null,
                'video' => null,
            ]);

            return ResponseFormatter::success([
                'outlet_id' => $outlet->id,
                'kode_outlet' => $outlet->kode_outlet,
            ], 'Outlet reset successfully');
        } catch (\Illuminate\Validation\ValidationException $e) {
            throw $e; // let Laravel handle 422 response
        } catch (\Exception $e) {
            return ResponseFormatter::error([
                'error' => $e->getMessage(),
            ], 'Failed to reset outlet: '.$e->getMessage(), 500);
        }
    }
}
