<?php

namespace Modules\Crm\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmConsultation extends Model
{
    protected $table = 'crm_consultations';

    protected $fillable = [
        'title', 'account_id', 'status', 'notes', 'created_by',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'account_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
