<?php

namespace Modules\Accounting\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AccChartAccount extends Model
{
    protected $table = 'acc_chart_accounts';

    protected $fillable = [
        'code', 'name', 'parent_id', 'type', 'is_postable',
    ];

    protected function casts(): array
    {
        return [
            'is_postable' => 'boolean',
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }
}
