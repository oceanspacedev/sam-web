<?php

namespace App\Http\Controllers\API;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Noo;
use App\Models\Outlet;
use App\Models\Visit;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

class VisitController extends Controller
{

    /**
     * Visit - Monitoring visiting sales ✅
     */
    public function monitor(Request $request)
    {
        try {

            $user = Auth::user();
            // Robby (GM ZTE)
            if ($user->id == 2 && $user->role_id == 8) {
                $visit = Visit::with([
                    'outlet.badanusaha',
                    'outlet.region',
                    'outlet.divisi',
                    'outlet.cluster',
                    'user.badanusaha',
                    'user.region',
                    'user.divisi',
                    'user.cluster',
                    'user.role',
                ])->whereHas('user', function ($query) {
                    $query->where('divisi_id', '8')
                        ->whereIn('region_id', [63, 64, 66, 67, 68, 78, 79, 80, 81]);
                })
                    ->whereDate('tanggal_visit', $request->date ? date('Y-m-d', strtotime($request->date))  : date('Y-m-d'))
                    ->latest()
                    ->get();

                // VisitNoo removed; only include regular visits
            }
            // Hendra Setia (GM Techno)
            else if ($user->id == 689 && $user->role_id == 8) {
                $visit = Visit::with([
                    'outlet.badanusaha',
                    'outlet.region',
                    'outlet.divisi',
                    'outlet.cluster',
                    'user.badanusaha',
                    'user.region',
                    'user.divisi',
                    'user.cluster',
                    'user.role',
                ])->whereHas('user', function ($query) {
                    $query->where('divisi_id', '11');
                })
                    ->whereDate('tanggal_visit', $request->date ? date('Y-m-d', strtotime($request->date))  : date('Y-m-d'))
                    ->latest()
                    ->get();

                // VisitNoo removed
            }
            #ASM || RKAM
            else if ($user->role_id == 1 || $user->role_id == 9) {
                $visit = Visit::with([
                    'outlet.badanusaha',
                    'outlet.region',
                    'outlet.divisi',
                    'outlet.cluster',
                    'user.badanusaha',
                    'user.region',
                    'user.divisi',
                    'user.cluster',
                    'user.role',
                ])->whereHas('user', function ($query) {
                    $query->where('tm_id', Auth::user()->id);
                })
                    ->whereDate('tanggal_visit', $request->date ? date('Y-m-d', strtotime($request->date))  : date('Y-m-d'))
                    ->latest()
                    ->get();

                // VisitNoo removed
            }
            #COO
            else if ($user->role_id == 6) {
                $visit = Visit::with([
                    'outlet.badanusaha',
                    'outlet.region',
                    'outlet.divisi',
                    'outlet.cluster',
                    'user.badanusaha',
                    'user.region',
                    'user.divisi',
                    'user.cluster',
                    'user.role',
                ])->whereDate('tanggal_visit', $request->date ? date('Y-m-d', strtotime($request->date))  : date('Y-m-d'))
                    ->latest()
                    ->get();

                // VisitNoo removed
            }
            #CSO
            else if ($user->role_id == 8) {
                $visit = Visit::with([
                    'outlet.badanusaha',
                    'outlet.region',
                    'outlet.divisi',
                    'outlet.cluster',
                    'user.badanusaha',
                    'user.region',
                    'user.divisi',
                    'user.cluster',
                    'user.role',
                ])->whereHas('outlet', function ($query) {
                    $query->where('divisi_id', 4);
                })
                    ->whereDate('tanggal_visit', $request->date ? date('Y-m-d', strtotime($request->date))  : date('Y-m-d'))
                    ->latest()
                    ->get();

                // VisitNoo removed
            }
            #CSO FAST EV
            else if ($user->role_id == 11) {
                $visit = Visit::with([
                    'outlet.badanusaha',
                    'outlet.region',
                    'outlet.divisi',
                    'outlet.cluster',
                    'user.badanusaha',
                    'user.region',
                    'user.divisi',
                    'user.cluster',
                    'user.role',
                ])->whereHas('outlet', function ($query) {
                    $query->where('divisi_id', 7);
                })
                    ->whereDate('tanggal_visit', $request->date ? date('Y-m-d', strtotime($request->date))  : date('Y-m-d'))
                    ->latest()
                    ->get();

                // VisitNoo removed
            } else {
                $visit = Visit::with([
                    'outlet.badanusaha',
                    'outlet.region',
                    'outlet.divisi',
                    'outlet.cluster',
                    'user.badanusaha',
                    'user.region',
                    'user.divisi',
                    'user.cluster',
                    'user.role',
                ])->whereHas('user', function ($query) {
                    $query->where('region_id', Auth::user()->region_id);
                })
                    ->whereDate('tanggal_visit', $request->date ? date('Y-m-d', strtotime($request->date))  : date('Y-m-d'))
                    ->latest()
                    ->get();

                // VisitNoo removed
            }

            return ResponseFormatter::success(
                $visit->map->formatForAPI(),
                'fetch monitoring visit success'
            );
        } catch (Exception $err) {
            return ResponseFormatter::error([
                'message' => $err->getMessage(),
            ], $err->getMessage(), 500);
        }
    }

