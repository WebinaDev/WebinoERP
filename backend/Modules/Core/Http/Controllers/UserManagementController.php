<?php

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Core\Entities\CoreActivity;
use Modules\Core\Entities\CoreUserDocument;
use Modules\Core\Entities\CoreUserProfile;
use Modules\Core\Services\NotificationPreferences;
use Modules\Core\Support\UserDirectory;
use Spatie\Permission\Models\Role;

/**
 * Admin user management: accounts, passwords, sessions, roles, profile data,
 * notification switches and audit trail for staff, customers and every other user.
 * Gated by core.users.view / core.users.manage via module.permission:core.
 */
class UserManagementController extends Controller
{
    public function meta(): JsonResponse
    {
        $roles = Role::query()->orderBy('id')->get(['id', 'name'])->map(fn (Role $r) => [
            'name' => $r->name,
            'label' => RolesAndPermissionsSeeder::LABELS[$r->name] ?? $r->name,
        ])->values();

        return response()->json(['data' => [
            'roles' => $roles,
            'document_types' => CoreUserDocument::TYPES,
            'notification_channels' => NotificationPreferences::CHANNELS,
            'notification_categories' => NotificationPreferences::CATEGORIES,
            'locked_categories' => NotificationPreferences::LOCKED_CATEGORIES,
        ]]);
    }

