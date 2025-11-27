<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Http\Resources\OutletResource;
use App\Models\Outlet;
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

    public function fetch(Request $request)
    {
        try {
            $user = Auth::user();
            // Default compact=true for lighter list payloads
            $compact = $request->boolean('compact', true);
            $hasLocationParams = $request->filled(['lat', 'lng']);

            // Base query with conditional eager loading
            $relations = $compact
                ? ['badanusaha:id,name', 'cluster:id,name', 'region:id,name', 'divisi:id,name']
                : ['badanusaha', 'cluster', 'region', 'divisi'];

            $query = Outlet::with($relations);

            if ($compact) {
                $query->select([
                    'id',
                    'kode_outlet',
                    'nama_outlet',
                    'status_outlet',
                    'latlong',
                    'radius',
                    'badanusaha_id',
                    'divisi_id',
                    'region_id',
                    'cluster_id',
                ]);
            }

            // Apply search filter
            if ($request->filled('search')) {
                $query->filter($request->search);
            }

            // Apply organizational scope using pivot assignments
            $query->visibleTo($user);

            // Apply nearby location filter if lat/lng provided
            if ($hasLocationParams) {
                $lat = (float) $request->lat;
                $lng = (float) $request->lng;
                $query->nearbyLocation($lat, $lng, 10);

                // Get results without pagination for nearby search
                $outlets = $query->get();

                // Return minimal or full data
                return OutletResource::collection($outlets)->additional([
                    'meta' => [
                        'code' => 200,
                        'status' => 'success',
                        'message' => 'berhasil',
                    ],
                    'errors' => null,
                ]);
            }

            // Normal pagination for non-nearby search
            $perPage = min((int) $request->get('per_page', 20), 100);
            $outlets = $query->orderBy('nama_outlet')->paginate($perPage);

            // Return minimal or full data
            return OutletResource::collection($outlets)->additional([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'berhasil',
                    'pagination' => [
                        'current_page' => $outlets->currentPage(),
                        'per_page' => $outlets->perPage(),
                        'total' => $outlets->total(),
                        'last_page' => $outlets->lastPage(),
                    ],
                ],
                'errors' => null,
            ]);
        } catch (Exception $e) {
            return ResponseFormatter::error([
                'message' => 'ada yang salah',
                'error' => $e->getMessage(),
            ], 'ERROR', 500);
        }
    }

    public function show(Request $request, int $id)
    {
        try {
            $user = Auth::user();
            $outlet = Outlet::with(['badanusaha', 'cluster', 'region', 'divisi'])
                ->visibleTo($user)
                ->where('id', $id)
                ->first();

            if (! $outlet) {
                return ResponseFormatter::error(null, 'Outlet tidak ditemukan', 404);
            }

            return (new OutletResource($outlet))->additional([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'berhasil',
                ],
                'errors' => null,
            ]);
        } catch (Exception $err) {
            return ResponseFormatter::error(null, 'ada kesalahan');
        }
    }

    public function update(Request $request, int $id)
    {
        try {
            $user = Auth::user();

            Log::channel('outlet')->info('Outlet update initiated', [
                'user_id' => $user->id,
                'outlet_id' => $id,
            ]);

            // Validasi dasar field non-file
            $baseRules = [
                'nama_pemilik_outlet' => ['required'],
                'nomer_tlp_outlet' => ['required'],
                'latlong' => ['required'],
            ];

            // Kumpulkan file yang ada untuk validasi dinamis
            $dynamicRules = [];
            // Dukungan skema lama: photo0..photo4
            for ($i = 0; $i <= 4; $i++) {
                if ($request->hasFile('photo'.$i)) {
                    $dynamicRules['photo'.$i] = ['file', 'image', 'mimes:jpg,jpeg,png', 'max:3072']; // 3MB
                }
            }
            // Dukungan skema baru: photos[]
            if ($request->hasFile('photos')) {
                $dynamicRules['photos'] = ['array'];
                $dynamicRules['photos.*'] = ['file', 'image', 'mimes:jpg,jpeg,png', 'max:3072'];
            }
            // Video opsional
            if ($request->hasFile('video')) {
                $dynamicRules['video'] = ['file', 'mimetypes:video/mp4,video/quicktime,video/webm', 'max:51200']; // 50MB
            }

            $request->validate(array_merge($baseRules, $dynamicRules));

            $outlet = Outlet::visibleTo($user)->where('id', $id)->first();
            if (! $outlet) {
                Log::channel('outlet')->warning('Outlet update failed: outlet not found', [
                    'user_id' => $user->id,
                    'outlet_id' => $id,
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

            // Auto-activate outlet when updated (set status to MAINTAIN)
            // This reverts archived outlets (UNMAINTAIN) back to active status
            if ($outlet->status_outlet !== 'MAINTAIN') {
                $outlet->status_outlet = 'MAINTAIN';
                Log::channel('outlet')->info('Outlet status auto-activated to MAINTAIN', [
                    'outlet_id' => $outlet->id,
                    'kode_outlet' => $outlet->kode_outlet,
                    'previous_status' => $outlet->getOriginal('status_outlet'),
                ]);
            }

            $outlet->save();

            Log::channel('outlet')->info('Outlet update success', [
                'user_id' => $user->id,
                'outlet_id' => $outlet->id,
                'kode_outlet' => $outlet->kode_outlet,
            ]);

            // Reload with relationships for response
            $outlet->load(['badanusaha', 'cluster', 'region', 'divisi']);

            return (new OutletResource($outlet))->additional([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'Outlet berhasil diupdate',
                ],
                'errors' => null,
            ]);
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
