<?php

namespace Modules\Projects\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Crm\Entities\CrmAccount;

class PrjAppointment extends Model
{
    protected $table = 'prj_appointments';

    protected $fillable = [
        'title', 'starts_at', 'ends_at', 'customer_account_id', 'status', 'notes', 'customer_user_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'customer_account_id');
    }

    public function customerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_user_id');
    }
}
