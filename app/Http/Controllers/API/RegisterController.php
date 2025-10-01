<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Helpers\SendNotif;
use App\Http\Controllers\Controller;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Register;
use App\Models\Outlet;
use App\Models\Region;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @group New Outlet Opening (NOO) Management
 *
 * API endpoints for managing New Outlet Opening (NOO) requests with sophisticated role-based access control,
 * approval workflows, and file upload capabilities. The system supports 8 different user roles with
 * hierarchical access patterns and a 3-stage approval process (submit → confirm → approve/reject).
 *
 * ## User Roles & Access Patterns:
 * - **ASM (ID: 1)**: Area Sales Manager - Access to NOOs created by their TM. Special case: user ID 158 (sodikc) has access to regions Bigtasik, Bigcrb, Bigpwt, Bigbdg, Bigkarawang with Realme division.
 * - **ASC (ID: 2)**: Area Sales Coordinator - Access to NOOs within their business unit, division, and region.
 * - **DSF/DM (ID: 3)**: District Sales Field/Manager - Access to NOOs within their specific cluster area.
 * - **COO (ID: 6)**: Chief Operating Officer - Full access to all NOOs across the system.
 * - **CSO (ID: 8)**: Chief Sales Officer - Access to NOOs with Realme division only.
 * - **RKAM (ID: 9)**: Regional Key Account Manager - Access to NOOs created by their TM, similar to ASM.
 * - **KAM (ID: 10)**: Key Account Manager - Access to NOOs within their business unit, division, and region.
 * - **CSO FAST EV (ID: 11)**: Chief Sales Officer Fast EV - Access to NOOs with Fast EV division.
 *
 * ## Approval Workflow:
 * 1. **Submit**: Initial NOO creation with role-based data assignment and automatic notification to appropriate stakeholders (AR/TM/ASC based on user hierarchy).
 * 2. **Confirm**: AR confirms the NOO with limit assignment and outlet code generation.
 * 3. **Approve/Reject**: Final decision - Approved NOOs automatically create outlet records; rejected NOOs track rejection reasons.
 *
 * ## File Upload System:
 * - **Photos**: Supports up to 5 photos (photo0-photo4) with intelligent categorization based on filename patterns:
 *   - Files containing "fotodepan" → Front photo (poto_depan)
 *   - Files containing "fotokanan" → Right photo (poto_kanan)
 *   - Files containing "fotokiri" → Left photo (poto_kiri)
 *   - Files containing "fotoktp" → KTP photo (poto_ktp)
 *   - All other photos → Shop sign photo (poto_shop_sign)
 * - **Videos**: Single video upload support with format validation.
 * - **Validation**: Photos max 5MB, videos max 50MB with strict MIME type checking.
 *
 * @authenticated
 *
 * @header Authorization Bearer {token}
 */
class RegisterController extends Controller
{
    /**
     * Fetch NOOs with Role-Based Access Control
     *
     * Retrieves NOOs based on the authenticated user's role and hierarchical permissions.
     * Each role has specific filtering logic for data access and visibility.
     *
     * **Role-Based Filtering Logic:**
     * - **ASM (ID: 1)**: Filters NOOs by TM_id. Special case for user ID 158 (sodikc) with regions [13, 27, 26, 23, 24] and division 4 (Realme).
     * - **ASC (ID: 2)**: Filters by business unit, division, and region hierarchy.
     * - **DSF/DM (ID: 3)**: Most restrictive - filters by complete hierarchy including cluster.
     * - **COO (ID: 6)**: Full access to all NOOs without filtering.
     * - **CSO (ID: 8)**: Filters by Realme division (division_id = 4).
     * - **RKAM (ID: 9)**: Similar to ASM - filters by TM_id.
     * - **KAM (ID: 10)**: Similar to ASC - filters by business unit, division, and region.
     * - **CSO FAST EV (ID: 11)**: Filters by Fast EV division (division_id = 7).
     *
     * **Response Structure:**
     * Each NOO includes complete hierarchical relationships (badanusaha, cluster, region, divisi)
     * and formatted data using the Noo model's formatForAPI method.
     *
     * @authenticated
     *
     * @response 200 {
     *   "meta": {
     *     "code": 200,
     *     "status": "success",
     *     "message": "fetch noo success"
     *   },
     *   "data": [
     *     {
     *       "id": 123,
     *       "kode_outlet": "TOK-2024-001",
     *       "nama_outlet": "Toko Maju Jaya",
     *       "alamat_outlet": "Jl. Raya No. 123, Jakarta",
     *       "nama_pemilik_outlet": "Budi Santoso",
     *       "nomer_tlp_outlet": "081234567890",
     *       "nomer_wakil_outlet": "081234567891",
     *       "ktp_outlet": "1234567890123456",
     *       "distric": "Jakarta Pusat",
     *       "region": {"id": 1, "name": "Jakarta"},
     *       "poto_shop_sign": "noo/photos/uuid1.jpg",
     *       "poto_depan": "noo/photos/uuid2.jpg",
     *       "poto_kiri": "noo/photos/uuid3.jpg",
     *       "poto_kanan": "noo/photos/uuid4.jpg",
     *       "poto_ktp": "noo/photos/uuid5.jpg",
     *       "video": "noo/videos/uuid1.mp4",
     *       "oppo": true,
     *       "vivo": false,
     *       "realme": true,
     *       "samsung": false,
     *       "xiaomi": false,
     *       "fl": false,
     *       "latlong": "-6.2088,106.8456",
     *       "limit": 5000000,
     *       "status": "CONFIRMED",
     *       "rejected_at": null,
     *       "rejected_by": null,
     *       "confirmed_at": 1705306200000,
     *       "confirmed_by": "Ahmad Rizki",
     *       "approved_at": null,
     *       "approved_by": null,
     *       "deleted_at": null,
     *       "created_at": 1705302600000,
     *       "updated_at": 1705306200000,
     *       "keterangan": null,
     *       "cluster": {"id": 1, "name": "Jakarta Pusat"},
     *       "badanusaha": {"id": 1, "name": "PT. Maju Bersama"},
     *       "divisi": {"id": 1, "name": "Realme"},
     *       "created_by": "Ahmad Rizki"
     *     }
     *   ]
     * }
     * @response 500 {
     *   "meta": {
     *     "code": 500,
     *     "status": "error",
     *     "message": "something wrong"
     *   },
     *   "data": {
     *     "message": "[Exception details]"
     *   }
     * }
     *
     * @param  Request  $request  HTTP request instance
     * @return \Illuminate\Http\JsonResponse
     */
    public function fetch(Request $request)
    {
        try {
            $user = Auth::user();
            $badanusahaId = $user->badanusaha_id;
            $divisiId = $user->divisi_id;
            $regionId = $user->region_id;
            $clusterId = $user->cluster_id;
            $roleId = $user->role_id;

            $query = Register::with(['badanusaha', 'cluster', 'region', 'divisi']);

            switch ($roleId) {
                // ASM
                case 1:
                    // Cek jika akunnya adalah sodikc maka ambil data dari region Bigtasik, Bigcrb, Bigpwt, Bigbdg, Bigkarawang dengan divisi realme
                    if ($user->id === 158) {
                        $registers = $query
                            ->whereIn('region_id', [13, 27, 26, 23, 24])
                            ->where('divisi_id', 4)
                            ->latest()
                            ->get();
                    } else {
                        $registers = $query
                            ->where('tm_id', $user->id)
                            ->latest()
                            ->get();
                    }

                    // Rule lama
                    // $registers = $query
                    // ->where('tm_id', $user->id)
                    // ->latest()
                    // ->get();

                    break;
                    // ASC
                case 2:
                    $registers = $query
                        ->where('badanusaha_id', $badanusahaId)
                        ->where('divisi_id', $divisiId)
                        ->where('region_id', $regionId)
                        ->latest()
                        ->get();
                    break;
                    // DSF/DM
                case 3:
                    $registers = $query
                        ->where('badanusaha_id', $badanusahaId)
                        ->where('divisi_id', $divisiId)
                        ->where('region_id', $regionId)
                        ->where('cluster_id', $clusterId)
                        ->orderBy('updated_at', 'DESC')
                        ->get();
                    break;
                    // COO
                case 6:
                    $registers = $query
                        ->latest()
                        ->get();
                    break;
                    // CSO
                case 8:
                    $registers = $query
                        ->where('divisi_id', 4)
                        ->latest()
                        ->get();
                    break;
                    // RKAM
                case 9:
                    $registers = $query
                        ->where('tm_id', $user->id)
                        ->latest()
                        ->get();
                    break;
                    // KAM
                case 10:
                    $registers = $query
                        ->where('badanusaha_id', $badanusahaId)
                        ->where('divisi_id', $divisiId)
                        ->where('region_id', $regionId)
                        ->latest()
                        ->get();
                    break;

                    // CSO FAST EV
                case 11:
                    $registers = $query
                        ->where('divisi_id', 7)
                        ->latest()
                        ->get();
                    break;

                default:
                    $registers = Register::with(['badanusaha', 'cluster', 'region', 'divisi'])->where('badanusaha_id', 2)->orWhere('badanusaha_id', 4)->whereIn('status', ['PENDING', 'CONFIRMED', 'REJECTED'])->latest()->get();
                    break;
            }

            return ResponseFormatter::success(
                $registers->map->formatForAPI(),
                'fetch register success',
            );
        } catch (Exception $err) {
            return ResponseFormatter::error([
                'message' => $err,
            ], 'something wrong', 500);
        }
    }

