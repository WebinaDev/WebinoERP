<?php

namespace Modules\Core\Services;

use Modules\Core\Entities\CoreNotification;
use Modules\Core\Entities\FeatureFlag;
use Modules\Core\Entities\WorkflowDefinition;
use Modules\Core\Entities\WorkflowRun;
use Modules\Projects\Entities\ProjectTask;

class WorkflowRunner
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function run(WorkflowDefinition $workflow, array $context = []): WorkflowRun
    {
        $graph = $workflow->graph ?? [];
        $nodes = [];
        foreach ((array) ($graph['nodes'] ?? []) as $node) {
            if (is_array($node) && isset($node['id'])) {
                $nodes[(string) $node['id']] = $node;
            }
        }
        $edges = [];
        foreach ((array) ($graph['edges'] ?? []) as $edge) {
            if (is_array($edge) && isset($edge['from'], $edge['to'])) {
                $edges[(string) $edge['from']][] = (string) $edge['to'];
            }
        }
        $start = null;
        foreach ($nodes as $id => $node) {
            if (($node['type'] ?? '') === 'trigger') {
                $start = $id;
                break;
            }
        }
        $log = [];
        $status = 'done';
        if ($start === null) {
            $status = 'error';
            $log[] = ['error' => 'missing_trigger'];
        } else {
            $this->walk($start, $nodes, $edges, $context, $log, []);
        }

        return WorkflowRun::query()->create([
            'workflow_id' => $workflow->id,
            'status' => $status,
            'context' => $context,
            'log' => $log,
        ]);
    }

    /**
     * @param  array<string, array<string, mixed>>  $nodes
     * @param  array<string, list<string>>  $edges
     * @param  array<string, mixed>  $context
     * @param  list<array<string, mixed>>  $log
     * @param  list<string>  $seen
     */
    private function walk(string $id, array $nodes, array $edges, array $context, array &$log, array $seen): void
    {
        if (in_array($id, $seen, true) || ! isset($nodes[$id])) {
            return;
        }
        $seen[] = $id;
        $node = $nodes[$id];
        $type = (string) ($node['type'] ?? '');
        $config = is_array($node['config'] ?? null) ? $node['config'] : [];
        $passed = true;
        if ($type === 'condition') {
            $field = (string) ($config['field'] ?? '');
            $equals = $config['equals'] ?? null;
            $passed = data_get($context, $field) == $equals;
            $log[] = ['node' => $id, 'type' => 'condition', 'passed' => $passed];
        } elseif ($type === 'notify') {
            $userId = (int) ($config['user_id'] ?? ($context['user_id'] ?? 0));
            if ($userId > 0) {
                CoreNotification::query()->create([
                    'user_id' => $userId,
                    'type' => 'workflow',
                    'data' => ['title' => (string) ($config['title'] ?? $node['id']), 'body' => (string) ($config['body'] ?? '')],
                    'is_read' => false,
                ]);
            }
            $log[] = ['node' => $id, 'type' => 'notify', 'user_id' => $userId];
        } elseif ($type === 'create_task') {
            $task = ProjectTask::query()->create([
                'title' => (string) ($config['title'] ?? 'Workflow task'),
                'project_id' => $config['project_id'] ?? ($context['project_id'] ?? null),
                'status' => 'open',
                'created_by' => $context['user_id'] ?? null,
            ]);
            $log[] = ['node' => $id, 'type' => 'create_task', 'task_id' => $task->id];
        } elseif ($type === 'set_flag') {
            $key = (string) ($config['key'] ?? '');
            if ($key !== '') {
                FeatureFlag::query()->updateOrCreate(
                    ['key' => $key],
                    ['enabled' => (bool) ($config['enabled'] ?? true), 'rollout_percent' => (int) ($config['rollout_percent'] ?? 100)]
                );
            }
            $log[] = ['node' => $id, 'type' => 'set_flag', 'key' => $key];
        } else {
            $log[] = ['node' => $id, 'type' => $type ?: 'trigger'];
        }

        if (! $passed) {
            return;
        }
        foreach ($edges[$id] ?? [] as $next) {
            $this->walk($next, $nodes, $edges, $context, $log, $seen);
        }
    }
}
