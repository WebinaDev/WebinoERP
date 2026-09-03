<?php

namespace Modules\Marketing\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingMediaFolder extends Model
{
    protected $table = 'marketing_media_folders';

    protected $fillable = ['parent_id', 'name'];

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
