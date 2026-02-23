<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return redirect('admin');
});

Route::get('download/app', function () {
    return response()->download(public_path('/storage/apk/SAM.apk'), 'SAM.apk', [
        'Content-Type' => 'application/vnd.android.package-archive',
        'Content-Disposition' => 'attachment; filename="android.apk"',
    ]);
})->middleware('throttle:expensive');

Route::view('/privacy-policy', 'privacy-policy');
