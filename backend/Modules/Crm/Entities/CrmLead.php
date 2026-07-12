<?php

namespace Modules\Crm\Entities;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class CrmLead extends Model
{
    use SoftDeletes;

    protected $table = 'crm_leads';

    protected $fillable = [
        'topic',
        'first_name',
        'last_name',
        'company',
        'job_title',
        'email',
        'mobile',
        'phone',
        'source_id',
        'status_id',
        'industry',
        'rating',
        'lead_score',
        'assigned_to',
        'description',
        'address_json',
        'converted_at',
        'converted_to_account_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'address_json' => 'array',
            'converted_at' => 'datetime',
            'rating' => 'integer',
            'lead_score' => 'integer',
        ];
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(CrmStatus::class, 'status_id');
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(CrmSource::class, 'source_id');
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
