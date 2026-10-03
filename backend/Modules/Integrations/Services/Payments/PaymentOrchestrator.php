<?php

namespace Modules\Integrations\Services\Payments;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Modules\Integrations\Entities\PaymentIntent;
use Modules\Integrations\Entities\PaymentLedgerEntry;

class PaymentOrchestrator
{
    public function __construct(
        private PaymentGatewayRegistry $registry,
        private GatewayConfigStore $configs,
        private PaymentFeeCalculator $fees,
        private PaymentCallbackSigner $signer,
        private BillableResolver $bills,
        private PaymentSideEffects $effects,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function quote(string $gateway, string $mode, int $baseAmount): array
    {
        $config = $this->assertPayableGateway($gateway, $mode);
        $quote = $this->fees->quote($baseAmount, (float) $config['fee_percent'], $mode, (bool) $config['apply_fee_on_cash']);
        $quote['gateway'] = $gateway;
        $quote['mode'] = $mode;
        $quote['currency'] = 'IRR';

        return $quote;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function start(array $input, ?int $userId = null, ?string $tenantDomain = null): PaymentIntent
    {
        $mode = (string) ($input['mode'] ?? 'cash');
        if (! in_array($mode, ['cash', 'installment'], true)) {
            throw new PaymentException('حالت پرداخت باید نقدی یا اقساطی باشد.', 'mode');
        }
        $bill = $this->bills->resolve($input, $tenantDomain);
        if ($bill['already_paid']) {
            throw new PaymentException('این صورتحساب قبلاً پرداخت شده است.', 'already_paid');
        }

        $gateway = $this->pickGateway(isset($input['gateway']) ? (string) $input['gateway'] : null, $mode);
        $config = $this->assertPayableGateway($gateway, $mode);
        $quote = $this->fees->quote($bill['base_amount'], (float) $config['fee_percent'], $mode, (bool) $config['apply_fee_on_cash']);

        if ($mode === 'installment' || $gateway === 'zarinpal') {
            $eligible = $this->registry->get($gateway)->checkEligible($quote['total_amount'], $config);
            if (! $eligible['eligible']) {
                throw new PaymentException($eligible['description'] !== '' ? $eligible['description'] : 'این مبلغ برای درگاه مجاز نیست.', 'not_eligible', 422, $eligible);
            }
        }

        $mobile = $this->mobile(isset($input['mobile']) ? (string) $input['mobile'] : null, $config);
        $idempotency = trim((string) ($input['idempotency_key'] ?? ''));
        if ($idempotency !== '') {
            $existing = PaymentIntent::query()->where('idempotency_key', $idempotency)->first();
            if ($existing && ! in_array($existing->status, ['failed', 'cancelled'], true)) {
                return $existing;
            }
            if ($existing) {
                $existing->forceFill(['idempotency_key' => null])->save();
            }
        }

        $open = PaymentIntent::query()
            ->where('payable_type', $bill['type'])
            ->where('payable_id', $bill['id'])
            ->where('gateway', $gateway)
            ->where('mode', $mode)
            ->where('total_amount', $quote['total_amount'])
            ->whereIn('status', ['pending', 'redirected'])
            ->where('created_at', '>', now()->subHours(2))
            ->latest()
            ->first();
        if ($open && $open->redirect_url) {
            return $open;
        }

        $returnUrl = trim((string) ($input['return_url'] ?? ''));
        if ($returnUrl !== '' && filter_var($returnUrl, FILTER_VALIDATE_URL) === false) {
            throw new PaymentException('آدرس بازگشت معتبر نیست.', 'return_url');
        }

        $intent = PaymentIntent::query()->create([
            'public_id' => (string) Str::uuid(),
            'idempotency_key' => $idempotency !== '' ? $idempotency : null,
            'user_id' => $userId,
            'domain' => $bill['domain'],
            'payable_type' => $bill['type'],
            'payable_id' => $bill['id'],
            'mode' => $mode,
            'gateway' => $gateway,
            'currency' => 'IRR',
            'base_amount' => $quote['base_amount'],
            'fee_percent' => $quote['fee_percent'],
            'fee_amount' => $quote['fee_amount'],
            'total_amount' => $quote['total_amount'],
            'fee_applied' => $quote['fee_applied'],
            'status' => 'pending',
            'return_url' => $returnUrl !== '' ? $returnUrl : null,
            'callback_token' => Str::random(40),
            'description' => $bill['description'],
            'mobile' => $mobile,
            'meta' => $bill['meta'],
        ]);
        $intent->provider_tx = 'P'.str_pad((string) $intent->id, 8, '0', STR_PAD_LEFT);
        $intent->save();

        $callbackBase = trim((string) ($config['callback_base_url'] ?? ''));
        $callbackUrl = $this->signer->callbackUrl($intent, $callbackBase !== '' ? $callbackBase : null);
        $result = $this->registry->get($gateway)->requestPayment($intent, $config, $callbackUrl);
        if (! $result->ok || $result->redirectUrl === '') {
            $intent->update([
                'status' => 'failed',
                'failed_at' => now(),
                'failure_reason' => $result->message,
                'provider_payload' => $result->payload,
            ]);
            throw new PaymentException($result->message !== '' ? $result->message : 'شروع پرداخت ناموفق بود.', 'gateway_request');
        }

        $intent->update([
            'status' => 'redirected',
            'authority' => $result->authority,
            'redirect_url' => $result->redirectUrl,
            'provider_payload' => $result->payload === [] ? null : $result->payload,
        ]);
        $this->ledger($intent, 'initiated', (int) $intent->base_amount, 'redirected', 'شروع پرداخت');
        if ((int) $intent->fee_amount > 0) {
            $this->ledger($intent, 'fee', (int) $intent->fee_amount, 'redirected', 'کارمزد درگاه');
        }
        Log::info('payment.intent.started', [
            'payment_id' => $intent->public_id,
            'gateway' => $gateway,
            'mode' => $mode,
            'total' => $intent->total_amount,
        ]);

        return $intent->fresh();
    }

    public function handleCallback(Request $request, string $gateway): JsonResponse|RedirectResponse
    {
        $intent = $this->findFromCallback($request, $gateway);
        if (! $intent) {
            return response()->json(['message' => 'پرداخت پیدا نشد.'], 404);
        }

        return $this->finish($intent, $request->all(), $request->wantsJson());
    }

    /**
     * @param  array<string, mixed>  $callback
     */
    public function finish(PaymentIntent $intent, array $callback, bool $asJson = true): JsonResponse|RedirectResponse
    {
        $failure = DB::transaction(function () use ($intent, $callback) {
            /** @var PaymentIntent $locked */
            $locked = PaymentIntent::query()->lockForUpdate()->findOrFail($intent->id);
            if ($locked->status === 'settled') {
                return null;
            }
            $sig = isset($callback['sig']) ? (string) $callback['sig'] : null;
            $config = $this->configs->for($locked->gateway);
            if (($sig !== null && $sig !== '' && ! $this->signer->matches($sig, $locked))
                || (! empty($config['local_simulation']) && ! $this->signer->matches($sig, $locked))) {
                return new PaymentException('امضای بازگشت معتبر نیست.', 'bad_signature', 422);
            }

            $verified = $this->registry->get($locked->gateway)->verifyPayment($locked, $config, $callback);
            if (! $verified->ok) {
                $locked->update([
                    'status' => 'failed',
                    'failed_at' => now(),
                    'failure_reason' => $verified->message,
                ]);
                $this->ledger($locked, 'failed', (int) $locked->total_amount, 'failed', $verified->message);

                return new PaymentException($verified->message !== '' ? $verified->message : 'تأیید پرداخت ناموفق بود.', 'verify_failed');
            }

            $locked->update([
                'status' => 'verified',
                'verified_at' => $locked->verified_at ?? now(),
                'provider_ref' => $verified->providerRef !== '' ? $verified->providerRef : $locked->provider_ref,
                'authority' => $verified->authority !== '' ? $verified->authority : $locked->authority,
            ]);
            $this->ledger($locked, 'verified', (int) $locked->total_amount, 'verified', 'تأیید شد');

            $settled = $this->registry->get($locked->gateway)->settlePayment($locked->fresh(), $config);
            if (! $settled->ok) {
                return new PaymentException($settled->message !== '' ? $settled->message : 'تسویه پرداخت ناموفق بود.', 'settle_failed');
            }

            $locked->update([
                'status' => 'settled',
                'settled_at' => now(),
                'provider_ref' => $settled->providerRef !== '' ? $settled->providerRef : $locked->provider_ref,
            ]);
            $this->ledger($locked, 'settled', (int) $locked->total_amount, 'settled', 'تسویه شد');
            $this->effects->apply($locked->fresh());
            $this->ledger($locked, 'side_effect', (int) $locked->base_amount, 'settled', $locked->payable_type);
            Log::info('payment.intent.settled', [
                'payment_id' => $locked->public_id,
                'gateway' => $locked->gateway,
                'ref' => $locked->provider_ref,
            ]);

            return null;
        });

        if ($failure instanceof PaymentException) {
            $intent->refresh();
            if ($asJson || trim((string) $intent->return_url) === '') {
                return response()->json([
                    'message' => $failure->getMessage(),
                    'error' => $failure->errorCode,
                    'data' => $intent->toApiArray(),
                ], $failure->status);
            }

            return redirect()->away((string) $this->signer->returnUrl($intent->fresh()));
        }

        $intent->refresh();

        return $this->respond($intent, $asJson);
    }

    public function cancel(PaymentIntent $intent, bool $confirmSettled = false): PaymentIntent
    {
        $config = $this->configs->for($intent->gateway);
        $driver = $this->registry->get($intent->gateway);
        if ($intent->status === 'settled') {
            if (! $confirmSettled) {
                throw new PaymentException('لغو پرداخت تسویه‌شده نیاز به تأیید دارد.', 'confirm_required');
            }
            $result = $driver->cancelPayment($intent, $config);
        } elseif ($intent->authority) {
            $result = $driver->revertPayment($intent, $config);
        } else {
            $result = GatewayResult::success('cancelled');
        }
        if (! $result->ok) {
            throw new PaymentException($result->message !== '' ? $result->message : 'لغو ناموفق بود.', 'cancel_failed');
        }
        $intent->update([
            'status' => $intent->status === 'settled' ? 'reverted' : 'cancelled',
            'failure_reason' => $result->message,
        ]);
        $this->ledger($intent, 'cancelled', (int) $intent->total_amount, $intent->status, $result->message);

        return $intent->fresh();
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function assertPayableGateway(string $gateway, string $mode): array
    {
        $config = $this->configs->for($gateway);
        if (! $config['enabled']) {
            throw new PaymentException('این درگاه غیرفعال است.', 'gateway_disabled');
        }
        $flag = $mode === 'cash' ? 'cash_enabled' : 'installment_enabled';
        $supports = $config['supports'] ?? [];
        if (! $config[$flag] || ! in_array($mode, $supports, true)) {
            throw new PaymentException('این درگاه برای حالت انتخاب‌شده فعال نیست.', 'mode_not_allowed');
        }
        if (! $config['sandbox'] && $this->configs->missingLiveCredentials($gateway, $config)) {
            throw new PaymentException('شناسه‌های درگاه در حالت عملیاتی کامل نیست.', 'missing_credentials');
        }

        return $config;
    }

    private function pickGateway(?string $requested, string $mode): string
    {
        $requested = trim((string) $requested);
        if ($requested !== '') {
            return $requested;
        }
        $options = $this->configs->optionsFor($mode);
        if ($options === []) {
            throw new PaymentException('درگاه فعالی برای این حالت پرداخت نیست.', 'no_gateway');
        }

        return (string) $options[0]['code'];
    }

    private function findFromCallback(Request $request, string $gateway): ?PaymentIntent
    {
        $publicId = (string) ($request->input('pt') ?: $request->input('payment_id') ?: $request->input('providerId') ?: '');
        if ($publicId !== '') {
            $found = PaymentIntent::query()->where('public_id', $publicId)->first();
            if ($found) {
                return $found;
            }
        }
        $tx = (string) ($request->input('transactionId') ?: '');
        if ($tx !== '') {
            $found = PaymentIntent::query()->where('provider_tx', $tx)->where('gateway', $gateway)->first();
            if ($found) {
                return $found;
            }
        }
        $authority = (string) ($request->input('Authority') ?: $request->input('authority') ?: '');
        if ($authority !== '') {
            return PaymentIntent::query()->where('authority', $authority)->first();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $config
     */
    private function mobile(?string $raw, array $config): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $raw) ?? '';
        if (str_starts_with($digits, '98') && strlen($digits) === 12) {
            $digits = '0'.substr($digits, 2);
        } elseif (strlen($digits) === 10 && str_starts_with($digits, '9')) {
            $digits = '0'.$digits;
        }
        if ($digits === '' && ! empty($config['local_simulation'])) {
            return '09120000000';
        }
        if ($digits === '' && in_array($config['code'] ?? '', ['snappay', 'digipay', 'torobpay'], true) && empty($config['local_simulation'])) {
            throw new PaymentException('شماره موبایل برای این درگاه الزامی است.', 'mobile');
        }

        return $digits !== '' ? $digits : null;
    }

    private function ledger(PaymentIntent $intent, string $type, int $amount, string $status, string $note): void
    {
        PaymentLedgerEntry::query()->create([
            'payment_intent_id' => $intent->id,
            'entry_type' => $type,
            'amount' => $amount,
            'status' => $status,
            'note' => mb_substr($note, 0, 255),
        ]);
    }

    private function respond(PaymentIntent $intent, bool $asJson): JsonResponse|RedirectResponse
    {
        $body = [
            'data' => $intent->toApiArray(),
            'message' => $intent->status === 'settled' ? 'پرداخت تأیید شد.' : 'وضعیت پرداخت ثبت شد.',
        ];
        if ($asJson || trim((string) $intent->return_url) === '') {
            return response()->json($body);
        }

        return redirect()->away((string) $this->signer->returnUrl($intent));
    }
}