    /**
     * Get All NOOs with Complete Relationships
     *
     * Retrieves all NOOs in the system with complete hierarchical relationships loaded.
     * This endpoint provides unrestricted access to all NOO records regardless of user role,
     * including their business unit, cluster, region, and division associations.
     *
     * **Use Cases:**
     * - Administrative oversight and reporting
     * - System-wide data analysis
     * - Complete NOO lifecycle management
     * - Backup and data export operations
     *
     * **Response Structure:**
     * Each NOO includes all related hierarchical entities (badanusaha, cluster, region, divisi)
     * and is formatted using the Noo model's formatForAPI method for consistent API response structure.
     *
     * @authenticated
     *
     * @response 200 {
     *   "meta": {
     *     "code": 200,
     *     "status": "success",
     *     "message": "fetch noo success"
     *   },
     *   "data": [
     *     {
     *       "id": 123,
     *       "kode_outlet": "TOK-2024-001",
     *       "nama_outlet": "Toko Maju Jaya",
     *       "alamat_outlet": "Jl. Raya No. 123, Jakarta",
     *       "nama_pemilik_outlet": "Budi Santoso",
     *       "nomer_tlp_outlet": "081234567890",
     *       "nomer_wakil_outlet": "081234567891",
     *       "ktp_outlet": "1234567890123456",
     *       "distric": "Jakarta Pusat",
     *       "region": {"id": 1, "name": "Jakarta"},
     *       "poto_shop_sign": "noo/photos/uuid1.jpg",
     *       "poto_depan": "noo/photos/uuid2.jpg",
     *       "poto_kiri": "noo/photos/uuid3.jpg",
     *       "poto_kanan": "noo/photos/uuid4.jpg",
     *       "poto_ktp": "noo/photos/uuid5.jpg",
     *       "video": "noo/videos/uuid1.mp4",
     *       "oppo": true,
     *       "vivo": false,
     *       "realme": true,
     *       "samsung": false,
     *       "xiaomi": false,
     *       "fl": false,
     *       "latlong": "-6.2088,106.8456",
     *       "limit": 5000000,
     *       "status": "APPROVED",
     *       "rejected_at": null,
     *       "rejected_by": null,
     *       "confirmed_at": 1705306200000,
     *       "confirmed_by": "Ahmad Rizki",
     *       "approved_at": 1705309800000,
     *       "approved_by": "Budi Santoso",
     *       "deleted_at": null,
     *       "created_at": 1705302600000,
     *       "updated_at": 1705309800000,
     *       "keterangan": null,
     *       "cluster": {"id": 1, "name": "Jakarta Pusat"},
     *       "badanusaha": {"id": 1, "name": "PT. Maju Bersama"},
     *       "divisi": {"id": 1, "name": "Realme"},
     *       "created_by": "Ahmad Rizki"
     *     },
     *     {
     *       "id": 124,
     *       "kode_outlet": "TOK-2024-002",
     *       "nama_outlet": "Toko Sejahtera",
     *       "alamat_outlet": "Jl. Sudirman No. 45, Bandung",
     *       "nama_pemilik_outlet": "Siti Aminah",
     *       "nomer_tlp_outlet": "081234567892",
     *       "nomer_wakil_outlet": "081234567893",
     *       "ktp_outlet": "9876543210987654",
     *       "distric": "Bandung Tengah",
     *       "region": {"id": 2, "name": "Bandung"},
     *       "poto_shop_sign": "noo/photos/uuid6.jpg",
     *       "poto_depan": "noo/photos/uuid7.jpg",
     *       "poto_kiri": "noo/photos/uuid8.jpg",
     *       "poto_kanan": "noo/photos/uuid9.jpg",
     *       "poto_ktp": "noo/photos/uuid10.jpg",
     *       "video": null,
     *       "oppo": false,
     *       "vivo": true,
     *       "realme": false,
     *       "samsung": true,
     *       "xiaomi": false,
     *       "fl": false,
     *       "latlong": "-6.9175,107.6191",
     *       "limit": 3000000,
     *       "status": "PENDING",
     *       "rejected_at": null,
     *       "rejected_by": null,
     *       "confirmed_at": null,
     *       "confirmed_by": null,
     *       "approved_at": null,
     *       "approved_by": null,
     *       "deleted_at": null,
     *       "created_at": 1705303000000,
     *       "updated_at": 1705303000000,
     *       "keterangan": null,
     *       "cluster": {"id": 3, "name": "Bandung Utara"},
     *       "badanusaha": {"id": 1, "name": "PT. Maju Bersama"},
     *       "divisi": {"id": 2, "name": "Oppo"},
     *       "created_by": "Siti Aminah"
     *     }
     *   ]
     * }
     * @response 500 {
     *   "meta": {
     *     "code": 500,
     *     "status": "error",
     *     "message": "something wrong"
     *   },
     *   "data": {
     *     "message": "[Exception details]"
     *   }
     * }
     *
     * @param  Request  $request  HTTP request instance
     * @return \Illuminate\Http\JsonResponse
     */
    public function all(Request $request)
    {
        try {
            $registers = Register::with(['badanusaha', 'cluster', 'region', 'divisi'])->get();

            return ResponseFormatter::success(
                $registers->map->formatForAPI(),
                'fetch register success',
            );
        } catch (Exception $err) {
            return ResponseFormatter::error([
                'message' => $err,
            ], 'something wrong', 500);
        }
    }

