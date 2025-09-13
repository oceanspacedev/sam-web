<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Region;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class OutletController extends Controller
{
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
                'error' => $e
            ], 'ERROR', 500);
        }
    }

    public function fetch(Request $request)
    {
        try {
            $user = Auth::user();
            $query = Outlet::with(['badanusaha', 'cluster', 'region', 'divisi']);
            switch ($user->role_id) {
                #ASM
                case 1:
                    $divisi = Division::where('name', $request->divisi)->first()->id;
                    $region = Region::where('name', $request->region)->where('divisi_id', $divisi)->first()->id;
                    $outlet = $query
                        ->where('divisi_id', $divisi)
                        ->where('region_id', $region)
                        ->orderBy('nama_outlet')
                        ->get();
                    break;
                #ASC
                case 2:
                    $outlet = $query
                        ->where('badanusaha_id', $user->badanusaha_id)
                        ->where('divisi_id', $user->divisi_id)
                        ->where('region_id', $user->region_id)
                        ->whereIn('cluster_id', [$user->cluster_id, $user->cluster_id2])
                        ->orderBy('nama_outlet')
                        ->get();
                    break;
                #DSF/DM
                case 3:
                    $outlet = $query
                        ->where('badanusaha_id', $user->badanusaha_id)
                        ->where('divisi_id', $user->divisi_id)
                        ->where('region_id', $user->region_id)
                        ->whereIn('cluster_id', [$user->cluster_id, $user->cluster_id2])
                        ->orderBy('nama_outlet')
                        ->get();
                    break;
                #COO
                case 6:
                    $divisi = Division::where('name', $request->divisi)->first()->id;
                    $region = Region::where('name', $request->region)->where('divisi_id', $divisi)->first()->id;
                    $outlet = $query
                        ->where('divisi_id', $divisi)
                        ->where('region_id', $region)
                        ->orderBy('nama_outlet')
                        ->get();
                    break;
                #CSO
                case 8:
                    $divisi = Division::where('name', $request->divisi)->first()->id;
                    $region = Region::where('name', $request->region)->where('divisi_id', $divisi)->first()->id;
                    $outlet = $query
                        ->where('divisi_id', $divisi)
                        ->where('region_id', $region)
                        ->orderBy('nama_outlet')
                        ->get();
                    break;
                #RKAM
                case 9:
                    $divisi = Division::where('name', $request->divisi)->first()->id;
                    $region = Region::where('name', $request->region)->where('divisi_id', $divisi)->first()->id;
                    $outlet = $query
                        ->where('divisi_id', $divisi)
                        ->where('region_id', $region)
                        ->orderBy('nama_outlet')
                        ->get();
                    break;
                #KAM
                case 10:
                    $outlet = $query
                        ->where('badanusaha_id', $user->badanusaha_id)
                        ->where('divisi_id', $user->divisi_id)
                        ->where('region_id', $user->region_id)
                        ->orderBy('nama_outlet')
                        ->get();
                    break;
                #CSO FAST EV
                case 11:
                    $divisi = Division::where('name', $request->divisi)->first()->id;
                    $region = Region::where('name', $request->region)->where('divisi_id', $divisi)->first()->id;
                    $outlet = $query
                        ->where('divisi_id', $divisi)
                        ->where('region_id', $region)
                        ->orderBy('nama_outlet')
                        ->get();
                    break;
                default:
                    $outlet = Outlet::with(['badanusaha', 'cluster', 'region', 'divisi'])->get();
                    break;
            }


            return ResponseFormatter::success(
                $outlet->map->formatForAPI(),
                count($outlet),
            );
        } catch (Exception $e) {

            return ResponseFormatter::error([
                'message' => 'ada yang salah',
                'error' => $e
            ], 'ERROR', 500);
        }
    }

    public function singleOutlet(Request $request, $nama)
    {
        try {
            $outlet = Outlet::with(['badanusaha', 'cluster', 'region', 'divisi'])
                ->where('kode_outlet', $nama)
                ->get();
            return ResponseFormatter::success($outlet->map->formatForAPI(), 'berhasil');
        } catch (Exception $err) {
            return ResponseFormatter::error(null, 'ada kesalahan');
        }
    }

    public function updatefoto(Request $request)
    {
        try {
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
                if ($request->hasFile('photo' . $i)) {
                    $dynamicRules['photo' . $i] = ['file', 'image', 'mimes:jpg,jpeg,png', 'max:5120']; // 5MB
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
            if (!$outlet) {
                return ResponseFormatter::error(null, 'Outlet tidak ditemukan', 404);
            }

            $disk = Storage::disk('public');
            // Sanitasi kode outlet untuk path
            $safeKode = preg_replace('/[^A-Za-z0-9._-]/', '_', $outlet->kode_outlet);
            $baseDir = 'outlets/' . $safeKode;

            // Proses foto (mendukung photo0..4 dan photos[])
            $photoFiles = [];
            for ($i = 0; $i <= 4; $i++) {
                $f = $request->file('photo' . $i);
                if ($f) {
                    $photoFiles[] = $f;
                }
            }
            if ($request->hasFile('photos')) {
                foreach ((array) $request->file('photos') as $pf) {
                    if ($pf) $photoFiles[] = $pf;
                }
            }

            foreach ($photoFiles as $file) {
                if (!$file->isValid()) {
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

                $ext = $file->guessExtension() ?: $file->extension();
                $filename = (string) Str::uuid() . '.' . $ext;
                $path = $disk->putFileAs($baseDir . '/photos', $file, $filename);
                // Simpan path relatif pada kolom agar hook model bisa hapus file lama
                $outlet->{$targetField} = $path;
            }

            // Proses video (opsional)
            if ($request->hasFile('video')) {
                $video = $request->file('video');
                if (!$video->isValid()) {
                    return ResponseFormatter::error(null, 'File video tidak valid', 422);
                }
                $vext = $video->guessExtension() ?: $video->extension();
                $vname = (string) Str::uuid() . '.' . $vext;
                $vpath = $disk->putFileAs($baseDir . '/videos', $video, $vname);
                $outlet->video = $vpath;
            }

            // Update field teks
            $outlet->nama_pemilik_outlet = strtoupper($request->nama_pemilik_outlet);
            $outlet->nomer_tlp_outlet = $request->nomer_tlp_outlet;
            $outlet->latlong = $request->latlong;
            $outlet->save();

            return ResponseFormatter::success(null, 'berhasil Update');
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseFormatter::error($e->errors(), 'VALIDATION_ERROR', 422);
        } catch (Exception $e) {
            error_log($e->getMessage());
            return ResponseFormatter::error(null, $e->getMessage(), 400);
        }
    }
}
