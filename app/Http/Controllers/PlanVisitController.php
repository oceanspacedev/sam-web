<?php

namespace App\Http\Controllers;

use App\Exports\PlanVisitExport;
use App\Exports\TemplatePlanVisiExport;
use App\Imports\PlanVisitImport;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class PlanVisitController extends Controller
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

            $planvisit = PlanVisit::whereHas('outlet', function ($q) use ($divisi) {
                $q->where('divisi_id', $divisi)->withTrashed();
            })->whereBetween('tanggal_visit', [$date1, $date2])->get();

            $this->delete($planvisit);

            return redirect('planvisit')->with(['success' => 'berhasil hapus plan visit secara bulk']);
        }

        $outlets = Outlet::with('divisi')
            ->orderBy('nama_outlet')->get();

        $planVisits = PlanVisit::with(['user', 'outlet'])->orderBy('tanggal_visit', 'desc')->paginate(10);

        return view('planvisit.index', [
            'planVisits' => $planVisits,
            'title' => 'Plan Visit',
            'active' => 'planvisit',
            'divisis' => Division::all()->except(5),
            'users' => User::orderBy('nama_lengkap')->get(),
            'outlets' => $outlets,
        ]);
    }

    public function store(Request $request)
    {
        try {
            PlanVisit::create([
                'user_id' => $request->user_id,
                'outlet_id' => $request->outlet_id,
                'tanggal_visit' => $request->tanggal_visit,
            ]);

            return redirect('planvisit')->with(['success' => 'Berhasil menambahkan plan visit']);
        } catch (Exception $e) {
            return redirect('planvisit')->with(['error' => 'Gagal menambahkan plan visit,'.$e->getMessage()]);
        }
    }

    public function export(Request $request)
    {
        if ($request->tanggal1 && $request->tanggal2) {
            return Excel::download(new PlanVisitExport($request->tanggal1, $request->tanggal2), 'planvisit.xlsx');
        } else {
            // $planVisits = PlanVisit::with(['user','outlet'])->orderBy('tanggal_visit','desc')->paginate(10);
            // return view('planvisit.index',[
            //     'planVisits' => $planVisits,
            //     'title' => 'Plan Visit',
            //     'active' => 'planvisit',
            // ]);
            // $this->index();
            return redirect('planvisit')->with(['error']);
        }
    }

    public function import(Request $request)
    {
        try {
            $file = $request->file('file');
            $namaFile = $file->getClientOriginalName();
            $file->move('import', $namaFile);

            Excel::import(new PlanVisitImport, public_path('/import/'.$namaFile));

            return redirect('planvisit')->with(['success' => 'berhasil import plan visit']);
        } catch (Exception $e) {
            return redirect('planvisit')->with(['error' => $e->getMessage()]);
        }

    }

    public function template()
    {
        return Excel::download(new TemplatePlanVisiExport, 'plan_visit_template.xlsx');
    }

    public function delete($planvisit)
    {
        try {
            foreach ($planvisit as $item) {
                $item->forceDelete($item);
            }

            return redirect('planvisit')->with(['success' => 'berhasil hapus data plan visit secara bulk']);
        } catch (Exception $e) {
            return redirect('planvisit')->with(['error' => $e->getMessage()]);
        }
    }
}
