<?php

namespace Modules\Docs\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Modules\Crm\Services\CrmOutboundMessenger;
use Illuminate\Support\Str;
use Modules\Docs\Entities\DocsContract;
use Modules\Docs\Entities\DocsContractAddendum;
use Modules\Docs\Entities\DocsContractInstallment;
use Modules\Docs\Entities\DocsSignatureEnvelope;

/**
 * Enterprise Contract Lifecycle Management (CLM) & E-Signature Engine.
 */
class ContractLifecycleService
{
    /**
     * Transition a contract through its business lifecycle with state-machine validation.
     */
    public function transitionState(DocsContract $contract, string $toState, ?string $reason = null): DocsContract
    {
        $fromState = $contract->status;
        $allowedTransitions = [
            'draft' => ['internal_review', 'cancelled'],
            'internal_review' => ['sent', 'draft', 'cancelled'],
            'sent' => ['customer_review', 'pending_signature', 'negotiation', 'cancelled'],
            'customer_review' => ['negotiation', 'pending_signature', 'cancelled'],
            'negotiation' => ['sent', 'cancelled'],
            'pending_signature' => ['signed', 'rejected', 'cancelled'],
            'signed' => ['active', 'cancelled'],
            'active' => ['suspended', 'expired', 'terminated'],
            'suspended' => ['active', 'terminated'],
            'expired' => ['archived'],
            'terminated' => ['archived'],
        ];

        if (! isset($allowedTransitions[$fromState]) || ! in_array($toState, $allowedTransitions[$fromState], true)) {
            throw new \InvalidArgumentException("تغییر وضعیت قرارداد از '{$fromState}' به '{$toState}' در چرخه حقوقی مجاز نیست.");
        }

        $updates = ['status' => $toState];
        if ($toState === 'signed') {
            $updates['is_locked'] = true;
            $updates['signed_at'] = now();
        }
        if (in_array($toState, ['cancelled', 'terminated'], true)) {
            $updates['cancelled_reason'] = $reason;
        }

        $contract->update($updates);

        return $contract;
    }

    /**
     * Create an electronic signature envelope with SMS OTP dispatch.
     */
    public function createSignatureEnvelope(DocsContract $contract, string $signerName, string $signerMobile): DocsSignatureEnvelope
    {
        $token = Str::random(40);
        $otp = (string) random_int(100000, 999999);

        $envelope = DocsSignatureEnvelope::create([
            'contract_id' => $contract->id,
            'envelope_token' => $token,
            'signer_name' => $signerName,
            'signer_mobile' => $signerMobile,
            'otp_code_hash' => bcrypt($otp),
            'otp_expires_at' => now()->addMinutes(10),
            'status' => 'pending',
        ]);

        $contract->update(['status' => 'pending_signature']);

        // Deliver the one-time code to the signer; without this the envelope can never be signed.
        $title = $contract->title ?? $contract->contract_number ?? ('#'.$contract->id);
        $sms = app(CrmOutboundMessenger::class)->deliverSms(
            $signerMobile,
            "کد امضای قرارداد «{$title}»: {$otp}\nاعتبار: ۱۰ دقیقه"
        );
        $envelope->setAttribute('otp_delivery', $sms['status'] ?? 'unknown');

        return $envelope;
    }

    /**
     * Verify OTP and complete tamper-evident digital signature.
     */
    public function signEnvelope(DocsSignatureEnvelope $envelope, string $otp, string $ip, string $userAgent): bool
    {
        if ($envelope->status === 'signed') {
            return true;
        }

        if (now()->isAfter($envelope->otp_expires_at)) {
            throw new \RuntimeException('کد یکبار مصرف امضا (OTP) منقضی شده است.');
        }

        // Brute-force guard: at most 5 wrong codes per envelope.
        $attemptsKey = 'docs:envelope-otp-attempts:'.$envelope->id;
        if ((int) Cache::get($attemptsKey, 0) >= 5) {
            throw new \RuntimeException('تعداد تلاش‌های ناموفق زیاد است. پاکت امضای جدید بسازید.');
        }

        if (! password_verify($otp, $envelope->otp_code_hash)) {
            Cache::put($attemptsKey, (int) Cache::get($attemptsKey, 0) + 1, now()->addMinutes(30));
            throw new \RuntimeException('کد اعتبارسنجی پیامک‌شده نامعتبر است.');
        }

        // ایجاد گواهی دیجیتال امضا بر اساس محتوا، زمان و هویت
        $contract = $envelope->contract_id ? DocsContract::find($envelope->contract_id) : null;
        $certData = ($contract?->contract_number ?? '') . '|' . $envelope->signer_mobile . '|' . now()->toIso8601String() . '|' . $ip;
        $certHash = hash('sha256', $certData);

        $envelope->update([
            'status' => 'signed',
            'signed_ip' => $ip,
            'signed_user_agent' => $userAgent,
            'digital_certificate_hash' => $certHash,
            'signed_at' => now(),
        ]);

        if ($contract) {
            $this->transitionState($contract, 'signed');
        }

        return true;
    }

    /**
     * Create an official Addendum to an active contract.
     */
    public function createAddendum(DocsContract $contract, array $data): DocsContractAddendum
    {
        return DB::transaction(function () use ($contract, $data) {
            $addendumNumber = ($contract->contract_number ?: 'CTR-' . $contract->id) . '-ADD-' . ($contract->addenda()->count() + 1);

            $addendum = DocsContractAddendum::create([
                'contract_id' => $contract->id,
                'addendum_number' => $addendumNumber,
                'title' => $data['title'],
                'type' => $data['type'],
                'amount_delta' => (float) ($data['amount_delta'] ?? 0),
                'new_end_date' => $data['new_end_date'] ?? null,
                'terms_and_body' => $data['terms_and_body'],
                'status' => 'active',
                'effective_date' => now(),
            ]);

            // اعمال تغییرات الحاقیه روی قرارداد مادر
            if ($addendum->amount_delta != 0) {
                $contract->increment('total_amount', $addendum->amount_delta);
            }
            if ($addendum->new_end_date) {
                $contract->update(['end_date' => $addendum->new_end_date]);
            }
            $contract->increment('version');

            return $addendum;
        });
    }
}
