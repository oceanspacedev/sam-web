<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LoginController extends Controller
{
    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\Response
     */
    public function index()
    {
        return view('login.index');
    }

    public function login(Request $request)
    {
        $credentials = request(['username', 'password']);
        if (! Auth::attempt($credentials)) {
            return redirect('/masuk');
        }

        return redirect('/dashboard');
    }
}
