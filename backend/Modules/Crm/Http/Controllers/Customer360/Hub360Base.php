<?php

namespace Modules\Crm\Http\Controllers\Customer360;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Services\Hub\CustomerHubAccess;
use Modules\Crm\Services\Hub\CustomerHubService;

/** Shared helpers for the Customer 360 phase 3 hub endpoints. */
abstract class Hub360Base extends Controller
{
    public function __construct(protected CustomerHubAccess $access, protected CustomerHubService $hub) {}

    protected function account(Request $r, int $id, string $section): CrmAccount
    {
        abort_unless($r->user() && $this->access->canView($r->user(), $section), 403, __('Forbidden'));
        $a = CrmAccount::query()->findOrFail($id);
        abort_if(! empty($a->merged_into_id), 404);

        return $a;
    }

    protected function money(Request $r): bool
    {
        return $this->access->canView($r->user(), 'billing');
    }

    protected function m(bool $show, $v): ?float
    {
        return $show && $v !== null ? round((float) $v, 2) : null;
    }

    protected function iso($v): ?string
    {
        return $v ? Carbon::parse($v)->toIso8601String() : null;
    }

    /** @return array{0: array, 1: array} */
    protected function page(Request $r, Collection $rows): array
    {
        $per = min(100, max(1, (int) $r->query('per_page', 20)));
        $page = max(1, (int) $r->query('page', 1));
        $total = $rows->count();

        return [$rows->slice(($page - 1) * $per, $per)->values()->all(), [
            'total' => $total, 'per_page' => $per, 'current_page' => $page, 'last_page' => max(1, (int) ceil($total / $per)),
        ]];
    }
}