    public function directory(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'nullable|in:all,staff,customer,other',
            'status' => 'nullable|in:all,active,inactive,locked,must_change',
            'role' => 'nullable|string|max:64',
            'search' => 'nullable|string|max:191',
            'per_page' => 'nullable|integer|min:5|max:100',
            'sort' => 'nullable|in:newest,oldest,name,last_login',
        ]);
        $q = User::query()->with('roles:id,name');
        $type = (string) $request->input('type', 'all');
        if ($type !== 'all') {
            UserDirectory::applyType($q, $type);
        }
        match ((string) $request->input('status', 'all')) {
            'active' => $q->where('is_active', true)->where(fn ($w) => $w->whereNull('locked_until')->orWhere('locked_until', '<=', now())),
            'inactive' => $q->where('is_active', false),
            'locked' => $q->where('locked_until', '>', now()),
            'must_change' => $q->where('must_change_password', true),
            default => null,
        };
        if ($request->filled('role')) {
            $q->whereHas('roles', fn ($r) => $r->where('name', $request->string('role')->toString()));
        }
        if ($request->filled('search')) {
            $s = '%'.mb_strtolower(trim($request->string('search')->toString())).'%';
            $q->where(function ($w) use ($s) {
                $w->whereRaw('lower(name) like ?', [$s])
                    ->orWhereRaw('lower(email) like ?', [$s])
                    ->orWhereRaw('lower(coalesce(username, \'\')) like ?', [$s])
                    ->orWhere('phone', 'like', $s);
            });
        }
        match ((string) $request->input('sort', 'newest')) {
            'oldest' => $q->orderBy('id'),
            'name' => $q->orderBy('name'),
            'last_login' => $q->orderByRaw('last_login_at is null')->orderByDesc('last_login_at'),
            default => $q->orderByDesc('id'),
        };
        $page = $q->paginate((int) $request->input('per_page', 20));
        $employees = UserDirectory::employeeIds($page->getCollection());
        $rows = $page->getCollection()->map(fn (User $u) => UserDirectory::row($u, $employees[$u->id] ?? null))->values();

        return response()->json([
            'data' => $rows,
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'stats' => $this->stats(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'email' => 'required|email|max:191|unique:users,email',
            'username' => ['nullable', 'string', 'min:3', 'max:60', 'regex:/^[a-zA-Z0-9._-]+$/', Rule::unique('users', 'username')],
            'phone' => 'nullable|string|max:30',
            'password' => 'nullable|string|min:8|max:191',
            'must_change_password' => 'nullable|boolean',
            'is_active' => 'nullable|boolean',
            'roles' => 'nullable|array',
            'roles.*' => 'string|exists:roles,name',
        ]);
        $actor = $request->user();
        $roles = $data['roles'] ?? [RolesAndPermissionsSeeder::ROLE_TEAM_MEMBER];
        $this->guardRoleChange($actor, null, $roles);
        $temporary = empty($data['password']) ? Str::password(14, symbols: false) : null;

        $user = DB::transaction(function () use ($data, $roles, $temporary) {
            $user = new User;
            $user->forceFill([
                'name' => $data['name'],
                'first_name' => $data['first_name'] ?? null,
                'last_name' => $data['last_name'] ?? null,
                'email' => strtolower($data['email']),
                'username' => isset($data['username']) ? strtolower($data['username']) : null,
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make($data['password'] ?? $temporary),
                'is_active' => $data['is_active'] ?? true,
                'must_change_password' => $temporary !== null ? true : (bool) ($data['must_change_password'] ?? false),
                'password_changed_at' => now(),
            ])->save();
            $user->syncRoles($roles);

            return $user;
        });
        $this->audit('user.created', $user, ['roles' => $roles]);

        return response()->json(['data' => [
            'user' => UserDirectory::row($user->fresh('roles')),
            'temporary_password' => $temporary,
        ]], 201);
    }

    public function show(int $id): JsonResponse
    {
        $user = User::query()->with('roles:id,name')->findOrFail($id);
        $employeeId = UserDirectory::employeeIds([$user])[$user->id] ?? null;
        $employee = null;
        if ($employeeId && Schema::hasTable('hrm_employees')) {
            $employee = DB::table('hrm_employees')->where('id', $employeeId)
                ->first(['id', 'employee_code', 'first_name', 'last_name', 'department', 'position', 'status', 'hire_date']);
        }
        $accounts = [];
        if (Schema::hasTable('crm_account_users') && Schema::hasTable('crm_accounts')) {
            $accounts = DB::table('crm_account_users')
                ->join('crm_accounts', 'crm_accounts.id', '=', 'crm_account_users.account_id')
                ->where('crm_account_users.user_id', $user->id)
                ->get(['crm_accounts.id', 'crm_accounts.name', 'crm_account_users.is_primary'])
                ->all();
        }
        $profile = CoreUserProfile::query()->firstOrNew(['user_id' => $user->id]);

        return response()->json(['data' => array_merge(UserDirectory::row($user, $employeeId), [
            'lock_reason' => $user->lock_reason ?? null,
            'password_changed_at' => $user->password_changed_at?->toIso8601String(),
            'last_login_ip' => $user->last_login_ip ?? null,
            'email_verified_at' => $user->email_verified_at?->toIso8601String(),
            'two_factor' => [
                'required' => \Modules\Core\Support\AdminTwoFactor::requiredFor($user),
                'setting_enabled' => \Modules\Core\Support\AdminTwoFactor::isEnabled(),
                'has_phone' => trim((string) $user->phone) !== '',
            ],
            'permissions' => $user->getAllPermissions()->pluck('name')->values(),
            'direct_permissions' => $user->getDirectPermissions()->pluck('name')->values(),
            'profile' => $profile->toArray(),
            'employee' => $employee,
            'crm_accounts' => $accounts,
            'documents_count' => CoreUserDocument::query()->where('user_id', $user->id)->count(),
            'sessions_count' => $user->tokens()->count(),
        ])]);
    }

    public function updateAccount(Request $request, int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $this->guardTarget($request->user(), $user);
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:191',
            'first_name' => 'sometimes|nullable|string|max:100',
            'last_name' => 'sometimes|nullable|string|max:100',
            'email' => ['sometimes', 'required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
            'username' => ['sometimes', 'nullable', 'string', 'min:3', 'max:60', 'regex:/^[a-zA-Z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user->id)],
            'phone' => 'sometimes|nullable|string|max:30',
            'is_active' => 'sometimes|boolean',
            'must_change_password' => 'sometimes|boolean',
            'email_verified' => 'sometimes|boolean',
        ]);
        if ($request->user()->id === $user->id && array_key_exists('is_active', $data) && ! $data['is_active']) {
            throw ValidationException::withMessages(['is_active' => ['نمی‌توانید حساب خودتان را غیرفعال کنید.']]);
        }
        if (array_key_exists('email', $data)) {
            $data['email'] = strtolower($data['email']);
        }
        if (array_key_exists('username', $data) && $data['username'] !== null) {
            $data['username'] = strtolower($data['username']);
        }
        if (array_key_exists('email_verified', $data)) {
            $data['email_verified_at'] = $data['email_verified'] ? ($user->email_verified_at ?? now()) : null;
            unset($data['email_verified']);
        }
        $before = $user->only(array_keys($data));
        $user->forceFill($data)->save();
        if (array_key_exists('is_active', $data) && ! $data['is_active']) {
            $user->tokens()->delete();
        }
        $this->audit('user.account_updated', $user, ['changed' => $this->diffKeys($before, $user->only(array_keys($data)))]);

        return $this->show($user->id);
    }

    public function setPassword(Request $request, int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $this->guardTarget($request->user(), $user);
        $data = $request->validate([
            'mode' => 'required|in:set,generate',
            'password' => 'required_if:mode,set|nullable|string|min:8|max:191|confirmed',
            'must_change_password' => 'nullable|boolean',
            'revoke_sessions' => 'nullable|boolean',
        ]);
        $temporary = $data['mode'] === 'generate' ? Str::password(14, symbols: false) : null;
        $mustChange = $temporary !== null ? true : (bool) ($data['must_change_password'] ?? false);
        $user->forceFill([
            'password' => Hash::make($temporary ?? $data['password']),
            'must_change_password' => $mustChange,
        ])->save();
        $revoked = 0;
        if ($data['revoke_sessions'] ?? true) {
            $currentId = $request->user()->id === $user->id ? $request->user()->currentAccessToken()?->id : null;
            $revoked = $user->tokens()->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))->delete();
        }
        $this->audit('user.password_reset', $user, ['mode' => $data['mode'], 'must_change' => $mustChange, 'revoked_sessions' => $revoked]);

        return response()->json(['data' => [
            'temporary_password' => $temporary,
            'must_change_password' => $mustChange,
            'revoked_sessions' => $revoked,
        ]]);
    }

    public function lock(Request $request, int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $this->guardTarget($request->user(), $user);
        $data = $request->validate([
            'locked' => 'required|boolean',
            'until' => 'nullable|date|after:now',
            'reason' => 'nullable|string|max:500',
        ]);
        if ($data['locked'] && $request->user()->id === $user->id) {
            throw ValidationException::withMessages(['locked' => ['نمی‌توانید حساب خودتان را قفل کنید.']]);
        }
        if ($data['locked']) {
            $user->forceFill([
                'locked_until' => isset($data['until']) ? \Illuminate\Support\Carbon::parse($data['until']) : now()->addYears(100),
                'lock_reason' => $data['reason'] ?? null,
            ])->save();
            $user->tokens()->delete();
        } else {
            $user->forceFill(['locked_until' => null, 'lock_reason' => null])->save();
        }
        $this->audit($data['locked'] ? 'user.locked' : 'user.unlocked', $user, ['until' => $data['until'] ?? null, 'reason' => $data['reason'] ?? null]);

        return $this->show($user->id);
    }

    public function roles(Request $request, int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $this->guardTarget($request->user(), $user);
        $data = $request->validate([
            'roles' => 'present|array',
            'roles.*' => 'string|exists:roles,name',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|exists:permissions,name',
        ]);
        $this->guardRoleChange($request->user(), $user, $data['roles']);
        $before = $user->getRoleNames()->all();
        $user->syncRoles($data['roles']);
        if (array_key_exists('permissions', $data) && $data['permissions'] !== null) {
            $user->syncPermissions($data['permissions']);
        }
        $this->audit('user.roles_updated', $user, ['before' => $before, 'after' => $data['roles']]);

        return $this->show($user->id);
    }

    public function sessions(int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $rows = PersonalAccessToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->id)
            ->orderByDesc('id')
            ->get()
            ->map(fn (PersonalAccessToken $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'device_name' => $t->device_name ?? null,
                'ip' => $t->ip ?? null,
                'user_agent' => $t->user_agent ?? null,
                'last_used_at' => $t->last_used_at?->toIso8601String(),
                'last_activity_at' => isset($t->last_activity_at) ? (string) $t->last_activity_at : null,
                'created_at' => $t->created_at?->toIso8601String(),
                'expires_at' => $t->expires_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $rows]);
    }

    public function revokeSession(Request $request, int $id, int $tokenId): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $this->guardTarget($request->user(), $user);
        $deleted = PersonalAccessToken::query()
            ->where('tokenable_type', $user->getMorphClass())
            ->where('tokenable_id', $user->id)
            ->whereKey($tokenId)
            ->delete();
        $this->audit('user.session_revoked', $user, ['token_id' => $tokenId]);

        return response()->json(['data' => ['revoked' => $deleted]]);
    }

    public function revokeAllSessions(Request $request, int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $this->guardTarget($request->user(), $user);
        $currentId = $request->user()->id === $user->id ? $request->user()->currentAccessToken()?->id : null;
        $deleted = $user->tokens()->when($currentId, fn ($q) => $q->where('id', '!=', $currentId))->delete();
        $this->audit('user.sessions_revoked', $user, ['count' => $deleted]);

        return response()->json(['data' => ['revoked' => $deleted]]);
    }

    public function profile(int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);

        return response()->json(['data' => CoreUserProfile::query()->firstOrNew(['user_id' => $user->id])->toArray()]);
    }

    public function updateProfile(Request $request, int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $this->guardTarget($request->user(), $user);
        $data = $request->validate(CoreUserProfile::rules());
        $profile = CoreUserProfile::query()->firstOrNew(['user_id' => $user->id]);
        $profile->fill($data)->save();
        $this->audit('user.profile_updated', $user, ['fields' => array_keys($data)]);

        return response()->json(['data' => $profile->fresh()->toArray()]);
    }

    public function notificationPreferences(int $id, NotificationPreferences $prefs): JsonResponse
    {
        $user = User::query()->findOrFail($id);

        return response()->json(['data' => $prefs->matrix($user->id)]);
    }

    public function updateNotificationPreferences(Request $request, int $id, NotificationPreferences $prefs): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $this->guardTarget($request->user(), $user);
        $data = $request->validate(self::preferenceRules());
        $matrix = $prefs->save($user->id, $data);
        $this->audit('user.notifications_updated', $user, ['by_admin' => true]);

        return response()->json(['data' => $matrix]);
    }

    public function activity(Request $request, int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $rows = CoreActivity::query()
            ->with('user:id,name')
            ->where(function ($w) use ($user) {
                $w->where(fn ($s) => $s->where('subject_type', $user->getMorphClass())->where('subject_id', $user->id))
                    ->orWhere('user_id', $user->id);
            })
            ->orderByDesc('id')
            ->limit(min(200, max(10, (int) $request->input('limit', 100))))
            ->get()
            ->map(fn (CoreActivity $a) => [
                'id' => $a->id,
                'action' => $a->action,
                'actor' => $a->user ? ['id' => $a->user->id, 'name' => $a->user->name] : null,
                'about_user' => $a->subject_type === $user->getMorphClass() && (int) $a->subject_id === $user->id,
                'subject_type' => class_basename((string) $a->subject_type),
                'subject_id' => $a->subject_id,
                'meta' => $a->meta,
                'created_at' => $a->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $rows]);
    }

    /** @return array<string, mixed> */
    public static function preferenceRules(): array
    {
        return [
            'channels' => 'nullable|array',
            'channels.*' => 'boolean',
            'categories' => 'nullable|array',
            'categories.*.enabled' => 'nullable|boolean',
            'categories.*.channels' => 'nullable|array',
            'categories.*.channels.*' => 'boolean',
            'quiet_hours' => 'nullable|array',
            'quiet_hours.start' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
            'quiet_hours.end' => ['nullable', 'regex:/^([01]\d|2[0-3]):[0-5]\d$/'],
        ];
    }

    /** @return array<string, int> */
    private function stats(): array
    {
        $count = function (?string $type = null, ?callable $extra = null): int {
            $q = User::query();
            if ($type) {
                UserDirectory::applyType($q, $type);
            }
            if ($extra) {
                $extra($q);
            }

            return $q->count();
        };

        return [
            'total' => $count(),
            'active' => $count(null, fn ($q) => $q->where('is_active', true)),
            'inactive' => $count(null, fn ($q) => $q->where('is_active', false)),
            'locked' => $count(null, fn ($q) => $q->where('locked_until', '>', now())),
            'staff' => $count('staff'),
            'customers' => $count('customer'),
        ];
    }

    /** Only a system manager may change a system manager. */
    private function guardTarget(User $actor, User $target): void
    {
        if ($target->hasRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER)
            && ! $actor->hasRole(RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER)) {
            abort(403, 'فقط مدیر سیستم می‌تواند حساب مدیر سیستم را تغییر دهد.');
        }
    }

    /** @param list<string> $roles */
    private function guardRoleChange(User $actor, ?User $target, array $roles): void
    {
        $sm = RolesAndPermissionsSeeder::ROLE_SYSTEM_MANAGER;
        $actorIsSm = $actor->hasRole($sm);
        if (in_array($sm, $roles, true) && ! $actorIsSm) {
            abort(403, 'فقط مدیر سیستم می‌تواند نقش مدیر سیستم بدهد.');
        }
        if ($target && $target->id === $actor->id && $actorIsSm && ! in_array($sm, $roles, true)) {
            throw ValidationException::withMessages(['roles' => ['نمی‌توانید نقش مدیر سیستم را از خودتان بگیرید.']]);
        }
        if ($target && $target->hasRole($sm) && ! in_array($sm, $roles, true)) {
            $others = User::role($sm)->where('id', '!=', $target->id)->where('is_active', true)->count();
            if ($others === 0) {
                throw ValidationException::withMessages(['roles' => ['دست‌کم یک مدیر سیستم فعال باید باقی بماند.']]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return list<string>
     */
    private function diffKeys(array $before, array $after): array
    {
        $changed = [];
        foreach ($after as $k => $v) {
            if (($before[$k] ?? null) != $v) {
                $changed[] = $k;
            }
        }

        return $changed;
    }

    /** @param array<string, mixed> $meta */
    private function audit(string $action, User $subject, array $meta = []): void
    {
        try {
            app(\Modules\Core\Services\ActivityLogger::class)->log($action, $subject, auth()->id(), $meta);
        } catch (\Throwable) {
            // Audit must never block the admin action.
        }
    }
}
