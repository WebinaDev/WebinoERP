<?php

namespace Modules\Crm\Services;

use App\Jobs\SendSmsJob;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Modules\Crm\Entities\CrmAccount;
use Modules\Crm\Entities\CrmActivity;
use Modules\Crm\Entities\CrmContact;
use Modules\Crm\Entities\CrmLead;
use Modules\Crm\Entities\CrmMessage;
use Modules\Crm\Entities\CrmMessageTemplate;
use Modules\Crm\Support\CrmRelation;
use Modules\Integrations\Entities\IntegrationSetting;
use Modules\Integrations\Services\ModirPayamakEdgeClient;

class CrmOutboundMessenger
{
    /**
     * @param  array<string, string>  $context
     */
    public function send(
        string $channel,
        string $relatedType,
        int $relatedId,
        string $to,
        string $body,
        ?string $subject,
        ?int $templateId,
        ?int $userId,
        array $context = [],
    ): CrmMessage {
        $renderedBody = $this->render($body, $context);
        $renderedSubject = $subject !== null ? $this->render($subject, $context) : null;
        $delivery = $channel === 'sms'
            ? $this->deliverSms($to, $renderedBody)
            : $this->deliverEmail($to, $renderedSubject ?: 'Webino', $renderedBody);

        $message = CrmMessage::query()->create([
            'channel' => $channel,
            'template_id' => $templateId,
            'related_type' => $relatedType,
            'related_id' => $relatedId,
            'to_address' => $to,
            'subject' => $renderedSubject,
            'body' => $renderedBody,
            'status' => $delivery['status'],
            'provider' => $delivery['provider'],
            'error' => $delivery['error'] ?? null,
            'created_by' => $userId,
        ]);

        if ($userId) {
            CrmActivity::query()->create([
                'type' => $channel === 'sms' ? 'sms' : 'email',
                'subject' => $renderedSubject ?: ($channel === 'sms' ? 'پیامک' : 'ایمیل'),
                'description' => $renderedBody,
                'related_model' => CrmRelation::classFor($relatedType),
                'related_id' => $relatedId,
                'completed_at' => now(),
                'created_by' => $userId,
                'meta' => ['message_id' => $message->id, 'provider' => $delivery['provider']],
            ]);
        }

        return $message;
    }

    public function sendTemplate(
        CrmMessageTemplate $template,
        string $relatedType,
        int $relatedId,
        ?int $userId,
    ): CrmMessage {
        $context = $this->contextFor($relatedType, $relatedId);
        $to = $template->channel === 'sms'
            ? (string) ($context['mobile'] ?? '')
            : (string) ($context['email'] ?? '');
        abort_if($to === '', 422, 'Recipient address is missing');

        return $this->send(
            $template->channel,
            $relatedType,
            $relatedId,
            $to,
            $template->body,
            $template->subject,
            $template->id,
            $userId,
            $context,
        );
    }

    /**
     * @return array{status: string, provider: string, error?: string|null}
     */
    public function deliverSms(string $to, string $body): array
    {
        try {
            if (class_exists(ModirPayamakEdgeClient::class)) {
                $edge = app(ModirPayamakEdgeClient::class);
                if ($edge->isConfigured()) {
                    $result = $edge->sendWebservice($edge->defaultFrom(), $body, [$to]);

                    return [
                        'status' => ($result['ok'] ?? false) ? 'sent' : 'failed',
                        'provider' => 'modirpayamak',
                        'error' => ($result['ok'] ?? false) ? null : (string) ($result['message'] ?? 'send failed'),
                    ];
                }
            }
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'provider' => 'modirpayamak', 'error' => $e->getMessage()];
        }

        $settings = [];
        try {
            $settings = IntegrationSetting::getJson('sms', 'settings', []);
        } catch (\Throwable) {
            $settings = [];
        }
        $provider = (string) ($settings['provider'] ?? 'log');
        if (in_array($provider, ['melipayamak', 'parsgreen'], true)) {
            SendSmsJob::dispatch($provider, $to, $body, $settings);

            return ['status' => 'queued', 'provider' => $provider, 'error' => null];
        }

        Log::info('crm.sms.logged', ['to' => $to, 'provider' => $provider]);

        return ['status' => 'logged', 'provider' => $provider !== '' ? $provider : 'log', 'error' => null];
    }

    /**
     * @return array{status: string, provider: string, error?: string|null}
     */
    public function deliverEmail(string $to, string $subject, string $body): array
    {
        try {
            Mail::raw($body, function ($message) use ($to, $subject) {
                $message->to($to)->subject($subject);
            });

            return ['status' => 'sent', 'provider' => (string) config('mail.default', 'mail'), 'error' => null];
        } catch (\Throwable $e) {
            return ['status' => 'failed', 'provider' => 'mail', 'error' => $e->getMessage()];
        }
    }

    /**
     * @param  array<string, string>  $context
     */
    public function render(string $template, array $context): string
    {
        return (string) preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function (array $match) use ($context) {
            $key = strtolower($match[1]);

            return $context[$key] ?? '';
        }, $template);
    }

    /**
     * @return array<string, string>
     */
    public function contextFor(string $relatedType, int $relatedId): array
    {
        return match ($relatedType) {
            'lead' => $this->leadContext($relatedId),
            'contact' => $this->contactContext($relatedId),
            'account' => $this->accountContext($relatedId),
            default => ['name' => ''],
        };
    }

    /**
     * @return array<string, string>
     */
    private function leadContext(int $id): array
    {
        $lead = CrmLead::query()->find($id);
        if (! $lead) {
            return [];
        }

        return [
            'name' => trim($lead->first_name.' '.$lead->last_name),
            'first_name' => (string) $lead->first_name,
            'last_name' => (string) $lead->last_name,
            'company' => (string) ($lead->company ?? ''),
            'email' => (string) ($lead->email ?? ''),
            'mobile' => (string) ($lead->mobile ?? ''),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function contactContext(int $id): array
    {
        $contact = CrmContact::query()->with('account')->find($id);
        if (! $contact) {
            return [];
        }

        return [
            'name' => trim($contact->first_name.' '.$contact->last_name),
            'first_name' => (string) $contact->first_name,
            'last_name' => (string) $contact->last_name,
            'company' => (string) ($contact->account?->name ?? ''),
            'email' => (string) ($contact->email ?? ''),
            'mobile' => (string) ($contact->mobile ?? $contact->phone ?? ''),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function accountContext(int $id): array
    {
        $account = CrmAccount::query()->with('contacts')->find($id);
        if (! $account) {
            return [];
        }
        $primary = $account->contacts->firstWhere('is_primary', true) ?? $account->contacts->first();

        return [
            'name' => (string) $account->name,
            'first_name' => (string) ($primary->first_name ?? ''),
            'last_name' => (string) ($primary->last_name ?? ''),
            'company' => (string) $account->name,
            'email' => (string) ($primary->email ?? ''),
            'mobile' => (string) ($primary->mobile ?? $primary->phone ?? ''),
        ];
    }
}
