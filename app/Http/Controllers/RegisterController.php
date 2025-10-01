<?php

namespace App\Http\Controllers;

use App\Exports\NooExport;
use App\Models\Division;
use App\Models\Noo;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class NooController extends Controller
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
            $noos = Noo::whereBetween('created_at', [$date1, $date2])
                ->where('divisi_id', $divisi)
                ->get();

            $this->deleteBulk($noos);
        }

        // Cek apakah ada inputan dari daterangesearch
        if ($request->daterangesearch) {
            $data = explode('-', preg_replace('/\s+/', '', $request->daterangesearch));
            $date1 = Carbon::parse($data[0])->format('Y-m-d');
            $date2 = Carbon::parse($data[1])->format('Y-m-d');
            $date2 = date('Y-m-d', strtotime('+ 1 day', strtotime($date2)));
            $noos = Noo::with(['badanusaha', 'cluster', 'region', 'divisi'])
                ->whereBetween('created_at', [$date1, $date2])
                ->orderBy('created_at')
                ->simplePaginate(100);
        } else {
            $noos = Noo::with(['badanusaha', 'cluster', 'region', 'divisi'])->latest()->filter()->simplePaginate(100);
        }

        return view('noo.index', [
            'noos' => $noos,
            'title' => 'NOO',
            'active' => 'noo',
            'divisis' => Division::all()->except(5),
        ]);
    }

    public function show(Request $request, $id)
    {
        $noo = Noo::findOrFail($id);

        return view('noo.edit', [
            'noo' => $noo,
            'title' => 'NOO',
            'active' => 'noo',
        ]);
    }

    public function update(Request $request, $id)
    {
        try {
            $noo = Noo::findOrFail($id);
            if ($request->status == 'PENDING') {
                $noo['status'] = $request->status;
                $noo['confirmed_by'] = null;
                $noo['confirmed_at'] = null;
                $noo['rejected_at'] = null;
                $noo['rejected_by'] = null;
                $noo['limit'] = null;
            } else {
                $noo['status'] = $request->status;
                $noo['rejected_at'] = null;
                $noo['rejected_by'] = null;
            }
            $noo['keterangan'] = null;
            $noo->save();

            return redirect('noo')->with(['success' => 'berhasil edit status noo']);
        } catch (Exception $e) {
            return redirect('noo')->with(['error' => $e->getMessage()]);
        }
    }

    public function export()
    {
        return Excel::download(new NooExport, 'noo.xlsx');
    }

    private function checkAssets($path)
    {
        if ($path == null) {
            return false;
        }

        return file_exists(storage_path('app/public/'.$path));
    }

    private function deleteAssets($noos)
    {
        if ($this->checkAssets($noos->poto_shop_sign)) {
            unlink(storage_path('app/public/'.$noos->poto_shop_sign));
        }

        if ($this->checkAssets($noos->poto_depan)) {
            unlink(storage_path('app/public/'.$noos->poto_depan));
        }

        if ($this->checkAssets($noos->poto_kanan)) {
            unlink(storage_path('app/public/'.$noos->poto_kanan));
        }

        if ($this->checkAssets($noos->poto_kiri)) {
            unlink(storage_path('app/public/'.$noos->poto_kiri));
        }

        if ($this->checkAssets($noos->poto_ktp)) {
            unlink(storage_path('app/public/'.$noos->poto_ktp));
        }

        if ($this->checkAssets($noos->video)) {
            unlink(storage_path('app/public/'.$noos->video));
        }
    }

    public function deleteBulk($noos)
    {
        try {
            foreach ($noos as $item) {
                $this->deleteAssets($item);
                $item->forceDelete($item);
            }

            return redirect('noo')->with(['success' => 'berhasil hapus media Noo secara bulk']);

        } catch (Exception $e) {
            return redirect('noo')->with(['error' => $e->getMessage()]);
        }
    }
}
