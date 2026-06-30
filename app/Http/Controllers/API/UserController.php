<?php

namespace App\Http\Controllers\API;

use App\Exceptions\Api\BadRequestException;
use App\Exceptions\Api\FileUploadException;
use App\Exceptions\Api\ForbiddenException;
use App\Exceptions\Api\ResourceNotFoundException;
use App\Exceptions\Api\UnauthorizedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\API\LoginRequest;
use App\Http\Requests\API\StoreUserRequest;
use App\Http\Requests\API\UpdateUserRequest;
use App\Http\Resources\UserResource;
use App\Models\Register;
use App\Models\Role;
use App\Models\User;
use App\Models\Visit;
use App\Services\FileUploadService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Throwable;

class UserController extends Controller
{
    public function __construct(
        protected FileUploadService $fileUpload
    ) {}

    /**
     * Get all users (with organizational scope filtering)
     * GET /users
     */
    public function index(Request $request)
    {
        $authUser = Auth::user();

        // SDUI: Check permission instead of hardcoded roles
        if (! $authUser->can('ViewAny:User')) {
            throw new ForbiddenException('Anda tidak memiliki akses untuk melihat daftar user');
        }

        $request->validate([
            'search' => 'sometimes|string',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        // Use visibleTo scope for organizational filtering
        $query = User::with(['clusters', 'regions', 'role', 'divisis', 'badanUsahas'])
            ->visibleTo($authUser)
            ->orderBy('nama_lengkap');

        if ($request->filled('search')) {
            $search = trim((string) $request->input('search'));
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('nama_lengkap', 'like', "%{$search}%")
                    ->orWhere('username', 'like', "%{$search}%");
            });
        }

        $perPage = min((int) $request->input('per_page', 25), 100);

        $users = $query->simplePaginate($perPage);

        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'Daftar user berhasil diambil',
                'pagination' => [
                    'current_page' => $users->currentPage(),
                    'per_page' => $users->perPage(),
                    'has_more_pages' => $users->hasMorePages(),
                ],
            ],
            'data' => UserResource::collection($users),
            'errors' => null,
        ]);
    }

    /**
     * Get user detail in current actor organizational scope.
     * GET /users/{id}
     */
    public function show(Request $request, int $id)
    {
        $authUser = Auth::user();

        if (! $authUser->can('ViewAny:User')) {
            throw new ForbiddenException('Anda tidak memiliki akses untuk melihat detail user');
        }

        $user = User::with(['clusters', 'regions', 'role', 'divisis', 'badanUsahas'])
            ->visibleTo($authUser)
            ->where('id', $id)
            ->first();

        if (! $user) {
            throw new ResourceNotFoundException('User tidak ditemukan');
        }

        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'Detail user berhasil diambil',
            ],
            'data' => new UserResource($user),
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

    /**
     * Update current user profile
     * PUT /user
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'username' => [
                'sometimes',
                'string',
                'max:255',
                'regex:/^\S*$/',
                'alpha_dash',
                Rule::unique('users', 'username')->ignore($user->id),
            ],
            'nama_lengkap' => ['sometimes', 'string', 'max:255'],
            'password' => ['sometimes', 'nullable', 'string', 'min:6'],
        ]);

        $updateData = [];
        if (array_key_exists('username', $validated)) {
            $updateData['username'] = strtolower(trim((string) $validated['username']));
        }
        if (array_key_exists('nama_lengkap', $validated)) {
            $updateData['nama_lengkap'] = strtoupper(trim((string) $validated['nama_lengkap']));
        }
        if (array_key_exists('password', $validated) && filled($validated['password'])) {
            $updateData['password'] = bcrypt((string) $validated['password']);
        }

        if (! empty($updateData)) {
            $user->update($updateData);
        }

        $user->refresh()->load(['clusters', 'regions', 'role', 'divisis', 'badanUsahas']);

        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'Profil berhasil diperbarui',
            ],
            'data' => new UserResource($user),
            'errors' => null,
        ]);
    }

    /**
     * Update current user profile photo
     * POST /user/photo
     */
    public function updateProfilePhoto(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'profile_photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $oldPath = $user->profile_photo_path;

        try {
            $path = $this->fileUpload->uploadImageOptimized(
                $validated['profile_photo'],
                'profile-photo',
                ['directory' => 'profile-photos']
            );
        } catch (Throwable $e) {
            throw (new FileUploadException('Gagal mengupload foto profil'))
                ->withData(['field' => 'profile_photo']);
        }

        $user->update([
            'profile_photo_path' => $path,
        ]);

        if ($oldPath && $oldPath !== $path) {
            $this->fileUpload->deleteFile($oldPath);
        }

        $user->refresh()->load(['clusters', 'regions', 'role', 'divisis', 'badanUsahas']);

        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'Foto profil berhasil diperbarui',
            ],
            'data' => new UserResource($user),
            'errors' => null,
        ]);
    }

    /**
     * Get current user's monthly statistics
     * GET /user/stats
     */
    public function stats(Request $request)
    {
        $user = Auth::user();
        $now = Carbon::now();

        // Get month boundaries
        $startOfMonth = $now->copy()->startOfMonth();
        $endOfMonth = $now->copy()->endOfMonth();

        // Count visits this month for current user
        $visitCount = Visit::where('user_id', $user->id)
            ->whereBetween('tanggal_visit', [$startOfMonth, $endOfMonth])
            ->count();

        // Count NOO approved this month (created by current user)
        // Using approved_at so lead→noo conversions count in the month they're approved
        $nooCount = Register::where('created_by_id', $user->id)
            ->where('type', 'NOO')
            ->whereNotNull('approved_at')
            ->whereBetween('approved_at', [$startOfMonth, $endOfMonth])
            ->count();

        // Count LEAD created this month (created by current user)
        $leadCount = Register::where('created_by_id', $user->id)
            ->where('type', 'LEAD')
            ->whereBetween('created_at', [$startOfMonth, $endOfMonth])
            ->count();

        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'Statistik berhasil diambil',
            ],
            'data' => [
                'month' => $now->format('F Y'),
                'month_id' => $now->locale('id')->translatedFormat('F Y'),
                'visit_count' => $visitCount,
                'noo_count' => $nooCount,
                'lead_count' => $leadCount,
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

        // Hapus semua token lama sebelum membuat token baru
        $user->tokens()->delete();

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

    /**
     * Delete current authenticated account (soft delete)
     * DELETE /user
     */
    public function deleteAccount(Request $request)
    {
        $user = $request->user();

        $profilePhotoPath = $user->profile_photo_path;

        $user->tokens()->delete();
        $user->delete();

        if ($profilePhotoPath) {
            $this->fileUpload->deleteFile($profilePhotoPath);
        }

        return response()->json([
            'meta' => [
                'code' => 200,
                'status' => 'success',
                'message' => 'Akun berhasil dihapus',
            ],
            'data' => null,
            'errors' => null,
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $user = Auth::user();

        // SDUI: Check permission
        if (! $user->can('Create:User')) {
            throw new ForbiddenException('Anda tidak memiliki akses untuk membuat user');
        }

        $targetRole = Role::find($request->role_id);
        if (! $targetRole) {
            throw new ResourceNotFoundException('Role tidak ditemukan');
        }

        $assignments = $this->resolveOrganizationalAssignments($request, $user, null, $targetRole);

        // Create user
        $payload = [
            'username' => strtolower(trim($request->username)),
            'nama_lengkap' => strtoupper(trim($request->nama_lengkap)),
            'password' => bcrypt($request->password),
            'role_id' => $request->role_id,
            'tm_id' => $user->id,  // Auto-fill
            'id_notif' => $request->id_notif,
        ];

        if ($this->hasLegacyOrganizationalColumns()) {
            $payload['badanusaha_id'] = $assignments['badanusaha_ids'][0] ?? null;
            $payload['divisi_id'] = $assignments['divisi_ids'][0] ?? null;
            $payload['region_id'] = $assignments['region_ids'][0] ?? null;
            $payload['cluster_id'] = $assignments['cluster_ids'][0] ?? null;
        }

        $newUser = User::create($payload);

        // Attach pivot tables
        $newUser->badanUsahas()->sync($assignments['badanusaha_ids']);
        $newUser->divisis()->sync($assignments['divisi_ids']);
        $newUser->regions()->sync($assignments['region_ids']);
        $newUser->clusters()->sync($assignments['cluster_ids']);

        $newUser->load(['role', 'badanUsahas', 'divisis', 'regions', 'clusters']);

        return response()->json([
            'meta' => ['code' => 200, 'status' => 'success', 'message' => 'User berhasil dibuat'],
            'data' => new UserResource($newUser),
            'errors' => null,
        ]);
    }

    /**
     * Update user
     * PUT /users/{id}
     */
    public function update(UpdateUserRequest $request, $id)
    {
        $authUser = Auth::user();

        // SDUI: Check permission
        if (! $authUser->can('Update:User')) {
            throw new ForbiddenException('Anda tidak memiliki akses untuk mengubah user');
        }

        $user = $this->findMutableUserInActorScope($authUser, (int) $id);

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

        $shouldResolveAssignments = $request->has('role_id') || $request->hasAny([
            'badanusaha_ids',
            'divisi_ids',
            'region_ids',
            'cluster_ids',
        ]);

        $assignments = null;
        if ($shouldResolveAssignments) {
            $targetRole = $request->has('role_id')
                ? Role::find($request->role_id)
                : $user->role;

            if (! $targetRole) {
                throw new ResourceNotFoundException('Role tidak ditemukan');
            }

            $assignments = $this->resolveOrganizationalAssignments($request, $authUser, $user, $targetRole);

            if ($this->hasLegacyOrganizationalColumns()) {
                $updateData['badanusaha_id'] = $assignments['badanusaha_ids'][0] ?? null;
                $updateData['divisi_id'] = $assignments['divisi_ids'][0] ?? null;
                $updateData['region_id'] = $assignments['region_ids'][0] ?? null;
                $updateData['cluster_id'] = $assignments['cluster_ids'][0] ?? null;
            }
        }

        if (! empty($updateData)) {
            $user->update($updateData);
        }

        // Sync pivot tables when organizational context changes
        if ($assignments !== null) {
            $user->badanUsahas()->sync($assignments['badanusaha_ids']);
            $user->divisis()->sync($assignments['divisi_ids']);
            $user->regions()->sync($assignments['region_ids']);
            $user->clusters()->sync($assignments['cluster_ids']);
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

        // SDUI: Check permission
        if (! $authUser->can('Delete:User')) {
            throw new ForbiddenException('Anda tidak memiliki akses untuk menghapus user');
        }

        $user = $this->findMutableUserInActorScope($authUser, (int) $id);

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

    /**
     * Resolve organizational assignments with compatibility fallback.
     *
     * Priority:
     * 1. Explicit request payload
     * 2. Existing target user assignments (update only)
     * 3. Current actor assignments
     */
    protected function resolveOrganizationalAssignments(
        Request $request,
        User $actor,
        ?User $targetUser,
        Role $targetRole
    ): array {
        $actorAssignments = $this->getOrganizationalAssignments($actor);
        $targetAssignments = $targetUser ? $this->getOrganizationalAssignments($targetUser) : [
            'badanusaha_ids' => [],
            'divisi_ids' => [],
            'region_ids' => [],
            'cluster_ids' => [],
        ];

        $resolved = [];
        foreach (['badanusaha_ids', 'divisi_ids', 'region_ids', 'cluster_ids'] as $field) {
            if ($request->has($field)) {
                $resolved[$field] = $this->normalizeAssignmentIds($request->input($field, []));

                continue;
            }

            if (! empty($targetAssignments[$field])) {
                $resolved[$field] = $targetAssignments[$field];

                continue;
            }

            $resolved[$field] = $actorAssignments[$field];
        }

        $scope = strtolower((string) $targetRole->organizational_scope_level);
        switch ($scope) {
            case 'all':
                $resolved['badanusaha_ids'] = [];
                $resolved['divisi_ids'] = [];
                $resolved['region_ids'] = [];
                $resolved['cluster_ids'] = [];
                break;
            case 'badanusaha':
                $resolved['divisi_ids'] = [];
                $resolved['region_ids'] = [];
                $resolved['cluster_ids'] = [];
                break;
            case 'divisi':
                $resolved['region_ids'] = [];
                $resolved['cluster_ids'] = [];
                break;
            case 'region':
                $resolved['cluster_ids'] = [];
                break;
        }

        $requiredFields = match ($scope) {
            'badanusaha' => ['badanusaha_ids'],
            'divisi' => ['badanusaha_ids', 'divisi_ids'],
            'region' => ['badanusaha_ids', 'divisi_ids', 'region_ids'],
            'cluster' => ['badanusaha_ids', 'divisi_ids', 'region_ids', 'cluster_ids'],
            default => [],
        };

        $missing = [];
        foreach ($requiredFields as $field) {
            if ($resolved[$field] === []) {
                $missing[$field] = ['Required'];
            }
        }

        if ($missing !== []) {
            throw new BadRequestException('Organizational assignment wajib diisi untuk role ini', $missing);
        }

        return $resolved;
    }

    protected function getOrganizationalAssignments(User $user): array
    {
        return [
            'badanusaha_ids' => $user->badanUsahas()->pluck('badan_usahas.id')->map(fn ($id) => (int) $id)->all(),
            'divisi_ids' => $user->divisis()->pluck('divisions.id')->map(fn ($id) => (int) $id)->all(),
            'region_ids' => $user->regions()->pluck('regions.id')->map(fn ($id) => (int) $id)->all(),
            'cluster_ids' => $user->clusters()->pluck('clusters.id')->map(fn ($id) => (int) $id)->all(),
        ];
    }

    protected function normalizeAssignmentIds(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return collect($value)
            ->filter(fn ($id) => is_numeric($id))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    protected function hasLegacyOrganizationalColumns(): bool
    {
        static $hasLegacyColumns = null;

        if ($hasLegacyColumns !== null) {
            return $hasLegacyColumns;
        }

        try {
            $hasLegacyColumns = Schema::hasColumns('users', [
                'badanusaha_id',
                'divisi_id',
                'region_id',
                'cluster_id',
            ]);
        } catch (Throwable) {
            $hasLegacyColumns = false;
        }

        return $hasLegacyColumns;
    }

    protected function findMutableUserInActorScope(User $actor, int $id): User
    {
        $target = User::query()->whereKey($id)->first();

        if (! $target || ! $this->canMutateUserInActorScope($actor, $target)) {
            throw new ResourceNotFoundException('User tidak ditemukan');
        }

        return $target;
    }

    protected function canMutateUserInActorScope(User $actor, User $target): bool
    {
        if (! $actor->role) {
            return false;
        }

        if ($actor->role->hasFullAccess()) {
            return true;
        }

        // Preserve compatibility for legacy/unassigned mobile-created users:
        // update can attach the actor's fallback assignments safely.
        if (! $this->hasOrganizationalAssignments($target)) {
            return true;
        }

        return User::query()
            ->visibleTo($actor)
            ->whereKey($target->id)
            ->exists();
    }

    protected function hasOrganizationalAssignments(User $user): bool
    {
        return $user->badanUsahas()->exists()
            || $user->divisis()->exists()
            || $user->regions()->exists()
            || $user->clusters()->exists();
    }
}
