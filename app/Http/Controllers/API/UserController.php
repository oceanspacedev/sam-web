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
     * Fetch authenticated user profile
     *
     * Retrieves the complete profile information for the authenticated user including all related entities.
     * Returns formatted user data with cluster, region, role, division, and business unit information.
     * Requires authentication via Bearer token.
     *
     * @authenticated
     *
     * @header Authorization string required Bearer token for authentication
     *
     * @response 200 {
     *   "meta": {
     *     "code": 200,
     *     "status": "success",
     *     "message": "Data profile user berhasil diambil"
     *   },
     *   "data": {
     *     "user": {
     *       "username": "johndoe",
     *       "nama_lengkap": "John Doe",
     *       "region": {
     *         "id": 1,
     *         "name": "Jakarta"
     *       },
     *       "cluster": {
     *         "id": 5,
     *         "name": "Cluster A"
     *       },
     *       "role": {
     *         "id": 3,
     *         "name": "Sales Representative"
     *       },
     *       "divisi": {
     *         "id": 2,
     *         "name": "Sales"
     *       },
     *       "badanusaha": {
     *         "id": 1,
     *         "name": "PT. Complete Selular"
     *       },
     *       "id_notif": "68a4636e-c000-4dbf-bff9-c374e4a8c5ff"
     *     },
     *     "message": "Data profile user berhasil diambil"
     *   }
     * }
     * @response 401 {
     *   "meta": {
     *     "code": 401,
     *     "status": "error",
     *     "message": "Unauthenticated"
     *   },
     *   "data": null
     * }
     */
    public function fetch(Request $request)
    {
        $user = User::with(['clusters', 'regions', 'role', 'divisis', 'badanUsahas'])->where('id', Auth::user()->id)->first();

        return ResponseFormatter::success(['user' => $user->formatForAPI(), 'message' => 'Data profile user berhasil diambil']);
    }

    /**
     * Authenticate user and generate access token
     *
     * Validates SAM application version (requires v1.0.3), authenticates user credentials,
     * updates notification ID for push notifications, and returns a Bearer token for API access.
     * This endpoint does not require authentication.
     *
     * @unauthenticated
     *
     * @bodyParam version string required Must be 1.0.3. Example: "1.0.3"
     * @bodyParam username string required The user's username. Example: "jdoe"
     * @bodyParam password string required The user's password. Example: "P@ssw0rd!"
     * @bodyParam notif_id string required The notification/device ID. Example: "68a4636e-c000-4dbf-bff9-c374e4a8c5ff"
     *
     * @response 200 {
     *   "meta": {
     *     "code": 200,
     *     "status": "success",
     *     "message": "Authenticated"
     *   },
     *   "data": {
     *     "access_token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
     *     "token_type": "Bearer",
     *     "user": {
     *       "id": 1,
     *       "username": "johndoe",
     *       "nama_lengkap": "John Doe",
     *       "id_notif": "68a4636e-c000-4dbf-bff9-c374e4a8c5ff",
     *       "region": {
     *         "id": 1,
     *         "name": "Jakarta"
     *       },
     *       "cluster": {
     *         "id": 5,
     *         "name": "Cluster A"
     *       },
     *       "role": {
     *         "id": 3,
     *         "name": "Sales Representative"
     *       },
     *       "divisi": {
     *         "id": 2,
     *         "name": "Sales"
     *       },
     *       "badanusaha": {
     *         "id": 1,
     *         "name": "PT. Complete Selular"
     *       }
     *     }
     *   }
     * }
     * @response 500 {
     *   "meta": {
     *     "code": 500,
     *     "status": "error",
     *     "message": "Gagal login, Update versi aplikasi SAM anda ke V1.0.3."
     *   },
     *   "data": {
     *     "message": "Unauthorized"
     *   }
     * }
     * @response 500 {
     *   "meta": {
     *     "code": 500,
     *     "status": "error",
     *     "message": "Gagal login, cek kembali username dan password anda"
     *   },
     *   "data": {
     *     "message": "Unauthorized"
     *   }
     * }
     * @response 500 {
     *   "meta": {
     *     "code": 500,
     *     "status": "error",
     *     "message": "Authentication Failed"
     *   },
     *   "data": {
     *     "message": "Something went wrong",
     *     "error": "Exception details"
     *   }
     * }
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
            'notif_id' => 'required|string',
        ]);

        try {
            $credentials = request(['username', 'password']);

            if (!Auth::attempt($credentials)) {
                return ResponseFormatter::error([
                    'message' => 'Unauthorized',
                ], 'Gagal login, cek kembali username dan password anda', 500);
            }

            $user = User::with(['regions', 'clusters', 'role', 'divisis', 'badanUsahas', 'tm'])
                ->where('username', $request->username)
                ->first();

            if (!Hash::check($request->password, $user->password)) {
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
                'error' => $error->getMessage(),
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
     * Revoke current access token
     *
     * Revokes the current Bearer token, effectively logging out the user from the current session.
     * The token will no longer be valid for API access. Requires authentication via Bearer token.
     *
     * @authenticated
     *
     * @header Authorization string required Bearer token for authentication
     *
     * @response 200 {
     *   "meta": {
     *     "code": 200,
     *     "status": "success",
     *     "message": "Token Revoked"
     *   },
     *   "data": true
     * }
     * @response 401 {
     *   "meta": {
     *     "code": 401,
     *     "status": "error",
     *     "message": "Unauthenticated"
     *   },
     *   "data": null
     * }
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
