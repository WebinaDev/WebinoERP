<?php

namespace Modules\Crm\Services\Customer360;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Duplicate customer detection (PHASE5_API.md §1). Scores pairs of non-merged accounts on shared
 * identifiers; merging itself is delegated to the existing CustomerMergeService.
 */
class CustomerDuplicateService
{
    public const SCORES = ['national_id' => 60, 'mobile' => 40, 'email' => 35, 'domain' => 35, 'name' => 25, 'similar_name' => 15];

    private const LIMIT = 5000;

    /** @return Collection<int, array<string,mixed>> */
    public function pairs(?int $accountId = null, int $minScore = 50): Collection
    {
        $accounts = $this->accounts();
        if ($accountId !== null && ! $accounts->has($accountId)) {
            return collect();
        }
        $buckets = [];
        foreach ($accounts as $a) {
            foreach ($this->keys($a) as $k) {
                $buckets[$k][] = $a['id'];
            }
        }
        $pairs = [];
        foreach ($buckets as $key => $ids) {
            $ids = array_values(array_unique($ids));
            if (count($ids) < 2 || count($ids) > 50) {
                continue; // huge buckets are placeholders (e.g. 09120000000), not real duplicates
            }
            $reason = strstr($key, ':', true);
            foreach ($ids as $i => $x) {
                foreach (array_slice($ids, $i + 1) as $y) {
                    [$lo, $hi] = $x < $y ? [$x, $y] : [$y, $x];
                    if ($accountId !== null && $lo !== $accountId && $hi !== $accountId) {
                        continue;
                    }
                    $pairs["$lo-$hi"][$reason] = true;
                }
            }
        }
        $dismissed = $this->dismissed();
        $counts = $this->invoiceCounts();

        return collect($pairs)->map(function (array $reasons, string $key) use ($accounts, $counts) {
            [$lo, $hi] = array_map('intval', explode('-', $key));
            $a = $accounts[$lo];
            $b = $accounts[$hi];
            if ($reasons === ['prefix' => true]) {
                if (! $this->similar($a['norm_name'], $b['norm_name'])) {
                    return null;
                }
                $reasons = ['similar_name' => true];
            }
            unset($reasons['prefix']);
            if (! isset($reasons['name']) && ! isset($reasons['similar_name']) && $this->similar($a['norm_name'], $b['norm_name'])) {
                $reasons['similar_name'] = true;
            }
            $score = min(100, array_sum(array_map(fn ($r) => self::SCORES[$r], array_keys($reasons))));
            $ca = $counts[$lo] ?? 0;
            $cb = $counts[$hi] ?? 0;
            $target = $ca === $cb ? $lo : ($ca > $cb ? $lo : $hi);

            return [
                'key' => $key, 'score' => $score, 'reasons' => array_values(array_map(fn ($r) => $r === 'similar_name' ? 'name' : $r, array_keys($reasons))),
                'a' => $this->brief($a, $ca), 'b' => $this->brief($b, $cb), 'suggested_target_id' => $target,
            ];
        })->filter()->reject(fn ($r) => isset($dismissed[$r['key']]) || $r['score'] < $minScore)
            ->sortBy([['score', 'desc'], ['key', 'asc']])->values();
    }

