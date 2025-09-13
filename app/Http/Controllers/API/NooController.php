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

class NooController extends Controller
{
    public function fetch(Request $request)
    {
        try {
            $user = Auth::user();
            $badanusahaId = $user->badanusaha_id;
            $divisiId = $user->divisi_id;
            $regionId = $user->region_id;
            $clusterId = $user->cluster_id;
            $roleId = $user->role_id;

            $query = Noo::with(['badanusaha', 'cluster', 'region', 'divisi']);

            switch ($roleId) {
                // ASM
                case 1:
                    // Cek jika akunnya adalah sodikc maka ambil data dari region Bigtasik, Bigcrb, Bigpwt, Bigbdg, Bigkarawang dengan divisi realme
                    if ($user->id === 158) {
                        $noos = $query
                            ->whereIn('region_id', [13, 27, 26, 23, 24])
                            ->where('divisi_id', 4)
                            ->latest()
                            ->get();
                    } else {
                        $noos = $query
                            ->where('tm_id', $user->id)
                            ->latest()
                            ->get();
                    }

                    // Rule lama
                    // $noos = $query
                    // ->where('tm_id', $user->id)
                    // ->latest()
                    // ->get();

                    break;
                    // ASC
                case 2:
                    $noos = $query
                        ->where('badanusaha_id', $badanusahaId)
                        ->where('divisi_id', $divisiId)
                        ->where('region_id', $regionId)
                        ->latest()
                        ->get();
                    break;
                    // DSF/DM
                case 3:
                    $noos = $query
                        ->where('badanusaha_id', $badanusahaId)
                        ->where('divisi_id', $divisiId)
                        ->where('region_id', $regionId)
                        ->where('cluster_id', $clusterId)
                        ->orderBy('updated_at', 'DESC')
                        ->get();
                    break;
                    // COO
                case 6:
                    $noos = $query
                        ->latest()
                        ->get();
                    break;
                    // CSO
                case 8:
                    $noos = $query
                        ->where('divisi_id', 4)
                        ->latest()
                        ->get();
                    break;
                    // RKAM
                case 9:
                    $noos = $query
                        ->where('tm_id', $user->id)
                        ->latest()
                        ->get();
                    break;
                    // KAM
                case 10:
                    $noos = $query
                        ->where('badanusaha_id', $badanusahaId)
                        ->where('divisi_id', $divisiId)
                        ->where('region_id', $regionId)
                        ->latest()
                        ->get();
                    break;

                    // CSO FAST EV
                case 11:
                    $noos = $query
                        ->where('divisi_id', 7)
                        ->latest()
                        ->get();
                    break;

                default:
                    $noos = Noo::with(['badanusaha', 'cluster', 'region', 'divisi'])->where('badanusaha_id', 2)->orWhere('badanusaha_id', 4)->whereIn('status', ['PENDING', 'CONFIRMED', 'REJECTED'])->latest()->get();
                    break;
            }

            return ResponseFormatter::success(
                $noos->map->formatForAPI(),
                'fetch noo success',
            );
        } catch (Exception $err) {
            return ResponseFormatter::error([
                'message' => $err,
            ], 'something wrong', 500);
        }
    }

    public function all(Request $request)
    {
        try {
            $noos = Noo::with(['badanusaha', 'cluster', 'region', 'divisi'])->get();

            return ResponseFormatter::success(
                $noos->map->formatForAPI(),
                'fetch noo success',
            );
        } catch (Exception $err) {
            return ResponseFormatter::error([
                'message' => $err,
            ], 'something wrong', 500);
        }
    }

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
            $insert = Noo::create($data);
            if ($insert && count($notifId) != 0) {
                SendNotif::sendMessage('Noo baru '.$request->nama_outlet.' ditambahkan oleh '.Auth::user()->nama_lengkap, $notifId);
            }

