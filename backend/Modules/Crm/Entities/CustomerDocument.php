<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Projects\Entities\Contract;
use Modules\Projects\Entities\Project;

/** Eloquent model over the Customer 360 hub table `crm_customer_documents`. */
class CustomerDocument extends Model
{
    protected $table = 'crm_customer_documents';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['visible_to_roles' => 'array', 'tags' => 'array', 'expires_at' => 'date', 'reviewed_at' => 'datetime'];
    }

    public function versions(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class, 'contract_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'project_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(CrmAccount::class, 'account_id');
    }
}
