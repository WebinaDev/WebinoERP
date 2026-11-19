<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Core\Entities\CoreActivity;
use Modules\Core\Entities\CoreUserProfile;
use Modules\Core\Services\NotificationPreferences;
use Modules\Core\Support\UserDirectory;

/** Self-service: every signed-in person edits their own profile and notification switches. */
class MeAccountController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $user = $request->user()->load('roles:id,name');
        $profile = CoreUserProfile::query()->firstOrNew(['user_id' => $user->id]);
        $data = array_merge(UserDirectory::row($user), [
            'password_changed_at' => $user->password_changed_at?->toIso8601String(),
            'profile' => collect($profile->toArray())->except(['admin_notes'])->all(),
        ]);

        return response()->json(['data' => $data]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        $rules = array_intersect_key(CoreUserProfile::rules(), array_flip(CoreUserProfile::SELF_FIELDS));
        $data = $request->validate(array_merge([
            'name' => 'sometimes|required|string|max:191',
            'first_name' => 'sometimes|nullable|string|max:100',
            'last_name' => 'sometimes|nullable|string|max:100',
            'phone' => 'sometimes|nullable|string|max:30',
            'username' => ['sometimes', 'nullable', 'string', 'min:3', 'max:60', 'regex:/^[a-zA-Z0-9._-]+$/', Rule::unique('users', 'username')->ignore($user->id)],
            'email' => ['sometimes', 'required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user->id)],
        ], $rules));
        $account = array_intersect_key($data, array_flip(['name', 'first_name', 'last_name', 'phone', 'username', 'email']));
        if (isset($account['username'])) {
            $account['username'] = strtolower($account['username']);
        }
        if (isset($account['email'])) {
            $account['email'] = strtolower($account['email']);
            if ($account['email'] !== $user->email) {
                $account['email_verified_at'] = null;
            }
        }
        if ($account !== []) {
            $user->forceFill($account)->save();
        }
        $profileData = array_intersect_key($data, array_flip(CoreUserProfile::SELF_FIELDS));
        if ($profileData !== []) {
            CoreUserProfile::query()->firstOrNew(['user_id' => $user->id])->fill($profileData)->save();
        }
        try {
            app(\Modules\Core\Services\ActivityLogger::class)->log('user.self_updated', $user, $user->id, ['fields' => array_keys($data)]);
        } catch (\Throwable) {
        }

        return $this->show($request);
    }

    public function notificationPreferences(Request $request, NotificationPreferences $prefs): JsonResponse
    {
        return response()->json(['data' => $prefs->matrix($request->user()->id)]);
    }

    public function updateNotificationPreferences(Request $request, NotificationPreferences $prefs): JsonResponse
    {
        $data = $request->validate(UserManagementController::preferenceRules());
        $matrix = $prefs->save($request->user()->id, $data);
        try {
            app(\Modules\Core\Services\ActivityLogger::class)->log('user.notifications_updated', $request->user(), $request->user()->id, ['by_admin' => false]);
        } catch (\Throwable) {
        }

        return response()->json(['data' => $matrix]);
    }

    public function activity(Request $request): JsonResponse
    {
        $user = $request->user();
        $rows = CoreActivity::query()
            ->with('user:id,name')
            ->where('subject_type', $user->getMorphClass())
            ->where('subject_id', $user->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get()
            ->map(fn (CoreActivity $a) => [
                'id' => $a->id,
                'action' => $a->action,
                'actor' => $a->user ? ['id' => $a->user->id, 'name' => $a->user->name] : null,
                'created_at' => $a->created_at?->toIso8601String(),
            ]);

        return response()->json(['data' => $rows]);
    }
}
