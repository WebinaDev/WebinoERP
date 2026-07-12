<?php

namespace Modules\Projects\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class PrjKanbanCard extends Model
{
    protected $table = 'prj_kanban_cards';

    protected $fillable = [
        'column_id',
        'title',
        'body',
        'sort_order',
        'cardable_type',
        'cardable_id',
    ];

    public function column(): BelongsTo
    {
        return $this->belongsTo(PrjKanbanColumn::class, 'column_id');
    }

    public function cardable(): MorphTo
    {
        return $this->morphTo();
    }
}
