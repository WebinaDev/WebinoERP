<?php

namespace Modules\Integrations\Entities;

use Illuminate\Database\Eloquent\Model;

class EmailAccount extends Model
{
    protected $table = 'int_email_accounts';

    protected $fillable = [
        'user_id', 'provider', 'email', 'access_token', 'refresh_token', 'expires_at',
        'imap_host', 'imap_port', 'smtp_host', 'smtp_port', 'username', 'password', 'encryption',
        'status', 'refresh_attempts', 'last_refresh_error', 'meta',
    ];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'refresh_token' => 'encrypted',
            'password' => 'encrypted',
            'expires_at' => 'datetime',
        ];
    }
}
