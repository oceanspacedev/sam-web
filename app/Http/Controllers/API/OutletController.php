<?php

namespace App\Http\Controllers\API;

use App\Exceptions\Api\BadRequestException;
use App\Exceptions\Api\FileUploadException;
use App\Exceptions\Api\ResourceNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\UpdateOutletRequest;
use App\Http\Resources\Outlet\OutletCompactResource;
use App\Http\Resources\Outlet\OutletResource;
use App\Models\Outlet;
use App\Services\FileUploadService;
use App\Support\StorageDisk;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class OutletController extends Controller
{
    public function __construct(
        protected FileUploadService $fileUpload
    ) {}

    public function fetch(Request $request)
    {
        // Basic input validation for filters
        $request->validate([
            'compact' => 'sometimes|boolean',
            'search' => 'sometimes|string',
            'per_page' => 'sometimes|integer|min:1|max:100',
            'lat' => 'nullable|numeric',
            'lng' => 'nullable|numeric',
        ]);

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

        // Determine resource class based on compact mode
        $resourceClass = $compact ? OutletCompactResource::class : OutletResource::class;

        // Apply nearby location filter if lat/lng provided
        if ($hasLocationParams) {
            $lat = (float) $request->lat;
            $lng = (float) $request->lng;
            $query->nearbyLocation($lat, $lng, 10);

            // Get results without pagination for nearby search
            $outlets = $query->get();

            return $resourceClass::collection($outlets)->additional([
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

        return $resourceClass::collection($outlets)->additional([
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
    }

    public function show(Request $request, int $id)
    {
        $user = Auth::user();
        $outlet = Outlet::with(['badanusaha', 'cluster', 'region', 'divisi'])
            ->visibleTo($user)
            ->where('id', $id)
            ->first();

        if (! $outlet) {
            throw new ResourceNotFoundException('Outlet tidak ditemukan');
        }

        return (new OutletResource($outlet))->additional([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'berhasil',
            ],
            'errors' => null,
        ]);
    }

    public function update(UpdateOutletRequest $request, int $id)
    {
        $user = Auth::user();

        Log::channel('outlet')->info('Pembaruan outlet dimulai', [
            'user_id' => $user->id,
            'outlet_id' => $id,
        ]);

        $outlet = Outlet::visibleTo($user)->where('id', $id)->first();
        if (! $outlet) {
            Log::channel('outlet')->warning('Pembaruan outlet gagal: outlet tidak ditemukan', [
                'user_id' => $user->id,
                'outlet_id' => $id,
            ]);

            throw new ResourceNotFoundException('Outlet tidak ditemukan');
        }

        // Proses foto dengan field name sesuai kolom DB (poto_*)
        foreach ([
            'poto_shop_sign',
            'poto_depan',
            'poto_kanan',
            'poto_kiri',
            'poto_ktp',
        ] as $field) {
            $file = $request->file($field);
            if (! $file) {
                continue;
            }

            if (! $file->isValid()) {
                throw (new FileUploadException('File foto tidak valid'))
                    ->withData(['field' => $field]);
            }

            try {
                $path = $this->fileUpload->uploadImageOptimized($file, 'outlet-photo');
            } catch (Throwable $e) {
                throw (new FileUploadException('Gagal mengupload foto'))
                    ->withData(['field' => $field]);
            }
            $this->deleteOutletMedia($outlet->{$field});
            $outlet->{$field} = $path;
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
                throw new FileUploadException('File foto tidak valid');
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
            } catch (Throwable $e) {
                throw new FileUploadException('Gagal mengupload foto');
            }

            $this->deleteOutletMedia($outlet->{$targetField});
            $outlet->{$targetField} = $path;
        }

        // Proses video (opsional)
        $videoFile = $request->file('video');
        if ($videoFile) {
            if (! $videoFile->isValid()) {
                throw (new FileUploadException('File video tidak valid'))
                    ->withData(['field' => 'video']);
            }

            try {
                $path = $this->fileUpload->uploadVideoOptimized($videoFile, 'outlet-video');
            } catch (Throwable $e) {
                throw (new FileUploadException('Gagal mengupload video'))
                    ->withData(['field' => 'video']);
            }

            $this->deleteOutletMedia($outlet->video);
            $outlet->video = $path;
        }

        // Update field teks - hanya jika ada di request (untuk mendukung partial update)
        if ($request->filled('alamat_outlet')) {
            $outlet->alamat_outlet = $request->alamat_outlet;
        }
        if ($request->filled('nama_pemilik_outlet')) {
            $outlet->nama_pemilik_outlet = strtoupper($request->nama_pemilik_outlet);
        }
        if ($request->filled('nomer_tlp_outlet')) {
            $outlet->nomer_tlp_outlet = $request->nomer_tlp_outlet;
        }
        if ($request->filled('latlong')) {
            $outlet->latlong = $request->latlong;
        }

        // Auto-activate outlet when updated (set status to MAINTAIN)
        // This reverts archived outlets (UNMAINTAIN) back to active status
        if ($outlet->status_outlet !== 'MAINTAIN') {
            $outlet->status_outlet = 'MAINTAIN';
            Log::channel('outlet')->info('Status outlet diaktifkan otomatis ke MAINTAIN', [
                'outlet_id' => $outlet->id,
                'kode_outlet' => $outlet->kode_outlet,
                'previous_status' => $outlet->getOriginal('status_outlet'),
            ]);
        }

        $outlet->save();

        Log::channel('outlet')->info('Pembaruan outlet berhasil', [
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
    }

    /**
     * Reset outlet data (owner info + media)
     * PATCH /outlet/{id}/reset
     */
    public function reset(int $id)
    {
        $user = Auth::user();

        return DB::transaction(function () use ($id, $user) {
            // Use lockForUpdate to prevent race condition
            $outlet = Outlet::visibleTo($user)->where('id', $id)->lockForUpdate()->first();

            if (! $outlet) {
                throw new ResourceNotFoundException('Outlet tidak ditemukan');
            }

            [$now, $resetCount] = $this->enforceResetLimits(
                $outlet->last_reset_at,
                (int) $outlet->reset_count_yearly,
                'Reset data outlet'
            );

            Log::channel('outlet')->info('Reset outlet dimulai', [
                'user_id' => $user->id,
                'outlet_id' => $outlet->id,
                'kode_outlet' => $outlet->kode_outlet,
            ]);

            // Delete all media files (keep KTP photo)
            $mediaFields = ['poto_depan', 'poto_kanan', 'poto_kiri', 'poto_shop_sign', 'video'];

            foreach ($mediaFields as $field) {
                $this->deleteOutletMedia($outlet->{$field});
                $outlet->{$field} = null;
            }

            // Reset owner fields.
            $outlet->nama_pemilik_outlet = null;
            $outlet->nomer_tlp_outlet = null;
            // `alamat_outlet` tidak nullable, gunakan placeholder yang konsisten.
            $outlet->alamat_outlet = '-';
            // Reset location as part of "reset data" for consistency.
            $outlet->latlong = null;

            $outlet->last_reset_at = $now;
            $outlet->reset_count_yearly = $resetCount + 1;
            $outlet->save();

            Log::channel('outlet')->info('Reset outlet berhasil', [
                'user_id' => $user->id,
                'outlet_id' => $outlet->id,
                'kode_outlet' => $outlet->kode_outlet,
                'last_reset_at' => $outlet->last_reset_at,
                'reset_count_yearly' => $outlet->reset_count_yearly,
            ]);

            return response()->json([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'Media outlet berhasil direset',
                ],
                'data' => null,
                'errors' => null,
            ]);
        });
    }

    /**
     * Reset outlet location (lat/long) and track reset count.
     * PATCH /outlet/{id}/reset-location
     */
    public function resetLocation(int $id)
    {
        $user = Auth::user();

        return DB::transaction(function () use ($id, $user) {
            // Use lockForUpdate to prevent race condition
            $outlet = Outlet::visibleTo($user)->where('id', $id)->lockForUpdate()->first();

            if (! $outlet) {
                throw new ResourceNotFoundException('Outlet tidak ditemukan');
            }

            if ($user && Gate::denies('resetLocation', $outlet)) {
                abort(403, 'Anda tidak memiliki akses untuk reset lokasi outlet ini.');
            }

            [$now, $resetCount] = $this->enforceResetLimits(
                $outlet->last_reset_at,
                (int) $outlet->reset_count_yearly,
                'Reset lokasi outlet'
            );

            // Reset lokasi sekaligus data pendukung (alamat + media) agar outlet perlu update ulang.
            // KTP dipertahankan.
            $mediaFields = ['poto_depan', 'poto_kanan', 'poto_kiri', 'poto_shop_sign', 'video'];
            foreach ($mediaFields as $field) {
                $this->deleteOutletMedia($outlet->{$field});
                $outlet->{$field} = null;
            }

            $outlet->latlong = null;
            // `alamat_outlet` tidak nullable, gunakan placeholder yang konsisten.
            $outlet->alamat_outlet = '-';
            $outlet->last_reset_at = $now;
            $outlet->reset_count_yearly = $resetCount + 1;
            $outlet->save();

            Log::channel('outlet')->info('Reset lokasi outlet', [
                'user_id' => $user?->id,
                'outlet_id' => $outlet->id,
                'kode_outlet' => $outlet->kode_outlet,
                'reset_count_yearly' => $outlet->reset_count_yearly,
                'last_reset_at' => $outlet->last_reset_at,
            ]);

            return response()->json([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'Lokasi outlet dan data pendukung berhasil direset',
                ],
                'data' => [
                    'last_reset_at' => $outlet->last_reset_at,
                    'reset_count_yearly' => $outlet->reset_count_yearly,
                ],
                'errors' => null,
            ]);
        });
    }

    /**
     * Soft delete outlet
     * DELETE /outlet/{id}
     */
    public function destroy(int $id)
    {
        $user = Auth::user();

        $outlet = Outlet::visibleTo($user)->where('id', $id)->first();

        if (! $outlet) {
            throw new ResourceNotFoundException('Outlet tidak ditemukan');
        }

        Log::channel('outlet')->info('Penghapusan outlet dimulai', [
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'kode_outlet' => $outlet->kode_outlet,
        ]);

        // Soft delete
        $outlet->delete();

        Log::channel('outlet')->info('Outlet dihapus', [
            'user_id' => $user->id,
            'outlet_id' => $outlet->id,
            'kode_outlet' => $outlet->kode_outlet,
        ]);

        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'Outlet berhasil dihapus',
            ],
            'data' => null,
            'errors' => null,
        ]);
    }

    /**
     * Enforce reset cooldown (30 hari) and yearly cap (4x) for outlet resets.
     *
     * @return array{0: \Carbon\CarbonInterface, 1: int} [now, normalizedResetCount]
     *
     * @throws BadRequestException
     */
    protected function enforceResetLimits(?Carbon $lastResetAt, int $resetCountYearly, string $actionLabel): array
    {
        $now = now();

        if ($lastResetAt && $lastResetAt->diffInDays($now) < 30) {
            $nextAllowedAt = $lastResetAt->copy()->addDays(30);

            throw (new BadRequestException(sprintf('%s hanya dapat dilakukan setiap 30 hari', $actionLabel)))
                ->withData([
                    'last_reset_at' => $lastResetAt,
                    'next_allowed_at' => $nextAllowedAt,
                    'reset_count_yearly' => $resetCountYearly,
                    'max_resets_per_year' => 4,
                ]);
        }

        if ($lastResetAt && $lastResetAt->year !== $now->year) {
            $resetCountYearly = 0;
        }

        if ($resetCountYearly >= 4) {
            throw (new BadRequestException(sprintf('%s maksimal 4 kali dalam setahun', $actionLabel)))
                ->withData([
                    'last_reset_at' => $lastResetAt,
                    'reset_count_yearly' => $resetCountYearly,
                    'max_resets_per_year' => 4,
                    'next_reset_window_start' => $now->copy()->startOfYear()->addYear(),
                ]);
        }

        return [$now, $resetCountYearly];
    }

    protected function deleteOutletMedia(?string $path): void
    {
        if (! $path || $path === '-' || $path === '0') {
            return;
        }

        $disk = StorageDisk::default();

        try {
            Storage::disk($disk)->delete($path);
        } catch (Throwable $e) {
            // Silent catch - log warning but don't fail the request
            Log::channel('outlet')->warning('Gagal menghapus media outlet', [
                'path' => $path,
                'disk' => $disk,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