    /**
     * Submit New NOO Request
     *
     * Creates a new New Outlet Opening (NOO) request with role-based data assignment and file upload capabilities.
     * This is the first stage of the 3-stage approval workflow (submit → confirm → approve/reject).
     *
     * **Role-Based Data Assignment:**
     * - **ASM (ID: 1)**: Allows selection of business unit, division, region, and cluster for complete hierarchy assignment.
     * - **ASC (ID: 2)**: Uses user's business unit, division, and region, with cluster selection from available options.
     * - **RKAM (ID: 9)**: Similar to ASM - allows complete hierarchy selection.
     * - **KAM (ID: 10)**: Similar to ASC - uses user's hierarchy with cluster selection.
     * - **Other roles**: Uses user's complete hierarchical data (business unit, division, region, cluster).
     *
     * **File Upload System:**
     * - **Photos**: Supports up to 5 photos (photo0-photo4) with intelligent categorization:
     *   - Files containing "fotodepan" → poto_depan (front photo)
     *   - Files containing "fotokanan" → poto_kanan (right photo)
     *   - Files containing "fotokiri" → poto_kiri (left photo)
     *   - Files containing "fotoktp" → poto_ktp (KTP photo)
     *   - All other photos → poto_shop_sign (shop sign photo)
     * - **Videos**: Single video upload support
     * - **Validation**: Photos max 5MB (JPG/JPEG/PNG), videos max 50MB (MP4/QuickTime/WebM)
     *
     * **Notification System:**
     * Automatically sends notifications to appropriate stakeholders based on user role:
     * - **ASM/RKAM**: Notifies AR (role_id 4)
     * - **ASC/KAM**: Notifies AR and TM
     * - **Other roles**: Notifies AR, TM, and ASC (if available)
     *
     * @authenticated
     *
     * @bodyParam nama_outlet string required Outlet name (max: 255). Example: "Toko Maju Jaya"
     * @bodyParam alamat_outlet string required Outlet address (max: 255). Example: "Jl. Raya No. 123, Jakarta"
     * @bodyParam nama_pemilik string required Owner name (max: 255). Example: "Budi Santoso"
     * @bodyParam nomer_pemilik string required Owner phone number (max: 255). Example: "081234567890"
     * @bodyParam nomer_perwakilan string required Representative phone number (max: 255). Example: "081234567891"
     * @bodyParam ktpnpwp string required KTP/NPWP number (max: 255). Example: "1234567890123456"
     * @bodyParam distric string required District name (max: 255). Example: "Jakarta Pusat"
     * @bodyParam oppo boolean required Oppo brand presence. Example: true
     * @bodyParam vivo boolean required Vivo brand presence. Example: false
     * @bodyParam samsung boolean required Samsung brand presence. Example: true
     * @bodyParam xiaomi boolean required Xiaomi brand presence. Example: false
     * @bodyParam realme boolean required Realme brand presence. Example: true
     * @bodyParam fl boolean required FL brand presence. Example: false
     * @bodyParam latlong string required Latitude and longitude coordinates. Example: "-6.2088,106.8456"
     * @bodyParam bu string required Business unit name (for ASM/RKAM roles). Example: "PT. Maju Bersama"
     * @bodyParam div string required Division name (for ASM/RKAM roles). Example: "Realme"
     * @bodyParam reg string required Region name (for ASM/RKAM roles). Example: "Jakarta"
     * @bodyParam clus string required Cluster name (for ASM/ASC/RKAM/KAM roles). Example: "Jakarta Pusat"
     * @bodyParam photo0 file optional Front photo (max 5MB, JPG/JPEG/PNG). Example: "fotodepan_outlet.jpg"
     * @bodyParam photo1 file optional Right photo (max 5MB, JPG/JPEG/PNG). Example: "fotokanan_outlet.jpg"
     * @bodyParam photo2 file optional Left photo (max 5MB, JPG/JPEG/PNG). Example: "fotokiri_outlet.jpg"
     * @bodyParam photo3 file optional KTP photo (max 5MB, JPG/JPEG/PNG). Example: "fotoktp_pemilik.jpg"
     * @bodyParam photo4 file optional Shop sign photo (max 5MB, JPG/JPEG/PNG). Example: "shop_sign_outlet.jpg"
     * @bodyParam video file optional Video file (max 50MB, MP4/QuickTime/WebM). Example: "outlet_tour.mp4"
     *
     * @response 200 {
     *   "meta": {
     *     "code": 200,
     *     "status": "success",
     *     "message": "berhasil menambahkan NOO Toko Maju Jaya"
     *   },
     *   "data": null
     * }
     * @response 422 {
     *   "meta": {
     *     "code": 422,
     *     "status": "error",
     *     "message": "The given data was invalid."
     *   },
     *   "data": {
     *     "nama_outlet": ["The nama outlet field is required."]
     *   }
     * }
     * @response 422 {
     *   "meta": {
     *     "code": 422,
     *     "status": "error",
     *     "message": "INVALID_FILE"
     *   },
     *   "data": "File foto tidak valid"
     * }
     * @response 500 {
     *   "meta": {
     *     "code": 500,
     *     "status": "error",
     *     "message": "gagal"
     *   },
     *   "data": "[Exception details]"
     * }
     *
     * @param  Request  $request  HTTP request with NOO data and files
     * @return \Illuminate\Http\JsonResponse
     */
    public function submit(Request $request)
    {
        try {
            $user = Auth::user();
            $data = [
                'nama_outlet' => $request->nama_outlet,
                'alamat_outlet' => $request->alamat_outlet,
                'nama_pemilik_outlet' => $request->nama_pemilik,
                'nomer_tlp_outlet' => $request->nomer_pemilik,
                'nomer_wakil_outlet' => $request->nomer_perwakilan,
                'ktp_outlet' => $request->ktpnpwp,
                'distric' => $request->distric,
                'oppo' => $request->oppo,
                'vivo' => $request->vivo,
                'samsung' => $request->samsung,
                'xiaomi' => $request->xiaomi,
                'realme' => $request->realme,
                'fl' => $request->fl,
                'latlong' => $request->latlong,
                'created_by' => $user->nama_lengkap,
                'tm_id' => $user->tm->id,
            ];
            switch ($user->role_id) {
                // ASM
                case 1:
                    $badanusaha_id = BadanUsaha::where('name', $request->bu)->first()->id;
                    $divisi_id = Division::where('badanusaha_id', $badanusaha_id)->where('name', $request->div)->first()->id;
                    $region_id = Region::where('badanusaha_id', $badanusaha_id)->where('divisi_id', $divisi_id)->where('name', $request->reg)->first()->id;
                    $cluster_id = Cluster::where('badanusaha_id', $badanusaha_id)->where('divisi_id', $divisi_id)->where('region_id', $region_id)->where('name', $request->clus)->first()->id;
                    $data['badanusaha_id'] = $badanusaha_id;
                    $data['divisi_id'] = $divisi_id;
                    $data['region_id'] = $region_id;
                    $data['cluster_id'] = $cluster_id;
                    break;

                    // ASC
                case 2:
                    $data['badanusaha_id'] = $user->badanusaha_id;
                    $data['divisi_id'] = $user->divisi_id;
                    $data['region_id'] = $user->region_id;
                    $data['cluster_id'] = Cluster::where('badanusaha_id', $user->badanusaha_id)->where('divisi_id', $user->divisi_id)->where('region_id', $user->region_id)->where('name', $request->clus)->first()->id;
                    error_log($data['cluster_id']);
                    break;

                    // RKAM
                case 9:
                    $badanusaha_id = BadanUsaha::where('name', $request->bu)->first()->id;
                    $divisi_id = Division::where('badanusaha_id', $badanusaha_id)->where('name', $request->div)->first()->id;
                    $region_id = Region::where('badanusaha_id', $badanusaha_id)->where('divisi_id', $divisi_id)->where('name', $request->reg)->first()->id;
                    $cluster_id = Cluster::where('badanusaha_id', $badanusaha_id)->where('divisi_id', $divisi_id)->where('region_id', $region_id)->where('name', $request->clus)->first()->id;
                    $data['badanusaha_id'] = $badanusaha_id;
                    $data['divisi_id'] = $divisi_id;
                    $data['region_id'] = $region_id;
                    $data['cluster_id'] = $cluster_id;
                    break;

                    // KAM
                case 10:
                    $data['badanusaha_id'] = $user->badanusaha_id;
                    $data['divisi_id'] = $user->divisi_id;
                    $data['region_id'] = $user->region_id;
                    $data['cluster_id'] = Cluster::where('badanusaha_id', $user->badanusaha_id)->where('divisi_id', $user->divisi_id)->where('region_id', $user->region_id)->where('name', $request->clus)->first()->id;
                    error_log($data['cluster_id']);
                    break;

                default:
                    $data['badanusaha_id'] = $user->badanusaha_id;
                    $data['divisi_id'] = $user->divisi_id;
                    $data['region_id'] = $user->region_id;
                    $data['cluster_id'] = $user->cluster_id;
                    break;
            }

            // Validasi dinamis
            $rules = [];
            for ($i = 0; $i <= 4; $i++) {
                if ($request->hasFile('photo'.$i)) {
                    $rules['photo'.$i] = ['file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'];
                }
            }
            if ($request->hasFile('video')) {
                $rules['video'] = ['file', 'mimetypes:video/mp4,video/quicktime,video/webm', 'max:51200']; // 50MB
            }
            if (! empty($rules)) {
                $request->validate($rules);
            }

            $disk = Storage::disk('public');
            for ($i = 0; $i <= 4; $i++) {
                $file = $request->file('photo'.$i);
                if (! $file) {
                    continue;
                }
                if (! $file->isValid()) {
                    return ResponseFormatter::error('File foto tidak valid', 'INVALID_FILE', 422);
                }
                $original = $file->getClientOriginalName();
                if (Str::contains($original, 'fotodepan')) {
                    $target = 'poto_depan';
                } elseif (Str::contains($original, 'fotokanan')) {
                    $target = 'poto_kanan';
                } elseif (Str::contains($original, 'fotokiri')) {
                    $target = 'poto_kiri';
                } elseif (Str::contains($original, 'fotoktp')) {
                    $target = 'poto_ktp';
                } else {
                    $target = 'poto_shop_sign';
                }
                $ext = $file->guessExtension() ?: $file->extension();
                $name = (string) Str::uuid().'.'.$ext;
                $path = $disk->putFileAs('register/photos', $file, $name);
                $data[$target] = $path;
            }

            if ($request->hasFile('video')) {
                $video = $request->file('video');
                if (! $video->isValid()) {
                    return ResponseFormatter::error('File video tidak valid', 'INVALID_FILE', 422);
                }
                $vext = $video->guessExtension() ?: $video->extension();
                $vname = (string) Str::uuid().'.'.$vext;
                $vpath = $disk->putFileAs('register/videos', $video, $vname);
                $data['video'] = $vpath;
            }

            switch ($user->id) {
                // ASM
                case 1:
                    $notifId = [];
                    array_push($notifId, User::where('role_id', 4)->first()->id_notif);
                    break;
                    // ASC
                case 2:
                    $notifId = [];
                    // notif ar
                    array_push($notifId, User::where('role_id', 4)->first()->id_notif);
                    // notif tm
                    array_push($notifId, $user->tm->id_notif);
                    break;
                    // RKAM
                case 9:
                    $notifId = [];
                    array_push($notifId, User::where('role_id', 4)->first()->id_notif);
                    break;
                    // KAM
                case 10:
                    $notifId = [];
                    // notif ar
                    array_push($notifId, User::where('role_id', 4)->first()->id_notif);
                    // notif tm
                    array_push($notifId, $user->tm->id_notif);
                    break;
                default:
                    $notifId = [];
                    // notif ar
                    array_push($notifId, User::where('role_id', 4)->first()->id_notif);
                    // notif tm
                    array_push($notifId, $user->tm->id_notif);
                    // notif asc
                    $asc = User::where('role_id', 2)->where('divisi_id', $user->divisi_id)->where('region_id', $user->region_id)->first()->id_notif ?? null;
                    if ($asc) {
                        array_push($notifId, $asc);
                    }
                    break;
            }
            $insert = Register::create($data);
            if ($insert && count($notifId) != 0) {
                SendNotif::sendMessage('Register baru '.$request->nama_outlet.' ditambahkan oleh '.Auth::user()->nama_lengkap, $notifId);
            }

            return ResponseFormatter::success(null, 'berhasil menambahkan register '.$request->nama_outlet);
        } catch (Exception $e) {
            error_log($e);

            return ResponseFormatter::error($e, 'gagal');
        }
    }

