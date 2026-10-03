<?php

namespace Modules\Projects\Http\Controllers;

use App\Support\CustomerAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Modules\Projects\Entities\PrjAppointment;
use Modules\Projects\Entities\PrjTicket;
use Modules\Projects\Entities\PrjTicketReply;
use Modules\Projects\Entities\Project;
use Modules\Projects\Services\ProjectProgress;
use Modules\Projects\Support\StatusMachine;
use Modules\SiteBuilder\Entities\WebinoSiteProvision;

class CustomerPortalController extends Controller
{
    public function summary(Request $request, CustomerAccess $access, ProjectProgress $progress): JsonResponse
    {
        $user = $this->customer($request, $access);
        $account = $user->crmAccounts()->orderByDesc('crm_account_users.is_primary')->first();

        $projects = Project::query()->orderByDesc('id');
        $access->scopeProjects($projects, $user);
        $projectRows = $projects->limit(20)->get();
        $progress->decorate($projectRows);

        $openTickets = PrjTicket::query();
        $access->scopeTickets($openTickets, $user);

        $upcoming = PrjAppointment::query()->where('starts_at', '>=', now())->orderBy('starts_at');
        $access->scopeAppointments($upcoming, $user);

        return response()->json([
            'data' => [
                'account' => $account ? [
                    'id' => $account->id,
                    'name' => $account->name,
                    'website' => $account->website,
                    'type' => $account->type,
                ] : null,
                'projects' => $projectRows->map(fn (Project $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'status' => $p->status,
                    'progress_percent' => $p->progress_percent,
                    'start_date' => optional($p->start_date)->toDateString(),
                    'due_date' => optional($p->due_date)->toDateString(),
                    'updated_at' => optional($p->updated_at)->utc()->toIso8601String(),
                ])->values(),
                'open_tickets' => (clone $openTickets)->whereIn('status', ['open', 'pending', 'in_progress'])->count(),
                'tickets' => $openTickets->orderByDesc('id')->limit(10)->get([
                    'id', 'subject', 'status', 'priority', 'project_id', 'created_at', 'updated_at',
                ]),
                'appointments' => $upcoming->limit(10)->get([
                    'id', 'title', 'status', 'starts_at', 'ends_at', 'customer_account_id',
                ]),
                'sites' => $this->sitesFor($access->accountIds($user)),
            ],
        ]);
    }

    public function storeTicket(Request $request, CustomerAccess $access): JsonResponse
    {
        $user = $this->customer($request, $access);
        $data = $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'nullable|string|max:5000',
            'project_id' => 'nullable|integer',
            'priority' => 'nullable|string|max:20',
        ]);
        $accountIds = $access->accountIds($user);
        $projectId = $data['project_id'] ?? null;
        if ($projectId) {
            $owns = Project::query()->whereKey($projectId)->whereIn('customer_account_id', $accountIds)->exists();
            abort_unless($owns, 422, 'Project is not linked to this customer.');
        }

        $ticket = PrjTicket::query()->create([
            'subject' => $data['subject'],
            'body' => $data['body'] ?? null,
            'status' => 'open',
            'priority' => $data['priority'] ?? 'normal',
            'customer_user_id' => $user->id,
            'customer_account_id' => $accountIds[0] ?? null,
            'project_id' => $projectId,
        ]);

        return response()->json(['data' => $ticket], 201);
    }

    public function reply(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        $user = $this->customer($request, $access);
        $ticket = PrjTicket::query()->whereKey($id);
        $access->scopeTickets($ticket, $user);
        $ticket = $ticket->firstOrFail();
        abort_if(in_array($ticket->status, ['closed'], true), 422, 'Ticket is closed.');

        $data = $request->validate(['body' => 'required|string|max:5000']);
        $reply = PrjTicketReply::query()->create([
            'ticket_id' => $ticket->id,
            'user_id' => $user->id,
            'body' => $data['body'],
        ]);
        if ($ticket->status === 'resolved') {
            $ticket->update(['status' => 'open']);
        }

        return response()->json(['data' => $reply], 201);
    }

    public function requestAppointment(Request $request, CustomerAccess $access): JsonResponse
    {
        $user = $this->customer($request, $access);
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'starts_at' => 'required|date',
            'ends_at' => 'nullable|date|after_or_equal:starts_at',
            'notes' => 'nullable|string|max:2000',
        ]);
        $accountIds = $access->accountIds($user);
        StatusMachine::assert(null, 'requested', 'appointment');

        $appointment = PrjAppointment::query()->create([
            'title' => $data['title'],
            'starts_at' => $data['starts_at'],
            'ends_at' => $data['ends_at'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => 'requested',
            'customer_user_id' => $user->id,
            'customer_account_id' => $accountIds[0] ?? null,
            'created_by' => $user->id,
        ]);

        return response()->json(['data' => $appointment], 201);
    }

    private function customer(Request $request, CustomerAccess $access): \App\Models\User
    {
        $user = $request->user();
        abort_unless($access->isPortalCustomer($user), 403);

        return $user;
    }

    /**
     * @param  list<int>  $accountIds
     * @return list<array<string, mixed>>
     */
    private function sitesFor(array $accountIds): array
    {
        if ($accountIds === [] || ! Schema::hasTable('webino_site_provisions')) {
            return [];
        }

        return WebinoSiteProvision::query()
            ->whereIn('crm_account_id', $accountIds)
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'domain', 'slug', 'status', 'subdomain', 'crm_account_id'])
            ->map(fn (WebinoSiteProvision $site) => [
                'id' => $site->id,
                'domain' => $site->domain,
                'slug' => $site->slug,
                'status' => $site->status,
                'subdomain' => $site->subdomain,
                'crm_account_id' => $site->crm_account_id,
            ])
            ->all();
    }
}
