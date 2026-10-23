<?php

namespace Modules\Crm\Services\Automations;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Http\Controllers\Customer360\HubAccountingController;
use Modules\Crm\Http\Controllers\Customer360\HubSmsController;
use Modules\Crm\Services\Hub\CustomerHubService;
use Modules\Crm\Services\Hub\CustomerHubService as Hub;
use Modules\Crm\Services\Hub\CustomerResolver;
use Modules\Crm\Services\Hub\CustomerSuspension;

/** Executes the Customer 360 automations (see AutomationRegistry). All steps are idempotent via crm_automation_hits. */
class AutomationRunner
{
    public const SCHEDULED = ['renewal_reminder', 'installment_reminder', 'debtor_status', 'auto_suspend', 'churn_risk', 'document_expiry'];

    private array $stats = [];

    /** @return array<string, array<string,int>> */
    public function run(?array $only = null, bool $dry = false): array
    {
        AutomationRegistry::flush();
        $out = [];
        foreach (self::SCHEDULED as $key) {
            if (($only && ! in_array($key, $only, true)) || ! AutomationRegistry::enabled($key)) {
                continue;
            }
            $this->stats = ['matched' => 0, 'actions' => 0];
            try {
                $this->{lcfirst(str_replace('_', '', ucwords($key, '_')))}(AutomationRegistry::config($key), $dry);
            } catch (\Throwable $e) {
                Log::warning('customer360 automation failed', ['rule' => $key, 'error' => $e->getMessage()]);
                $this->stats['error'] = 1;
            }
            $dry || AutomationRegistry::touch($key, $this->stats);
            $out[$key] = $this->stats;
        }

        return $out;
    }

    /** First time only: true when the (rule, account, subject, step) hit was newly recorded. */
    public function once(string $rule, int $acc, string $subject, string $step = '', array $meta = [], bool $dry = false): bool
    {
        $this->stats['matched'] = ($this->stats['matched'] ?? 0) + 1;
        if ($dry) {
            return ! DB::table('crm_automation_hits')->where(compact('rule', 'subject', 'step') + ['account_id' => $acc])->exists();
        }
        $ok = DB::table('crm_automation_hits')->insertOrIgnore(['rule' => $rule, 'account_id' => $acc, 'subject' => mb_substr($subject, 0, 120),
            'step' => $step, 'meta' => $meta ? json_encode($meta, JSON_UNESCAPED_UNICODE) : null, 'created_at' => now()]) > 0;
        $ok && $this->stats['actions'] = ($this->stats['actions'] ?? 0) + 1;

        return $ok;
    }

    private function accounts(array $ids)
    {
        return CrmAccount::query()->whereIn('id', array_unique($ids))->whereNull('merged_into_id')->get()->keyBy('id');
    }

    public static function render(string $tpl, array $vars): string
    {
        return strtr($tpl, collect($vars)->mapWithKeys(fn ($v, $k) => ['{{'.$k.'}}' => (string) $v])->all());
    }

    public static function task(CrmAccount $a, string $subject, ?string $due = null, ?string $desc = null): void
    {
        $by = $a->owner_id ?: DB::table('users')->orderBy('id')->value('id');
        if (! $by || ! Schema::hasTable('crm_activities')) {
            return;
        }
        DB::table('crm_activities')->insert(['type' => 'task', 'subject' => mb_substr($subject, 0, 250), 'description' => $desc,
            'related_model' => CrmAccount::class, 'related_id' => $a->id, 'due_date' => $due ?? now()->toDateString(),
            'assigned_to' => $a->owner_id, 'created_by' => $by, 'created_at' => now(), 'updated_at' => now()]);
    }

    // ---- rules ----

