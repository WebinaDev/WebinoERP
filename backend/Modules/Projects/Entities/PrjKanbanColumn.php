<?php

namespace Modules\Projects\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PrjKanbanColumn extends Model
{
    protected $table = 'prj_kanban_columns';

    protected $fillable = [
        'board_id',
        'name',
        'sort_order',
        'color',
        'wip_limit',
    ];

    public function board(): BelongsTo
    {
        return $this->belongsTo(PrjKanbanBoard::class, 'board_id');
    }

    public function cards(): HasMany
    {
        return $this->hasMany(PrjKanbanCard::class, 'column_id')->orderBy('sort_order');
    }
}
