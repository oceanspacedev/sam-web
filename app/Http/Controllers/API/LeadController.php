<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Helpers\SendNotif;
use App\Http\Controllers\Controller;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Region;
use App\Models\Register;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

/**
 * @group Lead Management
 *
 * API endpoints for managing Lead creation and updates with automatic outlet generation.
 * Leads are simplified NOO (New Outlet Opening) requests that automatically create
 * outlet records with LEAD prefix and default values for streamlined processing.
 *
 * ## Lead vs NOO Differences:
 * - **Automatic Outlet Creation**: Leads immediately create outlet records with LEAD prefix
 * - **Simplified Workflow**: No 3-stage approval process required
 * - **Default Values**: Pre-configured with business defaults (limit=0, radius=100, status=MAINTAIN)
 * - **KTP Management**: Separate update flow for KTP photo and information
 * - **Status Tracking**: Uses keterangan field with 'LEAD' status that can be converted to regular NOO
 *
 * ## File Upload System:
 * - **Photos**: Supports up to 4 photos (photo0-photo3) with intelligent categorization:
 *   - Files containing "fotodepan" → poto_depan (front photo)
 *   - Files containing "fotokanan" → poto_kanan (right photo)
 *   - Files containing "fotokiri" → poto_kiri (left photo)
 *   - All other photos → poto_shop_sign (shop sign photo)
 * - **Videos**: Single video upload support with validation
 * - **Validation**: Photos max 5MB (JPG/JPEG/PNG), videos max 50MB (MP4/QuickTime/WebM)
 *
 * @authenticated
 *
 * @header Authorization Bearer {token}
 */