    private function renewalReminder(array $c, bool $dry): void
    {
        $days = array_map('intval', (array) ($c['days'] ?? [30, 7, 1]));
        $max = now()->addDays(max($days ?: [0]))->toDateString();
        $items = collect();
        foreach (DB::table('crm_customer_domains')->whereNotNull('expires_at')->whereBetween('expires_at', [now()->toDateString(), $max])->get() as $d) {
            $items->push(['acc' => $d->account_id, 'subject' => 'domain:'.$d->id, 'label' => 'دامنه '.$d->domain, 'date' => $d->expires_at]);
        }
        foreach (DB::table('crm_customer_services')->whereNotNull('renews_at')->whereBetween('renews_at', [now()->toDateString(), $max])->get() as $s) {
            $items->push(['acc' => $s->account_id, 'subject' => 'service:'.$s->id, 'label' => 'سرویس '.$s->name, 'date' => $s->renews_at]);
        }
        $accs = $this->accounts($items->pluck('acc')->all());
        foreach ($items as $i) {
            $a = $accs[$i['acc']] ?? null;
            $left = (int) now()->startOfDay()->diffInDays(CarbonImmutable::parse($i['date'])->startOfDay());
            $step = collect($days)->sort()->first(fn ($d) => $left <= $d);
            if (! $a || $step === null || ! $this->once('renewal_reminder', $a->id, $i['subject'].':'.$i['date'], 'd'.$step, [], $dry) || $dry) {
                continue;
            }
            if (! empty($c['task'])) {
                self::task($a, 'تمدید '.$i['label'].' ('.$left.' روز مانده)', null);
            }
            if (! empty($c['sms'])) {
                HubSmsController::sendTo($a, self::render($c['template'], ['name' => $a->name, 'item' => $i['label'], 'date' => $i['date']]));
            }
        }
    }

    private function installmentReminder(array $c, bool $dry): void
    {
        if (! Schema::hasTable('prj_contract_installments')) {
            return;
        }
        $before = array_map('intval', (array) ($c['days_before'] ?? [3, 0]));
        $rows = DB::table('prj_contract_installments as i')->join('prj_contracts as c', 'c.id', '=', 'i.contract_id')
            ->leftJoin('prj_projects as p', 'p.id', '=', 'c.project_id')->whereNull('i.paid_at')->whereNotNull('i.due_date')
            ->where('i.due_date', '<=', now()->addDays(max($before ?: [0]))->toDateString())
            ->get(['i.id', 'i.amount', 'i.due_date', 'p.customer_account_id', 'c.customer_user_id']);
        foreach ($rows as $r) {
            $accId = $r->customer_account_id ? CustomerResolver::byRelated('account', $r->customer_account_id) : CustomerResolver::byUser($r->customer_user_id);
            $a = $accId ? CrmAccount::query()->find($accId) : null;
            if (! $a) {
                continue;
            }
            $left = (int) now()->startOfDay()->diffInDays(CarbonImmutable::parse($r->due_date)->startOfDay(), false);
            $step = $left >= 0 ? collect($before)->sort()->first(fn ($d) => $left <= $d) : null;
            $step = $step !== null ? 'b'.$step : ($left < 0 ? 'o'.intdiv(-$left, max(1, (int) ($c['overdue_every_days'] ?? 7))) : null);
            if ($step === null || ! $this->once('installment_reminder', $a->id, 'inst:'.$r->id, $step, [], $dry) || $dry) {
                continue;
            }
            HubSmsController::sendTo($a, self::render($c['template'], ['name' => $a->name, 'amount' => number_format((float) $r->amount), 'date' => $r->due_date]));
        }
    }

    /** @return array<int, array{acc: CrmAccount, overdue: float, days: int}> */
    private function debtors(int $minDays, float $min): array
    {
        $acc = app(HubAccountingController::class);
        $out = [];
        $ids = DB::table('sales_invoices')->whereNotNull('customer_id')->whereNotIn('status', Hub::CLOSED_INVOICE)->distinct()->pluck('customer_id');
        foreach ($this->accounts($ids->all()) as $a) {
            $od = $acc->invoiceRows($a)->where('open', true)->where('overdue_days', '>', $minDays);
            if ($od->isNotEmpty() && (float) $od->sum('remaining') > $min) {
                $out[$a->id] = ['acc' => $a, 'overdue' => (float) $od->sum('remaining'), 'days' => (int) $od->max('overdue_days')];
            }
        }

        return $out;
    }

    private function debtorStatus(array $c, bool $dry): void
    {
        if (! Schema::hasColumn('crm_accounts', 'hub_status')) {
            return;
        }
        $debtors = $this->debtors((int) ($c['overdue_days'] ?? 0), (float) ($c['min_amount'] ?? 0));
        foreach ($debtors as $d) {
            $this->stats['matched']++;
            if (in_array($d['acc']->hub_status, ['active', 'dormant', 'prospect', null], true)) {
                $this->stats['actions']++;
                $dry || DB::table('crm_accounts')->where('id', $d['acc']->id)->update(['hub_status' => 'debtor', 'updated_at' => now()]);
            }
        }
        if (! empty($c['restore'])) {
            $q = DB::table('crm_accounts')->where('hub_status', 'debtor')->whereNotIn('id', array_keys($debtors))->whereNull('suspended_at');
            $this->stats['restored'] = $dry ? $q->count() : $q->update(['hub_status' => 'active', 'updated_at' => now()]);
        }
    }

