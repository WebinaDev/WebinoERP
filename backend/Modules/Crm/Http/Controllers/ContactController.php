<?php

namespace Modules\Crm\Http\Controllers;

use App\Http\Controllers\Api\PaginatesApi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Crm\Entities\CrmContact;

class ContactController extends Controller
{
    use PaginatesApi;

    public function index(Request $request): JsonResponse
    {
        $query = CrmContact::query()->with('account');
        $paginator = $this->applyIndexQuery(
            $query,
            $request,
            ['account_id' => 'account_id'],
            ['first_name', 'last_name', 'email', 'mobile'],
            ['created_at', 'first_name', 'last_name'],
        );

        return $this->paginatedResponse($paginator);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'account_id' => 'required|exists:crm_accounts,id',
            'first_name' => 'required|string|max:100',
            'last_name' => 'required|string|max:100',
            'email' => 'nullable|email|max:150',
            'mobile' => 'nullable|string|max:20',
            'job_title' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'is_primary' => 'nullable|boolean',
        ]);
        $data['created_by'] = $request->user()->id;
        if (! empty($data['is_primary'])) {
            CrmContact::query()->where('account_id', $data['account_id'])->update(['is_primary' => false]);
        }
        $contact = CrmContact::create($data);

        return response()->json(['data' => $contact->load('account'), 'message' => 'Contact created'], 201);
    }

    public function show(CrmContact $contact): JsonResponse
    {
        $contact->load('account');

        return response()->json(['data' => $contact]);
    }

    public function update(Request $request, CrmContact $contact): JsonResponse
    {
        $data = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name' => 'sometimes|string|max:100',
            'email' => 'nullable|email|max:150',
            'mobile' => 'nullable|string|max:20',
            'job_title' => 'nullable|string|max:100',
            'description' => 'nullable|string',
            'is_primary' => 'nullable|boolean',
        ]);
        if (! empty($data['is_primary'])) {
            CrmContact::query()->where('account_id', $contact->account_id)->where('id', '!=', $contact->id)->update(['is_primary' => false]);
        }
        $contact->update($data);

        return response()->json(['data' => $contact->fresh('account'), 'message' => 'Contact updated']);
    }

    public function destroy(CrmContact $contact): JsonResponse
    {
        $contact->delete();

        return response()->noContent();
    }
}
