<?php

namespace Modules\Core\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Core\Entities\CannedResponse;
use Modules\Core\Entities\CoreNotification;
use Modules\Core\Entities\CorePosition;
use Modules\Core\Entities\CoreTaskCategory;
use Modules\Core\Entities\SystemSetting;

/**
 * CRUD parity: canned responses, positions, task categories, notifications, users.
 */
class CrudParityController extends Controller
{
    public function cannedIndex(): JsonResponse
    {
        return response()->json(['data' => CannedResponse::query()->orderByDesc('id')->get()]);
    }

    public function cannedStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:191',
            'body' => 'required|string',
        ]);
        $data['user_id'] = $request->user()?->id;
        $row = CannedResponse::query()->create($data);

        return response()->json(['data' => $row], 201);
    }

    public function cannedUpdate(Request $request, int $id): JsonResponse
    {
        $row = CannedResponse::query()->findOrFail($id);
        $data = $request->validate([
            'title' => 'sometimes|string|max:191',
            'body' => 'sometimes|string',
        ]);
        $row->update($data);

        return response()->json(['data' => $row]);
    }

    public function cannedDestroy(int $id): JsonResponse
    {
        CannedResponse::query()->whereKey($id)->delete();

        return response()->json([], 204);
    }

    public function cannedShow(int $id): JsonResponse
    {
        return response()->json(['data' => CannedResponse::query()->findOrFail($id)]);
    }

    public function positionsIndex(): JsonResponse
    {
        return response()->json(['data' => CorePosition::query()->orderBy('id')->get()]);
    }

    public function positionsStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title' => 'required|string|max:191',
            'permissions' => 'nullable|array',
        ]);
        $row = CorePosition::query()->create($data);

        return response()->json(['data' => $row], 201);
    }

    public function positionsUpdate(Request $request, int $id): JsonResponse
    {
        $row = CorePosition::query()->findOrFail($id);
        $data = $request->validate([
            'title' => 'sometimes|string|max:191',
            'permissions' => 'nullable|array',
        ]);
        $row->update($data);

        return response()->json(['data' => $row]);
    }

    public function positionsDestroy(int $id): JsonResponse
    {
        CorePosition::query()->whereKey($id)->delete();

        return response()->json([], 204);
    }

    public function taskCategoriesIndex(): JsonResponse
    {
        return response()->json(['data' => CoreTaskCategory::query()->orderBy('sort_order')->get()]);
    }

    public function taskCategoriesStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'color' => 'nullable|string|max:7',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $row = CoreTaskCategory::query()->create($data);

        return response()->json(['data' => $row], 201);
    }

    public function taskCategoriesUpdate(Request $request, int $id): JsonResponse
    {
        $row = CoreTaskCategory::query()->findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:191',
            'color' => 'nullable|string|max:7',
            'sort_order' => 'nullable|integer|min:0',
        ]);
        $row->update($data);

        return response()->json(['data' => $row]);
    }

    public function taskCategoriesDestroy(int $id): JsonResponse
    {
        CoreTaskCategory::query()->whereKey($id)->delete();

        return response()->json([], 204);
    }

    public function notificationsIndex(Request $request): JsonResponse
    {
        $rows = CoreNotification::query()
            ->where('user_id', $request->user()->id)
            ->orderByDesc('id')
            ->limit(100)
            ->get();

        return response()->json(['data' => $rows]);
    }

    public function notificationRead(Request $request, int $id): JsonResponse
    {
        $n = CoreNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();
        $n->update(['is_read' => true]);

        return response()->json(['data' => ['id' => $id, 'read' => true]]);
    }

    public function notificationStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|string|max:100',
            'data' => 'nullable|array',
        ]);
        $row = CoreNotification::query()->create([
            'user_id' => $request->user()->id,
            'type' => $data['type'],
            'data' => $data['data'] ?? [],
            'is_read' => false,
        ]);

        return response()->json(['data' => $row], 201);
    }

    public function notificationUpdate(Request $request, int $id): JsonResponse
    {
        $n = CoreNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();
        $data = $request->validate([
            'type' => 'sometimes|string|max:100',
            'data' => 'nullable|array',
            'is_read' => 'sometimes|boolean',
        ]);
        $n->update($data);

        return response()->json(['data' => $n->fresh()]);
    }

    public function notificationDestroy(Request $request, int $id): JsonResponse
    {
        $n = CoreNotification::query()
            ->where('user_id', $request->user()->id)
            ->whereKey($id)
            ->firstOrFail();
        $n->delete();

        return response()->json([], 204);
    }

    public function usersIndex(Request $request): JsonResponse
    {
        $q = User::query()->orderByDesc('id');
        if ($request->filled('search')) {
            $s = '%'.$request->string('search').'%';
            $q->where(function ($w) use ($s) {
                $w->where('name', 'like', $s)->orWhere('email', 'like', $s);
            });
        }
        $perPage = min((int) $request->input('per_page', 50), 100);

        return response()->json(['data' => $q->paginate($perPage)]);
    }

    public function usersStore(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:191',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone' => 'nullable|string|max:30',
        ]);
        $data['password'] = Hash::make($data['password']);
        $user = User::query()->create($data);
        $user->assignRole(RolesAndPermissionsSeeder::ROLE_TEAM_MEMBER);

        return response()->json(['data' => ['id' => $user->id]], 201);
    }

    public function usersUpdate(Request $request, int $id): JsonResponse
    {
        $user = User::query()->findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:191',
            'email' => 'sometimes|email|unique:users,email,'.$id,
            'phone' => 'nullable|string|max:30',
            'password' => 'nullable|string|min:8',
        ]);
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $user->update($data);

        return response()->json(['data' => ['id' => $id, 'updated' => true]]);
    }

    public function usersDestroy(int $id): JsonResponse
    {
        User::query()->whereKey($id)->delete();

        return response()->json([], 204);
    }

    public function usersSearch(Request $request): JsonResponse
    {
        $s = $request->string('q', '')->toString();
        $q = User::query()->orderBy('name')->limit(20);
        if ($s !== '') {
            $like = '%'.$s.'%';
            $q->where(function ($w) use ($like) {
                $w->where('name', 'like', $like)->orWhere('email', 'like', $like);
            });
        }

        return response()->json(['data' => $q->get(['id', 'name', 'email'])]);
    }

    public function meUpdate(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate([
            'name' => 'sometimes|string|max:191',
            'email' => 'sometimes|email|unique:users,email,'.$user->id,
            'phone' => 'nullable|string|max:30',
            'password' => 'nullable|string|min:8|confirmed',
        ]);
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        $user->update($data);

        return response()->json(['data' => ['updated' => true, 'user' => $user->fresh()]]);
    }

    public function preferences(Request $request): JsonResponse
    {
        $request->validate(['preferences' => 'required|array']);
        /** @var User $user */
        $user = $request->user();
        \Modules\Core\Entities\SystemSetting::set(
            'user_preferences_'.$user->id,
            json_encode($request->input('preferences')),
            'general'
        );

        return response()->json(['data' => ['saved' => true]]);
    }

    public function getPreferences(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $raw = SystemSetting::get('user_preferences_'.$user->id, '{}');
        $prefs = json_decode((string) $raw, true);

        return response()->json(['data' => ['preferences' => is_array($prefs) ? $prefs : []]]);
    }

    public function avatarUpload(Request $request): JsonResponse
    {
        $request->validate(['avatar' => 'required|image|max:2048']);
        /** @var User $user */
        $user = $request->user();
        $path = $request->file('avatar')->store('avatars', 'public');
        $user->update(['avatar_path' => $path]);

        return response()->json([
            'data' => [
                'avatar_path' => $path,
                'avatar_url' => Storage::disk('public')->url($path),
            ],
        ]);
    }

    public function pdfByToken(string $token): JsonResponse
    {
        $payload = Cache::get('pdf_download_token:'.$token);
        if (! is_array($payload) || empty($payload['path'])) {
            return response()->json(['data' => ['url' => null, 'message' => 'Invalid or expired token']], 404);
        }
        $disk = $payload['disk'] ?? 'public';
        $path = $payload['path'];
        if (! Storage::disk($disk)->exists($path)) {
            return response()->json(['data' => ['url' => null, 'message' => 'File no longer available']], 404);
        }

        return response()->json([
            'data' => [
                'url' => Storage::disk($disk)->url($path),
                'path' => $path,
            ],
        ]);
    }
}
