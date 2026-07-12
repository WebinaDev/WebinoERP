<?php

namespace Modules\Projects\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrjTicket extends Model
{
    protected $table = 'prj_tickets';

    protected $fillable = [
        'subject', 'body', 'status', 'priority', 'department', 'rating',
        'customer_user_id', 'assignee_id',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function replies(): HasMany
    {
        return $this->hasMany(PrjTicketReply::class, 'ticket_id');
    }
}
