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
        'customer_user_id', 'customer_account_id', 'project_id', 'assignee_id', 'converted_task_id',
        'sla_first_due_at', 'sla_resolve_due_at', 'first_responded_at', 'sla_breached_at',
    ];

    protected function casts(): array
    {
        return [
            'sla_first_due_at' => 'datetime',
            'sla_resolve_due_at' => 'datetime',
            'first_responded_at' => 'datetime',
            'sla_breached_at' => 'datetime',
        ];
    }

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