    /**
     * Confirm NOO Request (Stage 2 of Approval Workflow)
     *
     * Confirms a submitted NOO request by setting credit limit and outlet code.
     * This is the second stage of the 3-stage approval workflow (submit → confirm → approve/reject).
     * Typically performed by AR (Area Representative) role.
     *
     * **Business Logic:**
     * - Updates NOO status to CONFIRMED
     * - Sets credit limit for the outlet
     * - Assigns unique outlet code
     * - Records confirmation details (who and when)
     * - Sends notification to original creator and TM
     *
     * **Notification System:**
     * Automatically sends confirmation notification to:
     * - The user who created the NOO
     * - The TM (Territory Manager) associated with the NOO
     * - Notification includes limit amount formatted as Indonesian currency
     *
     * @authenticated
     *
     * @bodyParam id integer required NOO record ID to confirm. Example: 123
     * @bodyParam status string required New status (typically "CONFIRMED"). Example: "CONFIRMED"
     * @bodyParam limit integer required Credit limit amount. Example: 5000000
     * @bodyParam kode_outlet string required Unique outlet code. Example: "TOK-2024-001"
     *
     * @response 200 {
     *   "meta": {
     *     "code": 200,
     *     "status": "success",
     *     "message": "berhasil update"
     *   },
     *   "data": {
     *     "id": 123,
     *     "nama_outlet": "Toko Maju Jaya",
     *     "status": "CONFIRMED",
     *     "limit": 5000000,
     *     "kode_outlet": "TOK-2024-001",
     *     "confirmed_by": "Ahmad Rizki",
     *     "confirmed_at": "2024-01-15T10:30:00.000000Z"
     *   }
     * }
     * @response 422 {
     *   "meta": {
     *     "code": 422,
     *     "status": "error",
     *     "message": "The given data was invalid."
     *   },
     *   "data": {
     *     "id": ["The id field is required."]
     *   }
     * }
     * @response 404 {
     *   "meta": {
     *     "code": 404,
     *     "status": "error",
     *     "message": "No query results for model [App\\Models\\Noo] 123"
     *   }
     * }
     * @response 500 {
     *   "meta": {
     *     "code": 500,
     *     "status": "error",
     *     "message": "gagal"
     *   },
     *   "data": "[Exception details]"
     * }
     *
     * @param  Request  $request  HTTP request with confirmation data
     * @return \Illuminate\Http\JsonResponse
     */
    public function confirm(Request $request)
    {
        try {

            $request->validate([
                'id' => ['required'],
                'status' => ['required'],
                'limit' => ['required'],
                'kode_outlet' => ['required'],
            ]);

            $register = Register::findOrFail($request->id);
            $register->status = $request->status;
            $register->limit = $request->limit;
            $register->kode_outlet = $request->kode_outlet;
            $register->confirmed_by = Auth::user()->nama_lengkap;
            $register->confirmed_at = now();
            $register->update();
            SendNotif::sendMessage(
                'Register '.$register->nama_outlet.' sudah dikonfirmasi oleh '.
                    Auth::user()->nama_lengkap.PHP_EOL.
                    'Dengan limit : Rp '.number_format($request->limit, 0, ',', '.'),
                [User::where('nama_lengkap', $register->created_by)->first()->id_notif ?? '-', $register->tm->id_notif]

            );

            return ResponseFormatter::success($register, 'berhasil update');
        } catch (Exception $e) {
            error_log($e);

            return ResponseFormatter::error($e, 'gagal');
        }
    }

