<?php

namespace Modules\Crm\Services\Customer360;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Modules\Crm\Entities\CrmAccount;
use Modules\Hrm\Support\SimpleSpreadsheet;

/** Customer / address / contact CSV+XLSX export and import (PHASE5_API.md §2). */
class CustomerImportExportService
{
    public const COLUMNS = [
        'customers' => ['id', 'name', 'customer_kind', 'type', 'hub_status', 'national_id', 'economic_code', 'registration_no', 'mobile', 'phone', 'email', 'website', 'city', 'province', 'postal_code', 'address_line', 'segment', 'source', 'tags', 'account_manager_id', 'created_at'],
        'addresses' => ['id', 'account_id', 'account_name', 'label', 'province', 'city', 'postal_code', 'address_line', 'is_default'],
        'contacts' => ['id', 'account_id', 'account_name', 'first_name', 'last_name', 'mobile', 'phone', 'email', 'job_title', 'is_primary'],
    ];

    private const RULES = [
        'customers' => [
            'name' => 'required|string|max:255', 'customer_kind' => 'nullable|in:individual,company', 'type' => 'nullable|string|max:50',
            'hub_status' => 'nullable|string|max:30', 'national_id' => 'nullable|string|max:20', 'economic_code' => 'nullable|string|max:30',
            'registration_no' => 'nullable|string|max:30', 'mobile' => 'nullable|string|max:30', 'phone' => 'nullable|string|max:30',
            'email' => 'nullable|email|max:255', 'website' => 'nullable|string|max:255', 'city' => 'nullable|string|max:100',
            'province' => 'nullable|string|max:100', 'postal_code' => 'nullable|string|max:20', 'address_line' => 'nullable|string|max:1000',
            'segment' => 'nullable|string|max:40', 'source' => 'nullable|string|max:40', 'account_manager_id' => 'nullable|integer|exists:users,id',
        ],
        'addresses' => [
            'account_id' => 'required|integer|exists:crm_accounts,id', 'label' => 'nullable|string|max:100', 'province' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100', 'postal_code' => 'nullable|string|max:20', 'address_line' => 'nullable|string|max:2000', 'is_default' => 'nullable|boolean',
        ],
        'contacts' => [
            'account_id' => 'required|integer|exists:crm_accounts,id', 'first_name' => 'required|string|max:100', 'last_name' => 'nullable|string|max:100',
            'mobile' => 'nullable|string|max:20', 'phone' => 'nullable|string|max:20', 'email' => 'nullable|email|max:150',
            'job_title' => 'nullable|string|max:100', 'is_primary' => 'nullable|boolean',
        ],
    ];

