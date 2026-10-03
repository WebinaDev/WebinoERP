<?php

namespace Modules\Projects\Services;

/**
 * Finish-to-start critical path in whole days.
 *
 * @phpstan-type TaskIn array{id: int|string, title?: string, duration_days?: int|null, start?: string|null}
 * @phpstan-type LinkIn array{source_id: int|string, target_id: int|string, type?: string|null}
 */
class CriticalPathService
{
    /**
     * @param  list<TaskIn>  $tasks
     * @param  list<LinkIn>  $links
     * @return array{ok: bool, error?: string, project_duration: int, tasks: list<array<string, mixed>>, critical_ids: list<int|string>}
     */
    public function compute(array $tasks, array $links): array
    {
        $nodes = [];
        foreach ($tasks as $task) {
            $id = $task['id'];
            $duration = max(1, (int) ($task['duration_days'] ?? 1));
            $nodes[$id] = [
                'id' => $id,
                'title' => (string) ($task['title'] ?? $id),
                'duration' => $duration,
                'predecessors' => [],
                'successors' => [],
            ];
        }

        foreach ($links as $link) {
            $type = strtolower((string) ($link['type'] ?? 'finish_to_start'));
            if (! in_array($type, ['finish_to_start', 'fs', 'blocks'], true)) {
                continue;
            }
            $from = $link['source_id'];
            $to = $link['target_id'];
            if (! isset($nodes[$from], $nodes[$to]) || $from === $to) {
                continue;
            }
            $nodes[$to]['predecessors'][] = $from;
            $nodes[$from]['successors'][] = $to;
        }

        $order = $this->topo($nodes);
        if ($order === null) {
            return [
                'ok' => false,
                'error' => 'cycle',
                'project_duration' => 0,
                'tasks' => [],
                'critical_ids' => [],
            ];
        }

        foreach ($order as $id) {
            $es = 0;
            foreach ($nodes[$id]['predecessors'] as $pred) {
                $es = max($es, $nodes[$pred]['ef']);
            }
            $nodes[$id]['es'] = $es;
            $nodes[$id]['ef'] = $es + $nodes[$id]['duration'];
        }

        $project = 0;
        foreach ($nodes as $node) {
            $project = max($project, $node['ef']);
        }

        foreach (array_reverse($order) as $id) {
            if ($nodes[$id]['successors'] === []) {
                $lf = $project;
            } else {
                $lf = $project;
                foreach ($nodes[$id]['successors'] as $succ) {
                    $lf = min($lf, $nodes[$succ]['ls']);
                }
            }
            $nodes[$id]['lf'] = $lf;
            $nodes[$id]['ls'] = $lf - $nodes[$id]['duration'];
            $nodes[$id]['slack'] = $nodes[$id]['ls'] - $nodes[$id]['es'];
            $nodes[$id]['critical'] = $nodes[$id]['slack'] === 0;
        }

        $out = [];
        $critical = [];
        foreach ($order as $id) {
            $node = $nodes[$id];
            unset($node['predecessors'], $node['successors']);
            $out[] = $node;
            if ($node['critical']) {
                $critical[] = $id;
            }
        }

        return [
            'ok' => true,
            'project_duration' => $project,
            'tasks' => $out,
            'critical_ids' => $critical,
        ];
    }

    /**
     * @param  array<int|string, array<string, mixed>>  $nodes
     * @return list<int|string>|null
     */
    private function topo(array $nodes): ?array
    {
        $indegree = [];
        foreach ($nodes as $id => $node) {
            $indegree[$id] = count($node['predecessors']);
        }
        $queue = [];
        foreach ($indegree as $id => $degree) {
            if ($degree === 0) {
                $queue[] = $id;
            }
        }
        $order = [];
        while ($queue !== []) {
            $id = array_shift($queue);
            $order[] = $id;
            foreach ($nodes[$id]['successors'] as $succ) {
                $indegree[$succ]--;
                if ($indegree[$succ] === 0) {
                    $queue[] = $succ;
                }
            }
        }

        return count($order) === count($nodes) ? $order : null;
    }
}
