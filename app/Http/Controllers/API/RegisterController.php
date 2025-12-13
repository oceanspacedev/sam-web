<?php

namespace App\Http\Controllers\API;

use App\Exceptions\Api\BadRequestException;
use App\Exceptions\Api\FileUploadException;
use App\Exceptions\Api\ResourceNotFoundException;
use App\Exceptions\Api\UnauthorizedException;
use App\Http\Controllers\API\Traits\HasMediaUpload;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\ApproveNooRequest;
use App\Http\Requests\API\ConfirmNooRequest;
use App\Http\Requests\API\RejectNooRequest;
use App\Http\Requests\API\SubmitLeadRequest;
use App\Http\Requests\API\SubmitNooRequest;
use App\Http\Requests\API\UpgradeLeadRequest;
use App\Http\Resources\Register\RegisterCompactResource;
use App\Http\Resources\Register\RegisterResource;
use App\Jobs\SendNotificationJob;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Region;
use App\Models\Register;
use App\Models\User;
use App\Rules\VideoMimeOrSignature;
use App\Services\FileUploadService;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class RegisterController extends Controller
{
    use HasMediaUpload;

    public function __construct(
        protected FileUploadService $fileUpload
    ) {}

    public function submitLead(SubmitLeadRequest $request)
    {
        $temporaryFiles = [];
        $mediaQueue = [];
        $mediaDispatched = false;

        try {
            $user = Auth::user();
            $hierarchy = $this->resolveHierarchy($user, $request);

            if ($this->isHierarchyIncomplete($hierarchy)) {
                throw new BadRequestException('Organizational hierarchy is required', [
                    'badanusaha_id' => $hierarchy['badanusaha_id'] ? null : ['Required'],
                    'divisi_id' => $hierarchy['divisi_id'] ? null : ['Required'],
                    'region_id' => $hierarchy['region_id'] ? null : ['Required'],
                    'cluster_id' => $hierarchy['cluster_id'] ? null : ['Required'],
                ]);
            }

            // Log Lead store initiated
            Log::channel('lead')->info('Penyimpanan lead dimulai', [
                'user_id' => $user->id,
                'role_id' => $user->role_id,
                'payload' => [
                    'nama_outlet' => $request->nama_outlet,
                    'nama_pemilik' => $request->nama_pemilik,
                    'ktpnpwp' => $request->ktpnpwp ?? null,
                    'alamat_outlet' => $request->alamat_outlet,
                    'nomer_pemilik' => $request->nomer_pemilik,
                    'nomer_perwakilan' => $request->nomer_perwakilan,
                    'distric' => $request->distric,
                    'oppo' => $request->oppo,
                    'vivo' => $request->vivo,
                    'samsung' => $request->samsung,
                    'xiaomi' => $request->xiaomi,
                    'realme' => $request->realme,
                    'fl' => $request->fl,
                    'latlong' => $request->latlong,
                ],
            ]);

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
                'created_by_id' => $user->id,
                'tm_id' => $hierarchy['tm_id'],
                'keterangan' => 'LEAD',
                'poto_ktp' => '-',
                'badanusaha_id' => $hierarchy['badanusaha_id'],
                'divisi_id' => $hierarchy['divisi_id'],
                'region_id' => $hierarchy['region_id'],
                'cluster_id' => $hierarchy['cluster_id'],
            ];

            $rules = [];
            for ($i = 0; $i <= 3; $i++) {
                if ($request->hasFile('photo'.$i)) {
                    $rules['photo'.$i] = ['file', 'image', 'mimes:jpg,jpeg,png', 'max:3072'];
                }
            }
            if ($request->hasFile('video')) {
                $rules['video'] = ['file', new VideoMimeOrSignature, 'max:51200'];
            }
            if (! empty($rules)) {
                $request->validate($rules);
            }

            for ($i = 0; $i <= 3; $i++) {
                $file = $request->file('photo'.$i);
                if (! $file) {
                    continue;
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

                $temporaryPath = $this->fileUpload->storeTemporary($file, 'tmp');

                $temporaryFiles[] = $temporaryPath;
                $data[$target] = $temporaryPath;

                // Map target to file type for optimized processing
                $fileType = $this->mapTargetToFileType($target);

                $mediaQueue[] = [
                    'field' => $target,
                    'tmp_path' => $temporaryPath,
                    'type' => $fileType, // Use type instead of directory - TRUE FLAT STORAGE!
                ];
            }

            if ($request->hasFile('video')) {
                $video = $request->file('video');
                $temporaryPath = $this->fileUpload->storeTemporary($video, 'tmp');

                Log::channel('lead')->info('Video lead diantrekan untuk penyimpanan', [
                    'user_id' => $user->id,
                    'original_name' => $video->getClientOriginalName(),
                ]);

                $temporaryFiles[] = $temporaryPath;
                $data['video'] = $temporaryPath;
                $mediaQueue[] = [
                    'field' => 'video',
                    'tmp_path' => $temporaryPath,
                    'type' => 'register-video',
                ];
            }

            $register = Register::create($data);

            // Process media files using unified trait
            $mediaDispatched = $this->dispatchMediaJob('register', $register->id, $mediaQueue);

            // Log Lead store completed
            Log::channel('lead')->info('Penyimpanan lead selesai', [
                'lead_id' => $register->id,
                'outlet_code' => 'LEAD'.$register->id,
            ]);

            return response()->json([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'berhasil menambahkan LEAD '.$request->nama_outlet,
                ],
                'data' => null,
                'errors' => null,
            ]);
        } catch (RuntimeException $e) {
            $this->cleanupTemporaryFiles($temporaryFiles);
            throw new FileUploadException($e->getMessage());
        } catch (Exception $e) {
            if (! $mediaDispatched) {
                $this->cleanupTemporaryFiles($temporaryFiles);
            }

            Log::channel('lead')->error('Penyimpanan lead gagal', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function upgradeLead(UpgradeLeadRequest $request)
    {
        try {
            $lead = Register::find($request->id);

            // Validate that register is a LEAD before allowing upgrade
            if ($lead->keterangan !== 'LEAD') {
                throw new BadRequestException('Register bukan LEAD');
            }

            $file = $request->file('photo');

            $path = $this->fileUpload->uploadImageOptimized($file, 'register-ktp');
            $lead['poto_ktp'] = $path;
            $lead['ktp_outlet'] = $request->noktp;
            $lead['keterangan'] = null;
            $lead->update();

            $recipientIds = $this->buildNotificationRecipients(Auth::user(), [
                'badanusaha_id' => $lead->badanusaha_id,
                'divisi_id' => $lead->divisi_id,
                'region_id' => $lead->region_id,
                'cluster_id' => $lead->cluster_id,
                'tm_id' => $lead->tm_id ?? Auth::user()->tm?->id ?? Auth::user()->id,
            ]);

            $this->dispatchNotification(
                'Register baru '.$lead->nama_outlet.' ditambahkan oleh '.Auth::user()->nama_lengkap,
                $recipientIds
            );

            return response()->json([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'berhasil menambahkan Lead '.$request->nama_outlet,
                ],
                'data' => null,
                'errors' => null,
            ]);
        } catch (RuntimeException $e) {
            throw new FileUploadException($e->getMessage());
        } catch (Exception $e) {
            Log::channel('lead')->error('Upgrade lead gagal', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function fetch(Request $request)
    {
        try {
            $user = Auth::user();
            $compact = $request->boolean('compact', true);

            // Eager load relationships untuk menghindari N+1
            $query = Register::with($compact ? [
                'badanusaha:id,name',
                'cluster:id,name',
                'region:id,name',
                'divisi:id,name',
                'createdBy:id,nama_lengkap',
                'confirmedBy:id,nama_lengkap',
                'approvedBy:id,nama_lengkap',
                'rejectedBy:id,nama_lengkap',
            ] : [
                'badanusaha',
                'cluster',
                'region',
                'divisi',
                'createdBy',
                'confirmedBy',
                'approvedBy',
                'rejectedBy',
            ]);

            if ($compact) {
                $query->select([
                    'id',
                    'kode_outlet',
                    'nama_outlet',
                    'alamat_outlet',
                    'status',
                    'keterangan',
                    'distric',
                    'latlong',
                    'badanusaha_id',
                    'divisi_id',
                    'region_id',
                    'cluster_id',
                    'created_at',
                ]);
            }

            $registers = $query->visibleTo($user)->latest()->get();

            // Determine resource class based on compact mode
            $resourceClass = $compact ? RegisterCompactResource::class : RegisterResource::class;

            return $resourceClass::collection($registers)->additional([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'fetch register success',
                ],
                'errors' => null,
            ]);
        } catch (Exception $e) {
            Log::channel('noo')->error('Pengambilan register gagal', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    /**
     * Get register counts by status for current user
     */
    public function count(Request $request)
    {
        $user = Auth::user();

        $query = Register::visibleTo($user);

        $counts = [
            'lead' => (clone $query)->where('keterangan', 'LEAD')->count(),
            'pending' => (clone $query)->where('status', 'PENDING')->whereNull('keterangan')->count(),
            'confirmed' => (clone $query)->where('status', 'CONFIRMED')->count(),
            'approved' => (clone $query)->where('status', 'APPROVED')->count(),
            'rejected' => (clone $query)->where('status', 'REJECTED')->count(),
            'total' => $query->count(),
        ];

        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'berhasil',
            ],
            'data' => $counts,
            'errors' => null,
        ]);
    }

    public function all(Request $request)
    {
        try {
            $user = Auth::user();
            $compact = $request->boolean('compact', true);

            // CRITICAL: Block access if user or role is null
            if (! $user || ! $user->role) {
                throw new UnauthorizedException;
            }

            $scopeLevel = $user->role->organizational_scope_level;

            // CRITICAL: Block access if scope level is null
            if (! $scopeLevel) {
                throw new UnauthorizedException;
            }

            // If role has 'all' access, return all registers
            $relations = $compact ? [
                'badanusaha:id,name',
                'cluster:id,name',
                'region:id,name',
                'divisi:id,name',
                'createdBy:id,nama_lengkap',
                'confirmedBy:id,nama_lengkap',
                'approvedBy:id,nama_lengkap',
                'rejectedBy:id,nama_lengkap',
            ] : [
                'badanusaha',
                'cluster',
                'region',
                'divisi',
                'createdBy',
                'confirmedBy',
                'approvedBy',
                'rejectedBy',
            ];

            $selectColumns = $compact ? [
                'id',
                'kode_outlet',
                'nama_outlet',
                'alamat_outlet',
                'status',
                'keterangan',
                'distric',
                'latlong',
                'badanusaha_id',
                'divisi_id',
                'region_id',
                'cluster_id',
                'created_at',
            ] : ['*'];

            $query = Register::with($relations);
            if ($compact) {
                $query->select($selectColumns);
            }

            // Apply organizational scope filtering
            $registers = $query->visibleTo($user)->latest()->get();

            // Determine resource class based on compact mode
            $resourceClass = $compact ? RegisterCompactResource::class : RegisterResource::class;

            return $resourceClass::collection($registers)->additional([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'fetch register success',
                ],
                'errors' => null,
            ]);
        } catch (Exception $e) {
            Log::channel('noo')->error('Pengambilan semua register gagal', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function submitNoo(SubmitNooRequest $request)
    {
        $temporaryFiles = [];
        $mediaQueue = [];
        $mediaDispatched = false;

        try {
            $user = Auth::user();
            $hierarchy = $this->resolveHierarchy($user, $request);

            if ($this->isHierarchyIncomplete($hierarchy)) {
                throw new BadRequestException('Organizational hierarchy is required', [
                    'badanusaha_id' => $hierarchy['badanusaha_id'] ? null : ['Required'],
                    'divisi_id' => $hierarchy['divisi_id'] ? null : ['Required'],
                    'region_id' => $hierarchy['region_id'] ? null : ['Required'],
                    'cluster_id' => $hierarchy['cluster_id'] ? null : ['Required'],
                ]);
            }

            // Log NOO store initiated
            Log::channel('noo')->info('Penyimpanan NOO dimulai', [
                'user_id' => $user->id,
                'role_id' => $user->role_id,
                'payload' => [
                    'nama_outlet' => $request->nama_outlet,
                    'nama_pemilik' => $request->nama_pemilik,
                    'ktpnpwp' => $request->ktpnpwp,
                    'alamat_outlet' => $request->alamat_outlet,
                    'nomer_pemilik' => $request->nomer_pemilik,
                    'nomer_perwakilan' => $request->nomer_perwakilan,
                    'distric' => $request->distric,
                    'oppo' => $request->oppo,
                    'vivo' => $request->vivo,
                    'samsung' => $request->samsung,
                    'xiaomi' => $request->xiaomi,
                    'realme' => $request->realme,
                    'fl' => $request->fl,
                    'latlong' => $request->latlong,
                ],
            ]);

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
                'created_by_id' => $user->id,
                'tm_id' => $hierarchy['tm_id'],
                'badanusaha_id' => $hierarchy['badanusaha_id'],
                'divisi_id' => $hierarchy['divisi_id'],
                'region_id' => $hierarchy['region_id'],
                'cluster_id' => $hierarchy['cluster_id'],
            ];

            // Queue photo uploads for background processing
            for ($i = 0; $i <= 4; $i++) {
                $file = $request->file('photo'.$i);
                if (! $file) {
                    continue;
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

                $temporaryPath = $this->fileUpload->storeTemporary($file, 'tmp');
                $temporaryFiles[] = $temporaryPath;

                $fileType = $this->mapTargetToFileType($target);

                $mediaQueue[] = [
                    'field' => $target,
                    'tmp_path' => $temporaryPath,
                    'type' => $fileType,
                ];
                $data[$target] = $temporaryPath;
            }

            // Queue video upload for background processing
            if ($request->hasFile('video')) {
                $video = $request->file('video');
                $temporaryPath = $this->fileUpload->storeTemporary($video, 'tmp');

                Log::channel('noo')->info('Video NOO diantrekan untuk penyimpanan', [
                    'user_id' => $user->id,
                    'original_name' => $video->getClientOriginalName(),
                ]);

                $temporaryFiles[] = $temporaryPath;
                $mediaQueue[] = [
                    'field' => 'video',
                    'tmp_path' => $temporaryPath,
                    'type' => 'register-video',
                ];
                $data['video'] = $temporaryPath;
            } else {
                // Log missing video file (as seen in production logs)
                Log::channel('noo')->warning('File video NOO hilang', [
                    'user_id' => $user->id,
                    'payload_outlet' => $request->nama_outlet,
                ]);
            }

            $notifId = $this->buildNotificationRecipients($user, $hierarchy);
            $register = Register::create($data);
            if ($register && $notifId !== []) {
                $this->dispatchNotification(
                    'Register baru '.$request->nama_outlet.' ditambahkan oleh '.Auth::user()->nama_lengkap,
                    $notifId
                );
            }

            // Process media files using unified trait
            if ($register && $mediaQueue !== []) {
                $mediaDispatched = $this->dispatchMediaJob('register', $register->id, $mediaQueue);
            }

            // Log NOO store completed
            Log::channel('noo')->info('Penyimpanan NOO selesai', [
                'noo_id' => $register->id,
                'outlet_name' => $register->nama_outlet,
            ]);

            return response()->json([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'berhasil menambahkan register '.$request->nama_outlet,
                ],
                'data' => null,
                'errors' => null,
            ]);
        } catch (RuntimeException $e) {
            $this->cleanupTemporaryFiles($temporaryFiles);
            throw new FileUploadException($e->getMessage());
        } catch (Exception $e) {
            if (! $mediaDispatched) {
                $this->cleanupTemporaryFiles($temporaryFiles);
            }

            Log::channel('noo')->error('Penyimpanan NOO gagal', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function confirmNoo(ConfirmNooRequest $request)
    {
        try {
            $register = Register::findOrFail($request->id);

            // Validate that register.status is PENDING before allowing confirmation
            // Only pending NOO (status=PENDING) or upgraded LEAD (status=PENDING, keterangan=null) can be confirmed
            // Note: Database has status as enum with default 'PENDING', not null
            if ($register->status !== 'PENDING') {
                throw new BadRequestException('Register sudah diproses sebelumnya');
            }

            $register->status = $request->status;
            $register->limit = $request->limit;
            $register->kode_outlet = $request->kode_outlet;
            $register->confirmed_by_id = Auth::id();
            $register->confirmed_at = now();
            $register->update();
            $recipients = array_filter([
                optional($register->createdBy)->id_notif,
                optional($register->tm)->id_notif,
            ]);

            $this->dispatchNotification(
                'Register '.$register->nama_outlet.' sudah dikonfirmasi oleh '.
                Auth::user()->nama_lengkap.PHP_EOL.
                'Dengan limit : Rp '.number_format($request->limit, 0, ',', '.'),
                $recipients
            );

            return response()->json([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'berhasil update',
                ],
                'data' => $register,
                'errors' => null,
            ]);
        } catch (Exception $e) {
            Log::channel('noo')->error('Konfirmasi NOO gagal', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function cleanupTemporaryFiles(array $paths): void
    {
        if ($paths === []) {
            return;
        }

        $disk = Storage::disk($this->fileUpload->temporaryDisk());

        foreach ($paths as $path) {
            if (! $path) {
                continue;
            }

            $disk->delete($path);
        }
    }

    public function approveNoo(ApproveNooRequest $request)
    {
        try {
            $register = Register::find($request->id);

            // Validate that register.status is 'CONFIRMED' before allowing approval
            // Only confirmed NOO can be approved (state machine: CONFIRMED → APPROVED)
            if ($register->status !== 'CONFIRMED') {
                throw new BadRequestException('Register belum dikonfirmasi');
            }

            $register->status = $request->status;
            $register->approved_by_id = Auth::id();
            $register->approved_at = now();
            $register->update();

            $notif = [];
            $creatorNotifId = $register->createdBy?->id_notif;
            if ($creatorNotifId) {
                array_push($notif, $creatorNotifId);
            }

            $data = [
                'register_id' => $register->id,
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

            $outletExisting = Outlet::query()
                ->where('register_id', $register->id)
                ->orWhere(function ($query) use ($register) {
                    $query->where('badanusaha_id', $register->badanusaha_id)
                        ->where('divisi_id', $register->divisi_id)
                        ->where('region_id', $register->region_id)
                        ->where('cluster_id', $register->cluster_id)
                        ->where('kode_outlet', $register->kode_outlet);
                })
                ->first();

            if ($outletExisting) {
                $outletExisting->forceFill($data)->save();
                $insert = $outletExisting;
            } else {
                $insert = Outlet::create($data);
            }
            if ($insert && $notif !== []) {
                $this->dispatchNotification(
                    'Register '.$register->nama_outlet.' sudah disetujui oleh '.
                    Auth::user()->nama_lengkap,
                    $notif
                );
            }

            return response()->json([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'berhasil update',
                ],
                'data' => $register,
                'errors' => null,
            ]);
        } catch (Exception $e) {
            Log::channel('noo')->error('Persetujuan NOO gagal', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function rejectNoo(RejectNooRequest $request)
    {
        try {
            $register = Register::findOrFail($request->id);

            // Validate that register.status is null or 'CONFIRMED' before allowing rejection
            // State machine: null/LEAD → REJECTED, CONFIRMED → REJECTED
            // Already APPROVED or REJECTED registers cannot be rejected again
            if ($register->status === 'APPROVED' || $register->status === 'REJECTED') {
                throw new BadRequestException('Register sudah diproses sebelumnya');
            }

            $register->status = $request->status;
            $register->keterangan = $request->alasan;
            $register->rejected_by_id = Auth::id();
            $register->rejected_at = now();

            $register->update();

            $recipient = optional($register->tm)->id_notif;
            $this->dispatchNotification(
                'Register '.$register->nama_outlet.' ditolak oleh '.Auth::user()->nama_lengkap.PHP_EOL.'Alasan : '.$request->alasan,
                $recipient ? [$recipient] : []
            );

            return response()->json([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'berhasil update',
                ],
                'data' => $register,
                'errors' => null,
            ]);
        } catch (Exception $e) {
            Log::channel('noo')->error('Penolakan NOO gagal', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getRegisterOutlet(Request $request)
    {
        try {
            $user = Auth::user();
            $compact = $request->boolean('compact', true);
            $relations = $compact ? [
                'badanusaha:id,name',
                'cluster:id,name',
                'region:id,name',
                'divisi:id,name',
            ] : [
                'badanusaha',
                'cluster',
                'region',
                'divisi',
            ];

            $selectColumns = $compact ? [
                'id',
                'kode_outlet',
                'nama_outlet',
                'alamat_outlet',
                'status',
                'keterangan',
                'distric',
                'latlong',
                'badanusaha_id',
                'divisi_id',
                'region_id',
                'cluster_id',
                'created_at',
            ] : ['*'];

            $registers = Register::with($relations)
                ->whereNull('approved_by_id')
                ->when($compact, fn ($q) => $q->select($selectColumns))
                ->visibleTo($user)
                ->orderBy('nama_outlet')
                ->get();

            // Determine resource class based on compact mode
            $resourceClass = $compact ? RegisterCompactResource::class : RegisterResource::class;

            return $resourceClass::collection($registers)->additional([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'fetch register success',
                ],
                'errors' => null,
            ]);
        } catch (Exception $e) {
            Log::channel('noo')->error('Pengambilan register pending gagal', [
                'user_id' => Auth::id(),
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function show(Request $request, int $id)
    {
        try {
            $user = Auth::user();

            $register = Register::with(['badanusaha', 'cluster', 'region', 'divisi'])
                ->visibleTo($user)
                ->findOrFail($id);

            return (new RegisterResource($register))->additional([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'berhasil',
                ],
                'errors' => null,
            ]);
        } catch (Exception $e) {
            Log::channel('noo')->warning('Tampilkan register gagal', [
                'user_id' => Auth::id(),
                'register_id' => $id,
                'error' => $e->getMessage(),
            ]);

            throw new ResourceNotFoundException('Register tidak ditemukan');
        }
    }

    protected function mapTargetToFileType(string $target): string
    {
        return [
            'poto_shop_sign' => 'register-photo',
            'poto_depan' => 'register-photo',
            'poto_kiri' => 'register-photo',
            'poto_kanan' => 'register-photo',
            'poto_ktp' => 'register-ktp',
            'video' => 'register-video',
        ][$target] ?? 'register-photo';
    }

    protected function resolveHierarchy(User $user, Request $request): array
    {
        $ids = $user->getOrganizationalIds();
        $cluster = null;

        $clusterId = $request->integer('cluster_id') ?: ($ids['cluster'][0] ?? null);
        if (! $clusterId && $request->filled('clus')) {
            $cluster = Cluster::where('name', $request->clus)->first();
            $clusterId = $cluster?->id;
        } elseif ($clusterId) {
            $cluster = Cluster::find($clusterId);
        }

        $regionId = $request->integer('region_id') ?: ($ids['region'][0] ?? $cluster?->region_id);
        if (! $regionId && $request->filled('reg')) {
            $regionId = Region::where('name', $request->reg)->value('id');
        }

        $divisiId = $request->integer('divisi_id') ?: ($ids['divisi'][0] ?? $cluster?->divisi_id);
        if (! $divisiId && $request->filled('div')) {
            $divisiId = Division::where('name', $request->div)->value('id');
        }

        $badanusahaId = $request->integer('badanusaha_id') ?: ($ids['badanusaha'][0] ?? $cluster?->badanusaha_id);
        if (! $badanusahaId && $request->filled('bu')) {
            $badanusahaId = BadanUsaha::where('name', $request->bu)->value('id');
        }

        return [
            'badanusaha_id' => $badanusahaId,
            'divisi_id' => $divisiId,
            'region_id' => $regionId,
            'cluster_id' => $clusterId,
            'tm_id' => $user->tm?->id ?? $user->id,
        ];
    }

    protected function isHierarchyIncomplete(array $hierarchy): bool
    {
        return in_array(null, [
            $hierarchy['badanusaha_id'],
            $hierarchy['divisi_id'],
            $hierarchy['region_id'],
            $hierarchy['cluster_id'],
        ], true);
    }

    protected function buildNotificationRecipients(User $user, array $hierarchy): array
    {
        $recipients = [];

        if ($user->tm?->id_notif) {
            $recipients[] = $user->tm->id_notif;
        }

        if ($hierarchy['region_id']) {
            $recipients = array_merge($recipients, User::query()
                ->whereNotNull('id_notif')
                ->whereHas('role', fn ($query) => $query->where('organizational_scope_level', 'region'))
                ->whereHas('regions', fn ($query) => $query->where('regions.id', $hierarchy['region_id']))
                ->pluck('id_notif')
                ->toArray());
        }

        if ($hierarchy['cluster_id']) {
            $recipients = array_merge($recipients, User::query()
                ->whereNotNull('id_notif')
                ->whereHas('role', fn ($query) => $query->where('organizational_scope_level', 'cluster'))
                ->whereHas('clusters', fn ($query) => $query->where('clusters.id', $hierarchy['cluster_id']))
                ->pluck('id_notif')
                ->toArray());
        }

        return array_values(array_unique(array_filter($recipients)));
    }

    protected function dispatchNotification(string $message, array $recipientIds): void
    {
        $normalizedRecipients = array_values(array_filter($recipientIds));

        if ($normalizedRecipients === []) {
            return;
        }

        Queue::push((new SendNotificationJob($message, $normalizedRecipients))->onQueue('notifications'));
    }
}
