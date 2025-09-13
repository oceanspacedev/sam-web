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
            $request->validate([
                'kode_outlet' => ['required'],
                'nama_pemilik_outlet' => ['required'],
                'nomer_tlp_outlet' => ['required'],
                'latlong' => ['required'],
            ]);

            $data = Outlet::where('kode_outlet', $request->kode_outlet)->first();
            if ((count($request->files) > 1) AND (count($request->files) <= 5)) {
                for ($i = 0; $i <= 3; $i++) {
                    $namaFoto = $request->file('photo' . $i)->getClientOriginalName();
                    if (Str::contains($namaFoto, 'fotodepan')) {
                        $data['poto_depan'] = $namaFoto;
                    } else if (Str::contains($namaFoto, 'fotokanan')) {
                        $data['poto_kanan'] = $namaFoto;
                    } else if (Str::contains($namaFoto, 'fotokiri')) {
                        $data['poto_kiri'] = $namaFoto;
                    } else {
                        $data['poto_shop_sign'] = $namaFoto;
                    }
                    $request->file('photo' . $i)->move(storage_path('app/public/'), $namaFoto);
                }
            //menambahkan else if karena tidak bisa upload jika hanya 1 file (video aja)
            } else if (count($request->files) == 1) {
                if ($request->hasFile('video')) {
                    $name = $request->file('video')->getClientOriginalName();
                    $data['video'] = 'update-' . now() . $name ;
                    $request->file('video')->move(storage_path('app/public/'), 'update-' . now() . $name);
                }
                $data['nama_pemilik_outlet'] = strtoupper($request->nama_pemilik_outlet);
                $data['nomer_tlp_outlet'] = $request->nomer_tlp_outlet;
                $data['latlong'] = $request->latlong;
                $data->save();

                return ResponseFormatter::success(null, 'berhasil Update');
            } else {
                for ($i = 0; $i <= 4; $i++) {
                    $namaFoto = $request->file('photo' . $i)->getClientOriginalName();
                    if (Str::contains($namaFoto, 'fotodepan')) {
                        $data['poto_depan'] = $namaFoto;
                    } else if (Str::contains($namaFoto, 'fotokanan')) {
                        $data['poto_kanan'] = $namaFoto;
                    } else if (Str::contains($namaFoto, 'fotokiri')) {
                        $data['poto_kiri'] = $namaFoto;
                    } else if (Str::contains($namaFoto, 'fotoktp')) {
                        $data['poto_ktp'] = $namaFoto;
                    } else {
                        $data['poto_shop_sign'] = $namaFoto;
                    }
                    $request->file('photo' . $i)->move(storage_path('app/public/'), $namaFoto);
                }
            }

            if ($request->hasFile('video')) {
                $name = $request->file('video')->getClientOriginalName();
                $data['video'] = 'update-' . now() . $name;
                $request->file('video')->move(storage_path('app/public/'), 'update-' . now() . $name);
            }
            $data['nama_pemilik_outlet'] = strtoupper($request->nama_pemilik_outlet);
            $data['nomer_tlp_outlet'] = $request->nomer_tlp_outlet;
            $data['latlong'] = $request->latlong;
            $data->save();

            return ResponseFormatter::success(null, 'berhasil Update');
        } catch (Exception $e) {
            error_log($e->getMessage());
            return ResponseFormatter::error(null, $e->getMessage());
        }
    }
}