    /**
     * Approve NOO Request (Final Stage with Automatic Outlet Creation)
     *
     * Approves a confirmed NOO request, updating its status and automatically creating
     * an Outlet record. This is the final stage of the 3-stage approval workflow.
     * Typically performed by management roles with approval authority.
     *
     * **Business Logic:**
     * - Updates NOO status to APPROVED
     * - Records approval details (who and when)
     * - **Automatic Outlet Creation**: Creates a new Outlet record with:
     *   - Same hierarchical data as NOO (business unit, division, region, cluster)
     *   - All outlet information (name, address, owner details, etc.)
     *   - All photos and videos transferred from NOO
     *   - Default values: radius=0, status_outlet='MAINTAIN'
     *   - Preserves the assigned limit from confirmation stage
     * - **Duplicate Prevention**: Checks for existing outlets with same hierarchy and code
     *
     * **Notification System:**
     * Automatically sends approval notification to:
     * - The user who created the original NOO (if notification ID exists)
     *
     * **Data Transfer:**
     * All NOO data including photos (poto_depan, poto_kanan, poto_kiri, poto_ktp, poto_shop_sign)
     * and videos are transferred to the new Outlet record to maintain data consistency.
     *
     * @authenticated
     *
     * @bodyParam id integer required NOO record ID to approve. Example: 123
     * @bodyParam status string required New status (typically "APPROVED"). Example: "APPROVED"
     *
     * @response 200 {
     *   "meta": {
     *     "code": 200,
     *     "status": "success",
     *     "message": "berhasil update"
     *   },
     *   "data": {
     *     "id": 123,
     *     "nama_outlet": "Toko Maju Jaya",
     *     "status": "APPROVED",
     *     "kode_outlet": "TOK-2024-001",
     *     "approved_by": "Budi Santoso",
     *     "approved_at": "2024-01-15T11:00:00.000000Z",
     *     "limit": 5000000
     *   }
     * }
     * @response 200 {
     *   "meta": {
     *     "code": 200,
     *     "status": "success",
     *     "message": "berhasil update"
     *   },
     *   "data": {
     *     "id": 123,
     *     "nama_outlet": "Toko Maju Jaya",
     *     "status": "APPROVED",
     *     "note": "Outlet already exists, NOO approved without duplicate creation"
     *   }
     * }
     * @response 422 {
     *   "meta": {
     *     "code": 422,
     *     "status": "error",
     *     "message": "The given data was invalid."
     *   },
     *   "data": {
     *     "id": ["The id field is required."]
     *   }
     * }
     * @response 404 {
     *   "meta": {
     *     "code": 404,
     *     "status": "error",
     *     "message": "No query results for model [App\\Models\\Noo] 123"
     *   }
     * }
     * @response 500 {
     *   "meta": {
     *     "code": 500,
     *     "status": "error",
     *     "message": "gagal"
     *   },
     *   "data": "[Exception details]"
     * }
     *
     * @param  Request  $request  HTTP request with approval data
     * @return \Illuminate\Http\JsonResponse
     */
    public function approved(Request $request)
    {
        try {

            $request->validate([
                'id' => ['required'],
                'status' => ['required'],
            ]);

            $register = Register::find($request->id);
            $register->status = $request->status;
            $register->approved_by = Auth::user()->nama_lengkap;
            $register->approved_at = now();
            $register->update();

            $notif = [];
            $creatorNotifId = User::where('nama_lengkap', $register->created_by)->first()->id_notif;
            if ($creatorNotifId) {
                array_push($notif, $creatorNotifId);
            }

            $data = [
                'kode_outlet' => $register->kode_outlet,
                'badanusaha_id' => $register->badanusaha_id,
                'nama_outlet' => $register->nama_outlet,
                'divisi_id' => $register->divisi_id,
                'alamat_outlet' => $register->alamat_outlet,
                'nama_pemilik_outlet' => $register->nama_pemilik_outlet,
                'nomer_tlp_outlet' => $register->nomer_tlp_outlet,
                'distric' => $register->distric,
                'region_id' => $register->region_id,
                'cluster_id' => $register->cluster_id,
                'poto_shop_sign' => $register->poto_shop_sign,
                'poto_depan' => $register->poto_depan,
                'poto_kanan' => $register->poto_kanan,
                'poto_kiri' => $register->poto_kiri,
                'poto_ktp' => $register->poto_ktp,
                'video' => $register->video,
                'radius' => 0,
                'latlong' => $register->latlong,
                'status_outlet' => 'MAINTAIN',
                'limit' => $register->limit,
            ];
            $outletExisting = Outlet::where('badanusaha_id', $register->badanusaha_id)
                ->where('divisi_id', $register->divisi_id)
                ->where('region_id', $register->region_id)
                ->where('cluster_id', $register->cluster_id)
                ->where('kode_outlet', $register->kode_outlet)->first();
            if ($outletExisting) {
                return ResponseFormatter::success($register, 'berhasil update');
            } else {
                $insert = Outlet::create($data);
            }
            if (count($notif) != 0 && $insert) {
                SendNotif::sendMessage(
                    'Register '.$register->nama_outlet.' sudah disetujui oleh '.
                        Auth::user()->nama_lengkap,
                    $notif
                );
            }

            return ResponseFormatter::success($register, 'berhasil update');
        } catch (Exception $e) {
            error_log($e);

            return ResponseFormatter::error($e, 'gagal');
        }
    }

