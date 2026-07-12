<?php

namespace Modules\Crm\Http\Controllers;

use App\Support\AppliesIndexQuery;
use App\Support\BulkActionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmConsultation;
use Modules\Crm\Http\Requests\StoreAccountRequest;
use Modules\Crm\Http\Requests\UpdateAccountRequest;
use Modules\Crm\Services\DuplicateDetectionService;

class AccountController extends Controller
{
    use AppliesIndexQuery;

    public function index(Request $request): JsonResponse
    {
        $query = CrmAccount::query()->orderByDesc('created_at');
        $paginator = $this->applyIndexQuery(
            $query,
            $request,
            ['type' => 'type', 'owner_id' => 'owner_id'],
            ['name', 'email', 'phone'],
            ['name', 'created_at'],
        );

        return $this->paginatedJsonResponse($paginator);
    }

    public function store(StoreAccountRequest $request): JsonResponse
    {
        $account = CrmAccount::query()->create($request->validated() + ['created_by' => $request->user()->id]);

        return response()->json(['data' => $account], 201);
    }

    public function show(int $id): JsonResponse
    {
        $account = CrmAccount::query()->with(['contacts'])->findOrFail($id);

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

    public function duplicates(int $id, DuplicateDetectionService $duplicates): JsonResponse
    {
        $account = CrmAccount::query()->findOrFail($id);

        return response()->json(['data' => ['account_id' => $account->id, 'matches' => []]]);
    }
}
