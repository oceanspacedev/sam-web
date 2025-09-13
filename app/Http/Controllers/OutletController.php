<?php

namespace App\Http\Controllers;

use App\Exports\OutletExport;
use App\Exports\TemplateOutletExport;
use App\Imports\OutletImport;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Outlet;
use App\Models\Region;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class OutletController extends Controller
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
            $outlet = Outlet::whereBetween('created_at', [$date1, $date2])
                ->where('divisi_id', $divisi)
                ->withTrashed()
                ->get();

            $this->deleteBulk($outlet);
        }

        $outlets = Outlet::with(['badanusaha', 'cluster', 'region', 'divisi']);
        $users = User::with(['tm'])->get();

        return view('outlet.index', [
            'outlets' => $outlets->filter()->orderBy('kode_outlet')->simplePaginate(100),
            'title' => 'Outlet',
            'users' => $users,
            'active' => 'outlet',
            'divisis' => Division::all()->except(5),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function create()
    {
        //
    }

    /**
     * Store a newly created resource in storage.
     *
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        //
    }

    /**
     * Display the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function show($id)
    {
        $outlet = Outlet::find($id);

        return view('outlet.show', [
            'outlet' => $outlet,
            'title' => 'Detail',
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function edit($id)
    {
        $outlet = Outlet::findOrFail($id);
        $badanusahas = BadanUsaha::all();
        $divisis = Division::all();
        $regions = Region::orderBy('name')->get();
        $clusters = Cluster::orderBy('name')->get();

        return view('outlet.edit', [
            'title' => 'Outlet',
            'active' => 'outlet',
            'outlet' => $outlet,
            'badanusahas' => $badanusahas,
            'divisis' => $divisis,
            'regions' => $regions,
            'clusters' => $clusters,
        ]);
    }

    /**
     * Update the specified resource in storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function update(Request $request, $id)
    {

        try {
            $outlet = Outlet::findOrFail($id);
            $request->validate([
                'kode_outlet' => ['required', 'string', 'max:255', 'unique:outlets,kode_outlet,'.$outlet->id],
                'nama_outlet' => ['required', 'string'],
                'alamat_outlet' => ['required', 'string'],
                'radius' => ['required'],
                'status_outlet' => ['required'],
                'limit' => ['required'],
                'badanusaha_id' => ['required'],
                'divisi_id' => ['required'],
                'region_id' => ['required'],
                'cluster_id' => ['required'],
            ]);
            $data = $request->all();
            $data['kode_outlet'] = strtoupper($request->kode_outlet);
            $data['nama_outlet'] = strtoupper($request->nama_outlet);
            $data['nama_pemilik_outlet'] = strtoupper($request->nama_pemilik_outlet);
            $data['alamat_outlet'] = strtoupper($request->alamat_outlet);
            $outlet->update($data);

            return redirect('outlet')->with(['success' => 'berhasil edit outlet']);
        } catch (Exception $e) {
            error_log($e);

            return redirect('outlet')->with(['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     *
     * @param  int  $id
     * @return \Illuminate\Http\Response
     */
    public function destroyall()
    {
        try {
            $outlets = Outlet::where('divisi_id', 4)->get();
            foreach ($outlets as $outlet) {
                $outlet->delete();
            }

            return 'berhasil';
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    public function export()
    {
        return Excel::download(new OutletExport, 'outlet.xlsx');
    }

    public function import(Request $request)
    {
        try {
            $file = $request->file('file');
            $namaFile = $file->getClientOriginalName();
            $file->move('import', $namaFile);

            Excel::import(new OutletImport, public_path('/import/'.$namaFile));

            return redirect('outlet')->with(['success' => 'berhasil import outlet']);
        } catch (Exception $e) {
            return redirect('outlet')->with(['error' => $e->getMessage()]);
        }

    }

    public function template()
    {
        return Excel::download(new TemplateOutletExport, 'outlet_template.xlsx');
    }

    private function checkAssets($path)
    {
        if ($path == null) {
            return false;
        }

        return file_exists(storage_path('app/public/'.$path));
    }

    private function deleteAssets($outlet)
    {
        if ($this->checkAssets($outlet->poto_shop_sign)) {
            unlink(storage_path('app/public/'.$outlet->poto_shop_sign));
        }

        if ($this->checkAssets($outlet->poto_depan)) {
            unlink(storage_path('app/public/'.$outlet->poto_depan));
        }

        if ($this->checkAssets($outlet->poto_kanan)) {
            unlink(storage_path('app/public/'.$outlet->poto_kanan));
        }

        if ($this->checkAssets($outlet->poto_kiri)) {
            unlink(storage_path('app/public/'.$outlet->poto_kiri));
        }

        if ($this->checkAssets($outlet->poto_ktp)) {
            unlink(storage_path('app/public/'.$outlet->poto_ktp));
        }

        if ($this->checkAssets($outlet->video)) {
            unlink(storage_path('app/public/'.$outlet->video));
        }
    }

    public function delete(Request $request, $id)
    {
        try {
            $outlet = Outlet::find($id);

            $this->deleteAssets($outlet);

            $outlet->forceDelete();

            return redirect('outlet')->with(['success' => 'berhasil hapus media outlet']);

        } catch (Exception $e) {
            return redirect('outlet')->with(['error' => $e->getMessage()]);
        }
    }

    public function deleteBulk($outlet)
    {
        try {
            foreach ($outlet as $item) {
                $this->deleteAssets($item);
                $item->forceDelete($item);
            }

            return redirect('outlet')->with(['success' => 'berhasil hapus media outlet secara bulk']);

        } catch (Exception $e) {
            return redirect('outlet')->with(['error' => $e->getMessage()]);
        }
    }
}
