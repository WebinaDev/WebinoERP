<?php

namespace Modules\Crm\Http\Controllers;

use App\Models\User;
use App\Support\AppliesIndexQuery;
use App\Support\BulkActionRequest;
use App\Support\CustomerAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Modules\Core\Database\Seeders\RolesAndPermissionsSeeder;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmConsultation;
use Modules\Crm\Entities\CrmContact;
use Modules\Crm\Http\Requests\StoreAccountRequest;
use Modules\Crm\Http\Requests\UpdateAccountRequest;
use Modules\Crm\Services\DuplicateDetectionService;

class AccountController extends Controller
{
    use AppliesIndexQuery;

    public function index(Request $request): JsonResponse
    {
        $query = CrmAccount::query()
            ->with('owner:id,name')
            ->withCount(['projects', 'tickets'])
            ->addSelect([
                'contact_phone' => CrmContact::query()
                    ->selectRaw("COALESCE(NULLIF(phone, ''), NULLIF(mobile, ''))")
                    ->whereColumn('crm_contacts.account_id', 'crm_accounts.id')
                    ->orderByDesc('is_primary')
                    ->limit(1),
            ]);
        if (Schema::hasTable('webino_site_provisions')) {
            $query->withCount('siteProvisions');
        }
        if ($request->filled('search')) {
            $term = '%'.$request->string('search').'%';
            $query->where(function ($q) use ($term) {
                $q->where('crm_accounts.name', 'like', $term)
                    ->orWhere('crm_accounts.website', 'like', $term)
                    ->orWhereHas('contacts', function ($contacts) use ($term) {
                        $contacts->where('phone', 'like', $term)
                            ->orWhere('mobile', 'like', $term)
                            ->orWhere('email', 'like', $term)
                            ->orWhere('first_name', 'like', $term)
                            ->orWhere('last_name', 'like', $term);
                    });
            });
            $request->merge(['search' => null]);
        }
        $query->orderByDesc('created_at');
        $paginator = $this->applyIndexQuery(
            $query,
            $request,
            ['type' => 'type', 'owner_id' => 'owner_id'],
            [],
            ['name', 'created_at'],
        );

        return $this->paginatedJsonResponse($paginator);
    }

    public function store(StoreAccountRequest $request): JsonResponse
    {
        $payload = $request->validated() + ['created_by' => $request->user()?->id];
        $payload['type'] = $payload['type'] ?: 'customer';

        $account = CrmAccount::query()->create($payload);

        return response()->json(['data' => $account], 201);
    }

    public function show(int $id): JsonResponse
    {
        $account = CrmAccount::query()->with(['contacts', 'portalUsers:id,name,email'])->withCount(['projects', 'tickets'])->findOrFail($id);

        return response()->json(['data' => $account]);
    }

    public function update(UpdateAccountRequest $request, int $id): JsonResponse
    {
        $account = CrmAccount::query()->findOrFail($id);
        $account->update($request->validated());

        return response()->json(['data' => $account->fresh()]);
    }

    public function destroy(int $id): JsonResponse
    {
        CrmAccount::query()->whereKey($id)->delete();

        return response()->noContent();
    }

    public function bulkDelete(BulkActionRequest $request): JsonResponse
    {
        CrmAccount::query()->whereIn('id', $request->validated('ids'))->delete();

        return response()->json(['data' => ['deleted' => count($request->validated('ids'))]]);
    }

    public function portalAccess(Request $request, int $id): JsonResponse
    {
        $account = CrmAccount::query()->findOrFail($id);
        $data = $request->validate([
            'email' => 'required|email|max:150',
            'name' => 'required|string|max:150',
            'password' => 'nullable|string|min:8|max:100',
        ]);

        $user = User::query()->where('email', $data['email'])->first();
        $temporary = null;
        $created = false;
        if (! $user) {
            $temporary = $data['password'] ?: Str::password(12);
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $temporary,
            ]);
            $user->forceFill([
                'is_active' => true,
                'email_verified_at' => now(),
            ])->save();
            $created = true;
        } else {
            foreach (CustomerAccess::STAFF_ROLES as $role) {
                if ($user->hasRole($role)) {
                    throw ValidationException::withMessages([
                        'email' => 'Staff accounts cannot be used as customer portal logins.',
                    ]);
                }
            }
        }

        if (! $user->hasRole(RolesAndPermissionsSeeder::ROLE_CLIENT)) {
            $user->assignRole(RolesAndPermissionsSeeder::ROLE_CLIENT);
        }

        $isPrimary = ! $account->portalUsers()->exists();
        $account->portalUsers()->syncWithoutDetaching([
            $user->id => ['is_primary' => $isPrimary],
        ]);

        return response()->json([
            'data' => [
                'user_id' => $user->id,
                'email' => $user->email,
                'name' => $user->name,
                'account_id' => $account->id,
                'temporary_password' => $temporary,
            ],
        ], $created ? 201 : 200);
    }

    public function duplicates(int $id, DuplicateDetectionService $duplicates): JsonResponse
    {
        $account = CrmAccount::query()->findOrFail($id);

        return response()->json(['data' => ['account_id' => $account->id, 'matches' => []]]);
    }
}