    /**
     * Visit - Fetch data visit ✅
     */
    public function fetch(Request $request)
    {
        try {
            $visit = Visit::with([
                'outlet.badanusaha',
                'outlet.region',
                'outlet.divisi',
                'outlet.cluster',
                'user.badanusaha',
                'user.region',
                'user.divisi',
                'user.cluster',
                'user.role'
            ])
                ->where('user_id', Auth::user()->id)
                ->whereDate('tanggal_visit', date('Y-m-d'))
                ->latest()
                ->get();

            return ResponseFormatter::success(
                $visit->map->formatForAPI(),
                'fetch visit succes'
            );
        } catch (Exception $err) {
            return ResponseFormatter::error([
                'message' => $err,
            ], $err, 500);
        }
    }
    public function check(Request $request)
    {
        $request->validate([
            'kode_outlet' => ['required'],
        ]);

        ##cek database terkahir dari tanggal sekarang dan user tersebut
        $lastDataVisit = Visit::whereDate('tanggal_visit', date('Y-m-d'))->where('user_id', Auth::user()->id)->latest()->first();
        ##cek data terkahir durasi

        ##cek table dengan outlet yang dikirim dan tanggal hari ini
        // $lastDataSelectedOutletVisit = Visit::with(['outlet.cluster','outlet.user.cluster'])->whereDate('tanggal_visit',date('Y-m-d'))->where('user_id',Auth::user()->id)->where('outlet_id',$outletId)->first();

        ##kalo mode ci
        if ($request->check_in) {
            //cek apa ada data terakhir kosong ?
            if ($lastDataVisit) {
                // if($lastDataSelectedOutletVisit)
                // {
                //     return ResponseFormatter::error(null,'anda sudah check in hari ini di outlet '. $lastDataSelectedOutletVisit->outlet->kode_outlet);
                // }
                // else
                if ($lastDataVisit->check_out_time) {
                    return ResponseFormatter::success(null, 'ok');
                } else {
                    //notif error
                    return ResponseFormatter::error([
                        'message' => 'error'
                    ], "Belum check out dari outlet " . $lastDataVisit->outlet->kode_outlet, 400);
                }
            } else {
                return ResponseFormatter::success(null, 'ok');
            }
        }
        ##disini co
        else {
            // $outlet = Outlet::where('kode_outlet', $request->kode_outlet)->first();
            // $outletId = $outlet->id;
            // if ($lastDataVisit) {
            //     if ($lastDataVisit->durasi_visit) {
            //         $isExistingLastDurasi = true;
            //     }
            //     $isExistingLastDurasi = false;
            // } else {
            //     $isExistingLastDurasi = false;
            // }
            // #kalau belum ada durasi maka bernilai true
            // if (!$isExistingLastDurasi) {
            //     // ##kalau ada histori outlet tersebut di hari ini maka bernilai true
            //     // if($lastDataSelectedOutletVisit)
            //     // {
            //     //     ##kalau outlet tersebut sudah ada durasi visit bernilai true
            //     //     if($lastDataSelectedOutletVisit->durasi_visit)
            //     //     {
            //     //         return ResponseFormatter::error([
            //     //                 'data' => 'anda sudah checkout'
            //     //                 ],'anda hari ini sudah check out di outlet '.$lastDataSelectedOutletVisit->outlet->kode_outlet,400);
            //     //     }
            //     // }
            //     ##cek data last visit hari ini bernilai true jika ada
            //     // else
            //     if ($lastDataVisit) {
            //         ##cek dari data terkahir visit apakah ada durasi visit dan sama outlet id nya dengan yang dikirim jika keduanya salah maka bernilai true
            //         if ($lastDataVisit->durasi_visit == null && $lastDataVisit->outlet_id != $outletId) {
            //             return ResponseFormatter::error([
            //                 'message' => 'error'
            //             ], "Belum check out dari outlet " . $lastDataVisit->outlet->kode_outlet, 400);
            //         } else {
            //             if ($lastDataVisit->outlet_id == $outletId) {
            //                 return ResponseFormatter::success(null, 'ok');
            //             }
            //             ##jika belum ci dimanapun
            //             return  ResponseFormatter::error([
            //                 'data' => 'anda belum checkin'
            //             ], 'anda belum check in di outlet manapun ', 400);
            //         }
            //     }
            // } else {
            //     ##jika belum ci dimanapun
            //     return  ResponseFormatter::error([
            //         'data' => 'anda belum checkin'
            //     ], 'anda belum check in di outlet manapun ', 400);
            // }
            return ResponseFormatter::success(null, 'ok');
        }
    }

