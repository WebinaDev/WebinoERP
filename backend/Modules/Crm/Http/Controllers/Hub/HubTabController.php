<?php

namespace Modules\Crm\Http\Controllers\Hub;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\MutationAudit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Services\Hub\CustomerHubAccess;

/** Shared plumbing for the Customer 360 tab controllers (documents / contracts / projects). */
abstract class HubTabController extends Controller
{
    public function __construct(protected CustomerHubAccess $access) {}

    /** @var array<string,bool> */
    private static array $colCache = [];

    protected function account(int $id): CrmAccount
    {
        return CrmAccount::query()->findOrFail($id);
    }

    protected function gate(Request $r, string $section): void
    {
        abort_unless($this->access->canView($r->user(), $section), 403, __('Forbidden'));
    }

    protected function gateManage(Request $r, string $action): void
    {
        abort_unless($this->access->canManage($r->user(), $action), 403, __('Forbidden'));
    }

    protected function canManage(Request $r, string $action): bool
    {
        return $this->access->canManage($r->user(), $action);
    }

    /** Money fields are hidden from users without the billing section. */
    protected function seesMoney(Request $r): bool
    {
        return $this->access->canView($r->user(), 'billing');
    }

    protected function has(string $table, ?string $col = null): bool
    {
        $k = $table.'.'.($col ?? '*');
        if (! array_key_exists($k, self::$colCache)) {
            self::$colCache[$k] = $col === null ? Schema::hasTable($table) : Schema::hasTable($table) && Schema::hasColumn($table, $col);
        }

        return self::$colCache[$k];
    }

    /** Drop keys whose column does not exist yet (Phase 0 may not be merged). */
    protected function onlyColumns(string $table, array $data): array
    {
        return array_filter($data, fn ($k) => $this->has($table, $k), ARRAY_FILTER_USE_KEY);
    }

    protected function audit(Request $r, CrmAccount $a, string $action, array $changes = []): void
    {
        DB::table('crm_customer_audit')->insert([
            'account_id' => $a->id, 'user_id' => $r->user()?->id, 'action' => $action,
            'changes' => json_encode($changes, JSON_UNESCAPED_UNICODE), 'ip' => $r->ip(), 'created_at' => now(),
        ]);
        MutationAudit::record($r->user()?->id, 'crm', 'customer_hub.'.$action, CrmAccount::class, $a->id, $changes);
        if ($action !== 'document.download') {
            try {
                event(new \Modules\Crm\Events\CustomerHubUpdated($a->id, $action, $r->user()?->id));
            } catch (\Throwable) {
                // broadcasting is best-effort; clients also poll
            }
        }
    }

    protected function perPage(Request $r): int
    {
        return max(1, min(100, (int) $r->query('per_page', 15)));
    }

    /** Apply `sort=-created_at` style sorting limited to an allow-list. */
    protected function sort($q, Request $r, array $allowed, string $default = '-id')
    {
        $s = (string) $r->query('sort', $default);
        $dir = str_starts_with($s, '-') ? 'desc' : 'asc';
        $col = ltrim($s, '-');
        if (! in_array($col, $allowed, true)) {
            $col = ltrim($default, '-');
            $dir = str_starts_with($default, '-') ? 'desc' : 'asc';
        }

        return $q->orderBy($col, $dir)->orderBy('id', 'desc');
    }

    protected function meta(LengthAwarePaginator $p, array $extra = []): array
    {
        return ['current_page' => $p->currentPage(), 'per_page' => $p->perPage(), 'total' => $p->total(), 'last_page' => $p->lastPage()] + $extra;
    }

    protected function roles(User $u): array
    {
        try {
            return $u->getRoleNames()->all();
        } catch (\Throwable) {
            return [];
        }
    }

    protected function userRef(?int $id): ?array
    {
        if (! $id) {
            return null;
        }
        $u = DB::table('users')->where('id', $id)->first(['id', 'name']);

        return $u ? ['id' => (int) $u->id, 'name' => $u->name] : null;
    }

    /** Escape LIKE wildcards in a user search term. */
    protected function like(string $q): string
    {
        return '%'.addcslashes($q, '%_\\').'%';
    }

    protected function storeFile(CrmAccount $a, UploadedFile $f): array
    {
        $ext = strtolower($f->getClientOriginalExtension() ?: $f->extension() ?: 'bin');
        $path = $f->storeAs('crm/customer-docs/'.$a->id, Str::uuid().'.'.$ext, 'local');

        return ['file_path' => $path, 'file_name' => Str::limit($f->getClientOriginalName(), 250, ''), 'file_mime' => $f->getMimeType(), 'file_size' => $f->getSize(),
            // Phase 0 mirror columns (onlyColumns() drops them where absent)
            'mime' => $f->getMimeType(), 'size' => $f->getSize()];
    }
}
