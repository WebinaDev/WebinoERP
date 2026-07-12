<?php

namespace Modules\Mfg\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MfgBom extends Model
{
    protected $table = 'mfg_boms';

    protected $fillable = ['product_id', 'version', 'status', 'notes'];

    public function lines(): HasMany
    {
        return $this->hasMany(MfgBomLine::class, 'bom_id');
    }
}
