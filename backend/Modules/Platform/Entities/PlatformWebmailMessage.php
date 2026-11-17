<?php

namespace Modules\Platform\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PlatformWebmailMessage extends Model
{
    protected $table = 'platform_webmail_messages';

    protected $fillable = [
        'mailbox_id', 'folder', 'message_id', 'sender', 'sender_name',
        'recipients', 'subject', 'body_text', 'body_html', 'attachments',
        'is_read', 'is_starred', 'received_at',
    ];

    protected $casts = [
        'recipients' => 'array',
        'attachments' => 'array',
        'is_read' => 'boolean',
        'is_starred' => 'boolean',
        'received_at' => 'datetime',
    ];

    public function mailbox(): BelongsTo
    {
        return $this->belongsTo(PlatformMailbox::class, 'mailbox_id');
    }
}
