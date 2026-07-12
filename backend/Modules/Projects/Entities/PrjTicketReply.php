<?php

namespace Modules\Projects\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrjTicketReply extends Model
{
    protected $table = 'prj_ticket_replies';

    protected $fillable = ['ticket_id', 'user_id', 'body', 'attachments'];

    protected function casts(): array
    {
        return [
            'attachments' => 'array',
        ];
    }

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(PrjTicket::class, 'ticket_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
