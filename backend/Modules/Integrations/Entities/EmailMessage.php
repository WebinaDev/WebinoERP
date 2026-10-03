<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EmailMessage extends Model
{
    protected $table = 'int_email_messages';

    protected $fillable = [
        'account_id', 'external_id', 'thread_key', 'direction', 'from_email', 'to_email',
        'subject', 'body', 'attachments', 'crm_account_id', 'activity_id', 'sent_at',
        'is_spam', 'spam_score', 'folder',
    ];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
            'sent_at' => 'datetime',
            'is_spam' => 'boolean',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(EmailAccount::class, 'account_id');
    }
}
