<?php

namespace Modules\Core\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Modules\Core\Entities\FieldPermission;
use Modules\Core\Services\FieldSecurityService;

/**
 * Parity with webinocrm includes/class-field-security.php:
 *   - `webinocrm_update_field_permissions` (POST) → PUT /api/v1/core/field-permissions
 *   - Plus GET to read current matrix (webinocrm exposes it via filter `webinocrm_default_field_permissions`).
 */
class FieldPermissionsController extends Controller
{
    public function __construct(private FieldSecurityService $service) {}

    public function index(): JsonResponse
    {
        $rows = FieldPermission::query()
            ->orderBy('entity_type')
            ->orderBy('field_name')
            ->get()
            ->map(fn (FieldPermission $row) => [
                'id' => $row->id,
                'entity_type' => $row->entity_type,
                'field_name' => $row->field_name,
                'view_roles' => $row->view_roles ?: [],
                'edit_roles' => $row->edit_roles ?: [],
                'mask_view' => $row->mask_view,
                'mask_strategy' => $row->mask_strategy,
                'updated_at' => $row->updated_at,
            ])
            ->values();

        return response()->json([
            'data' => [
                'defaults' => FieldPermission::defaults(),
                'matrix' => FieldPermission::matrix(),
                'rows' => $rows,
            ],
        ]);
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user === null || ! $user->hasRole('system_manager')) {
            return response()->json([
                'data' => ['message' => 'دسترسی کافی ندارید'],
            ], 403);
        }

        $payload = $request->validate([
            'entity_type' => 'required|string|max:50',
            'field_name' => 'required|string|max:100',
            'permissions' => 'required|array',
            'permissions.view' => 'nullable|array',
            'permissions.view.*' => 'string|max:50',
            'permissions.edit' => 'nullable|array',
            'permissions.edit.*' => 'string|max:50',
            'permissions.mask_view' => 'nullable|boolean',
            'permissions.mask_strategy' => 'nullable|string|in:email,phone,bank,card',
        ]);

        $row = FieldPermission::query()->updateOrCreate(
            [
                'entity_type' => $payload['entity_type'],
                'field_name' => $payload['field_name'],
            ],
            [
                'view_roles' => $payload['permissions']['view'] ?? [],
                'edit_roles' => $payload['permissions']['edit'] ?? [],
                'mask_view' => (bool) ($payload['permissions']['mask_view'] ?? false),
                'mask_strategy' => $payload['permissions']['mask_strategy'] ?? null,
                'updated_by' => $user->id,
            ],
        );

        return response()->json([
            'data' => [
                'saved' => true,
                'message' => 'دسترسی‌ها بروزرسانی شد',
                'row' => $row->only(['id', 'entity_type', 'field_name', 'view_roles', 'edit_roles', 'mask_view', 'mask_strategy']),
            ],
        ]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        if ($user === null || ! $user->hasRole('system_manager')) {
            return response()->json([
                'data' => ['message' => 'دسترسی کافی ندارید'],
            ], 403);
        }
        $row = FieldPermission::query()->findOrFail($id);
        $row->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /**
     * GET /api/v1/core/field-permissions/viewable?entity=lead
     * Returns the list of fields the current user can view and edit for an entity.
     */
    public function viewable(Request $request): JsonResponse
    {
        $entity = (string) $request->query('entity', '');
        if ($entity === '') {
            return response()->json(['data' => ['viewable' => [], 'editable' => []]]);
        }
        $user = $request->user();
        $matrix = FieldPermission::matrix()[$entity] ?? FieldPermission::defaults()[$entity] ?? [];
        $viewable = [];
        $editable = [];
        foreach (array_keys($matrix) as $field) {
            if ($user && $this->service->canView($user, $entity, $field)) {
                $viewable[] = $field;
            }
            if ($user && $this->service->canEdit($user, $entity, $field)) {
                $editable[] = $field;
            }
        }

        return response()->json([
            'data' => [
                'entity' => $entity,
                'viewable' => $viewable,
                'editable' => $editable,
            ],
        ]);
    }
}
