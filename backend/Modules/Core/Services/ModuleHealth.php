<?php

namespace Modules\Core\Services;

use Illuminate\Support\Facades\Schema;
use Modules\Crm\Entities\ContentItem;
use Modules\Crm\Entities\CrmEInvoice;
use Modules\Integrations\Entities\CalendarAccount;
use Modules\Integrations\Entities\EmailAccount;
use Modules\Integrations\Entities\InboundEvent;

class ModuleHealth
{
    /**
     * @return array<string, mixed>
     */
    public function report(): array
    {
        $oauth = 0;
        $webhookFailed = 0;
        $webhookQueued = 0;
        $moadianRetry = 0;
        $moadianFailed = 0;
        $publishFailed = 0;
        if (Schema::hasTable('int_calendar_accounts')) {
            $oauth += CalendarAccount::query()->where('status', 'needs_reauth')->count();
        }
        if (Schema::hasTable('int_email_accounts')) {
            $oauth += EmailAccount::query()->where('status', 'needs_reauth')->count();
        }
        if (Schema::hasTable('int_inbound_events')) {
            $webhookFailed = InboundEvent::query()->where('status', 'failed')->count();
            $webhookQueued = InboundEvent::query()->where('status', 'queued')->count();
        }
        if (Schema::hasTable('crm_einvoices') && Schema::hasColumn('crm_einvoices', 'attempts')) {
            $moadianRetry = CrmEInvoice::query()->where('status', 'retry')->count();
            $moadianFailed = CrmEInvoice::query()->where('status', 'failed')->count();
        }
        if (Schema::hasTable('crm_content_items') && Schema::hasColumn('crm_content_items', 'publish_status')) {
            $publishFailed = ContentItem::query()->where('publish_status', 'failed')->count();
        }
        $degraded = $oauth > 0 || $webhookFailed > 0 || $moadianFailed > 0 || $publishFailed > 0;

        return [
            'status' => $degraded ? 'degraded' : 'ok',
            'oauth_needs_reauth' => $oauth,
            'webhook_failed' => $webhookFailed,
            'webhook_queued' => $webhookQueued,
            'moadian_retry' => $moadianRetry,
            'moadian_failed' => $moadianFailed,
            'publish_failed' => $publishFailed,
            'sandbox' => (bool) config('integrations.sandbox'),
        ];
    }
}