    private function autoSuspend(array $c, bool $dry): void
    {
        foreach ($this->debtors((int) ($c['overdue_days'] ?? 60), (float) ($c['min_amount'] ?? 0)) as $d) {
            $a = $d['acc'];
            if ($a->suspended_at || ! $this->once('auto_suspend', $a->id, 'suspend:'.now()->format('Y-m'), '', ['overdue' => $d['overdue']], $dry) || $dry) {
                continue;
            }
            app(CustomerSuspension::class)->suspend($a, 'بدهی معوق '.$d['days'].' روزه (تعلیق خودکار)', null);
        }
    }

    private function churnRisk(array $c, bool $dry): void
    {
        $hub = app(CustomerHubService::class);
        $below = (int) ($c['health_below'] ?? 40);
        CrmAccount::query()->whereNull('merged_into_id')->where('type', 'customer')->whereNotIn('hub_status', ['blocked', 'prospect'])
            ->chunkById(200, function ($accs) use ($hub, $below, $c, $dry) {
                foreach ($accs as $a) {
                    $score = (int) ($hub->health($a)['score'] ?? 100);
                    if ($score >= $below || ! $this->once('churn_risk', $a->id, 'risk:'.now()->format('Y-m'), '', ['score' => $score], $dry) || $dry) {
                        continue;
                    }
                    if (! DB::table('crm_customer_alerts')->where('account_id', $a->id)->where('kind', 'risk')->whereNull('resolved_at')->exists()) {
                        DB::table('crm_customer_alerts')->insert(['account_id' => $a->id, 'kind' => 'risk', 'severity' => $score < 20 ? 'high' : 'medium',
                            'title' => 'ریسک ریزش', 'body' => 'امتیاز سلامت مشتری '.$score.' است (زیر '.$below.').', 'created_at' => now(), 'updated_at' => now()]);
                    }
                    ! empty($c['task']) && self::task($a, 'پیگیری مشتری در ریسک ریزش (سلامت '.$score.')');
                }
            });
    }

    private function documentExpiry(array $c, bool $dry): void
    {
        $days = (int) ($c['days'] ?? 30);
        $docs = DB::table('crm_customer_documents')->whereNotNull('expires_at')->where('expires_at', '<=', now()->addDays($days)->toDateString())->get();
        $accs = $this->accounts($docs->pluck('account_id')->all());
        foreach ($docs as $d) {
            $a = $accs[$d->account_id] ?? null;
            $past = CarbonImmutable::parse($d->expires_at)->isPast();
            if (! $a || ! $this->once('document_expiry', $a->id, 'doc:'.$d->id.':'.$d->expires_at, $past ? 'expired' : 'soon', [], $dry) || $dry) {
                continue;
            }
            self::task($a, ($past ? 'مدرک منقضی شده: ' : 'تمدید مدرک: ').($d->title ?: $d->category).' ('.$d->expires_at.')', $d->expires_at);
        }
    }

    // ---- event-driven ----

    public function onboarding(int $accountId, int $contractId): void
    {
        AutomationRegistry::flush();
        if (! AutomationRegistry::enabled('onboarding') || ! ($a = CrmAccount::query()->find($accountId))) {
            return;
        }
        $c = AutomationRegistry::config('onboarding');
        if (! $this->once('onboarding', $a->id, 'contract:'.$contractId)) {
            return;
        }
        $have = DB::table('crm_customer_onboarding_items')->where('account_id', $a->id)->pluck('title')->all();
        $sort = (int) DB::table('crm_customer_onboarding_items')->where('account_id', $a->id)->max('sort');
        foreach ((array) ($c['items'] ?? []) as $t) {
            if (! in_array($t, $have, true)) {
                DB::table('crm_customer_onboarding_items')->insert(['account_id' => $a->id, 'title' => $t, 'sort' => ++$sort, 'created_at' => now(), 'updated_at' => now()]);
            }
        }
        self::task($a, 'شروع آنبوردینگ قرارداد #'.$contractId, now()->addDays((int) ($c['task_days'] ?? 2))->toDateString());
    }

    public function welcome(CrmAccount $a): void
    {
        AutomationRegistry::flush();
        if (! AutomationRegistry::enabled('welcome_sms') || $a->type !== 'customer' || ! $a->mobile || ! $this->once('welcome_sms', $a->id, 'welcome')) {
            return;
        }
        HubSmsController::sendTo($a, self::render(AutomationRegistry::config('welcome_sms')['template'], ['name' => $a->name]));
    }
}
