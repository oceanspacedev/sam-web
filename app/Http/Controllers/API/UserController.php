<?php

namespace App\Http\Controllers\API;

use App\Actions\Fortify\PasswordValidationRules;
use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\LoginRequest;
use App\Http\Requests\API\StoreUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    use PasswordValidationRules;

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

    public function login(LoginRequest $request)
    {
        try {
            $credentials = $request->only(['username', 'password']);

            if (! Auth::attempt($credentials)) {
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

    public function store(StoreUserRequest $request)
    {
        try {
            $user = Auth::user();

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
            if ($request->badanusaha_ids) {
                $newUser->badanUsahas()->attach($request->badanusaha_ids);
            }
            if ($request->divisi_ids) {
                $newUser->divisis()->attach($request->divisi_ids);
            }
            if ($request->region_ids) {
                $newUser->regions()->attach($request->region_ids);
            }
            if ($request->cluster_ids) {
                $newUser->clusters()->attach($request->cluster_ids);
            }

            $newUser->load(['role', 'badanUsahas', 'divisis', 'regions', 'clusters']);

            return response()->json([
                'meta' => ['code' => 201, 'status' => 'success', 'message' => 'User berhasil dibuat'],
                'data' => ['user' => new UserResource($newUser)],
                'errors' => null,
            ], 201);
        } catch (Exception $e) {
            return ResponseFormatter::error(['error' => 'Terjadi kesalahan saat membuat user'], 'ERROR', 500);
        }
    }
}
