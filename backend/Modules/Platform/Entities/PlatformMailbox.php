<?php

namespace Modules\Platform\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PlatformMailbox extends Model
{
    protected $table = 'platform_mailboxes';

    protected $fillable = [
        'mail_domain_id', 'email_address', 'local_part', 'display_name',
        'password_hash', 'quota_bytes', 'used_bytes', 'is_active', 'is_catch_all',
        'auto_reply_subject', 'auto_reply_body', 'auto_reply_active', 'last_login_at',
    ];

    protected $hidden = [
        'password_hash',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_catch_all' => 'boolean',
        'auto_reply_active' => 'boolean',
        'last_login_at' => 'datetime',
    ];

    public function mailDomain(): BelongsTo
    {
        return $this->belongsTo(PlatformMailDomain::class, 'mail_domain_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(PlatformWebmailMessage::class, 'mailbox_id');
    }
}
