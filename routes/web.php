<?php

use App\Filament\Auth\Pages\PhoneLogin;
use App\Http\Controllers\ArchivedStorageController;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
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

Route::get('storage-archive/{path}', ArchivedStorageController::class)
    ->where('path', '.*')
    ->middleware(['signed', 'throttle:expensive'])
    ->name('storage.archive.show');

Route::get('storage/{path}', ArchivedStorageController::class)
    ->where('path', '.*')
    ->name('storage.archive.public');

Route::view('/privacy-policy', 'privacy-policy');

Route::get('/phone-login', PhoneLogin::class)
    ->middleware([
        DisableBladeIconComponents::class,
        DispatchServingFilamentEvent::class,
    ])
    ->name('phone-login');
