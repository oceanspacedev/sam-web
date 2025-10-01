<?php

namespace App\Http\Controllers;

use App\Exports\RegisterExport;
use App\Models\Division;
use App\Models\Register;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class RegisterController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index(Request $request)
    {
        if ($request->bulkDelete) {
            $data = explode('-', preg_replace('/\s+/', '', $request->bulkDelete));
            $date1 = Carbon::parse($data[0])->format('Y-m-d');
            $date2 = Carbon::parse($data[1])->addDay(1)->format('Y-m-d');
            $divisi = $request->divisi_id;
            $registers = Register::whereBetween('created_at', [$date1, $date2])
                ->where('divisi_id', $divisi)
                ->get();

            $this->deleteBulk($registers);
        }

        // Cek apakah ada inputan dari daterangesearch
        if ($request->daterangesearch) {
            $data = explode('-', preg_replace('/\s+/', '', $request->daterangesearch));
            $date1 = Carbon::parse($data[0])->format('Y-m-d');
            $date2 = Carbon::parse($data[1])->format('Y-m-d');
            $date2 = date('Y-m-d', strtotime('+ 1 day', strtotime($date2)));
            $registers = Register::with(['badanusaha', 'cluster', 'region', 'divisi'])
                ->whereBetween('created_at', [$date1, $date2])
                ->orderBy('created_at')
                ->simplePaginate(100);
        } else {
            $registers = Register::with(['badanusaha', 'cluster', 'region', 'divisi'])->latest()->filter()->simplePaginate(100);
        }

        return view('register.index', [
            'registers' => $registers,
            'title' => 'Register',
            'active' => 'register',
            'divisis' => Division::all()->except(5),
        ]);
    }

    public function show(Request $request, $id)
    {
        $register = Register::findOrFail($id);

        return view('register.edit', [
            'register' => $register,
            'title' => 'Register',
            'active' => 'register',
        ]);
    }

    public function update(Request $request, $id)
    {
        try {
            $register = Register::findOrFail($id);
            if ($request->status == 'PENDING') {
                $register['status'] = $request->status;
                $register['confirmed_by'] = null;
                $register['confirmed_at'] = null;
                $register['rejected_at'] = null;
                $register['rejected_by'] = null;
                $register['limit'] = null;
            } else {
                $register['status'] = $request->status;
                $register['rejected_at'] = null;
                $register['rejected_by'] = null;
            }
            $register['keterangan'] = null;
            $register->save();

            return redirect('register')->with(['success' => 'berhasil mengubah status register']);
        } catch (Exception $e) {
            return redirect('register')->with(['error' => $e->getMessage()]);
        }
    }

    public function export()
    {
        return Excel::download(new RegisterExport, 'register.xlsx');
    }

    private function checkAssets($path)
    {
        if ($path == null) {
            return false;
        }

        return file_exists(storage_path('app/public/'.$path));
    }

    private function deleteAssets($register)
    {
        if ($this->checkAssets($register->poto_shop_sign)) {
            unlink(storage_path('app/public/'.$register->poto_shop_sign));
        }

        if ($this->checkAssets($register->poto_depan)) {
            unlink(storage_path('app/public/'.$register->poto_depan));
        }

        if ($this->checkAssets($register->poto_kanan)) {
            unlink(storage_path('app/public/'.$register->poto_kanan));
        }

        if ($this->checkAssets($register->poto_kiri)) {
            unlink(storage_path('app/public/'.$register->poto_kiri));
        }

        if ($this->checkAssets($register->poto_ktp)) {
            unlink(storage_path('app/public/'.$register->poto_ktp));
        }

        if ($this->checkAssets($register->video)) {
            unlink(storage_path('app/public/'.$register->video));
        }
    }

    public function deleteBulk($registers)
    {
        try {
            foreach ($registers as $item) {
                $this->deleteAssets($item);
                $item->forceDelete($item);
            }

            return redirect('register')->with(['success' => 'berhasil hapus media register secara bulk']);

        } catch (Exception $e) {
            return redirect('register')->with(['error' => $e->getMessage()]);
        }
    }
}
