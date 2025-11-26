<?php

namespace App\Http\Controllers\API;

use App\Actions\Fortify\PasswordValidationRules;
use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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

        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'Data profile user berhasil diambil',
            ],
            'data' => [
                'user' => new UserResource($user),
            ],
            'errors' => null,
        ]);
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
    public function login(LoginRequest $request)
    {
        try {
            $credentials = $request->only(['username', 'password']);

            if (!Auth::attempt($credentials)) {
                return ResponseFormatter::error([
                    'message' => 'Unauthorized',
                ], 'Username atau password salah', 401);
            }

            // Auth::attempt already validated credentials, just get the user
            $user = User::with(['regions', 'clusters', 'role', 'divisis', 'badanUsahas'])
                ->where('username', $request->username)
                ->first();

            // Update notification ID
            $user->id_notif = $request->notif_id;
            $user->update();

            $tokenResult = $user->createToken('authToken')->plainTextToken;

            return response()->json([
                'meta' => [
                    'code' => 200,
                    'status' => 'success',
                    'message' => 'Authenticated',
                ],
                'data' => [
                    'access_token' => $tokenResult,
                    'token_type' => 'Bearer',
                    'user' => new UserResource($user),
                ],
                'errors' => null,
            ]);
        } catch (Exception $error) {
            return ResponseFormatter::error([
                'message' => 'Something went wrong',
                'error' => $error->getMessage(),
            ], 'Authentication Failed', 500);
        }
    }

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

        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'Token Revoked',
            ],
            'data' => $token,
            'errors' => null,
        ]);
    }

    /**
     * Create new user
     */
    public function store(Request $request)
    {
        try {
            $user = Auth::user();

            // Validate basic fields
            $request->validate([
                'username' => 'required|string|unique:users,username|max:255|regex:/^\S*$/|alpha_dash',
                'nama_lengkap' => 'required|string|max:255',
                'password' => 'required|string|min:8',
                'role_id' => 'required|integer|exists:roles,id',
                'badanusaha_ids' => 'nullable|array',
                'divisi_ids' => 'nullable|array',
                'region_ids' => 'nullable|array',
                'cluster_ids' => 'nullable|array',
                'id_notif' => 'nullable|string',
            ], [
                'username.regex' => 'Username tidak boleh mengandung spasi',
                'username.alpha_dash' => 'Username hanya boleh huruf, angka, dash dan underscore',
            ]);

            $targetRole = \App\Models\Role::find($request->role_id);
            $scopeLevel = $targetRole->organizational_scope_level;

            // Validate org fields based on scope
            if (in_array($scopeLevel, ['badanusaha', 'divisi', 'region', 'cluster']) && empty($request->badanusaha_ids)) {
                return ResponseFormatter::error(['badanusaha_ids' => ['Required for this role']], 'Validation error', 422);
            }
            if (in_array($scopeLevel, ['divisi', 'region', 'cluster']) && empty($request->divisi_ids)) {
                return ResponseFormatter::error(['divisi_ids' => ['Required for this role']], 'Validation error', 422);
            }
            if (in_array($scopeLevel, ['region', 'cluster']) && empty($request->region_ids)) {
                return ResponseFormatter::error(['region_ids' => ['Required for this role']], 'Validation error', 422);
            }
            if ($scopeLevel === 'cluster' && empty($request->cluster_ids)) {
                return ResponseFormatter::error(['cluster_ids' => ['Required for this role']], 'Validation error', 422);
            }

            // Create user
            $newUser = User::create([
                'username' => strtolower(trim($request->username)),
                'nama_lengkap' => strtoupper(trim($request->nama_lengkap)),
                'password' => bcrypt($request->password),
                'role_id' => $request->role_id,
                'tm_id' => $user->id,  // Auto-fill
                'id_notif' => $request->id_notif,
                'badanusaha_id' => $request->badanusaha_ids[0] ?? 0,
                'divisi_id' => $request->divisi_ids[0] ?? 0,
                'region_id' => $request->region_ids[0] ?? 0,
                'cluster_id' => $request->cluster_ids[0] ?? 0,
            ]);

            // Attach pivot tables
            if ($request->badanusaha_ids)
                $newUser->badanUsahas()->attach($request->badanusaha_ids);
            if ($request->divisi_ids)
                $newUser->divisis()->attach($request->divisi_ids);
            if ($request->region_ids)
                $newUser->regions()->attach($request->region_ids);
            if ($request->cluster_ids)
                $newUser->clusters()->attach($request->cluster_ids);

            $newUser->load(['role', 'badanUsahas', 'divisis', 'regions', 'clusters']);

            return response()->json([
                'meta' => ['code' => 201, 'status' => 'success', 'message' => 'User berhasil dibuat'],
                'data' => ['user' => new UserResource($newUser)],
                'errors' => null,
            ], 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseFormatter::error($e->errors(), 'Validation error', 422);
        } catch (Exception $e) {
            return ResponseFormatter::error(['error' => $e->getMessage()], 'ERROR', 500);
        }
    }
}