            return ResponseFormatter::success(null, 'berhasil menambahkan NOO '.$request->nama_outlet);
        } catch (Exception $e) {
            error_log($e);

            return ResponseFormatter::error($e, 'gagal');
        }
    }

    public function confirm(Request $request)
    {
        try {

            $request->validate([
                'id' => ['required'],
                'status' => ['required'],
                'limit' => ['required'],
                'kode_outlet' => ['required'],
            ]);

            $noo = Noo::findOrFail($request->id);
            $noo->status = $request->status;
            $noo->limit = $request->limit;
            $noo->kode_outlet = $request->kode_outlet;
            $noo->confirmed_by = Auth::user()->nama_lengkap;
            $noo->confirmed_at = now();
            $noo->update();
            SendNotif::sendMessage(
                'Noo '.$noo->nama_outlet.' sudah di konfirmasi oleh '.
                    Auth::user()->nama_lengkap.PHP_EOL.
                    'Dengan limit : Rp '.number_format($request->limit, 0, ',', '.'),
                [User::where('nama_lengkap', $noo->created_by)->first()->id_notif ?? '-', $noo->tm->id_notif]

            );

            return ResponseFormatter::success($noo, 'berhasil update');
        } catch (Exception $e) {
            error_log($e);

            return ResponseFormatter::error($e, 'gagal');
        }
    }

    public function approved(Request $request)
    {
        try {

            $request->validate([
                'id' => ['required'],
                'status' => ['required'],
            ]);

            $noo = Noo::find($request->id);
            $noo->status = $request->status;
            $noo->approved_by = Auth::user()->nama_lengkap;
            $noo->approved_at = now();
            $noo->update();

            $notif = [];
            $register = User::where('nama_lengkap', $noo->created_by)->first()->id_notif;
            if ($register) {
                array_push($notif, $register);
            }

            $data = [
                'kode_outlet' => $noo->kode_outlet,
                'badanusaha_id' => $noo->badanusaha_id,
                'nama_outlet' => $noo->nama_outlet,
                'divisi_id' => $noo->divisi_id,
                'alamat_outlet' => $noo->alamat_outlet,
                'nama_pemilik_outlet' => $noo->nama_pemilik_outlet,
                'nomer_tlp_outlet' => $noo->nomer_tlp_outlet,
                'distric' => $noo->distric,
                'region_id' => $noo->region_id,
                'cluster_id' => $noo->cluster_id,
                'poto_shop_sign' => $noo->poto_shop_sign,
                'poto_depan' => $noo->poto_depan,
                'poto_kanan' => $noo->poto_kanan,
                'poto_kiri' => $noo->poto_kiri,
                'poto_ktp' => $noo->poto_ktp,
                'video' => $noo->video,
                'radius' => 0,
                'latlong' => $noo->latlong,
                'status_outlet' => 'MAINTAIN',
                'limit' => $noo->limit,
            ];
            $outletExisting = Outlet::where('badanusaha_id', $noo->badanusaha_id)
                ->where('divisi_id', $noo->divisi_id)
                ->where('region_id', $noo->region_id)
                ->where('cluster_id', $noo->cluster_id)
                ->where('kode_outlet', $noo->kode_outlet)->first();
            if ($outletExisting) {
                return ResponseFormatter::success($noo, 'berhasil update');
            } else {
                $insert = Outlet::create($data);
            }
            if (count($notif) != 0 && $insert) {
                SendNotif::sendMessage(
                    'Noo '.$noo->nama_outlet.' sudah di setujui oleh '.
                        Auth::user()->nama_lengkap,
                    $notif
                );
            }

            return ResponseFormatter::success($noo, 'berhasil update');
        } catch (Exception $e) {
            error_log($e);

            return ResponseFormatter::error($e, 'gagal');
        }
    }

    public function reject(Request $request)
    {
        try {
            $request->validate([
                'id' => ['required'],
                'status' => ['required'],
                'alasan' => ['required'],
            ]);

            $noo = Noo::findOrFail($request->id);
            $noo->status = $request->status;
            $noo->keterangan = $request->alasan;
            $noo->rejected_by = Auth::user()->nama_lengkap;
            $noo->rejected_at = now();

            $noo->update();

            SendNotif::sendMessage('Noo '.$noo->nama_outlet.' ditolak oleh '.Auth::user()->nama_lengkap.PHP_EOL.'Alasan : '.$request->alasan, [$noo->tm->id_notif]);

            return ResponseFormatter::success($noo, 'berhasil update');
        } catch (Exception $e) {
            return ResponseFormatter::error($e, 'gagal');
        }
    }

    public function getbu(Request $request)
    {
        try {
            $badanusahas = BadanUsaha::all();

            return ResponseFormatter::success($badanusahas, 'berhasil');
        } catch (Exception $e) {
            return ResponseFormatter::error([], $e->getMessage());
        }
    }

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

    public function getnoooutlet(Request $request)
    {
        try {
            $user = Auth::user();
            $badanusahaId = $user->badanusaha_id;
            $divisiId = $user->divisi_id;
            $regionId = $user->region_id;
            $clusterId = $user->cluster_id;
            $roleId = $user->role_id;

            $query = Noo::with(['badanusaha', 'cluster', 'region', 'divisi'])->where('approved_by', null);

            switch ($roleId) {
                // ASM
                case 1:
                    $noos = $query
                        ->where('tm_id', $user->id)
                        ->orderBy('nama_outlet')
                        ->get();
                    break;
                    // ASC
                case 2:
                    $noos = $query
                        ->where('badanusaha_id', $badanusahaId)
                        ->where('divisi_id', $divisiId)
                        ->where('region_id', $regionId)
                        ->orderBy('nama_outlet')
                        ->get();
                    break;
                    // DSF/DM
                case 3:
                    $noos = $query
                        ->where('badanusaha_id', $badanusahaId)
                        ->where('divisi_id', $divisiId)
                        ->where('region_id', $regionId)
                        ->where('cluster_id', $clusterId)
                        ->orderBy('nama_outlet')
                        ->get();
                    break;

                default:
                    $noos = Noo::with(['badanusaha', 'cluster', 'region', 'divisi'])->where('badanusaha_id', 2)->orWhere('badanusaha_id', 4)->whereIn('status', ['PENDING', 'CONFIRMED', 'REJECTED'])->latest()->get();
                    break;
            }

            return ResponseFormatter::success(
                $noos,
                'fetch noo success',
            );
        } catch (Exception $err) {
            return ResponseFormatter::error([
                'message' => $err,
            ], 'something wrong', 500);
        }
    }

    public function singleOutlet(Request $request, $kodeOutlet)
    {
        // dd($request->all());
        try {
            $noo = Noo::with(['badanusaha', 'cluster', 'region', 'divisi'])
                ->where('id', $kodeOutlet)
                ->get();

            return ResponseFormatter::success($noo, 'berhasil');
        } catch (Exception $err) {
            return ResponseFormatter::error(null, 'ada kesalahan');
        }
    }
}
