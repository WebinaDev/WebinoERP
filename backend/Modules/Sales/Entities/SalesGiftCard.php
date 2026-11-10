<?php

namespace Modules\Sales\Entities;

use Illuminate\Database\Eloquent\Model;

class SalesGiftCard extends Model
{
    protected $table = 'sales_gift_cards';

    protected $fillable = ['code', 'initial_minor', 'balance_minor', 'status', 'user_id', 'recipient_name', 'recipient_phone', 'recipient_email', 'message', 'note', 'expires_at', 'issued_by', 'meta'];

    protected $casts = ['initial_minor' => 'integer', 'balance_minor' => 'integer', 'expires_at' => 'datetime', 'meta' => 'array'];

    public function transactions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(SalesGiftCardTransaction::class, 'gift_card_id');
    }

    public function maskedCode(): string
    {
        $c = (string) $this->code;

        return strlen($c) <= 4 ? $c : str_repeat('•', max(0, strlen($c) - 4)).substr($c, -4);
    }
}
