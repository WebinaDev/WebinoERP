<?php

namespace Modules\Crm\Http\Controllers\Customer360;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Crm\Services\Automations\AutomationRegistry;
use Modules\Crm\Services\Hub\CustomerHubAccess;

/** GET/PATCH /crm/customers/automations (PHASE5_API.md §5). */
class CustomerAutomationsController extends Controller
{
    public function __construct(private CustomerHubAccess $access) {}

    public function index(Request $r): JsonResponse
    {
        abort_unless($this->access->canManage($r->user(), 'automations'), 403, __('Forbidden'));
        AutomationRegistry::flush();
        AutomationRegistry::all();

        return response()->json(['data' => array_map(fn ($k) => AutomationRegistry::row($k), array_keys(AutomationRegistry::DEFS))]);
    }

    public function update(Request $r, string $key): JsonResponse
    {
        abort_unless($this->access->canManage($r->user(), 'automations'), 403, __('Forbidden'));
        abort_unless(isset(AutomationRegistry::DEFS[$key]), 404);
        $v = $r->validate(['enabled' => 'nullable|boolean', 'config' => 'nullable|array']);
        AutomationRegistry::flush();
        $w = AutomationRegistry::all()[$key];
        $g = (array) $w->graph;
        if (isset($v['config'])) {
            $allowed = array_keys(AutomationRegistry::DEFS[$key]['config']);
            $g['config'] = array_replace((array) ($g['config'] ?? []), array_intersect_key($v['config'], array_flip($allowed)));
        }
        $w->update(['graph' => $g] + (array_key_exists('enabled', $v) && $v['enabled'] !== null ? ['status' => $v['enabled'] ? 'active' : 'inactive'] : []));
        AutomationRegistry::flush();

        return response()->json(['data' => AutomationRegistry::row($key)]);
    }
}