    /**
     * Reject NOO Request with Reason Tracking
     *
     * Rejects a NOO request and records the rejection reason for transparency and future reference.
     * This endpoint can be used at any stage of the approval workflow to terminate a NOO request.
     *
     * **Business Logic:**
     * - Updates NOO status to REJECTED
     * - Records rejection reason in keterangan field
     * - Tracks who rejected and when
     * - Prevents further processing of rejected NOOs
     * - Sends rejection notification with detailed reason to TM
     *
     * **Reason Tracking:**
     * The rejection reason is stored in the keterangan field and included in notifications
     * to provide clear feedback to stakeholders about why the NOO was rejected.
     *
     * **Notification System:**
     * Automatically sends rejection notification to:
     * - The TM (Territory Manager) associated with the NOO
     * - Notification includes both rejection notice and the specific reason
     *
     * **Use Cases:**
     * - Incomplete documentation
     * - Invalid location or data
     * - Policy violations
     * - Duplicate submissions
     * - Credit risk concerns
     *
     * @authenticated
     *
     * @bodyParam id integer required NOO record ID to reject. Example: 123
     * @bodyParam status string required New status (typically "REJECTED"). Example: "REJECTED"
     * @bodyParam alasan string required Rejection reason for record-keeping and notifications. Example: "Outlet location outside coverage area"
     *
     * @response 200 {
     *   "meta": {
     *     "code": 200,
     *     "status": "success",
     *     "message": "berhasil update"
     *   },
     *   "data": {
     *     "id": 123,
     *     "nama_outlet": "Toko Maju Jaya",
     *     "status": "REJECTED",
     *     "keterangan": "Outlet location outside coverage area",
     *     "rejected_by": "Ahmad Rizki",
     *     "rejected_at": "2024-01-15T10:45:00.000000Z"
     *   }
     * }
     * @response 422 {
     *   "meta": {
     *     "code": 422,
     *     "status": "error",
     *     "message": "The given data was invalid."
     *   },
     *   "data": {
     *     "alasan": ["The alasan field is required."]
     *   }
     * }
     * @response 404 {
     *   "meta": {
     *     "code": 404,
     *     "status": "error",
     *     "message": "No query results for model [App\\Models\\Noo] 123"
     *   }
     * }
     * @response 500 {
     *   "meta": {
     *     "code": 500,
     *     "status": "error",
     *     "message": "gagal"
     *   },
     *   "data": "[Exception details]"
     * }
     *
     * @param  Request  $request  HTTP request with rejection data
     * @return \Illuminate\Http\JsonResponse
     */
    public function reject(Request $request)
    {
        try {
            $request->validate([
                'id' => ['required'],
                'status' => ['required'],
                'alasan' => ['required'],
            ]);

            $register = Register::findOrFail($request->id);
            $register->status = $request->status;
            $register->keterangan = $request->alasan;
            $register->rejected_by = Auth::user()->nama_lengkap;
            $register->rejected_at = now();

            $register->update();

            SendNotif::sendMessage('Register '.$register->nama_outlet.' ditolak oleh '.Auth::user()->nama_lengkap.PHP_EOL.'Alasan : '.$request->alasan, [$register->tm->id_notif]);

            return ResponseFormatter::success($register, 'berhasil update');
        } catch (Exception $e) {
            return ResponseFormatter::error($e, 'gagal');
        }
    }

    /**
     * Get All Business Units
     *
     * Retrieves all available business units (Badan Usaha) in the system.
     * Used for hierarchical data selection in NOO submission forms.
     *
     * **Use Cases:**
     * - NOO submission form dropdowns
     * - Hierarchical data filtering
     * - Business unit selection for ASM/RKAM roles
     * - Administrative business unit management
     *
     * **Response Structure:**
     * Returns array of BadanUsaha objects with id and name fields
     * for use in form dropdowns and hierarchical filtering.
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
     *     {
     *       "id": 1,
     *       "name": "PT. Maju Bersama"
     *     },
     *     {
     *       "id": 2,
     *       "name": "PT. Teknologi Indonesia"
     *     }
     *   ]
     * }
     * @response 500 {
     *   "meta": {
     *     "code": 500,
     *     "status": "error",
     *     "message": "[Error message]"
     *   },
     *   "data": []
     * }
     *
     * @param  Request  $request  HTTP request instance
     * @return \Illuminate\Http\JsonResponse
     */
    public function getbu(Request $request)
    {
        try {
            $badanusahas = BadanUsaha::all();

            return ResponseFormatter::success($badanusahas, 'berhasil');
        } catch (Exception $e) {
            return ResponseFormatter::error([], $e->getMessage());
        }
    }

