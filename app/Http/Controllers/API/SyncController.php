<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseFormatter;
use App\Models\BadanUsaha;
use App\Models\Division;
use App\Models\Region;
use App\Models\Cluster;
use App\Models\Role;
use App\Models\User;
use App\Models\Outlet;
use App\Models\Visit;
use App\Models\PlanVisit;
use Carbon\Carbon;
use Illuminate\Http\Request;

class SyncController extends Controller
{
    /**
     * Format date safely from database datetime
     */
    private function formatDate($date)
    {
        if (!$date) return null;
        
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
     */
    private function formatTanggalVisit($timestamp)
    {
        if (!$timestamp) return null;
        
        try {
            if (is_numeric($timestamp)) {
                // Convert milliseconds to seconds and create Carbon instance
                return Carbon::createFromTimestamp($timestamp / 1000)->format('Y-m-d H:i:s');
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
            return ResponseFormatter::error(null, 'Gagal mengambil data Badan Usaha: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all Division data with Badan Usaha relation
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
                        'created_at' => $item->created_at ? $item->created_at->format('Y-m-d H:i:s') : null,
                        'updated_at' => $item->updated_at ? $item->updated_at->format('Y-m-d H:i:s') : null,
                    ];
                });

            return ResponseFormatter::success($divisions, 'Data Divisi berhasil diambil');
        } catch (\Exception $e) {
            return ResponseFormatter::error(null, 'Gagal mengambil data Divisi: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all Region data with relations
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRegion()
    {
        try {
            $regions = Region::select('id', 'name', 'badanusaha_id', 'divisi_id', 'created_at', 'updated_at')
                ->with([
                    'badanusaha:id,name',
                    'divisi:id,name'
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
                        'created_at' => $item->created_at ? $item->created_at->format('Y-m-d H:i:s') : null,
                        'updated_at' => $item->updated_at ? $item->updated_at->format('Y-m-d H:i:s') : null,
                    ];
                });

            return ResponseFormatter::success($regions, 'Data Region berhasil diambil');
        } catch (\Exception $e) {
            return ResponseFormatter::error(null, 'Gagal mengambil data Region: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all Cluster data with relations
     * @return \Illuminate\Http\JsonResponse
     */
    public function getCluster()
    {
        try {
            $clusters = Cluster::select('id', 'name', 'badanusaha_id', 'divisi_id', 'region_id', 'created_at', 'updated_at')
                ->with([
                    'badanusaha:id,name',
                    'divisi:id,name',
                    'region:id,name'
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
                        'created_at' => $item->created_at ? $item->created_at->format('Y-m-d H:i:s') : null,
                        'updated_at' => $item->updated_at ? $item->updated_at->format('Y-m-d H:i:s') : null,
                    ];
                });

            return ResponseFormatter::success($clusters, 'Data Cluster berhasil diambil');
        } catch (\Exception $e) {
            return ResponseFormatter::error(null, 'Gagal mengambil data Cluster: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all Role data
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
                        'created_at' => $item->created_at ? $item->created_at->format('Y-m-d H:i:s') : null,
                        'updated_at' => $item->updated_at ? $item->updated_at->format('Y-m-d H:i:s') : null,
                    ];
                });

            return ResponseFormatter::success($roles, 'Data Role berhasil diambil');
        } catch (\Exception $e) {
            return ResponseFormatter::error(null, 'Gagal mengambil data Role: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all User data with relations
     * @return \Illuminate\Http\JsonResponse
     */
    public function getUser()
    {
        try {
            $users = User::select('id', 'nama_lengkap', 'username', 'password', 'role_id', 'badanusaha_id', 'divisi_id', 'region_id', 'cluster_id', 'cluster_id2', 'tm_id', 'created_at', 'updated_at')
                ->where('id', '!=', 691)
                ->with([
                    'role:id,name',
                    'badanusaha:id,name',
                    'divisi:id,name',
                    'region:id,name',
                    'cluster:id,name',
                    'cluster2:id,name',
                    'tm:id,nama_lengkap'
                ])
                ->orderBy('id')
                ->get()
                ->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'nama_lengkap' => $item->nama_lengkap,
                        'username' => $item->username,
                        'password' => $item->password,
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
                        'created_at' => $item->created_at ? $item->created_at->format('Y-m-d H:i:s') : null,
                        'updated_at' => $item->updated_at ? $item->updated_at->format('Y-m-d H:i:s') : null,
                    ];
                });

            return ResponseFormatter::success($users, 'Data User berhasil diambil');
        } catch (\Exception $e) {
            return ResponseFormatter::error(null, 'Gagal mengambil data User: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all Outlet data with relations
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
            return ResponseFormatter::error(null, 'Gagal mengambil data Outlet: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all Visit data with relations
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
                ->whereBetween('created_at', [$startDate, $endDate])
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
            return ResponseFormatter::error(null, 'Gagal mengambil data Visit: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get all Plan Visit data with relations
     * @return \Illuminate\Http\JsonResponse
     */
    public function getPlanVisit()
    {
        try {
            $planVisits = PlanVisit::select('id', 'tanggal_visit', 'user_id', 'outlet_id', 'created_at', 'updated_at')
                ->whereBetween('tanggal_visit', ['2025-07-01', '2025-08-30'])
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
            return ResponseFormatter::error(null, 'Gagal mengambil data Plan Visit: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Create visit data from external system
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
            if (!$durasi) {
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
            $imageNameIn = date('Y-m-d') . '-' . $username . '-' . 'IN-' . 
                           Carbon::now()->getPreciseTimestamp(3) . '.' . 
                           $request->picture_visit_in->extension();

            // Generate nama file foto check out
            $imageNameOut = date('Y-m-d') . '-' . $username . '-' . 'OUT-' . 
                            Carbon::now()->getPreciseTimestamp(3) . '.' . 
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
                'visit' => $visit
            ], 'Visit berhasil dibuat');

        } catch (\Exception $e) {
            return ResponseFormatter::error([
                'error' => $e->getMessage()
            ], 'Gagal membuat visit: ' . $e->getMessage(), 500);
        }
    }
}
