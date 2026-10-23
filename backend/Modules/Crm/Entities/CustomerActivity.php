<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerActivity extends Model
{
    protected $table = 'crm_customer_activities';

    protected $fillable = ['account_id', 'occurred_at', 'module', 'type', 'subject_type', 'subject_id', 'title', 'meta', 'actor_id', 'visibility_section', 'dedupe_key'];

    protected function casts(): array
    {
        return ['meta' => 'array', 'occurred_at' => 'datetime'];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'account_id');
    }
}
