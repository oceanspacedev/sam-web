<?php

namespace App\Http\Controllers;

use App\Models\Outlet;
use App\Models\PlanVisit;
use App\Models\Register;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;

class DashboardController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return Response
     */
    public function index()
    {
        return view('dashboard.index', [
            'user' => count(User::all()),
            'outlet' => count(Outlet::all()),
            'noo' => count(Register::all()),
            'visit' => count(Visit::all()),
            'planvisit' => count(PlanVisit::all()),
            'title' => 'DASHBOARD',
            'active' => 'dashboard',
        ]);
    }

    public function logout()
    {
        Auth::logout();

        return redirect('/masuk');
    }
}
