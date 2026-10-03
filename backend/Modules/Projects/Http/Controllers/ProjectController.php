<?php

namespace Modules\Projects\Http\Controllers;

use App\Support\CalendarDate;
use App\Support\CustomerAccess;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Projects\Entities\CatalogProduct;
use Modules\Projects\Entities\Project;
use Modules\Projects\Services\ProjectPortalLinks;
use Modules\Projects\Services\ProjectProgress;
use Modules\Projects\Support\StatusMachine;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ProjectController extends Controller
{
    public function index(Request $request, CustomerAccess $access, ProjectProgress $progress, ProjectPortalLinks $links): JsonResponse
    {
        $query = Project::query()->orderByDesc('created_at');
        $access->scopeProjects($query, $request->user());
        if ($request->filled('search')) {
            $s = '%'.$request->string('search').'%';
            $query->where(function ($w) use ($s) {
                $w->where('name', 'like', $s)->orWhere('description', 'like', $s);
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->string('status'));
        }
        if ($request->filled('customer_account_id')) {
            $query->where('customer_account_id', $request->integer('customer_account_id'));
        }
        $perPage = min(max((int) $request->input('per_page', 15), 1), 100);
        $paginator = $query->paginate($perPage);
        $progress->decorate($paginator->getCollection());
        $links->attach($paginator->getCollection());

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, Project $project, CustomerAccess $access, ProjectProgress $progress): JsonResponse
    {
        $this->visibleProject($request, $project, $access);
        $progress->decorate([$project]);

        return response()->json(['data' => $project]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:50',
            'customer_account_id' => 'nullable|exists:crm_accounts,id',
            'manager_user_id' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date_format:Y-m-d',
            'due_date' => 'nullable|date_format:Y-m-d|after_or_equal:start_date',
            'is_template' => 'nullable|boolean',
        ]);
        $data['created_by'] = $request->user()->id;
        $data['status'] = $data['status'] ?? 'active';
        StatusMachine::assert(null, $data['status'], 'project');
        $p = Project::query()->create($data);

        return response()->json(['data' => $p], 201);
    }

    public function update(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        $p = $this->findVisible($request, $id, $access);
        abort_if($access->isPortalCustomer($request->user()), 403);
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'nullable|string',
            'status' => 'nullable|string|max:50',
            'customer_account_id' => 'nullable|exists:crm_accounts,id',
            'manager_user_id' => 'nullable|exists:users,id',
            'start_date' => 'nullable|date_format:Y-m-d',
            'due_date' => 'nullable|date',
            'is_template' => 'nullable|boolean',
        ]);
        if (! empty($data['status'])) {
            StatusMachine::assert($p->status, $data['status'], 'project');
        }
        $p->update($data);

        return response()->json(['data' => $p->fresh()]);
    }

    public function destroy(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        abort_if($access->isPortalCustomer($request->user()), 403);
        $this->findVisible($request, $id, $access)->delete();

        return response()->json([], 204);
    }

    public function templates(Request $request, CustomerAccess $access): JsonResponse
    {
        abort_if($access->isPortalCustomer($request->user()), 403);

        return response()->json([
            'data' => Project::query()->where('is_template', true)->orderBy('name')->get(),
        ]);
    }

    public function assignees(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        abort_if($access->isPortalCustomer($request->user()), 403);
        $this->findVisible($request, $id, $access);

        return response()->json(['data' => DB::table('users')->select('id', 'name', 'email')->limit(50)->get()]);
    }

    public function productProjectsPreview(Request $request, int $id, CustomerAccess $access): JsonResponse
    {
        $product = CatalogProduct::query()->findOrFail($id);
        $projects = Project::query()
            ->whereHas('tasks', function ($q) use ($product) {
                $q->where('title', 'like', '%'.$product->name.'%');
            })
            ->orderByDesc('id')
            ->limit(50);
        $access->scopeProjects($projects, $request->user());

        return response()->json(['data' => [
            'product_id' => $id,
            'projects' => $projects->get(['id', 'name', 'status', 'customer_account_id']),
        ]]);
    }

    public function assignableUsers(Request $request, CustomerAccess $access): JsonResponse
    {
        abort_if($access->isPortalCustomer($request->user()), 403);
        $users = DB::table('users')->select('id', 'name', 'email')->orderBy('name')->limit(200)->get();

        return response()->json(['data' => $users]);
    }

    public function details(Request $request, int $id, CustomerAccess $access, ProjectProgress $progress, ProjectPortalLinks $links): JsonResponse
    {
        $p = $this->findVisible($request, $id, $access);
        $p->load(['tasks', 'contracts', 'tickets', 'milestones', 'account:id,name,website']);
        $progress->decorate([$p]);
        $links->attach([$p]);

        return response()->json(['data' => $p]);
    }

    public function export(Request $request, CustomerAccess $access): StreamedResponse
    {
        $locale = CalendarDate::localeFromRequest($request);
        $filename = 'projects-'.now()->format('Y-m-d_His').'.csv';

        return response()->streamDownload(function () use ($access, $request, $locale) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['id', 'name', 'status', 'customer_account_id', 'created_at', 'created_at_display', 'progress_percent']);

            $query = Project::query()->orderBy('id');
            $access->scopeProjects($query, $request->user());
            $progress = app(ProjectProgress::class);
            $query->chunk(200, function ($chunk) use ($out, $locale, $progress) {
                $progress->decorate($chunk);
                foreach ($chunk as $p) {
                    fputcsv($out, [
                        $p->id,
                        $p->name,
                        $p->status,
                        $p->customer_account_id,
                        optional($p->created_at)->utc()->toIso8601String(),
                        CalendarDate::format($p->created_at, $locale, true),
                        $p->progress_percent,
                    ]);
                }
            });

            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    private function findVisible(Request $request, int $id, CustomerAccess $access): Project
    {
        $query = Project::query()->whereKey($id);
        $access->scopeProjects($query, $request->user());

        return $query->firstOrFail();
    }

    private function visibleProject(Request $request, Project $project, CustomerAccess $access): void
    {
        if (! $access->isPortalCustomer($request->user())) {
            return;
        }
        $ids = $access->accountIds($request->user());
        abort_unless(in_array((int) $project->customer_account_id, $ids, true), 404);
    }
}
