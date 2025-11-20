<?php

namespace App\Http\Controllers;

use App\Exports\UserExport;
use App\Exports\UserTempateExport;
use App\Imports\UserImport;
use App\Models\BadanUsaha;
use App\Models\Cluster;
use App\Models\Division;
use App\Models\Region;
use App\Models\Role;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use Maatwebsite\Excel\Facades\Excel;

class UserController extends Controller
{
    public function index()
    {
        $users = User::with(['role', 'badanUsahas', 'divisis', 'regions', 'clusters'])->orderBy('nama_lengkap')->filter()->get();

        return view('user.index', [
            'users' => $users,
            'title' => 'User',
            'active' => 'user',
        ]);
    }

    public function edit($id)
    {
        $user = User::with(['role', 'regions', 'clusters', 'divisis', 'badanUsahas'])->findOrFail($id);
        $roles = Role::all();
        $badanusahas = BadanUsaha::all();
        $divisis = Division::with(['badanusaha'])->get();
        $regions = Region::with(['badanusaha', 'divisi'])->get();
        $clusters = Cluster::with(['badanusaha', 'divisi', 'region'])->get();

        return view('user.edit', [
            'user' => $user,
            'title' => 'User',
            'active' => 'user',
            'roles' => $roles,
            'badanusahas' => $badanusahas,
            'divisis' => $divisis,
            'regions' => $regions,
            'clusters' => $clusters,
        ]);
    }

    public function update(Request $request, $id)
    {
        try {
            $user = User::findOrFail($id);
            $request->validate([
                'username' => ['required', 'string', 'max:255', 'unique:users,username,'.$user->id],
                'nama_lengkap' => ['required', 'string'],
                'role_id' => ['required'],
                'badanusaha_ids' => ['array'],
                'divisi_ids' => ['array'],
                'region_ids' => ['array'],
                'cluster_ids' => ['array'],
                // password is optional in update usually, but here it was required. keeping it required if that was intent, or maybe nullable?
                // The original code had 'password' => ['required']. I'll keep it but maybe check if filled?
                // Actually original code re-hashed password every time.
                'password' => ['nullable'],
            ]);

            $data = $request->except(['badanusaha_ids', 'divisi_ids', 'region_ids', 'cluster_ids', 'password']);
            if ($request->filled('password')) {
                $data['password'] = bcrypt($request->password);
            }
            $data['nama_lengkap'] = strtoupper($request->nama_lengkap);

            $user->update($data);

            if ($request->has('badanusaha_ids')) {
                $user->badanUsahas()->sync($request->badanusaha_ids);
            }
            if ($request->has('divisi_ids')) {
                $user->divisis()->sync($request->divisi_ids);
            }
            if ($request->has('region_ids')) {
                $user->regions()->sync($request->region_ids);
            }
            if ($request->has('cluster_ids')) {
                $user->clusters()->sync($request->cluster_ids);
            }

            return redirect('user')->with(['success' => 'berhasil edit user']);
        } catch (Exception $e) {
            error_log($e);

            return redirect('user')->with(['error' => $e->getMessage()]);
        }
    }

    public function export()
    {
        return Excel::download(new UserExport, 'user.xlsx');
    }

    public function import(Request $request)
    {
        $file = $request->file('file');
        $namaFile = $file->getClientOriginalName();
        $file->move(public_path('import'), $namaFile);
        Excel::import(new UserImport, public_path('/import/'.$namaFile));

        return redirect('user')->with(['success' => 'berhasil import user']);
    }

    public function template()
    {
        return Excel::download(new UserTempateExport, 'user_template.xlsx');
    }

    public function destroyall()
    {
        try {
            // Assuming we want to delete users associated with division 1 (Realme?)
            // Using whereHas for many-to-many
            $users = User::whereHas('divisis', function ($q) {
                $q->where('id', 1);
            })->get();

            foreach ($users as $user) {
                $token = PersonalAccessToken::where('tokenable_id', $user->id)->get();
                if ($token) {
                    foreach ($token as $tkn) {
                        $tkn->forceDelete();
                    }
                }
                $user->forceDelete();
            }

            return 'berhasil';
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }
}
