<?php

namespace Modules\Crm\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ContentCalendar extends Model
{
    protected $table = 'crm_content_calendars';

    protected $fillable = [
        'account_id', 'company_id', 'kind', 'network', 'name',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(ContentItem::class, 'calendar_id');
    }
}