    /** @return list<list<mixed>> header + rows */
    public function rows(string $entity, array $ids = [], ?string $q = null): array
    {
        $out = [self::COLUMNS[$entity]];
        $accounts = CrmAccount::query()->whereNull('merged_into_id')
            ->when($ids, fn ($w) => $w->whereIn('id', $ids))
            ->when($q, fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', "%$q%")->orWhere('mobile', 'like', "%$q%")->orWhere('email', 'like', "%$q%")->orWhere('national_id', 'like', "%$q%")));
        if ($entity === 'customers') {
            foreach ($accounts->orderBy('id')->cursor() as $a) {
                $out[] = array_map(fn ($c) => match ($c) {
                    'tags' => implode('|', (array) ($a->tags ?? [])),
                    'created_at' => optional($a->created_at)->toIso8601String(),
                    default => $a->getAttribute($c),
                }, self::COLUMNS[$entity]);
            }

            return $out;
        }
        $names = (clone $accounts)->pluck('name', 'id');
        if ($entity === 'addresses') {
            DB::table('crm_account_addresses')->whereIn('account_id', $names->keys())->orderBy('id')->get()
                ->each(function ($r) use (&$out, $names) {
                    $out[] = [$r->id, $r->account_id, $names[$r->account_id] ?? null, $r->title, $r->province, $r->city, $r->postal_code, $r->line, (int) $r->is_default];
                });
        } else {
            DB::table('crm_contacts')->whereIn('account_id', $names->keys())->whereNull('deleted_at')->orderBy('id')->get()
                ->each(function ($r) use (&$out, $names) {
                    $out[] = [$r->id, $r->account_id, $names[$r->account_id] ?? null, $r->first_name, $r->last_name, $r->mobile, $r->phone, $r->email, $r->job_title, (int) $r->is_primary];
                });
        }

        return $out;
    }

    public function encode(array $rows, string $format): string
    {
        return $format === 'xlsx' ? SimpleSpreadsheet::toXlsx($rows) : SimpleSpreadsheet::toCsv($rows);
    }

    /**
     * @param  list<list<string>>  $sheet  header row first
     * @return array{total:int,created:int,updated:int,skipped:int,errors:list<array{row:int,field:?string,message:string}>}
     */
    public function import(string $entity, array $sheet, string $mode, bool $dryRun, ?int $userId): array
    {
        $res = ['total' => 0, 'created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), array_shift($sheet) ?? []);
        if (! in_array($entity === 'customers' ? 'name' : 'account_id', $header, true)) {
            $res['errors'][] = ['row' => 1, 'field' => null, 'message' => 'Header row is missing required columns.'];

            return $res;
        }
        $allowed = array_merge(['id'], array_keys(self::RULES[$entity]), $entity === 'customers' ? ['tags'] : []);
        DB::beginTransaction();
        try {
            foreach ($sheet as $i => $line) {
                $rowNo = $i + 2;
                if (count(array_filter($line, fn ($v) => trim((string) $v) !== '')) === 0) {
                    continue;
                }
                $res['total']++;
                $data = [];
                foreach ($header as $j => $h) {
                    if (in_array($h, $allowed, true)) {
                        $v = trim((string) ($line[$j] ?? ''));
                        $data[$h] = $v === '' ? null : $v;
                    }
                }
                $v = Validator::make($data, self::RULES[$entity]);
                if ($v->fails()) {
                    foreach ($v->errors()->messages() as $field => $msgs) {
                        $res['errors'][] = ['row' => $rowNo, 'field' => $field, 'message' => $msgs[0]];
                    }
                    $res['skipped']++;

                    continue;
                }
                $existing = $mode === 'upsert' ? $this->find($entity, $data) : null;
                $payload = array_intersect_key($data, self::RULES[$entity] + ($entity === 'customers' ? ['tags' => 1] : []));
                $payload = array_filter($payload, fn ($x) => $x !== null);
                if ($entity === 'customers' && isset($payload['tags'])) {
                    $payload['tags'] = array_values(array_filter(array_map('trim', explode('|', $payload['tags']))));
                }
                if (! $dryRun) {
                    $this->write($entity, $existing, $payload, $userId);
                }
                $existing ? $res['updated']++ : $res['created']++;
            }
            $dryRun ? DB::rollBack() : DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }

        return $res;
    }

    private function find(string $entity, array $d): ?int
    {
        if ($entity === 'customers') {
            $q = CrmAccount::query()->whereNull('merged_into_id');
            if (! empty($d['id']) && ($id = (clone $q)->whereKey((int) $d['id'])->value('id'))) {
                return (int) $id;
            }
            if (! empty($d['national_id']) && ($id = (clone $q)->where('national_id', $d['national_id'])->value('id'))) {
                return (int) $id;
            }
            if ($m = CustomerDuplicateService::normalizeMobile($d['mobile'] ?? null)) {
                $id = (clone $q)->where('mobile', 'like', '%'.$m)->value('id');

                return $id ? (int) $id : null;
            }

            return null;
        }
        if (empty($d['id'])) {
            return null;
        }
        $table = $entity === 'addresses' ? 'crm_account_addresses' : 'crm_contacts';
        $id = DB::table($table)->where('id', (int) $d['id'])->where('account_id', (int) $d['account_id'])->value('id');

        return $id ? (int) $id : null;
    }

    private function write(string $entity, ?int $id, array $p, ?int $userId): void
    {
        if ($entity === 'customers') {
            $p += $id ? [] : ['type' => 'customer', 'created_by' => $userId];
            $id ? CrmAccount::query()->findOrFail($id)->update($p) : CrmAccount::query()->create($p);

            return;
        }
        if ($entity === 'addresses') {
            $row = ['account_id' => $p['account_id'], 'title' => $p['label'] ?? null, 'province' => $p['province'] ?? null, 'city' => $p['city'] ?? null,
                'postal_code' => $p['postal_code'] ?? null, 'line' => $p['address_line'] ?? null, 'is_default' => (bool) ($p['is_default'] ?? false), 'updated_at' => now()];
            $id ? DB::table('crm_account_addresses')->where('id', $id)->update($row) : DB::table('crm_account_addresses')->insert($row + ['type' => 'billing', 'created_at' => now()]);

            return;
        }
        $row = array_intersect_key($p, array_flip(['account_id', 'first_name', 'last_name', 'mobile', 'phone', 'email', 'job_title', 'is_primary'])) + ['updated_at' => now()];
        $row['last_name'] ??= '';
        $id ? DB::table('crm_contacts')->where('id', $id)->update($row) : DB::table('crm_contacts')->insert($row + ['created_by' => $userId, 'created_at' => now()]);
    }

    public static function filename(string $entity, string $format): string
    {
        return 'customers-'.$entity.'-'.Carbon::now()->format('Ymd-His').'.'.$format;
    }
}