    /**
     * Get Divisions by Business Unit
     *
     * Retrieves all divisions within a specific business unit for hierarchical filtering.
     * Used in NOO submission forms for cascading dropdown functionality.
     *
     * **Hierarchical Flow:**
     * Business Unit → Divisions → Regions → Clusters
     *
     * **Use Cases:**
     * - Cascading dropdown in NOO forms
     * - Hierarchical data filtering for ASM/RKAM roles
     * - Division selection within business unit context
     * - Form validation and data integrity
     *
     * **Query Logic:**
     * Finds business unit by name, then returns all divisions associated with that business unit.
     * Supports the hierarchical structure required for proper NOO data assignment.
     *
     * @authenticated
     *
     * @queryParam bu string required Business unit name to filter divisions. Example: "PT. Maju Bersama"
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
     *       "name": "Realme",
     *       "badanusaha_id": 1
     *     },
     *     {
     *       "id": 2,
     *       "name": "Oppo",
     *       "badanusaha_id": 1
     *     }
     *   ]
     * }
     * @response 500 {
     *   "meta": {
     *     "code": 500,
     *     "status": "error",
     *     "message": "[Error message]"
     *   },
     *   "data": []
     * }
     *
     * @param  Request  $request  HTTP request with business unit parameter
     * @return \Illuminate\Http\JsonResponse
     */
    public function getdiv(Request $request)
    {
        try {
            $badanusaha_id = BadanUsaha::where('name', $request->bu)->first()->id;
            $divisi = Division::where('badanusaha_id', $badanusaha_id)->get();

            return ResponseFormatter::success($divisi, 'berhasil');
        } catch (Exception $e) {
            return ResponseFormatter::error([], $e->getMessage());
        }
    }

    /**
     * Get Regions by Business Unit and Division
     *
     * Retrieves all regions within a specific business unit and division combination.
     * Used for the third level of hierarchical filtering in NOO submission forms.
     *
     * **Hierarchical Flow:**
     * Business Unit → Divisions → Regions → Clusters
     *
     * **Use Cases:**
     * - Third level cascading dropdown in NOO forms
     * - Regional data filtering for ASM/RKAM roles
     * - Region selection within business unit and division context
     * - Geographical data assignment for NOOs
     *
     * **Query Logic:**
     * 1. Find business unit by name
     * 2. Find division within that business unit by name
     * 3. Return all regions within that business unit/division combination
     *
     * **Data Integrity:**
     * Ensures that regions are properly scoped within the correct
     * business unit and division hierarchy to maintain data consistency.
     *
     * @authenticated
     *
     * @queryParam bu string required Business unit name. Example: "PT. Maju Bersama"
     * @queryParam div string required Division name within business unit. Example: "Realme"
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
     *       "name": "Jakarta",
     *       "badanusaha_id": 1,
     *       "divisi_id": 1
     *     },
     *     {
     *       "id": 2,
     *       "name": "Bandung",
     *       "badanusaha_id": 1,
     *       "divisi_id": 1
     *     }
     *   ]
     * }
     * @response 500 {
     *   "meta": {
     *     "code": 500,
     *     "status": "error",
     *     "message": "[Error message]"
     *   },
     *   "data": []
     * }
     *
     * @param  Request  $request  HTTP request with business unit and division parameters
     * @return \Illuminate\Http\JsonResponse
     */
    public function getreg(Request $request)
    {
        try {
            $badanusaha_id = BadanUsaha::where('name', $request->bu)->first()->id;
            $divisi_id = Division::where('badanusaha_id', $badanusaha_id)->where('name', $request->div)->first()->id;
            $region = Region::where('badanusaha_id', $badanusaha_id)->where('divisi_id', $divisi_id)->get();

            return ResponseFormatter::success($region, 'berhasil');
        } catch (Exception $e) {
            return ResponseFormatter::error([], $e->getMessage());
        }
    }

    /**
     * Get Clusters with Role-Based or Hierarchical Filtering
     *
     * Retrieves clusters based on either user role context or full hierarchical selection.
     * This is the final level of the 4-tier hierarchy for NOO data assignment.
     *
     * **Hierarchical Flow:**
     * Business Unit → Divisions → Regions → Clusters
     *
     * **Two Operation Modes:**
     *
     * **1. Role-Based Mode (when role parameter is provided):**
     * - Uses authenticated user's hierarchical data (business unit, division, region)
     * - Returns clusters within user's assigned region
     * - Used for ASC/KAM roles and other users with fixed hierarchical assignments
     *
     * **2. Full Hierarchical Mode (when role parameter is not provided):**
     * - Requires complete hierarchical parameters (bu, div, reg)
     * - Returns clusters within specified business unit/division/region
     * - Used for ASM/RKAM roles who can select complete hierarchy
     *
     * **Use Cases:**
     * - Final level cascading dropdown in NOO forms
     * - Cluster assignment for different user roles
     * - Geographical granularity in NOO data
     * - Territory management and sales area definition
     *
     * @authenticated
     *
     * @queryParam role boolean optional Use role-based filtering if true, full hierarchy if false. Example: true
     * @queryParam bu string required Business unit name (for full hierarchy mode). Example: "PT. Maju Bersama"
     * @queryParam div string required Division name (for full hierarchy mode). Example: "Realme"
     * @queryParam reg string required Region name (for full hierarchy mode). Example: "Jakarta"
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
     *       "name": "Jakarta Pusat",
     *       "badanusaha_id": 1,
     *       "divisi_id": 1,
     *       "region_id": 1
     *     },
     *     {
     *       "id": 2,
     *       "name": "Jakarta Selatan",
     *       "badanusaha_id": 1,
     *       "divisi_id": 1,
     *       "region_id": 1
     *     }
     *   ]
     * }
     * @response 500 {
     *   "meta": {
     *     "code": 500,
     *     "status": "error",
     *     "message": "[Error message]"
     *   },
     *   "data": "[Error message]"
     * }
     *
     * @param  Request  $request  HTTP request with filtering parameters
     * @return \Illuminate\Http\JsonResponse
     */
    public function getclus(Request $request)
    {
        try {
            if ($request->role) {
                $user = Auth::user();
                $cluster = Cluster::where('badanusaha_id', $user->badanusaha_id)->where('divisi_id', $user->divisi_id)->where('region_id', $user->region_id)->get();
            } else {
                $badanusaha_id = BadanUsaha::where('name', $request->bu)->first()->id;
                $divisi_id = Division::where('badanusaha_id', $badanusaha_id)->where('name', $request->div)->first()->id;
                $region_id = Region::where('badanusaha_id', $badanusaha_id)->where('divisi_id', $divisi_id)->where('name', $request->reg)->first()->id;
                $cluster = Cluster::where('badanusaha_id', $badanusaha_id)->where('divisi_id', $divisi_id)->where('region_id', $region_id)->get();
            }

            return ResponseFormatter::success($cluster, 'berhasil');
        } catch (Exception $e) {
            return ResponseFormatter::error($e->getMessage(), $e->getMessage());
        }
    }

    public function tesgetclus(Request $request)
    {
        try {
            if ($request->role) {
                $user = Auth::user();
                $cluster = Cluster::where('badanusaha_id', $user->badanusaha_id)->where('divisi_id', $user->divisi_id)->where('region_id', $user->region_id)->get();
                dd($cluster);
            } else {
                $badanusaha_id = BadanUsaha::where('name', $request->bu)->first()->id;
                $divisi_id = Division::where('badanusaha_id', $badanusaha_id)->where('name', $request->div)->first()->id;
                $region_id = Region::where('badanusaha_id', $badanusaha_id)->where('divisi_id', $divisi_id)->where('name', $request->reg)->first()->id;
                $cluster = Cluster::where('badanusaha_id', $badanusaha_id)->where('divisi_id', $divisi_id)->where('region_id', $region_id)->get();
            }

            return ResponseFormatter::success($cluster, 'berhasil');
        } catch (Exception $e) {
            return ResponseFormatter::error($e->getMessage(), $e->getMessage());
        }
    }

