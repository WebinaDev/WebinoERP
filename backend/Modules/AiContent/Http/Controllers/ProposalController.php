<?php

namespace Modules\AiContent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AiContent\Entities\AiJob;
use Modules\AiContent\Entities\AiProduct;
use Modules\AiContent\Entities\AiProposal;

class ProposalController extends Controller
{
    public function index(Request $request, string $kind): JsonResponse
    {
        $status = $request->string('status', 'pending')->toString();
        $limit = min(500, max(1, $request->integer('limit', 100)));
        $items = AiProposal::query()
            ->where('kind', $kind)
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->limit($limit)
            ->get()
            ->map(fn (AiProposal $p) => $this->serialize($p))
            ->values();

        return response()->json([
            'items' => $items,
            'total' => AiProposal::query()->where('kind', $kind)->when($status !== '', fn ($q) => $q->where('status', $status))->count(),
        ]);
    }

    public function enqueue(Request $request, string $kind): JsonResponse
    {
        $ids = $request->input('ids');
        $products = AiProduct::query()
            ->when(is_array($ids) && $ids !== [], fn ($q) => $q->whereIn('id', $ids))
            ->limit(100)
            ->get();

        if ($products->isEmpty()) {
            // Create demo proposals when no products exist yet
            $demo = AiProduct::query()->create([
                'name' => 'Sample product',
                'status' => 'incomplete',
                'missing' => ['title'],
            ]);
            $products = collect([$demo]);
        }

        $jobIds = [];
        foreach ($products as $product) {
            $proposed = $kind === 'title'
                ? ['name' => '[AI] '.$product->name]
                : ['categories' => ['Sample category'], 'brands' => ['Sample brand']];

            AiProposal::query()->updateOrCreate(
                ['kind' => $kind, 'product_id' => $product->id],
                [
                    'product_name' => $product->name,
                    'current_json' => ['name' => $product->name],
                    'proposed_json' => $proposed,
                    'status' => 'pending',
                ],
            );

            $job = AiJob::query()->create([
                'job_type' => $kind === 'title' ? 'title' : 'catalog',
                'target_type' => 'product',
                'target_id' => $product->id,
                'payload' => ['kind' => $kind, 'title' => $product->name],
                'status' => 'done',
                'provider' => 'stub',
                'model' => 'placeholder',
                'result_summary' => 'Stub proposal created',
                'started_at' => now(),
                'finished_at' => now(),
                'attempts' => 1,
            ]);
            $jobIds[] = $job->id;
        }

        return response()->json([
            'ok' => true,
            'job_ids' => $jobIds,
            'count' => count($jobIds),
            'chunks' => 1,
        ]);
    }

    public function apply(Request $request, int $id): JsonResponse
    {
        $proposal = AiProposal::query()->findOrFail($id);
        $data = $request->validate([
            'name' => 'nullable|string|max:255',
            'proposed' => 'nullable|array',
        ]);

        $proposed = $proposal->proposed_json ?? [];
        if (isset($data['name'])) {
            $proposed['name'] = $data['name'];
        }
        if (isset($data['proposed'])) {
            $proposed = array_merge($proposed, $data['proposed']);
        }

        if ($proposal->kind === 'title' && $proposal->product_id) {
            AiProduct::query()->whereKey($proposal->product_id)->update([
                'name' => $proposed['name'] ?? $proposal->product_name,
            ]);
        }

        $proposal->update([
            'proposed_json' => $proposed,
            'status' => 'applied',
            'product_name' => $proposed['name'] ?? $proposal->product_name,
        ]);

        return response()->json($this->serialize($proposal->fresh()));
    }

    public function skip(int $id): JsonResponse
    {
        $proposal = AiProposal::query()->findOrFail($id);
        $proposal->update(['status' => 'skipped']);

        return response()->json($this->serialize($proposal->fresh()));
    }

    public function requeue(string $kind, int $productId): JsonResponse
    {
        AiProposal::query()->where('kind', $kind)->where('product_id', $productId)->delete();
        $product = AiProduct::query()->find($productId);
        if (! $product) {
            return response()->json(['ok' => false, 'count' => 0], 404);
        }

        $request = Request::create('/', 'POST', ['ids' => [$productId]]);
        $this->enqueue($request, $kind);

        return response()->json(['ok' => true, 'count' => 1]);
    }

    private function serialize(AiProposal $p): array
    {
        return [
            'id' => $p->id,
            'kind' => $p->kind,
            'product_id' => $p->product_id,
            'product_name' => $p->product_name,
            'current' => $p->current_json ?? [],
            'proposed' => $p->proposed_json ?? [],
            'status' => $p->status,
            'created_at' => optional($p->created_at)?->toIso8601String(),
            'updated_at' => optional($p->updated_at)?->toIso8601String(),
        ];
    }
}
