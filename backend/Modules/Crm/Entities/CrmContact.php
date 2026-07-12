<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmContact extends Model
{
    use SoftDeletes;

    protected $table = 'crm_contacts';

    protected $fillable = [
        'account_id', 'first_name', 'last_name', 'email', 'mobile', 'phone',
        'job_title', 'reports_to', 'decision_role', 'is_primary', 'social_profiles',
        'assigned_to', 'description', 'created_by',
    ];

    protected $casts = [
        'is_primary' => 'boolean',
        'social_profiles' => 'array',
    ];

    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'account_id');
    }
}