    /**
     * Get Unapproved NOOs for Outlet Creation
     *
     * Retrieves NOOs that haven't been approved yet (approved_by is null) with role-based filtering.
     * Used for identifying NOOs that are candidates for outlet creation and approval management.
     *
     * **Business Purpose:**
     * - Shows NOOs awaiting final approval
     * - Identifies pending outlet creation opportunities
     * - Supports approval workflow management
     * - Filters unapproved NOOs by user role permissions
     *
     * **Role-Based Filtering Logic:**
     * - **ASM (ID: 1)**: Filters by TM_id to show unapproved NOOs from their territory managers
     * - **ASC (ID: 2)**: Filters by business unit, division, and region hierarchy
     * - **DSF/DM (ID: 3)**: Most restrictive - filters by complete hierarchy including cluster
     * - **Default**: Fallback to business units 2 and 4 with specific status filters
     *
     * **Key Filter:**
     * Only returns NOOs where approved_by is null, indicating they haven't reached
     * the final approval stage and haven't created outlet records yet.
     *
     * **Use Cases:**
     * - Approval queue management
     * - Pending outlet creation tracking
     * - Sales team performance monitoring
     * - Hierarchical approval oversight
     *
     * @authenticated
     *
     * @response 200 {
     *   "meta": {
     *     "code": 200,
     *     "status": "success",
     *     "message": "fetch noo success"
     *   },
     *   "data": [
     *     {
     *       "id": 123,
     *       "nama_outlet": "Toko Maju Jaya",
     *       "status": "CONFIRMED",
     *       "approved_by": null,
     *       "badanusaha": {"id": 1, "name": "PT. Maju Bersama"},
     *       "cluster": {"id": 1, "name": "Jakarta Pusat"},
     *       "region": {"id": 1, "name": "Jakarta"},
     *       "divisi": {"id": 1, "name": "Realme"}
     *     }
     *   ]
     * }
     * @response 500 {
     *   "meta": {
     *     "code": 500,
     *     "status": "error",
     *     "message": "something wrong"
     *   },
     *   "data": {
     *     "message": "[Exception details]"
     *   }
     * }
     *
     * @param  Request  $request  HTTP request instance
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRegisterOutlet(Request $request)
    {
        try {
            $user = Auth::user();
            $badanusahaId = $user->badanusaha_id;
            $divisiId = $user->divisi_id;
            $regionId = $user->region_id;
            $clusterId = $user->cluster_id;
            $roleId = $user->role_id;

            $query = Register::with(['badanusaha', 'cluster', 'region', 'divisi'])->where('approved_by', null);

            switch ($roleId) {
                // ASM
                case 1:
                    $registers = $query
                        ->where('tm_id', $user->id)
                        ->orderBy('nama_outlet')
                        ->get();
                    break;
                    // ASC
                case 2:
                    $registers = $query
                        ->where('badanusaha_id', $badanusahaId)
                        ->where('divisi_id', $divisiId)
                        ->where('region_id', $regionId)
                        ->orderBy('nama_outlet')
                        ->get();
                    break;
                    // DSF/DM
                case 3:
                    $registers = $query
                        ->where('badanusaha_id', $badanusahaId)
                        ->where('divisi_id', $divisiId)
                        ->where('region_id', $regionId)
                        ->where('cluster_id', $clusterId)
                        ->orderBy('nama_outlet')
                        ->get();
                    break;

                default:
                    $registers = Register::with(['badanusaha', 'cluster', 'region', 'divisi'])->where('badanusaha_id', 2)->orWhere('badanusaha_id', 4)->whereIn('status', ['PENDING', 'CONFIRMED', 'REJECTED'])->latest()->get();
                    break;
            }

            return ResponseFormatter::success(
                $registers,
                'fetch register success',
            );
        } catch (Exception $err) {
            return ResponseFormatter::error([
                'message' => $err,
            ], 'something wrong', 500);
        }
    }

    /**
     * Get Single NOO by ID
     *
     * Retrieves a specific NOO record by its ID with complete hierarchical relationships.
     * Used for detailed view, editing, and individual NOO management operations.
     *
     * **Business Purpose:**
     * - Detailed NOO information display
     * - Editing and update operations
     * - Approval workflow management for specific NOOs
     * - Status tracking and history viewing
     *
     * **Response Structure:**
     * Returns single NOO with complete hierarchical data including:
     * - Business unit, cluster, region, and division relationships
     * - All NOO details (outlet info, owner details, location, etc.)
     * - File references (photos, videos)
     * - Status and approval history
     *
     * **Use Cases:**
     * - NOO detail pages in web/mobile applications
     * - Approval/rejection workflows
     * - Status monitoring for specific NOOs
     * - Data validation and correction
     * - Historical reference and audit trails
     *
     * **Parameter Note:**
     * The parameter name is $kodeOutlet but it actually searches by NOO ID.
     * This is a legacy naming convention from earlier system versions.
     *
     * @authenticated
     *
     * @pathParam kodeOutlet integer required NOO record ID. Example: 123
     *
     * @response 200 {
     *   "meta": {
     *     "code": 200,
     *     "status": "success",
     *     "message": "berhasil"
     *   },
     *   "data": [
     *     {
     *       "id": 123,
     *       "nama_outlet": "Toko Maju Jaya",
     *       "alamat_outlet": "Jl. Raya No. 123, Jakarta",
     *       "nama_pemilik_outlet": "Budi Santoso",
     *       "status": "CONFIRMED",
     *       "created_by": "Ahmad Rizki",
     *       "tm_id": 45,
     *       "badanusaha": {"id": 1, "name": "PT. Maju Bersama"},
     *       "cluster": {"id": 1, "name": "Jakarta Pusat"},
     *       "region": {"id": 1, "name": "Jakarta"},
     *       "divisi": {"id": 1, "name": "Realme"}
     *     }
     *   ]
     * }
     * @response 200 {
     *   "meta": {
     *     "code": 200,
     *     "status": "success",
     *     "message": "berhasil"
     *   },
     *   "data": []
     * }
     * @response 500 {
     *   "meta": {
     *     "code": 500,
     *     "status": "error",
     *     "message": "ada kesalahan"
     *   },
     *   "data": null
     * }
     *
     * @param  Request  $request  HTTP request instance
     * @param  mixed  $kodeOutlet  NOO record ID to retrieve
     * @return \Illuminate\Http\JsonResponse
     */
    public function singleOutlet(Request $request, $kodeOutlet)
    {
        // dd($request->all());
        try {
            $register = Register::with(['badanusaha', 'cluster', 'region', 'divisi'])
                ->where('id', $kodeOutlet)
                ->get();

            return ResponseFormatter::success($register, 'berhasil');
        } catch (Exception $err) {
            return ResponseFormatter::error(null, 'ada kesalahan');
        }
    }
}
