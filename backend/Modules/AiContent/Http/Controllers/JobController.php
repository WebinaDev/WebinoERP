<?php

namespace Modules\AiContent\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\AiContent\Entities\AiJob;
use Modules\AiContent\Entities\AiSetting;

class JobController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = AiJob::query()->orderByDesc('id');
        if ($status = $request->string('status')->toString()) {
            $query->where('status', $status);
        }
        $limit = min(200, max(1, $request->integer('limit', 50)));
        $items = $query->limit($limit)->get()->map(fn (AiJob $job) => $this->serialize($job))->values();

        return response()->json([
            'items' => $items,
            'total' => AiJob::query()->when($status, fn ($q) => $q->where('status', $status))->count(),
        ]);
    }

    public function show(int $id): JsonResponse
    {
        $job = AiJob::query()->findOrFail($id);

        return response()->json($this->serialize($job));
    }

    public function retry(int $id): JsonResponse
    {
        $job = AiJob::query()->findOrFail($id);
        $job->update([
            'status' => 'pending',
            'error_message' => null,
            'started_at' => null,
            'finished_at' => null,
        ]);

        return response()->json(['ok' => true]);
    }

    public function cancel(int $id): JsonResponse
    {
        $job = AiJob::query()->findOrFail($id);
        $job->update([
            'status' => 'cancelled',
            'finished_at' => now(),
        ]);

        return response()->json(['ok' => true, 'job' => $this->serialize($job->fresh())]);
    }

    public function cancelPending(): JsonResponse
    {
        $count = AiJob::query()->where('status', 'pending')->update([
            'status' => 'cancelled',
            'finished_at' => now(),
        ]);

        return response()->json(['ok' => true, 'count' => $count]);
    }

    public function run(int $id): JsonResponse
    {
        $job = AiJob::query()->findOrFail($id);
        $this->completeStub($job);

        return response()->json([
            'ok' => true,
            'job' => $this->serialize($job->fresh()),
            'accepted' => true,
        ]);
    }

    public function runDue(Request $request): JsonResponse
    {
        $settings = AiSetting::query()->where('key', 'main')->first();
        $paused = (bool) (($settings?->value['queue_paused'] ?? false));
        if ($paused) {
            return response()->json(['processed' => [], 'count' => 0, 'paused' => true]);
        }

        $limit = min(20, max(1, $request->integer('limit', 1)));
        $jobs = AiJob::query()->where('status', 'pending')->orderBy('id')->limit($limit)->get();
        $processed = [];
        foreach ($jobs as $job) {
            $this->completeStub($job);
            $processed[] = $job->id;
        }

        return response()->json(['processed' => $processed, 'count' => count($processed)]);
    }

    public function generate(Request $request): JsonResponse
    {
        $data = $request->validate([
            'type' => 'required|string|max:64',
            'id' => 'nullable|integer',
            'sync' => 'nullable|boolean',
            'run_now' => 'nullable|boolean',
            'page_prompt' => 'nullable|string',
        ]);

        $job = AiJob::query()->create([
            'job_type' => $data['type'],
            'target_type' => $data['type'],
            'target_id' => (int) ($data['id'] ?? 0),
            'payload' => $data,
            'status' => 'pending',
            'provider' => 'stub',
            'model' => 'placeholder',
        ]);

        $runNow = (bool) ($data['sync'] ?? false) || (bool) ($data['run_now'] ?? false);
        if ($runNow) {
            $this->completeStub($job);
        }

        return response()->json([
            'ok' => true,
            'job_id' => $job->id,
            'queued' => ! $runNow,
            'job' => $this->serialize($job->fresh()),
        ]);
    }

    private function completeStub(AiJob $job): void
    {
        $job->update([
            'status' => 'done',
            'provider' => $job->provider ?: 'stub',
            'model' => $job->model ?: 'placeholder',
            'tokens_in' => 100,
            'tokens_out' => 200,
            'cost_toman' => 0,
            'result_summary' => 'Placeholder content generated (no AI provider configured).',
            'attempts' => $job->attempts + 1,
            'started_at' => now(),
            'finished_at' => now(),
        ]);
    }

    private function serialize(AiJob $job): array
    {
        return [
            'id' => $job->id,
            'job_type' => $job->job_type,
            'target_type' => $job->target_type,
            'target_id' => $job->target_id,
            'target_title' => is_array($job->payload) ? (string) ($job->payload['title'] ?? '') : '',
            'status' => $job->status,
            'provider' => $job->provider,
            'model' => $job->model,
            'error_message' => (string) ($job->error_message ?? ''),
            'result_summary' => (string) ($job->result_summary ?? ''),
            'attempts' => $job->attempts,
            'tokens_in' => $job->tokens_in,
            'tokens_out' => $job->tokens_out,
            'cost_toman' => (float) $job->cost_toman,
            'cost_estimated' => false,
            'created_at' => optional($job->created_at)?->toIso8601String(),
            'started_at' => optional($job->started_at)?->toIso8601String(),
            'updated_at' => optional($job->updated_at)?->toIso8601String(),
            'finished_at' => optional($job->finished_at)?->toIso8601String(),
        ];
    }
}
