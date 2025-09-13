<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Helpers\SendNotif;
use App\Http\Controllers\Controller;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Noo;
use App\Models\Outlet;
use App\Models\Region;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class LeadController extends Controller
{
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

            $disk = Storage::disk('public');
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
                $path = $disk->putFileAs('noo/photos', $file, $name);
                $data[$target] = $path;
            }

            if ($request->hasFile('video')) {
                $video = $request->file('video');
                if (! $video->isValid()) {
                    return ResponseFormatter::error('File video tidak valid', 'INVALID_FILE', 422);
                }
                $vext = $video->guessExtension() ?: $video->extension();
                $vname = (string) Str::uuid().'.'.$vext;
                $vpath = $disk->putFileAs('noo/videos', $video, $vname);
                $data['video'] = $vpath;
            }

            $noo = Noo::create($data);

            // Gabungkan $data dengan $outletData dan buat Outlet
            $outletData = [
                'kode_outlet' => 'LEAD'.$noo->id,
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

            $lead = Noo::find($request->id);
            if ($request->hasFile('photo')) {
                $file = $request->file('photo');
                if (! $file->isValid()) {
                    return ResponseFormatter::error('File KTP tidak valid', 'INVALID_FILE', 422);
                }
                $ext = $file->guessExtension() ?: $file->extension();
                $name = (string) Str::uuid().'.'.$ext;
                $path = Storage::disk('public')->putFileAs('noo/ktp', $file, $name);
                $lead['poto_ktp'] = $path;
            }
            $lead['ktp_outlet'] = $request->noktp;
            $lead['keterangan'] = null;
            $lead->update();
            SendNotif::sendMessage('Noo baru '.$lead->nama_outlet.' ditambahkan oleh '.Auth::user()->nama_lengkap, [User::where('role_id', 4)->first()->id_notif]);

            return ResponseFormatter::success(null, 'berhasil menambahkan Lead '.$request->nama_outlet);
        } catch (Exception $e) {
            return ResponseFormatter::error($e->getMessage(), $e->getMessage());
        }
    }
}