    public function submit(Request $request)
    {
        try {
            $checkIn = $request->latlong_in;
            $checkOut = $request->latlong_out;
            if ($checkIn) {
                $user = Auth::user();
                if ($user->role->name === 'DSF/DM' || $user->role->name === 'ASC') {
                    $outlet = Outlet::where('kode_outlet', $request->kode_outlet)
                                    ->where('divisi_id', $user->divisi_id)
                                    ->first();
                } else {
                    $outlet = Outlet::where('kode_outlet', $request->kode_outlet)->first();
                }
                $outletId = $outlet->id;
                $request->validate([
                    'kode_outlet' => ['required'],
                    'picture_visit' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
                    'latlong_in' => ['required', 'string'],
                    'tipe_visit' => ['required'],
                ]);
                if (!$request->hasFile('picture_visit') || !$request->file('picture_visit')->isValid()) {
                    return ResponseFormatter::error('File gambar tidak valid', 'INVALID_FILE', 422);
                }
                $ext = $request->file('picture_visit')->guessExtension() ?: $request->file('picture_visit')->extension();
                $imageName = date('Y-m-d') . '-' . Auth::user()->username . '-' . 'IN-' . Carbon::now()->getPreciseTimestamp(3)  . '.' . $ext;
                $path = Storage::disk('public')->putFileAs('visits/in', $request->file('picture_visit'), $imageName);
                $visit = Visit::create([
                    'tanggal_visit' => date('Y-m-d'),
                    'user_id' => Auth::user()->id,
                    'outlet_id' => $outletId,
                    'tipe_visit' => $request->tipe_visit,
                    'latlong_in' => $request->latlong_in,
                    'check_in_time' => Carbon::now(),
                    'picture_visit_in' => $path,
                ]);
                return ResponseFormatter::success([
                    'visit' => $visit
                ], 'berhasil check in');
            }
            if ($checkOut) {
                $lastDataVisit = Visit::whereDate('tanggal_visit', date('Y-m-d'))->where('user_id', Auth::user()->id)->latest()->first();
                if ($lastDataVisit != null) {
                    $request->validate([
                        'latlong_out' => ['required'],
                        'laporan_visit' => ['required'],
                        'picture_visit' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png', 'max:5120'],
                        'transaksi' => ['required'],
                    ]);
                    if (!$request->hasFile('picture_visit') || !$request->file('picture_visit')->isValid()) {
                        return ResponseFormatter::error('File gambar tidak valid', 'INVALID_FILE', 422);
                    }
                    $awal = Carbon::parse($lastDataVisit->check_in_time);
                    $akhir = Carbon::now();
                    $durasi = $awal->diffInMinutes($akhir);
                    $ext = $request->file('picture_visit')->guessExtension() ?: $request->file('picture_visit')->extension();
                    $imageName = date('Y-m-d') . '-' . Auth::user()->username . '-' . 'OUT-' . Carbon::now()->getPreciseTimestamp(3) . '.' . $ext;
                    $path = Storage::disk('public')->putFileAs('visits/out', $request->file('picture_visit'), $imageName);
                    $data = [
                        'tanggal_visit' => date('Y-m-d'),
                        'latlong_out' => $request->latlong_out,
                        'check_out_time' => now(),
                        'laporan_visit' => $request->laporan_visit,
                        'durasi_visit' => $durasi,
                        'picture_visit_out' => $path,
                        'transaksi' => $request->transaksi,
                    ];
                    $lastDataVisit->update($data);
                    return ResponseFormatter::success([
                        'visit' => $data
                    ], 'berhasil check out');
                } else {
                    return ResponseFormatter::error('Belum ada check-in untuk di check-out', 'INVALID_STATE', 422);
                }
            }
        } catch (Exception $error) {
            return ResponseFormatter::error([
                'error' => $error
            ], 'error', 500);
        }
    }

    // submitNoo removed
}
