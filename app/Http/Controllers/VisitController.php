<?php

namespace App\Http\Controllers;

use App\Exports\VisitExport;
use App\Models\Division;
use App\Models\Visit;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class VisitController extends Controller
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
            $date2 = Carbon::parse($data[1])->format('Y-m-d');
            $divisi = $request->divisi_id;
            $visit = Visit::whereHas('outlet', function ($query) use ($divisi) {
                $query->where('divisi_id', $divisi)->withTrashed();
            })
                ->whereBetween('tanggal_visit', [$date1, $date2])
                ->get();

            $this->deleteBulk($visit);

            return redirect('visit')->with(['success' => 'berhasil hapus data visit secara bulk']);
        }

        $visits = Visit::with(['user', 'outlet.divisi', 'outlet.cluster', 'outlet.region'])->latest()->simplePaginate(100);

        return view('visit.index', [
            'visits' => $visits,
            'title' => 'Visit',
            'active' => 'visit',
            'divisis' => Division::all()->except(5),
        ]);
    }

    public function export(Request $request)
    {
        if ($request->tanggal1 && $request->tanggal2) {
            return Excel::download(new VisitExport($request->tanggal1, $request->tanggal2), 'visit.xlsx');
        } else {
            $visits = Visit::with(['user', 'outlet'])->get();

            return view('visit.index', [
                'visits' => $visits,
                'title' => 'Visit',
                'active' => 'visit',
            ]);
        }
    }

    private function checkAssets($path)
    {
        if ($path == null) {
            return false;
        }

        return file_exists(storage_path('app/public/'.$path));
    }

    private function deleteAssets($visit)
    {
        if ($this->checkAssets($visit->picture_visit_in)) {
            unlink(storage_path('app/public/'.$visit->picture_visit_in));
        }

        if ($this->checkAssets($visit->picture_visit_out)) {
            unlink(storage_path('app/public/'.$visit->picture_visit_out));
        }
    }

    public function deleteBulk($visit)
    {
        try {
            foreach ($visit as $item) {
                $this->deleteAssets($item);
                $item->forceDelete($item);
            }

            return redirect('visit')->with(['success' => 'berhasil hapus media visit secara bulk']);
        } catch (Exception $e) {
            return redirect('outlet')->with(['error' => $e->getMessage()]);
        }
    }
}
