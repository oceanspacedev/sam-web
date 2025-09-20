<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use Carbon\Carbon;
use Illuminate\Http\Request;

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
    public function getPlanVisit()
    {
        try {
            // Get parameters from request with default current month and year
            $month = request('month', date('m'));
            $year = request('year', date('Y'));

            // Build date range for the specified month
            $startDate = Carbon::createFromDate($year, $month, 1)->startOfMonth()->format('Y-m-d');
            $endDate = Carbon::createFromDate($year, $month, 1)->endOfMonth()->format('Y-m-d');

            $planVisits = PlanVisit::select('id', 'tanggal_visit', 'user_id', 'outlet_id', 'created_at', 'updated_at')
                ->whereBetween('tanggal_visit', [$startDate, $endDate])
                ->orderBy('id')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'tanggal_visit' => $this->formatTanggalVisit($item->tanggal_visit),
                        'user_id' => $item->user_id,
                        'outlet_id' => $item->outlet_id,
                        'created_at' => $this->formatDate($item->created_at),
                        'updated_at' => $this->formatDate($item->updated_at),
                    ];
                });

            return ResponseFormatter::success($planVisits, 'Data Plan Visit berhasil diambil');
        } catch (\Exception $e) {
            return ResponseFormatter::error(null, 'Gagal mengambil data Plan Visit: '.$e->getMessage(), 500);
        }
    }

    /**
     * Create visit data from external system
     *
     * Creates a new visit record from external system data with dual photo uploads
     * and automatic duration calculation. Handles file storage with intelligent naming
     * conventions and validates all required visit information including coordinates,
     * transaction data, and visit reports.
     *
     * @unauthenticated
     *
     * @group Sync
     *
     * @bodyParam mixed tanggal_visit required Visit date in timestamp (ms/sec), datetime, or string format. Example: 1640995200000 or "2024-01-01T10:00:00Z"
     * @bodyParam int user_id required User ID who performed the visit. Must exist in users table. Example: 123
     * @bodyParam int outlet_id required Outlet ID that was visited. Must exist in outlets table. Example: 456
     * @bodyParam string tipe_visit required Type of visit performed. Max 255 characters. Example: "Sales Call"
     * @bodyParam string latlong_in required Check-in coordinates in "lat,lng" format. Example: "-6.2088,106.8456"
     * @bodyParam string latlong_out required Check-out coordinates in "lat,lng" format. Example: "-6.2088,106.8456"
     * @bodyParam mixed check_in_time required Check-in time in ISO 8601, datetime, or timestamp format. Example: "2024-01-01T10:00:00Z"
     * @bodyParam mixed check_out_time required Check-out time in ISO 8601, datetime, or timestamp format. Example: "2024-01-01T11:30:00Z"
     * @bodyParam string laporan_visit required Visit report/notes. Max 65535 characters. Example: "Customer interested in new product"
     * @bodyParam string transaksi required Transaction details or status. Max 65535 characters. Example: "Successful sale"
     * @bodyParam file picture_visit_in required Check-in photo file (image/*, mimes: jpg,jpeg,png, max 2048KB). Example: photo_in.jpg
     * @bodyParam file picture_visit_out required Check-out photo file (image/*, mimes: jpg,jpeg,png, max 2048KB). Example: photo_out.jpg
     * @bodyParam int durasi_visit optional Visit duration in minutes. Calculated automatically if not provided. Example: 90
     *
     * @response 201 {"meta":{"code":201,"status":"success","message":"Visit berhasil dibuat"},"data":{"visit":{"id":1,"tanggal_visit":"2024-01-01 10:00:00","user_id":123,"outlet_id":456,"tipe_visit":"Sales Call","picture_visit_in":"2024-01-01-username-IN-1234567890123.jpg","picture_visit_out":"2024-01-01-username-OUT-1234567890123.jpg","latlong_in":"-6.2088,106.8456","latlong_out":"-6.2088,106.8456","check_in_time":"2024-01-01 10:00:00","check_out_time":"2024-01-01 11:30:00","durasi_visit":90,"laporan_visit":"Good visit","transaksi":"Success","created_at":"2024-01-01 00:00:00","updated_at":"2024-01-01 00:00:00"}}}
     * @response 422 {"meta":{"code":422,"status":"error","message":"The given data was invalid."},"data":{"message":"The given data was invalid.","errors":{"tanggal_visit":["The tanggal visit field is required."],"user_id":["The user id field is required."],"outlet_id":["The outlet id field is required."],"tipe_visit":["The tipe visit field is required."],"latlong_in":["The latlong in field is required."],"latlong_out":["The latlong out field is required."],"check_in_time":["The check in time field is required."],"check_out_time":["The check out time field is required."],"laporan_visit":["The laporan visit field is required."],"transaksi":["The transaksi field is required."],"picture_visit_in":["The picture visit in field is required."],"picture_visit_out":["The picture visit out field is required."]}}}
     * @response 500 {"meta":{"code":500,"status":"error","message":"Gagal membuat visit"},"data":{"error":"Error message"}}
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function createVisit(Request $request)
    {
        try {
            // Validasi input sesuai struktur tabel visit
            $request->validate([
                'tanggal_visit' => ['required'],
                'user_id' => ['required', 'exists:users,id'],
                'outlet_id' => ['required', 'exists:outlets,id'],
                'tipe_visit' => ['required'],
                'latlong_in' => ['required', 'string'],
                'latlong_out' => ['required', 'string'],
                'check_in_time' => ['required'],
                'check_out_time' => ['required'],
                'laporan_visit' => ['required'],
                'transaksi' => ['required'],
                'picture_visit_in' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
                'picture_visit_out' => ['required', 'image', 'mimes:jpg,jpeg,png', 'max:2048'],
                'durasi_visit' => ['nullable', 'integer'],
            ]);

            // Hitung durasi visit jika tidak diberikan
            $durasi = $request->durasi_visit;
            if (! $durasi) {
                $checkInTime = Carbon::parse($request->check_in_time);
                $checkOutTime = Carbon::parse($request->check_out_time);
                $durasi = $checkInTime->diffInMinutes($checkOutTime);
            }

            // Format tanggal visit
            $tanggalVisit = $this->formatTanggalVisit($request->tanggal_visit);

            // Get username from user_id
            $user = User::find($request->user_id);
            $username = $user ? $user->username : $request->user_id;

            // Generate nama file foto check in
            $imageNameIn = date('Y-m-d').'-'.$username.'-'.'IN-'.
                           Carbon::now()->getPreciseTimestamp(3).'.'.
                           $request->picture_visit_in->extension();

            // Generate nama file foto check out
            $imageNameOut = date('Y-m-d').'-'.$username.'-'.'OUT-'.
                            Carbon::now()->getPreciseTimestamp(3).'.'.
                            $request->picture_visit_out->extension();

            // Simpan foto ke storage
            $request->picture_visit_in->move(storage_path('app/public/'), $imageNameIn);
            $request->picture_visit_out->move(storage_path('app/public/'), $imageNameOut);

            // Buat data visit sesuai struktur tabel
            $visit = Visit::create([
                'tanggal_visit' => $tanggalVisit,
                'user_id' => $request->user_id,
                'outlet_id' => $request->outlet_id,
                'tipe_visit' => $request->tipe_visit,
                'picture_visit_in' => $imageNameIn,
                'picture_visit_out' => $imageNameOut,
                'latlong_in' => $request->latlong_in,
                'latlong_out' => $request->latlong_out,
                'check_in_time' => $request->check_in_time,
                'check_out_time' => $request->check_out_time,
                'durasi_visit' => $durasi,
                'laporan_visit' => $request->laporan_visit,
                'transaksi' => $request->transaksi,
            ]);

            return ResponseFormatter::success([
                'visit' => $visit,
            ], 'Visit berhasil dibuat');

        } catch (\Exception $e) {
            return ResponseFormatter::error([
                'error' => $e->getMessage(),
            ], 'Gagal membuat visit: '.$e->getMessage(), 500);
        }
    }
}
