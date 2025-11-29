<?php

namespace App\Http\Controllers\API;

use App\Exceptions\Api\BadRequestException;
use App\Exceptions\Api\ForbiddenException;
use App\Exceptions\Api\ResourceNotFoundException;
use App\Exceptions\Api\UnauthorizedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\LoginRequest;
use App\Http\Requests\API\StoreUserRequest;
use App\Http\Requests\API\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    /**
     * Get all users (with organizational scope filtering)
     * GET /users
     */
    public function index(Request $request)
    {
        $authUser = Auth::user();

        // Role-based access: only Admin (1), CSO (9), ASM (6) can list users
        $allowedRoles = [1, 9, 6];
        if (! in_array($authUser->role_id, $allowedRoles)) {
            throw new ForbiddenException('Anda tidak memiliki akses untuk melihat daftar user');
        }

        // Base query with eager loading
        $query = User::with(['clusters', 'regions', 'role', 'divisis', 'badanUsahas'])
            ->whereNull('deleted_at');

        // Apply organizational scope filtering based on user's role
        $orgIds = $authUser->getOrganizationalIds();
        $scopeLevel = $orgIds['scope_level'];

        // Admin (role 1) sees all, others filtered by scope
        if ($authUser->role_id !== 1) {
            if ($scopeLevel === 'cluster' && ! empty($orgIds['cluster'])) {
                $query->whereHas('clusters', function ($q) use ($orgIds) {
                    $q->whereIn('clusters.id', $orgIds['cluster']);
                });
            } elseif ($scopeLevel === 'region' && ! empty($orgIds['region'])) {
                $query->whereHas('regions', function ($q) use ($orgIds) {
                    $q->whereIn('regions.id', $orgIds['region']);
                });
            } elseif ($scopeLevel === 'divisi' && ! empty($orgIds['divisi'])) {
                $query->whereHas('divisis', function ($q) use ($orgIds) {
                    $q->whereIn('divisions.id', $orgIds['divisi']);
                });
            } elseif ($scopeLevel === 'badanusaha' && ! empty($orgIds['badanusaha'])) {
                $query->whereHas('badanUsahas', function ($q) use ($orgIds) {
                    $q->whereIn('badan_usahas.id', $orgIds['badanusaha']);
                });
            }
        }

        $users = $query->orderBy('nama_lengkap')->get();

        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'Daftar user berhasil diambil',
            ],
            'data' => UserResource::collection($users),
            'errors' => null,
        ]);
    }

    public function fetch(Request $request)
    {
        $user = User::with(['clusters', 'regions', 'role', 'divisis', 'badanUsahas'])
            ->where('id', Auth::id())
            ->first();

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
        $credentials = $request->only(['username', 'password']);

        if (! Auth::attempt($credentials)) {
            throw new UnauthorizedException('Username atau password salah');
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
            'data' => new UserResource($newUser),
            'errors' => null,
        ], 201);
    }

    /**
     * Update user
     * PUT /users/{id}
     */
    public function update(UpdateUserRequest $request, $id)
    {
        $authUser = Auth::user();

        // Role-based access: only Admin (1), CSO (9), ASM (6) can update users
        $allowedRoles = [1, 9, 6];
        if (! in_array($authUser->role_id, $allowedRoles)) {
            throw new ForbiddenException('Anda tidak memiliki akses untuk mengubah user');
        }

        $user = User::find($id);
        if (! $user) {
            throw new ResourceNotFoundException('User tidak ditemukan');
        }

        // Update basic fields
        $updateData = [];
        if ($request->has('username')) {
            $updateData['username'] = strtolower(trim($request->username));
        }
        if ($request->has('nama_lengkap')) {
            $updateData['nama_lengkap'] = strtoupper(trim($request->nama_lengkap));
        }
        if ($request->filled('password')) {
            $updateData['password'] = bcrypt($request->password);
        }
        if ($request->has('role_id')) {
            $updateData['role_id'] = $request->role_id;
        }
        if ($request->has('id_notif')) {
            $updateData['id_notif'] = $request->id_notif;
        }

        if (! empty($updateData)) {
            $user->update($updateData);
        }

        // Sync pivot tables if provided
        if ($request->has('badanusaha_ids')) {
            $user->badanUsahas()->sync($request->badanusaha_ids ?? []);
        }
        if ($request->has('divisi_ids')) {
            $user->divisis()->sync($request->divisi_ids ?? []);
        }
        if ($request->has('region_ids')) {
            $user->regions()->sync($request->region_ids ?? []);
        }
        if ($request->has('cluster_ids')) {
            $user->clusters()->sync($request->cluster_ids ?? []);
        }

        $user->load(['role', 'badanUsahas', 'divisis', 'regions', 'clusters']);

        return response()->json([
            'meta' => ['code' => 200, 'status' => 'success', 'message' => 'User berhasil diupdate'],
            'data' => new UserResource($user),
            'errors' => null,
        ]);
    }

    /**
     * Delete user (soft delete)
     * DELETE /users/{id}
     */
    public function destroy(Request $request, $id)
    {
        $authUser = Auth::user();

        // Role-based access: only Admin (1), CSO (9), ASM (6) can delete users
        $allowedRoles = [1, 9, 6];
        if (! in_array($authUser->role_id, $allowedRoles)) {
            throw new ForbiddenException('Anda tidak memiliki akses untuk menghapus user');
        }

        $user = User::find($id);
        if (! $user) {
            throw new ResourceNotFoundException('User tidak ditemukan');
        }

        // Prevent deleting self
        if ($user->id === $authUser->id) {
            throw new BadRequestException('Tidak dapat menghapus akun sendiri');
        }

        // Soft delete
        $user->delete();

        return response()->json([
            'meta' => ['code' => 200, 'status' => 'success', 'message' => 'User berhasil dihapus'],
            'data' => null,
            'errors' => null,
        ]);
    }
}
