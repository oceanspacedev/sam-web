<?php

namespace App\Http\Controllers\API;

use App\Exceptions\Api\ResourceNotFoundException;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\UpdateOutletRequest;
use App\Http\Resources\OutletResource;
use App\Models\Outlet;
use App\Services\FileUploadService;
use App\Support\StorageDisk;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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

        Log::channel('outlet')->info('Outlet update initiated', [
            'user_id' => $user->id,
            'outlet_id' => $id,
        ]);

        $outlet = Outlet::visibleTo($user)->where('id', $id)->first();
        if (! $outlet) {
            Log::channel('outlet')->warning('Outlet update failed: outlet not found', [
                'user_id' => $user->id,
                'outlet_id' => $id,
            ]);

            throw new ResourceNotFoundException('Outlet tidak ditemukan');
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
                throw new \RuntimeException('File foto tidak valid');
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

            $path = $this->fileUpload->uploadImageOptimized($file, 'outlet-photo');

            $this->deleteOutletMedia($outlet->{$targetField});
            $outlet->{$targetField} = $path;
        }

        // Proses video (opsional)
        if ($request->hasFile('video')) {
            $path = $this->fileUpload->uploadVideoOptimized($request->file('video'), 'outlet-video');

            $this->deleteOutletMedia($outlet->video);
            $outlet->video = $path;
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
            Log::channel('outlet')->warning('Failed to delete outlet media', [
                'path' => $path,
                'disk' => $disk,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
