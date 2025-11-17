<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Region;
use App\Services\FileUploadService;
use App\Support\StorageDisk;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class OutletController extends Controller
{
    public function __construct(
        protected FileUploadService $fileUpload
    ) {}

    /**
     * Retrieve all outlets with complete relationship data
     *
     * Returns a comprehensive list of all outlets in the system including their business entity,
     * cluster, region, and division relationships. This endpoint is typically used for
     * administrative purposes and complete data synchronization.
     *
     * @response array{
     *   data: array{
     *     id: int,
     *     kode_outlet: string,
     *     nama_outlet: string,
     *     alamat_outlet: string,
     *     nama_pemilik_outlet: string,
     *     nomer_tlp_outlet: string,
     *     distric: string,
     *     badanusaha: array{id: int, name: string}|null,
     *     poto_shop_sign: string|null,
     *     poto_depan: string|null,
     *     poto_kiri: string|null,
     *     poto_kanan: string|null,
     *     poto_ktp: string|null,
     *     video: string|null,
     *     limit: string,
     *     radius: string,
     *     latlong: string,
     *     status_outlet: string,
     *     region: array{id: int, name: string}|null,
     *     cluster: array{id: int, name: string}|null,
     *     divisi: array{id: int, name: string}|null
     *   }[],
     *   message: string
     * }
     * @response 500 array{
     *   data: array{
     *     message: string,
     *     error: string
     *   },
     *   message: string
     * }
     */
    public function all()
    {
        try {
            $outlet = Outlet::with(['badanusaha', 'cluster', 'region', 'divisi'])
                ->get();

            return ResponseFormatter::success(
                $outlet->map->formatForAPI(),
                'berhasil'
            );
        } catch (Exception $e) {
            return ResponseFormatter::error([
                'message' => 'ada yang salah',
                'error' => $e,
            ], 'ERROR', 500);
        }
    }

    /**
     * Retrieve role-based outlet access with filtered results
     *
     * Returns outlets filtered based on the authenticated user's role and permissions.
     * Each role has specific filtering criteria to ensure users only access authorized outlets.
     *
     * **Role-based access patterns:**
     * - **ASM (role_id: 1)**: Requires divisi and region parameters
     * - **ASC (role_id: 2)**: Filtered by user's badanusaha, divisi, region, and cluster IDs
     * - **DSF/DM (role_id: 3)**: Same filtering as ASC role
     * - **COO (role_id: 6)**: Requires divisi and region parameters
     * - **CSO (role_id: 8)**: Requires divisi and region parameters
     * - **RKAM (role_id: 9)**: Requires divisi and region parameters
     * - **KAM (role_id: 10)**: Filtered by user's badanusaha, divisi, and region IDs
     * - **CSO FAST EV (role_id: 11)**: Requires divisi and region parameters
     *
     * @queryParam divisi string Required for roles: ASM, COO, CSO, RKAM, CSO FAST EV. Division name to filter outlets. Example: "Realme"
     * @queryParam region string Required for roles: ASM, COO, CSO, RKAM, CSO FAST EV. Region name to filter outlets. Example: "Jakarta"
     *
     * @response array{
     *   data: array{
     *     id: int,
     *     kode_outlet: string,
     *     nama_outlet: string,
     *     alamat_outlet: string,
     *     nama_pemilik_outlet: string,
     *     nomer_tlp_outlet: string,
     *     distric: string,
     *     badanusaha: array{id: int, name: string}|null,
     *     poto_shop_sign: string|null,
     *     poto_depan: string|null,
     *     poto_kiri: string|null,
     *     poto_kanan: string|null,
     *     poto_ktp: string|null,
     *     video: string|null,
     *     limit: string,
     *     radius: string,
     *     latlong: string,
     *     status_outlet: string,
     *     region: array{id: int, name: string}|null,
     *     cluster: array{id: int, name: string}|null,
     *     divisi: array{id: int, name: string}|null
     *   }[],
     *   message: int
     * }
     * @response 500 array{
     *   data: array{
     *     message: string,
     *     error: string
     *   },
     *   message: string
     * }
     */
    public function fetch(Request $request)
    {
        try {
            $user = Auth::user();

            // Eager load relationships untuk menghindari N+1
            $query = Outlet::with(['badanusaha', 'cluster', 'region', 'divisi']);

            // Roles yang memerlukan divisi & region dari request
            $roleName = $user->role?->name;
            $requiresDivisionRegion = in_array($roleName, ['ASM', 'COO', 'CSO', 'RKAM', 'CSO FAST EV'], true);

            if ($requiresDivisionRegion) {
                $divisi = Division::where('name', $request->divisi)->first();
                if (! $divisi) {
                    return ResponseFormatter::error(['divisi' => ['Division not found']], 'Division not found', 422);
                }
                $region = Region::where('name', $request->region)->where('divisi_id', $divisi->id)->first();
                if (! $region) {
                    return ResponseFormatter::error(['region' => ['Region not found']], 'Region not found', 422);
                }

                $outlet = $query
                    ->where('divisi_id', $divisi->id)
                    ->where('region_id', $region->id)
                    ->orderBy('nama_outlet')
                    ->get();
            } else {
                // Gunakan organizational scope trait
                $outlet = $query->visibleTo($user)->orderBy('nama_outlet')->get();
            }

            return ResponseFormatter::success(
                $outlet->map->formatForAPI(),
                count($outlet),
            );
        } catch (Exception $e) {

            return ResponseFormatter::error([
                'message' => 'ada yang salah',
                'error' => $e,
            ], 'ERROR', 500);
        }
    }

    /**
     * Retrieve single outlet by outlet code
     *
     * Returns detailed information about a specific outlet using its unique kode_outlet identifier.
     * Includes all relationships such as business entity, cluster, region, and division data.
     *
     * @param  Request  $request  The HTTP request instance
     * @param  string  $nama  The outlet code (kode_outlet) to retrieve
     *
     * @response array{
     *   data: array{
     *     id: int,
     *     kode_outlet: string,
     *     nama_outlet: string,
     *     alamat_outlet: string,
     *     nama_pemilik_outlet: string,
     *     nomer_tlp_outlet: string,
     *     distric: string,
     *     badanusaha: array{id: int, name: string}|null,
     *     poto_shop_sign: string|null,
     *     poto_depan: string|null,
     *     poto_kiri: string|null,
     *     poto_kanan: string|null,
     *     poto_ktp: string|null,
     *     video: string|null,
     *     limit: string,
     *     radius: string,
     *     latlong: string,
     *     status_outlet: string,
     *     region: array{id: int, name: string}|null,
     *     cluster: array{id: int, name: string}|null,
     *     divisi: array{id: int, name: string}|null
     *   }[],
     *   message: string
     * }
     * @response 404 array{
     *   data: null,
     *   message: string
     * }
     */
    public function singleOutlet(Request $request, $nama)
    {
        try {
            $outlet = Outlet::with(['badanusaha', 'cluster', 'region', 'divisi'])
                ->where('kode_outlet', $nama)
                ->first();

            if (! $outlet) {
                return ResponseFormatter::error(null, 'Outlet tidak ditemukan', 404);
            }

            return ResponseFormatter::success([$outlet->formatForAPI()], 'berhasil');
        } catch (Exception $err) {
            return ResponseFormatter::error(null, 'ada kesalahan');
        }
    }

    /**
     * Update outlet photos and owner information
     *
     * Updates outlet data including owner information and uploads photos/videos.
     * Supports both legacy (photo0-photo4) and modern (photos[]) upload schemes.
     * Files are automatically categorized based on naming patterns and stored with UUID names.
     *
     * **File categorization patterns:**
     * - Files containing "fotodepan" → poto_depan
     * - Files containing "fotokanan" → poto_kanan
     * - Files containing "fotokiri" → poto_kiri
     * - Files containing "fotoktp" → poto_ktp
     * - All other files → poto_shop_sign
     *
     * @bodyParam kode_outlet string required Outlet unique identifier. Example: "OUTLET001"
     * @bodyParam nama_pemilik_outlet string required Outlet owner name. Example: "John Doe"
     * @bodyParam nomer_tlp_outlet string required Outlet phone number. Example: "081234567890"
     * @bodyParam latlong string required Outlet coordinates. Example: "-6.2088,106.8456"
     * @bodyParam photo0 file optional Outlet photo file (legacy format). Max 5MB, formats: jpg,jpeg,png
     * @bodyParam photo1 file optional Outlet photo file (legacy format). Max 5MB, formats: jpg,jpeg,png
     * @bodyParam photo2 file optional Outlet photo file (legacy format). Max 5MB, formats: jpg,jpeg,png
     * @bodyParam photo3 file optional Outlet photo file (legacy format). Max 5MB, formats: jpg,jpeg,png
     * @bodyParam photo4 file optional Outlet photo file (legacy format). Max 5MB, formats: jpg,jpeg,png
     * @bodyParam photos file[] optional Array of outlet photos (modern format). Max 5MB each, formats: jpg,jpeg,png
     * @bodyParam video file optional Outlet video file. Max 50MB, formats: mp4,mov,webm
     *
     * @response array{
     *   data: null,
     *   message: string
     * }
     * @response 422 array{
     *   data: array{
     *     field: string[]
     *   },
     *   message: string
     * }
     * @response 404 array{
     *   data: null,
     *   message: string
     * }
     */
    public function updatefoto(Request $request)
    {
        try {
            $user = Auth::user();

            Log::channel('outlet')->info('Outlet update foto initiated', [
                'user_id' => $user->id,
                'kode_outlet' => $request->kode_outlet,
            ]);

            // Validasi dasar field non-file
            $baseRules = [
                'kode_outlet' => ['required'],
                'nama_pemilik_outlet' => ['required'],
                'nomer_tlp_outlet' => ['required'],
                'latlong' => ['required'],
            ];

            // Kumpulkan file yang ada untuk validasi dinamis
            $dynamicRules = [];
            // Dukungan skema lama: photo0..photo4
            for ($i = 0; $i <= 4; $i++) {
                if ($request->hasFile('photo'.$i)) {
                    $dynamicRules['photo'.$i] = ['file', 'image', 'mimes:jpg,jpeg,png', 'max:5120']; // 5MB
                }
            }
            // Dukungan skema baru: photos[]
            if ($request->hasFile('photos')) {
                $dynamicRules['photos'] = ['array'];
                $dynamicRules['photos.*'] = ['file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'];
            }
            // Video opsional
            if ($request->hasFile('video')) {
                $dynamicRules['video'] = ['file', 'mimetypes:video/mp4,video/quicktime,video/webm', 'max:51200']; // 50MB
            }

            $request->validate(array_merge($baseRules, $dynamicRules));

            $outlet = Outlet::where('kode_outlet', $request->kode_outlet)->first();
            if (! $outlet) {
                Log::channel('outlet')->warning('Outlet update foto failed: outlet not found', [
                    'user_id' => $user->id,
                    'kode_outlet' => $request->kode_outlet,
                ]);

                return ResponseFormatter::error(null, 'Outlet tidak ditemukan', 404);
            }

            // Proses foto (mendukung photo0..4 dan photos[])
            $photoFiles = [];
            for ($i = 0; $i <= 4; $i++) {
                $f = $request->file('photo'.$i);
                if ($f) {
                    $photoFiles[] = $f;
                }
            }
            if ($request->hasFile('photos')) {
                foreach ((array) $request->file('photos') as $pf) {
                    if ($pf) {
                        $photoFiles[] = $pf;
                    }
                }
            }

            foreach ($photoFiles as $file) {
                if (! $file->isValid()) {
                    return ResponseFormatter::error(null, 'File foto tidak valid', 422);
                }
                $original = $file->getClientOriginalName();
                // Tentukan kolom tujuan berdasarkan pola nama (kompatibel lama)
                if (Str::contains($original, 'fotodepan')) {
                    $targetField = 'poto_depan';
                } elseif (Str::contains($original, 'fotokanan')) {
                    $targetField = 'poto_kanan';
                } elseif (Str::contains($original, 'fotokiri')) {
                    $targetField = 'poto_kiri';
                } elseif (Str::contains($original, 'fotoktp')) {
                    $targetField = 'poto_ktp';
                } else {
                    $targetField = 'poto_shop_sign';
                }

                try {
                    $path = $this->fileUpload->uploadImageOptimized($file, 'outlet-photo');

                    $this->deleteOutletMedia($outlet->{$targetField});
                    $outlet->{$targetField} = $path;
                } catch (RuntimeException $e) {
                    return ResponseFormatter::error($e->getMessage(), 'INVALID_FILE', 422);
                }
            }

            // Proses video (opsional)
            if ($request->hasFile('video')) {
                try {
                    $path = $this->fileUpload->uploadVideoOptimized($request->file('video'), 'outlet-video');

                    $this->deleteOutletMedia($outlet->video);
                    $outlet->video = $path;
                } catch (RuntimeException $e) {
                    return ResponseFormatter::error($e->getMessage(), 'INVALID_VIDEO', 422);
                }
            }

            // Update field teks
            $outlet->nama_pemilik_outlet = strtoupper($request->nama_pemilik_outlet);
            $outlet->nomer_tlp_outlet = $request->nomer_tlp_outlet;
            $outlet->latlong = $request->latlong;
            $outlet->save();

            Log::channel('outlet')->info('Outlet update foto success', [
                'user_id' => $user->id,
                'outlet_id' => $outlet->id,
                'kode_outlet' => $outlet->kode_outlet,
            ]);

            return ResponseFormatter::success(null, 'berhasil Update');
        } catch (ValidationException $e) {
            return ResponseFormatter::error($e->errors(), 'VALIDATION_ERROR', 422);
        } catch (Exception $e) {
            error_log($e->getMessage());

            return ResponseFormatter::error(null, $e->getMessage(), 400);
        }
    }

    protected function deleteOutletMedia(?string $path): void
    {
        if (! $path || $path === '-' || $path === '0') {
            return;
        }

        $disk = StorageDisk::default();

        try {
            Storage::disk($disk)->delete($path);
        } catch (Exception $exception) {
            Log::channel('outlet')->warning('Failed to delete outlet media', [
                'path' => $path,
                'disk' => $disk,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
