<?php

namespace App\Http\Controllers\API;

use App\Actions\Fortify\PasswordValidationRules;
use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class UserController extends Controller
{
    use PasswordValidationRules;

    /**
     * User - Fetch profile ✅
     */
    public function fetch(Request $request)
    {
        $user = User::with(['cluster', 'region', 'role', 'divisi', 'badanusaha'])->where('id', Auth::user()->id)->first();

        return ResponseFormatter::success(['user' => $user->formatForAPI(), 'message' => 'Data profile user berhasil diambil']);
    }

    /**
     * User - Login ✅
     *
     * @unauthenticated
     */
    public function login(Request $request)
    {
        if ($request->version != '1.0.3') {
            return ResponseFormatter::error([
                'message' => 'Unauthorized',
            ], 'Gagal login, Update versi aplikasi SAM anda ke V1.0.3.', 500);
        }

        // Validate the request parameters
        $request->validate([
            'version' => 'required|string|in:1.0.3',
            'username' => 'required|string',
            'password' => 'required|string',
            /**
             * @var string
             *
             * @example "68a4636e-c000-4dbf-bff9-c374e4a8c5ff"
             */
            'notif_id' => 'required|string',
        ]);

        try {
            $credentials = request(['username', 'password']);
            if (! Auth::attempt($credentials)) {
                return ResponseFormatter::error([
                    'message' => 'Unauthorized',
                ], 'Gagal login, cek kembali username dan password anda', 500);
            }

            $user = User::with(['region', 'cluster', 'role', 'divisi', 'badanusaha', 'tm'])
                ->where('username', $request->username)
                ->first();

            if (! Hash::check($request->password, $user->password)) {
                throw new Exception('Invalid Credentials');
            }

            $user->id_notif = $request->notif_id;
            $user->update();

            $tokenResult = $user->createToken('authToken')->plainTextToken;

            return ResponseFormatter::success([
                'access_token' => $tokenResult,
                'token_type' => 'Bearer',
                'user' => $user,
            ], 'Authenticated');
        } catch (Exception $error) {
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
                'error' => $error,
            ], 'Authentication Failed', 500);
        }
    }

    // public function login(Request $request)
    // {
    //     // Validate the request parameters
    //     $request->validate([
    //         'version' => 'required|string|in:1.0.3',
    //         'username' => 'required|string',
    //         'password' => 'required|string',
    //         /**
    //          * @var string
    //          * @example "68a4636e-c000-4dbf-bff9-c374e4a8c5ff"
    //          */
    //         'notif_id' => 'required|string',
    //     ]);

    //     try {
    //         $credentials = request(['username', 'password']);

    //         if (!Auth::attempt($credentials)) {
    //             return ResponseFormatter::error(null, 'Gagal login, cek kembali username dan password anda', 401);
    //         }

    //         $user = User::with(['region', 'cluster', 'role', 'divisi', 'badanusaha', 'tm'])
    //             ->where('username', $request->username)
    //             ->first();

    //         if (!Hash::check($request->password, $user->password)) {
    //             return ResponseFormatter::error(null, 'Invalid credentials', 401);
    //         }

    //         $user->id_notif = $request->notif_id;
    //         $user->update();

    //         $tokenResult = $user->createToken('authToken')->plainTextToken;

    //         return ResponseFormatter::success([
    //             'access_token' => $tokenResult,
    //             'token_type' => 'Bearer',
    //             'user' => $user
    //         ], 'Authenticated');
    //     } catch (Exception $error) {
    //         return ResponseFormatter::error(null, 'Authentication failed', 500);
    //     }
    // }

    /**
     * User - Logout ✅
     */
    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken()->delete();

        return ResponseFormatter::success($token, 'Token Revoked');
    }

    // public function register(Request $request)
    // {
    //     try {
    //         $request->validate([
    //             'username' => ['required', 'string', 'min:3', 'max:255', 'unique:users'],
    //             'nama_lengkap' => ['required', 'string'],
    //             'region' => ['required', 'string'],
    //             'cluster_id' => ['required'],
    //             'password' => $this->passwordRules()
    //         ]);

    //         User::create([
    //             'username' => $request->username,
    //             'nama_lengkap' => $request->nama_lengkap,
    //             'region' => $request->region,
    //             'cluster_id' => $request->cluster_id,
    //             'password' => Hash::make($request->password),
    //         ]);

    //         $user = User::where('username', $request->username)->first();

    //         $tokenResult = $user->createToken('authToken')->plainTextToken;

    //         return ResponseFormatter::success([
    //             'access_token' => $tokenResult,
    //             'token_type' => 'Bearer',
    //             'user' => $user
    //         ], 'User Registered');
    //     } catch (Exception $error) {
    //         return ResponseFormatter::error([
    //             'message' => 'Something went wrong',
    //             'error' => $error,
    //         ], 'Authentication Failed', 500);
    //     }
    // }
}