    public function dismiss(int $a, int $b, ?int $userId): void
    {
        abort_if($a === $b, 422, __('messages.choose_two_different_accounts'));
        [$lo, $hi] = $a < $b ? [$a, $b] : [$b, $a];
        DB::table('crm_duplicate_dismissals')->updateOrInsert(
            ['account_a_id' => $lo, 'account_b_id' => $hi],
            ['dismissed_by' => $userId, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public static function normalizeMobile(?string $v): ?string
    {
        $d = preg_replace('/\D+/', '', strtr((string) $v, ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']));

        return strlen($d) >= 10 ? substr($d, -10) : null;
    }

    public static function normalizeName(?string $v): string
    {
        $v = mb_strtolower(trim((string) $v));
        $v = strtr($v, ['ي' => 'ی', 'ك' => 'ک', '‌' => ' ']);
        $v = preg_replace('/\b(co|company|ltd|inc|llc)\b|شرکت|فروشگاه|گروه/u', ' ', $v);

        return trim(preg_replace('/[\s\.\-_,]+/u', ' ', $v));
    }

    public static function normalizeDomain(?string $v): ?string
    {
        $v = mb_strtolower(trim((string) $v));
        if ($v === '') {
            return null;
        }
        $host = parse_url(str_contains($v, '://') ? $v : 'http://'.$v, PHP_URL_HOST) ?: $v;

        return preg_replace('/^www\./', '', $host) ?: null;
    }

    private function similar(string $x, string $y): bool
    {
        if ($x === '' || $y === '' || $x === $y || mb_strlen($x) < 4) {
            return false;
        }
        similar_text($x, $y, $pct);

        return $pct >= 85;
    }

    /** @return list<string> */
    private function keys(array $a): array
    {
        $k = [];
        if ($a['national_id']) {
            $k[] = 'national_id:'.$a['national_id'];
        }
        if ($m = self::normalizeMobile($a['mobile'])) {
            $k[] = 'mobile:'.$m;
        }
        if ($a['email']) {
            $k[] = 'email:'.mb_strtolower(trim($a['email']));
        }
        foreach ($a['domains'] as $d) {
            $k[] = 'domain:'.$d;
        }
        if (mb_strlen($a['norm_name']) >= 3) {
            $k[] = 'name:'.$a['norm_name'];
            $k[] = 'prefix:'.mb_substr($a['norm_name'], 0, 4);
        }

        return array_values(array_unique($k));
    }

    private function accounts(): Collection
    {
        $cols = array_values(array_filter(['id', 'name', 'mobile', 'email', 'national_id', 'website', 'created_at'], fn ($c) => Schema::hasColumn('crm_accounts', $c)));
        $domains = Schema::hasTable('crm_customer_domains')
            ? DB::table('crm_customer_domains')->get(['account_id', 'domain'])->groupBy('account_id') : collect();

        return DB::table('crm_accounts')->whereNull('merged_into_id')
            ->when(Schema::hasColumn('crm_accounts', 'deleted_at'), fn ($q) => $q->whereNull('deleted_at'))
            ->orderBy('id')->limit(self::LIMIT)->get($cols)
            ->mapWithKeys(function ($r) use ($domains) {
                $d = collect($domains->get($r->id, []))->map(fn ($x) => self::normalizeDomain($x->domain));
                $d->push(self::normalizeDomain($r->website ?? null));

                return [(int) $r->id => [
                    'id' => (int) $r->id, 'name' => $r->name, 'mobile' => $r->mobile ?? null, 'email' => $r->email ?? null,
                    'national_id' => trim((string) ($r->national_id ?? '')) ?: null, 'website' => $r->website ?? null, 'created_at' => $r->created_at ?? null,
                    'domains' => $d->filter()->unique()->values()->all(), 'norm_name' => self::normalizeName($r->name),
                ]];
            });
    }

    private function brief(array $a, int $invoices): array
    {
        return [
            'id' => $a['id'], 'name' => $a['name'], 'mobile' => $a['mobile'], 'email' => $a['email'], 'national_id' => $a['national_id'],
            'website' => $a['website'], 'created_at' => $a['created_at'] ? \Illuminate\Support\Carbon::parse($a['created_at'])->toIso8601String() : null,
            'invoices_count' => $invoices,
        ];
    }

    /** @return array<string,bool> */
    private function dismissed(): array
    {
        if (! Schema::hasTable('crm_duplicate_dismissals')) {
            return [];
        }

        return DB::table('crm_duplicate_dismissals')->get(['account_a_id', 'account_b_id'])
            ->mapWithKeys(fn ($r) => [min($r->account_a_id, $r->account_b_id).'-'.max($r->account_a_id, $r->account_b_id) => true])->all();
    }

    /** @return array<int,int> */
    private function invoiceCounts(): array
    {
        if (! Schema::hasColumn('sales_invoices', 'customer_id')) {
            return [];
        }

        return DB::table('sales_invoices')->whereNotNull('customer_id')->groupBy('customer_id')
            ->selectRaw('customer_id, count(*) c')->pluck('c', 'customer_id')->map(fn ($v) => (int) $v)->all();
    }
}
