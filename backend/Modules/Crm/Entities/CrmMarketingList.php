<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CrmMarketingList extends Model
{
    protected $table = 'crm_marketing_lists';

    protected $fillable = ['name', 'description', 'campaign_id', 'created_by'];

    public function members(): HasMany
    {
        return $this->hasMany(CrmListMember::class, 'list_id');
    }
}
