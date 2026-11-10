<?php

namespace Modules\Sales\Services\Storefront;

use Modules\Sales\Entities\SalesGiftCard as GiftCard;
use Modules\Sales\Entities\SalesGiftCardTransaction as GiftCardTransaction;
use Modules\Sales\Entities\SalesStorefrontOrder as Order;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Store gift cards: issue, check balance, hold at checkout, release on cancel or failed payment.
 *
 * The balance moves when the order is created (type "redeem"), so two carts cannot spend the
 * same balance; a cancelled, failed or refunded order puts the amount back (type "release").
 */
class StorefrontGiftCards
{
    public const RELEASE_STATUSES = ['cancelled', 'failed', 'payment_failed', 'refunded'];

    public static function normalizeCode(?string $code): string
    {
        $c = strtr(trim((string) $code), ['۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9']);

        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $c) ?? '');
    }

    public function generateCode(): string
    {
        do {
            $raw = strtoupper(Str::random(16));
            $raw = preg_replace('/[^A-Z0-9]/', 'X', $raw);
            $code = substr($raw, 0, 4).substr($raw, 4, 4).substr($raw, 8, 4).substr($raw, 12, 4);
        } while (GiftCard::query()->where('code', $code)->exists());

        return $code;
    }

    /** @param array<string, mixed> $data */
    public function issue(array $data, ?int $issuedBy = null): GiftCard
    {
        $amount = (int) ($data['amount_minor'] ?? 0);
        if ($amount < 1) {
            throw ValidationException::withMessages(['amount_minor' => 'مبلغ کارت هدیه باید بیشتر از صفر باشد.']);
        }
        $code = self::normalizeCode($data['code'] ?? '') ?: $this->generateCode();
        if (GiftCard::query()->where('code', $code)->exists()) {
            throw ValidationException::withMessages(['code' => 'این کد کارت هدیه قبلا ثبت شده است.']);
        }

        return DB::transaction(function () use ($data, $amount, $code, $issuedBy) {
            $card = GiftCard::query()->create([
                'code' => $code,
                'initial_minor' => $amount,
                'balance_minor' => $amount,
                'status' => 'active',
                'user_id' => $data['user_id'] ?? null,
                'recipient_name' => $data['recipient_name'] ?? null,
                'recipient_phone' => $data['recipient_phone'] ?? null,
                'recipient_email' => $data['recipient_email'] ?? null,
                'message' => $data['message'] ?? null,
                'note' => $data['note'] ?? null,
                'expires_at' => $data['expires_at'] ?? null,
                'issued_by' => $issuedBy,
            ]);
            GiftCardTransaction::query()->create([
                'gift_card_id' => $card->id,
                'user_id' => $issuedBy,
                'type' => 'issue',
                'amount_minor' => $amount,
                'balance_after_minor' => $amount,
                'note' => 'صدور کارت',
            ]);

            return $card;
        });
    }

    public function find(?string $code): ?GiftCard
    {
        $code = self::normalizeCode($code);
        if ($code === '') {
            return null;
        }

        return GiftCard::query()->where('code', $code)->first();
    }

    /**
     * Validate a code for checkout and return how much of `payable` it covers.
     *
     * @return array{card: GiftCard, applied_minor: int, balance_minor: int, remaining_minor: int}
     */
    public function preview(?string $code, int $payableMinor): array
    {
        $card = $this->find($code);
        if (! $card) {
            throw ValidationException::withMessages(['gift_card_code' => 'کارت هدیه پیدا نشد.']);
        }
        if ($card->status !== 'active') {
            throw ValidationException::withMessages(['gift_card_code' => 'این کارت هدیه غیرفعال است.']);
        }
        if ($card->expires_at !== null && ! $card->expires_at->isFuture()) {
            throw ValidationException::withMessages(['gift_card_code' => 'مهلت استفاده از این کارت هدیه تمام شده است.']);
        }
        if ((int) $card->balance_minor < 1) {
            throw ValidationException::withMessages(['gift_card_code' => 'موجودی این کارت هدیه تمام شده است.']);
        }
        $applied = max(0, min((int) $card->balance_minor, $payableMinor));

        return [
            'card' => $card,
            'applied_minor' => $applied,
            'balance_minor' => (int) $card->balance_minor,
            'remaining_minor' => (int) $card->balance_minor - $applied,
        ];
    }

    /**
     * Debit the card for an order (idempotent per order). Returns the amount taken.
     */
    public function redeem(GiftCard $card, Order $order, int $amountMinor, ?int $userId = null): int
    {
        return DB::transaction(function () use ($card, $order, $amountMinor, $userId) {
            $already = GiftCardTransaction::query()->where('order_id', $order->id)->where('type', 'redeem')->exists();
            if ($already) {
                return 0;
            }
            $locked = GiftCard::query()->whereKey($card->id)->lockForUpdate()->firstOrFail();
            if (! self::usable($locked)) {
                throw ValidationException::withMessages(['gift_card_code' => 'این کارت هدیه قابل استفاده نیست.']);
            }
            $take = max(0, min((int) $locked->balance_minor, $amountMinor));
            if ($take < 1) {
                return 0;
            }
            $locked->balance_minor = (int) $locked->balance_minor - $take;
            $locked->save();
            GiftCardTransaction::query()->create([
                'gift_card_id' => $locked->id,
                'order_id' => $order->id,
                'user_id' => $userId,
                'type' => 'redeem',
                'amount_minor' => -$take,
                'balance_after_minor' => (int) $locked->balance_minor,
                'note' => 'سفارش '.($order->number ?: $order->id),
            ]);

            return $take;
        });
    }

    /** Put the held amount back when the order is cancelled, refunded or its payment fails. */
    public function release(Order $order, string $reason = 'order_cancel'): int
    {
        return DB::transaction(function () use ($order, $reason) {
            $redeemed = GiftCardTransaction::query()->where('order_id', $order->id)->where('type', 'redeem')->lockForUpdate()->get();
            $released = GiftCardTransaction::query()->where('order_id', $order->id)->where('type', 'release')->sum('amount_minor');
            $total = 0;
            foreach ($redeemed as $tx) {
                $total += abs((int) $tx->amount_minor);
            }
            $due = $total - (int) $released;
            if ($due < 1 || $redeemed->isEmpty()) {
                return 0;
            }
            $card = GiftCard::query()->whereKey($redeemed->first()->gift_card_id)->lockForUpdate()->first();
            if (! $card) {
                return 0;
            }
            $card->balance_minor = (int) $card->balance_minor + $due;
            $card->save();
            GiftCardTransaction::query()->create([
                'gift_card_id' => $card->id,
                'order_id' => $order->id,
                'type' => 'release',
                'amount_minor' => $due,
                'balance_after_minor' => (int) $card->balance_minor,
                'note' => $reason === 'order_cancel' ? 'بازگشت مبلغ سفارش لغو شده' : 'بازگشت مبلغ',
            ]);

            return $due;
        });
    }

    /** Order status edge hook (admin status change / payment failure). */
    public function syncOrder(Order $order, ?string $from, string $to): void
    {
        $meta = is_array($order->meta) ? $order->meta : [];
        if (empty($meta['gift_card'])) {
            return;
        }
        if (in_array($to, self::RELEASE_STATUSES, true) && $from !== $to) {
            $this->release($order);
        }
    }

    /** Manual admin adjustment (credit adds, debit removes). */
    public function adjust(GiftCard $card, string $direction, int $amountMinor, ?string $note = null, ?int $userId = null): GiftCard
    {
        if ($amountMinor < 1) {
            throw ValidationException::withMessages(['amount_minor' => 'مبلغ باید بیشتر از صفر باشد.']);
        }

        return DB::transaction(function () use ($card, $direction, $amountMinor, $note, $userId) {
            $locked = GiftCard::query()->whereKey($card->id)->lockForUpdate()->firstOrFail();
            if ($direction === 'debit') {
                if ((int) $locked->balance_minor < $amountMinor) {
                    throw ValidationException::withMessages(['amount_minor' => 'موجودی کارت کافی نیست.']);
                }
                $locked->balance_minor = (int) $locked->balance_minor - $amountMinor;
            } else {
                $locked->balance_minor = (int) $locked->balance_minor + $amountMinor;
            }
            $locked->save();
            GiftCardTransaction::query()->create([
                'gift_card_id' => $locked->id,
                'user_id' => $userId,
                'type' => $direction === 'debit' ? 'debit' : 'credit',
                'amount_minor' => $direction === 'debit' ? -$amountMinor : $amountMinor,
                'balance_after_minor' => (int) $locked->balance_minor,
                'note' => $note,
            ]);

            return $locked->fresh();
        });
    }

    /** @return array<string, mixed> */
    public static function publicPayload(GiftCard $card): array
    {
        return [
            'code' => $card->maskedCode(),
            'balance_minor' => (int) $card->balance_minor,
            'status' => self::usable($card) ? 'active' : ($card->status === 'active' ? 'empty_or_expired' : $card->status),
            'expires_at' => $card->expires_at?->toIso8601String(),
        ];
    }

    public static function usable(GiftCard $card): bool
    {
        return $card->status === 'active' && (int) $card->balance_minor > 0 && ($card->expires_at === null || $card->expires_at->isFuture());
    }
}