class LeadController extends Controller
{
    /**
     * Create New Lead with Automatic Outlet Generation
     *
     * Creates a new Lead record and automatically generates a corresponding Outlet record
     * with LEAD prefix and default business values. This streamlined process bypasses
     * the traditional NOO approval workflow.
     *
     * **Business Logic:**
     * - Creates NOO record with keterangan='LEAD' status
     * - Automatically generates Outlet record with 'LEAD{noo_id}' format
     * - Sets default business values: limit=0, radius=100, status_outlet='MAINTAIN', is_member=0
     * - Supports role-based hierarchical data assignment similar to NOO system
     * - No approval workflow required - immediate outlet creation
     *
     * **Role-Based Data Assignment:**
     * - **ASM (ID: 1)**: Allows complete hierarchy selection (business unit, division, region, cluster)
     * - **ASC (ID: 2)**: Uses user's business unit, division, and region, with cluster selection
     * - **Other roles**: Uses user's complete hierarchical data
     *
     * **File Upload System:**
     * - **Photos**: Supports up to 4 photos (photo0-photo3) with intelligent categorization:
     *   - Files containing "fotodepan" → poto_depan (front photo)
     *   - Files containing "fotokanan" → poto_kanan (right photo)
     *   - Files containing "fotokiri" → poto_kiri (left photo)
     *   - All other photos → poto_shop_sign (shop sign photo)
     * - **Videos**: Single video upload support
     * - **Validation**: Photos max 5MB (JPG/JPEG/PNG), videos max 50MB (MP4/QuickTime/WebM)
     *
     * **Default Values:**
     * - ktp_outlet: '-' (placeholder for KTP information)
     * - poto_ktp: '-' (placeholder for KTP photo)
     * - keterangan: 'LEAD' (status identifier)
     * - TM assignment: Uses user's TM or falls back to user ID
     *
     * **Outlet Generation:**
     * - Automatic outlet creation with format 'LEAD{noo_id}'
     * - Inherits all hierarchical and outlet data from lead
     * - Applies business defaults for immediate operational use
     *
     * @authenticated
     *
     * @bodyParam nama_outlet string required Outlet name (max: 255). Example: "Toko Maju Jaya"
     * @bodyParam alamat_outlet string required Outlet address (max: 255). Example: "Jl. Raya No. 123, Jakarta"
     * @bodyParam nama_pemilik string required Owner name (max: 255). Example: "Budi Santoso"
     * @bodyParam nomer_pemilik string required Owner phone number (max: 255). Example: "081234567890"
     * @bodyParam nomer_perwakilan string required Representative phone number (max: 255). Example: "081234567891"
     * @bodyParam distric string required District name (max: 255). Example: "Jakarta Pusat"
     * @bodyParam oppo boolean required Oppo brand presence. Example: true
     * @bodyParam vivo boolean required Vivo brand presence. Example: false
     * @bodyParam samsung boolean required Samsung brand presence. Example: true
     * @bodyParam xiaomi boolean required Xiaomi brand presence. Example: false
     * @bodyParam realme boolean required Realme brand presence. Example: true
     * @bodyParam fl boolean required FL brand presence. Example: false
     * @bodyParam latlong string required Latitude and longitude coordinates. Example: "-6.2088,106.8456"
     * @bodyParam bu string required Business unit name (for ASM role). Example: "PT. Maju Bersama"
     * @bodyParam div string required Division name (for ASM role). Example: "Realme"
     * @bodyParam reg string required Region name (for ASM role). Example: "Jakarta"
     * @bodyParam clus string required Cluster name (for ASM/ASC roles). Example: "Jakarta Pusat"
     * @bodyParam photo0 file optional Front photo (max 5MB, JPG/JPEG/PNG). Example: "fotodepan_outlet.jpg"
     * @bodyParam photo1 file optional Right photo (max 5MB, JPG/JPEG/PNG). Example: "fotokanan_outlet.jpg"
     * @bodyParam photo2 file optional Left photo (max 5MB, JPG/JPEG/PNG). Example: "fotokiri_outlet.jpg"
     * @bodyParam photo3 file optional Shop sign photo (max 5MB, JPG/JPEG/PNG). Example: "shop_sign_outlet.jpg"
     * @bodyParam video file optional Video file (max 50MB, MP4/QuickTime/WebM). Example: "outlet_tour.mp4"
     *
     * @response 200 {
     *   "meta": {
     *     "code": 200,
     *     "status": "success",
     *     "message": "berhasil menambahkan LEAD Toko Maju Jaya"
     *   },
     *   "data": null
     * }
     *   "message": "No query results for model [App\\Models\\Register] 123"
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
     *     "message": "[Error message]"
     *   },
     *   "data": {
     *     "message": "[Exception details]",
     *     "trace": "[Stack trace]"
     *   }
     * }
     *
     * @param  Request  $request  HTTP request with lead data and files
     * @return \Illuminate\Http\JsonResponse
     */
    public function create(Request $request)
    {
        try {
            $user = Auth::user();
            $data = [
                'nama_outlet' => $request->nama_outlet,
                'alamat_outlet' => $request->alamat_outlet,
                'nama_pemilik_outlet' => $request->nama_pemilik,
                'nomer_tlp_outlet' => $request->nomer_pemilik,
                'nomer_wakil_outlet' => $request->nomer_perwakilan,
                'ktp_outlet' => '-',
                'distric' => $request->distric,
                'oppo' => $request->oppo,
                'vivo' => $request->vivo,
                'samsung' => $request->samsung,
                'xiaomi' => $request->xiaomi,
                'realme' => $request->realme,
                'fl' => $request->fl,
                'latlong' => $request->latlong,
                'created_by' => $user->nama_lengkap,
                'tm_id' => optional($user->tm)->id ?? $user->id,
                'keterangan' => 'LEAD',
                'poto_ktp' => '-',
            ];

            switch ($user->role_id) {
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

                case 2:
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

            // Validasi dinamis untuk file foto/video jika ada
            $rules = [];
            for ($i = 0; $i <= 3; $i++) {
                if ($request->hasFile('photo'.$i)) {
                    $rules['photo'.$i] = ['file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'];
                }
            }
            if ($request->hasFile('video')) {
                $rules['video'] = ['file', 'mimetypes:video/mp4,video/quicktime,video/webm', 'max:51200'];
            }
            if (! empty($rules)) {
                $request->validate($rules);
            }

            // Proses foto 0..3 (kompatibel pola lama)
            for ($i = 0; $i <= 3; $i++) {
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
                } else {
                    $target = 'poto_shop_sign';
                }
                $ext = $file->guessExtension() ?: $file->extension();
                $name = (string) Str::uuid().'.'.$ext;
                $path = $file->storeAs('register/photos', $name, 'public');
                $data[$target] = $path;
            }

            if ($request->hasFile('video')) {
                $video = $request->file('video');
                if (! $video->isValid()) {
                    return ResponseFormatter::error('File video tidak valid', 'INVALID_FILE', 422);
                }
                $vext = $video->guessExtension() ?: $video->extension();
                $vname = (string) Str::uuid().'.'.$vext;
                $vpath = $video->storeAs('register/videos', $vname, 'public');
                $data['video'] = $vpath;
            }

            $register = Register::create($data);

            // Gabungkan $data dengan $outletData dan buat Outlet
            $outletData = [
                'kode_outlet' => 'LEAD'.$register->id,
                'limit' => '0',
                'radius' => '100',
                'is_member' => '0',
                'status_outlet' => 'MAINTAIN',
            ];

            // Gabungkan $data dengan $outletData dan buat Outlet
            $outletCompleteData = array_merge($data, $outletData);
            Outlet::create($outletCompleteData);

            return ResponseFormatter::success(null, 'berhasil menambahkan LEAD '.$request->nama_outlet);
        } catch (Exception $e) {
            return ResponseFormatter::error(['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()], $e->getMessage());
        }
    }

    /**
     * Update Lead with KTP Information and Status Change
     *
     * Updates an existing Lead record by adding KTP information and optional KTP photo.
     * This endpoint transitions the Lead from initial 'LEAD' status to a more complete state,
     * preparing it for potential conversion to a regular NOO or enhanced outlet operations.
     *
     * **Business Logic:**
     * - Updates KTP information (noktp) in the ktp_outlet field
     * - Optionally uploads and stores KTP photo in dedicated 'register/ktp' directory
     * - Clears keterangan field (removes 'LEAD' status)
     * - Sends notification to AR (Area Representative) about the update
     * - Prepares lead for potential conversion to regular outlet status
     *
     * **KTP Photo Management:**
     * - KTP photos are stored in dedicated 'register/ktp' directory (separate from other photos)
     * - Uses UUID-based filename generation for security and uniqueness
     * - Supports JPG/JPEG/PNG formats with 5MB size limit
     * - Updates poto_ktp field with the stored file path
     *
     * **Status Transition:**
     * - Clears keterangan field (removes 'LEAD' identifier)
     * - This prepares the record for potential conversion to regular NOO status
     * - Enables enhanced business operations with complete documentation
     *
     * **Notification System:**
     * Automatically sends update notification to AR (role_id 4) to inform about:
     * - Lead update completion
     * - KTP information availability
     * - Readiness for next processing stage
     *
     * **Use Cases:**
     * - Completing lead documentation after initial creation
     * - Adding mandatory KTP information for regulatory compliance
     * - Preparing leads for conversion to regular outlets
     * - Updating lead status for enhanced business operations
     *
     * @authenticated
     *
     * @bodyParam id integer required Lead record ID to update. Example: 123
     * @bodyParam noktp string required KTP/NPWP number for regulatory compliance. Example: "1234567890123456"
     * @bodyParam photo file optional KTP photo (max 5MB, JPG/JPEG/PNG). Example: "ktp_pemilik.jpg"
     *
     * @response 200 {
     *   "meta": {
     *     "code": 200,
     *     "status": "success",
     *     "message": "berhasil menambahkan Lead Toko Maju Jaya"
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
     *     "id": ["The id field is required."],
     *     "noktp": ["The noktp field is required."]
     *   }
     * }
     * @response 422 {
     *   "meta": {
     *     "code": 422,
     *     "status": "error",
     *     "message": "INVALID_FILE"
     *   },
     *   "data": "File KTP tidak valid"
     * }
     * @response 404 {
     *   "meta": {
     *     "code": 404,
     *     "status": "error",
     *     "message": "No query results for model [App\\Models\\Register] 123"
     *   }
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
     * @param  Request  $request  HTTP request with update data
     * @return \Illuminate\Http\JsonResponse
     */
    public function update(Request $request)
    {
        try {
            $baseRules = [
                'id' => ['required'],
                'noktp' => ['required'],
            ];
            $fileRules = [];
            if ($request->hasFile('photo')) {
                $fileRules['photo'] = ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'];
            }
            $request->validate(array_merge($baseRules, $fileRules));

            $lead = Register::find($request->id);
            if ($request->hasFile('photo')) {
                $file = $request->file('photo');
                if (! $file->isValid()) {
                    return ResponseFormatter::error('File KTP tidak valid', 'INVALID_FILE', 422);
                }
                $ext = $file->guessExtension() ?: $file->extension();
                $name = (string) Str::uuid().'.'.$ext;
                $path = $file->storeAs('register/ktp', $name, 'public');
                $lead['poto_ktp'] = $path;
            }
            $lead['ktp_outlet'] = $request->noktp;
            $lead['keterangan'] = null;
            $lead->update();
            SendNotif::sendMessage('Register baru '.$lead->nama_outlet.' ditambahkan oleh '.Auth::user()->nama_lengkap, [User::where('role_id', 4)->first()->id_notif]);

            return ResponseFormatter::success(null, 'berhasil menambahkan Lead '.$request->nama_outlet);
        } catch (Exception $e) {
            return ResponseFormatter::error($e->getMessage(), $e->getMessage());
        }
    }
}
