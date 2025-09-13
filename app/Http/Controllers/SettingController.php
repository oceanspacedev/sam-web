<?php

namespace App\Http\Controllers;

use App\Helpers\ResponseFormatter;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\Role;
use Exception;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    // ROLE
    public function role(Request $request)
    {
        $roles = Role::orderBy('name')->get();
        $badanusahas = BadanUsaha::orderBy('name')->get();
        $clusters = Cluster::orderBy('name')->get();
        $divisis = Division::orderBy('name')->get();
        $regions = Region::orderBy('name')->get();

        return view('settings.index', [
            'title' => 'Role',
            'active' => 'setting',
            'roles' => $roles,
            'badanusahas' => $badanusahas,
            'clusters' => $clusters,
            'divisis' => $divisis,
            'regions' => $regions,
        ]);
    }

    public function roleedit(Request $request, $id)
    {
        $role = Role::findOrFail($id);

        return view('settings.edit', [
            'title' => 'Role',
            'active' => 'setting',
            'role' => $role,

        ]);
    }

    public function roleadd(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required',
            ]);
            Role::create([
                'name' => preg_replace('/\s+/', '', strtoupper($request->name)),
            ]);

            return redirect('setting/role')->with(['success' => 'Berhasil menambahkan role']);
        } catch (Exception $e) {
            return redirect('setting/role')->with(['error' => 'Gagal menambahkan role,'.$e->getMessage()]);
        }
    }

    public function roleupdate(Request $request, $id)
    {
        try {
            $request->validate([
                'name' => 'required',
            ]);

            $role = Role::findOrFail($id);
            $role->update([
                'name' => preg_replace('/\s+/', '', strtoupper($request->name)),
            ]);

            return redirect('setting/role')->with(['success' => 'Berhasil update role']);
        } catch (Exception $e) {
            return redirect('setting/role')->with(['error' => 'Gagal update role,'.$e->getMessage()]);
        }
    }

    // BADAN USAHA
    public function badanusaha(Request $request)
    {
        $badanusahas = BadanUsaha::orderBy('name')->get();
        $clusters = Cluster::orderBy('name')->get();
        $divisis = Division::orderBy('name')->get();
        $regions = Region::orderBy('name')->get();

        return view('settings.index', [
            'title' => 'Badan Usaha',
            'active' => 'setting',
            'badanusahas' => $badanusahas,
            'clusters' => $clusters,
            'divisis' => $divisis,
            'regions' => $regions,
        ]);
    }

    public function buedit(Request $request, $id)
    {
        $badanusaha = BadanUsaha::findOrFail($id);

        return view('settings.edit', [
            'title' => 'Badan Usaha',
            'active' => 'setting',
            'badanusaha' => $badanusaha,

        ]);
    }

    public function buadd(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required',
            ]);

            BadanUsaha::create([
                'name' => preg_replace('/\s+/', '', strtoupper($request->name)),
            ]);

            return redirect('/setting/badanusaha')->with(['success' => 'Berhasil menambahkan badan usaha baru']);
        } catch (Exception $e) {
            return redirect('/setting/badanusaha')->with(['error' => 'Gagal menambahkan badan usaha baru,'.$e->getMessage()]);
        }
    }

    public function buupdate(Request $request, $id)
    {
        try {
            $request->validate([
                'name' => 'required',
            ]);

            $badanusaha = BadanUsaha::findOrFail($id);
            $badanusaha->update([
                'name' => preg_replace('/\s+/', '', strtoupper($request->name)),
            ]);

            return redirect('/setting/badanusaha')->with(['success' => 'Berhasil update badan usaha']);
        } catch (Exception $e) {
            return redirect('/setting/badanusaha')->with(['error' => 'Gagal update badan usaha,'.$e->getMessage()]);
        }
    }

    // DIVISI
    public function divisi(Request $request)
    {
        $clusters = Cluster::orderBy('name')->get();
        $badanusahas = BadanUsaha::orderBy('name')->get();
        $divisis = Division::with(['badanusaha'])->orderBy('name')->get();
        $regions = Region::orderBy('name')->get();

        return view('settings.index', [
            'title' => 'Divisi',
            'active' => 'setting',
            'divisis' => $divisis,
            'badanusahas' => $badanusahas,
            'clusters' => $clusters,
            'regions' => $regions,

        ]);
    }

    public function divadd(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required',
                'badanusaha_id' => 'required',
            ]);

            Division::create([
                'name' => preg_replace('/\s+/', '', strtoupper($request->name)),
                'badanusaha_id' => $request->badanusaha_id,
            ]);

            return redirect('/setting/divisi')->with(['success' => 'Berhasil menambahkan divisi baru']);
        } catch (Exception $e) {
            return redirect('/setting/divisi')->with(['error' => 'Gagal menambahkan divisi baru,'.$e->getMessage()]);
        }
    }

    public function divedit(Request $request, $id)
    {
        $divisi = Division::findOrFail($id);

        return view('settings.edit', [
            'title' => 'Divisi',
            'active' => 'setting',
            'divisi' => $divisi,

        ]);
    }

    public function divupdate(Request $request, $id)
    {
        try {
            $request->validate([
                'name' => 'required',
            ]);

            $divisi = Division::findOrFail($id);
            $divisi->update([
                'name' => preg_replace('/\s+/', '', strtoupper($request->name)),
            ]);

            return redirect('/setting/divisi')->with(['success' => 'Berhasil update divisi']);
        } catch (Exception $e) {
            return redirect('/setting/divisi')->with(['error' => 'Gagal update divisi,'.$e->getMessage()]);
        }
    }

    // REGION
    public function region(Request $request)
    {
        $clusters = Cluster::orderBy('name')->get();
        $badanusahas = BadanUsaha::orderBy('name')->get();
        $divisis = Division::orderBy('name')->get();
        $regions = Region::with(['badanusaha', 'divisi'])->orderBy('name')->get();

        return view('settings.index', [
            'title' => 'Region',
            'active' => 'setting',
            'regions' => $regions,
            'badanusahas' => $badanusahas,
            'divisis' => $divisis,
            'clusters' => $clusters,
        ]);
    }

    public function regedit(Request $request, $id)
    {
        $region = Region::findOrFail($id);

        return view('settings.edit', [
            'title' => 'Region',
            'active' => 'setting',
            'region' => $region,

        ]);
    }

    public function regadd(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required',
                'badanusaha_id' => 'required',
                'divisi_id' => 'required',
            ]);

            Region::create([
                'name' => preg_replace('/\s+/', '', strtoupper($request->name)),
                'badanusaha_id' => $request->badanusaha_id,
                'divisi_id' => $request->divisi_id,
            ]);

            return redirect('/setting/region')->with(['success' => 'Berhasil menambahkan region baru']);
        } catch (Exception $e) {
            return redirect('/setting/region')->with(['error' => 'Gagal menambahkan region baru,'.$e->getMessage()]);
        }
    }

    public function regupdate(Request $request, $id)
    {
        try {
            $request->validate([
                'name' => 'required',
            ]);

            $region = Region::findOrFail($id);
            $region->update([
                'name' => preg_replace('/\s+/', '', strtoupper($request->name)),
            ]);

            return redirect('/setting/region')->with(['success' => 'Berhasil update region']);
        } catch (Exception $e) {
            return redirect('/setting/region')->with(['error' => 'Gagal update region,'.$e->getMessage()]);
        }
    }

    // CLUSTER
    public function cluster(Request $request)
    {
        $clusters = Cluster::with(['region', 'divisi', 'badanusaha'])
            ->orderBy('name')->get();
        $badanusahas = BadanUsaha::orderBy('name')->get();
        $divisis = Division::orderBy('name')->get();
        $regions = Region::orderBy('name')->get();

        return view('settings.index', [
            'title' => 'Cluster',
            'active' => 'setting',
            'clusters' => $clusters,
            'badanusahas' => $badanusahas,
            'divisis' => $divisis,
            'regions' => $regions,
        ]);
    }

    public function clusedit(Request $request, $id)
    {
        $cluster = Cluster::findOrFail($id);

        return view('settings.edit', [
            'title' => 'Cluster',
            'active' => 'setting',
            'cluster' => $cluster,
        ]);
    }

    public function clusadd(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required',
                'badanusaha_id' => 'required',
                'divisi_id' => 'required',
                'region_id' => 'required',
            ]);

            Cluster::create([
                'name' => preg_replace('/\s+/', '', strtoupper($request->name)),
                'badanusaha_id' => $request->badanusaha_id,
                'divisi_id' => $request->divisi_id,
                'region_id' => $request->region_id,
            ]);

            return redirect('/setting/cluster')->with(['success' => 'Berhasil menambahkan cluster baru']);
        } catch (Exception $e) {
            return redirect('/setting/cluster')->with(['error' => 'Gagal menambahkan cluster baru,'.$e->getMessage()]);
        }
    }

    public function clusupdate(Request $request, $id)
    {
        try {
            $request->validate([
                'name' => 'required',
            ]);

            $cluster = Cluster::findOrFail($id);
            $cluster->update([
                'name' => preg_replace('/\s+/', '', strtoupper($request->name)),
            ]);

            return redirect('/setting/cluster')->with(['success' => 'Berhasil update cluster']);
        } catch (Exception $e) {
            return redirect('/setting/cluster')->with(['error' => 'Gagal update cluster,'.$e->getMessage()]);
        }
    }

    public function getdivisi(Request $request)
    {
        $divisis = Division::all();

        return ResponseFormatter::success($divisis, 'berhasil');
    }

    public function getregion(Request $request)
    {
        $divisi_id = Division::where('name', $request->divisi)->first()->id;
        $regions = Region::where('divisi_id', $divisi_id)->get();

        return ResponseFormatter::success($regions, 'berhasil');
    }

    public function download(Request $request)
    {
        return response()->download(public_path('/storage/apk/SAM.apk'), 'SAM.apk', [
            'Content-Type' => 'application/vnd.android.package-archive',
            'Content-Disposition' => 'attachment; filename="android.apk"',
        ]);
    }
}
